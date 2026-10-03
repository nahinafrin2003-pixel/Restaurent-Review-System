<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectIfAuthenticatedSession
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->attributes->get('auth_user')) {
            return redirect($user->homePath());
        }

        return $next($request);
    }
}
