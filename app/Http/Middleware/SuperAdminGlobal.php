<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo permite el paso al Super Admin GLOBAL: role=super_admin y sin tenant.
 * Los admins de institución (con tenant_id) y analistas reciben 403.
 */
class SuperAdminGlobal
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return redirect('/login');
        }

        $user = auth()->user();

        if ($user->role !== 'super_admin' || $user->tenant_id) {
            abort(403, 'Acceso restringido al administrador global.');
        }

        return $next($request);
    }
}
