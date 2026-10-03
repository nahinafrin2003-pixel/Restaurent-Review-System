<?php

namespace App\Http\Controllers;

use App\Mail\TwoFactorOtpMail;
use App\Models\Otp;
use App\Models\SessionTable;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\AuditLogger;
use App\Services\Crypto\SHA256;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    private const OTP_TTL_SECONDS = 60;

    public function showRegister()
    {
        return view('auth.register');
    }

    /** Self-registration creates customers only. Officers are provisioned by the admin. */
    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|size:10|regex:/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/',
            'email' => 'required|email|max:180',
            'contact_info' => 'required|digits:11',
            'password' => 'required|string|min:12|confirmed',
        ], [
            'username.size' => 'Username must be exactly 10 characters.',
            'username.regex' => 'Username must contain letters, numbers and at least one symbol.',
        ]);

        $email = strtolower(trim($request->input('email')));

        if (User::findByUsername($request->input('username'))) {
            return back()->withInput()->withErrors(['username' => 'This username is already taken.']);
        }
        if (User::findByEmail($email)) {
            return redirect('/login')->with('status', 'This email is already registered. Please log in.');
        }
        if (User::findByContact($request->input('contact_info'))) {
            return back()->withInput()->withErrors(['contact_info' => 'This contact number is already registered.']);
        }

        $user = User::create([
            'username' => $request->input('username'),
            'email' => $email,
            'contact_info' => $request->input('contact_info'),
            'password' => $request->input('password'),
            'role' => 'customer',
            'is_active' => true,
        ]);

        UserActivity::create(['user_id' => $user->user_id, 'remaining_reviews' => 5]);
        AuditLogger::log('auth.registered', $user, [], $user);

        return redirect('/login')->with('status', 'Account created. Please log in to continue.');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    /** Step 1: password check, then an OTP is e-mailed (mandatory 2FA). */
    public function authenticate(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:180',
            'password' => 'required|string',
        ]);

        $email = strtolower(trim($request->input('email')));
        $user = User::findByEmail($email);

        if (! $user || ! $user->verifyPassword($request->input('password'))) {
            AuditLogger::log('auth.login_failed', $user, ['email_hash' => substr(SHA256::hash($email), 0, 16)]);

            return back()->withInput($request->only('email'))->withErrors(['email' => 'Invalid credentials.']);
        }

        if (! $user->isActive()) {
            AuditLogger::log('auth.login_blocked_inactive', $user, [], $user);

            return back()->withErrors(['email' => 'Your account has been deactivated. Please contact the administrator.']);
        }

        $request->session()->put('pending_2fa_user', $user->user_id);
        $request->session()->put('pending_2fa_email', $email);

        return $this->issueOtp($request, $user, $email, 'A verification code has been sent to your e-mail.');
    }

    public function showVerify(Request $request)
    {
        if (! $request->session()->has('pending_2fa_user')) {
            return redirect('/login');
        }

        $otpRecord = Otp::where('user_id', $request->session()->get('pending_2fa_user'))
            ->where('is_used', false)
            ->orderByDesc('generated_at')
            ->first();

        $timeRemaining = $otpRecord ? max(0, (int) now()->diffInSeconds($otpRecord->expiry_time, false)) : 0;

        return view('auth.verify', [
            'verificationEmail' => $this->maskEmail((string) $request->session()->get('pending_2fa_email')),
            'timeRemaining' => $timeRemaining,
            'showResend' => $timeRemaining <= 0,
        ]);
    }

    public function resendTwoFactor(Request $request)
    {
        $user = User::find($request->session()->get('pending_2fa_user'));
        if (! $user) {
            return redirect('/login');
        }

        $current = Otp::where('user_id', $user->user_id)->where('is_used', false)->first();
        if ($current && ! $current->isExpired()) {
            return redirect('/verify-2fa')->with('status', 'Your current code is still valid. Please wait until it expires.');
        }

        return $this->issueOtp($request, $user, (string) $request->session()->get('pending_2fa_email'), 'A new verification code has been sent.');
    }

    /** Step 2: OTP check, then the custom session token is issued. */
    public function verifyTwoFactor(Request $request)
    {
        @set_time_limit(120);

        $request->validate(['verification_code' => 'required|digits:6']);

        $userId = $request->session()->get('pending_2fa_user');
        if (! $userId) {
            return redirect('/login');
        }

        $otp = Otp::where('user_id', $userId)
            ->where('otp_code', SHA256::hash($request->input('verification_code')))
            ->where('expiry_time', '>', now())
            ->where('is_used', false)
            ->first();

        if (! $otp) {
            return back()->withErrors(['verification_code' => 'Wrong or expired code. Please try again.']);
        }

        $otp->update(['is_used' => true]);

        $user = User::find($userId);
        if (! $user || ! $user->isActive()) {
            return redirect('/login')->withErrors(['email' => 'Account unavailable.']);
        }

        $token = bin2hex(random_bytes(32));
        SessionTable::create([
            'user_id' => $user->user_id,
            'token' => $token,
            'created_at' => now(),
            'expiry_time' => now()->addHours(2),
            'last_activity' => now(),
        ]);

        $request->session()->forget(['pending_2fa_user', 'pending_2fa_email']);
        $request->session()->regenerate();
        AuditLogger::log('auth.login', $user, [], $user);

        return redirect($user->homePath())
            ->withCookie(cookie('auth_token', $token, 120, '/', null, $request->isSecure(), true, false, 'Lax'))
            ->with('status', 'Welcome back!');
    }

    public function logout(Request $request)
    {
        if ($token = $request->cookie('auth_token')) {
            SessionTable::deleteByToken($token);
        }
        AuditLogger::log('auth.logout');
        $request->session()->invalidate();

        return redirect('/login')->withoutCookie('auth_token')->with('status', 'You have been logged out.');
    }

    protected function issueOtp(Request $request, User $user, string $email, string $message)
    {
        $otp = (string) random_int(100000, 999999);

        Otp::updateOrCreate(
            ['user_id' => $user->user_id],
            [
                'otp_code' => SHA256::hash($otp),
                'generated_at' => now(),
                'expiry_time' => now()->addSeconds(self::OTP_TTL_SECONDS),
                'is_used' => false,
            ]
        );

        $delivered = ! str_ends_with($email, '.test');   // .test = seeded demo accounts
        try {
            if ($delivered) Mail::to($email)->send(new TwoFactorOtpMail($otp, ucfirst(explode('@', $email)[0] ?: 'there')));
        } catch (\Throwable $e) {
            $delivered = false;
            \Log::warning('OTP email delivery failed: ' . $e->getMessage());
        }

        $redirect = redirect('/verify-2fa')->with('status', $message);

        // Local development aid: if mail is not configured, surface the code so the app stays usable.
        if (config('app.debug') && (! $delivered || in_array(config('mail.default'), ['log', 'array'], true))) {
            $redirect->with('dev_otp', $otp);
        }

        return $redirect;
    }

    protected function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 2) . str_repeat('•', max(1, mb_strlen($name) - 2)) . '@' . $domain;
    }
}
