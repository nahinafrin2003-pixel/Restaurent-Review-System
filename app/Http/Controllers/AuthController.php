<?php

namespace App\Http\Controllers;

use App\Mail\TwoFactorOtpMail;
use App\Models\Otp;
use App\Models\SessionTable;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'username' => 'required|string|size:10|regex:/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/',
            'email' => 'required|email|max:180',
            'contact_info' => 'required|digits:11',
            'password' => 'required|string|min:12',
            'role' => 'required|in:user,admin',
        ]);

        // Check if admin already exists (only one admin allowed)
        if ($request->input('role') === 'admin' && User::where('role', 'admin')->exists()) {
            return back()->withErrors(['role' => 'An admin already exists. Only one admin is allowed.']);
        }

        $email = strtolower(trim($request->input('email')));
        // Check for existing username, email, and contact info
        if (User::findByUsername($request->input('username'))) {
            return back()->withErrors(['username' => 'This username is already taken. Please choose a different one.']);
        }

        if (User::findByEmail($email)) {
            return redirect('/login')->with('status', 'This email is already registered. Please login to continue.');
        }

        if (User::findByContact($request->input('contact_info'))) {
            return back()->withErrors(['contact_info' => 'This contact is already registered.']);
        }

        $user = User::create([
            'username' => $request->input('username'),
            'email' => $email,
            'contact_info' => $request->input('contact_info'),
            'password' => $request->input('password'),
            'role' => $request->input('role'),
        ]);

        // Create user activity record
        UserActivity::create([
            'user_id' => $user->user_id,
            'remaining_reviews' => 5,
        ]);

        return redirect('/login')->with('status', 'Account created successfully. Please login to continue.');
    }

    public function showLogin()
    {
        return view('auth.login');
    }
    //login method with 2FA implementation
    //login credential validate first then generate OTP and send to email, then redirect to OTP verification page
    public function authenticate(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:180',
            'password' => 'required|string',
        ]);

        $email = strtolower(trim($request->input('email')));
        $user = User::findByEmail($email);

        if (!$user || !$user->verifyPassword($request->input('password'))) {
            return back()->withErrors(['email' => 'Invalid credentials.']);
        }

        // Generate OTP and persist it immediately before sending email.
        $otp = (string) rand(100000, 999999);
        Otp::updateOrCreate(
            ['user_id' => $user->user_id],
            [
                'otp_code' => hash('sha256', $otp),
                'generated_at' => now(),
                'expiry_time' => now()->addSeconds(60),
                'is_used' => false,
            ]
        );

        $request->session()->put('pending_2fa_user', $user->user_id); // Store user ID in session for 2FA verification temporarily
        $request->session()->put('pending_2fa_email', $email);
        $request->session()->put('otp_resend_available_at', now()->addSeconds(60));

        $displayName = explode('@', $email)[0] ?: 'there';
        // Send OTP email
        try {
            Mail::to($email)->send(new TwoFactorOtpMail($otp, ucfirst($displayName)));
        } catch (\Exception $e) {
            \Log::warning('OTP email delivery failed: ' . $e->getMessage());
        }

        return redirect('/verify-2fa')->with('status', 'OTP has been sent. Please check your inbox.');
    }

    public function showVerify(Request $request)
    {
        if (!$request->session()->has('pending_2fa_user')) {
            return redirect('/login');
        }

        $userId = $request->session()->get('pending_2fa_user');
        $verificationEmail = $request->session()->get('pending_2fa_email');
        // Check if there's an active OTP for the user and calculate time remaining for display and resend logic
        $otpRecord = Otp::where('user_id', $userId)
            ->where('is_used', false)
            ->orderByDesc('generated_at')
            ->first();

        $timeRemaining = 0;
        if ($otpRecord) { // Calculate time remaining until OTP expires
            $timeRemaining = now()->diffInSeconds($otpRecord->expiry_time, false);
            if ($timeRemaining < 0) {
                $timeRemaining = 0;
            }
        }

        $resendAvailableAt = $request->session()->get('otp_resend_available_at', now());
        $resendCooldown = max(0, now()->diffInSeconds($resendAvailableAt, false));
        $showResend = $timeRemaining <= 0; //resend button show only when OTP is expired, not based on cooldown, because user should be able to request new OTP immediately after expiration without waiting for cooldown if they want to. Cooldown only prevents multiple requests while OTP is still valid.

        return view('auth.verify', [
            'verificationEmail' => $verificationEmail,
            'timeRemaining' => $timeRemaining,
            'resendCooldown' => $resendCooldown,
            'showResend' => $showResend,
        ]);
    }

    public function resendTwoFactor(Request $request)
    {
        $userId = $request->session()->get('pending_2fa_user');

        if (!$userId) {
            return redirect('/login');
        }

        $user = User::find($userId);

        if (!$user) {
            return redirect('/login');
        }

        $availableAt = $request->session()->get('otp_resend_available_at', now());

        if (now()->lessThan($availableAt) && !$request->session()->get('otp_allow_resend_now', false)) {
            return redirect('/verify-2fa')->with('status', 'Please wait before requesting a new OTP.');
        }

        $otp = (string) rand(100000, 999999);

        Otp::updateOrCreate(
            ['user_id' => $user->user_id],
            [
                'otp_code' => hash('sha256', $otp),
                'generated_at' => now(),
                'expiry_time' => now()->addSeconds(60),
                'is_used' => false,
            ]
        );

        $email = $request->session()->get('pending_2fa_email');
        $displayName = explode('@', $email)[0] ?: 'there';
        
        try {
            Mail::to($email)->send(new TwoFactorOtpMail($otp, ucfirst($displayName)));
        } catch (\Exception $e) {
            \Log::warning('OTP resend email delivery failed: ' . $e->getMessage());
        }

        $request->session()->put('otp_resend_available_at', now()->addSeconds(60));
        $request->session()->forget('otp_allow_resend_now');

        return redirect('/verify-2fa')->with('status', 'OTP resend has been sent. Please check your inbox.');
    }

    public function verifyTwoFactor(Request $request)
    {
        // Increase execution time for crypto operations
        ini_set('max_execution_time', 300);

        $request->validate([
            'verification_code' => 'required|numeric',
        ]);

        $userId = $request->session()->get('pending_2fa_user');

        if (!$userId) {
            return redirect('/login');
        }

        $otp = Otp::where('user_id', $userId)
            ->where('otp_code', hash('sha256', $request->input('verification_code')))
            ->where('expiry_time', '>', now())
            ->where('is_used', false)
            ->first();

        if (!$otp) {
            return back()->withErrors(['verification_code' => 'Wrong OTP. Please try again.']);
        }

        // Mark OTP as used
        $otp->update(['is_used' => true]);

        $user = User::find($userId);

        if (!$user) {
            return redirect('/login');
        }

        // Create session
        $token = bin2hex(random_bytes(32));
        SessionTable::create([
            'user_id' => $user->user_id,
            'token' => $token,
            'created_at' => now(),
            'expiry_time' => now()->addHours(2),
            'last_activity' => now(),
        ]);

        $request->session()->forget(['pending_2fa_user', 'pending_2fa_email', 'otp_resend_available_at']);

        // Set auth cookie and redirect according to role
        $redirectPath = $user->isAdmin() ? '/admin/dashboard' : '/dashboard';
        $secureCookie = app()->environment('production');

        return redirect($redirectPath)
            ->withCookie(cookie('auth_token', $token, 120, '/', null, $secureCookie, true, false, 'Strict'))
            ->with('status', 'Login successful.');
    }

    public function logout(Request $request)
    {
        $token = $request->cookie('auth_token');

        if ($token) {
            SessionTable::deleteByToken($token);
        }

        $request->session()->invalidate();

        return redirect('/login')->withoutCookie('auth_token');
    }
}
