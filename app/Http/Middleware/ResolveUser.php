<?php

namespace App\Http\Middleware;

use App\Models\SessionTable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

/**
 * Runs on every web request. Resolves the custom session token (auth_token
 * cookie) into a user, enforces expiry and account deactivation, and shares
 * the user with all views. Never redirects — enforcement is AuthenticateSession's job.
 */
class ResolveUser
{
    public function handle(Request $request, Closure $next)
    {
        $user = null;
        $token = $request->cookie('auth_token');

        if ($token) {
            $session = SessionTable::findByToken($token);

            if ($session && $session->isExpired()) {
                $session->delete();
                $session = null;
            }

            if ($session && $session->user && ! $session->user->isActive()) {
                // Deactivated account: revoke every session immediately.
                SessionTable::where('user_id', $session->user_id)->delete();
                $request->attributes->set('auth_deactivated', true);
                $session = null;
            }

            if ($session) {
                if (! $session->last_activity || $session->last_activity->lt(now()->subMinute())) {
                    $session->update(['last_activity' => now()]);
                }
                $user = $session->user;
                $request->attributes->set('auth_user', $user);
                $request->attributes->set('session_id', $session->session_id);
            }
        }

        View::share('authUser', $user);

        return $next($request);
    }
}
