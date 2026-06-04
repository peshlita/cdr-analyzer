<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (!$request->user()?->isSuperAdmin()) {
            return redirect()->route('dashboard')
                ->with('error', 'Acceso restringido al panel de administración.');
        }

        return $next($request);
    }
}
