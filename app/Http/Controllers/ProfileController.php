<?php

namespace App\Http\Controllers;

use App\Models\SessionTable;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\AuditLogger;
use App\Services\RewardService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        @set_time_limit(120);
        $user = $request->attributes->get('auth_user');

        return view('profile.show', [
            'user' => $user,
            'badges' => UserBadge::where('user_id', $user->user_id)->pluck('badge')->all(),
            'catalog' => RewardService::BADGES,
            'sessions' => SessionTable::where('user_id', $user->user_id)->where('expiry_time', '>', now())->orderByDesc('last_activity')->get(),
            'currentSession' => $request->attributes->get('session_id'),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->attributes->get('auth_user');

        $data = $request->validate([
            'username' => 'required|string|size:10|regex:/^(?=.*[a-zA-Z])(?=.*\d)(?=.*[^a-zA-Z\d]).+$/',
            'email' => 'required|email|max:180',
            'contact_info' => 'required|digits:11',
            'current_password' => 'required|string',
            'password' => 'nullable|string|min:12|confirmed',
        ]);

        if (! $user->verifyPassword($data['current_password'])) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        foreach (['username' => User::findByUsername($data['username']), 'email' => User::findByEmail($data['email']), 'contact_info' => User::findByContact($data['contact_info'])] as $field => $match) {
            if ($match && $match->user_id !== $user->user_id) {
                return back()->withErrors([$field => 'This ' . str_replace('_', ' ', $field) . ' is already in use.']);
            }
        }

        if ($data['username'] !== $user->username) {
            $user->username = $data['username'];
        }
        if (strtolower(trim($data['email'])) !== $user->email) {
            $user->email = $data['email'];
        }
        if ($data['contact_info'] !== $user->contact_info) {
            $user->contact_info = $data['contact_info'];
        }
        if (! empty($data['password'])) {
            $user->password = $data['password'];
            // Changing the password signs out every other session.
            SessionTable::where('user_id', $user->user_id)->where('session_id', '!=', $request->attributes->get('session_id'))->delete();
        }
        $user->save();
        AuditLogger::log('profile.updated', $user, ['password_changed' => ! empty($data['password'])]);

        return redirect('/profile')->with('status', 'Profile updated.');
    }
}
