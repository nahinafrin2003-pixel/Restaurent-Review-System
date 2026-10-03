<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Requires a valid custom session (resolved by ResolveUser). */
class AuthenticateSession
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->attributes->get('auth_user')) {
            $redirect = redirect('/login')->withoutCookie('auth_token');

            if ($request->attributes->get('auth_deactivated')) {
                return $redirect->withErrors(['email' => 'Your account has been deactivated. Please contact the administrator.']);
            }

            return $redirect->with('status', 'Please log in to continue.');
        }

        return $next($request);
    }
}
