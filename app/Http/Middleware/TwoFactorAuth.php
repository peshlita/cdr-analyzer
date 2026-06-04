<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TwoFactorAuth
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->two_factor_enabled && !session('two_factor_verified')) {
            auth()->logout();
            return redirect()->route('two-factor.challenge');
        }

        return $next($request);
    }
}
