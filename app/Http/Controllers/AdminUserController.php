<?php

namespace App\Http\Controllers;

use App\Models\SessionTable;
use App\Models\User;
use App\Models\UserActivity;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function officers(Request $request)
    {
        $officers = User::where('role', 'officer')->orderByDesc('user_id')->get();

        return view('admin.officers', [
            'officers' => $officers,
            'sessions' => SessionTable::where('expiry_time', '>', now())->get()->groupBy('user_id')->map->count(),
            'assigned' => \App\Models\Complaint::whereIn('status', \App\Support\ComplaintStatus::OPEN_STATES)
                ->whereNotNull('assigned_officer_id')->get(['assigned_officer_id'])->groupBy('assigned_officer_id')->map->count(),
        ]);
    }

    /** Officers are provisioned only here — never self-registered. */
    public function storeOfficer(Request $request)
    {
        $admin = $request->attributes->get('auth_user');
        $data = $request->validate([
            'username' => 'required|string|size:10|regex:/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/',
            'email' => 'required|email|max:180',
            'contact_info' => 'required|digits:11',
            'password' => 'required|string|min:12',
        ], [
            'username.size' => 'Username must be exactly 10 characters.',
            'username.regex' => 'Username must contain letters, numbers and a symbol.',
        ]);

        if (User::findByUsername($data['username'])) {
            return back()->withInput()->withErrors(['username' => 'Username already taken.']);
        }
        if (User::findByEmail($data['email'])) {
            return back()->withInput()->withErrors(['email' => 'Email already registered.']);
        }
        if (User::findByContact($data['contact_info'])) {
            return back()->withInput()->withErrors(['contact_info' => 'Contact number already registered.']);
        }

        $officer = User::create($data + ['role' => 'officer', 'is_active' => true, 'created_by' => $admin->user_id]);
        UserActivity::create(['user_id' => $officer->user_id, 'remaining_reviews' => 5]);
        AuditLogger::log('admin.officer_created', $officer, ['officer_id' => $officer->user_id]);

        return back()->with('status', 'Officer account created. They log in with the e-mail and password you set (OTP required).');
    }

    public function customers(Request $request)
    {
        return view('admin.users', [
            'users' => User::whereIn('role', ['customer', 'user'])->withCount('complaints')->orderByDesc('user_id')->paginate(25),
            'sessions' => SessionTable::where('expiry_time', '>', now())->get()->groupBy('user_id')->map->count(),
        ]);
    }

    public function toggleActive(Request $request, User $user)
    {
        $admin = $request->attributes->get('auth_user');
        abort_if($user->isAdmin() || $user->user_id === $admin->user_id, 403, 'Admin accounts cannot be deactivated here.');

        $activate = ! $user->isActive();
        $user->forceFill(['is_active' => $activate, 'deactivated_at' => $activate ? null : now()])->save();

        if (! $activate) {
            SessionTable::where('user_id', $user->user_id)->delete();   // kicked out immediately
        }
        AuditLogger::log($activate ? 'admin.user_activated' : 'admin.user_deactivated', $user, ['role' => $user->role]);

        return back()->with('status', $activate ? 'Account activated.' : 'Account deactivated and all sessions revoked.');
    }

    public function revokeSessions(Request $request, User $user)
    {
        $count = SessionTable::where('user_id', $user->user_id)->delete();
        AuditLogger::log('admin.sessions_revoked', $user, ['count' => $count]);

        return back()->with('status', "{$count} session(s) revoked.");
    }
}
