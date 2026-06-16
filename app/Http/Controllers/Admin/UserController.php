<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $admin = auth()->user();

        $users = User::with('tenant')
            ->visibleTo($admin)
            ->orderBy('name')
            ->get();

        // El admin global puede asignar institución al crear; el de institución no.
        $tenants  = $admin->isGlobalAdmin() ? Tenant::orderBy('name')->get() : collect();
        $isGlobal = $admin->isGlobalAdmin();

        return view('admin.users.index', compact('users', 'tenants', 'isGlobal'));
    }

    public function store(Request $request)
    {
        $admin = auth()->user();

        $rules = [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => 'required|in:super_admin,analyst',
        ];
        if ($admin->isGlobalAdmin()) {
            $rules['tenant_id'] = ['nullable', Rule::exists('tenants', 'id')];
        }

        $data = $request->validate($rules);

        // El admin de institución SIEMPRE crea dentro de su propia institución.
        // El admin global puede elegir institución (o ninguna = admin global).
        $tenantId = $admin->isGlobalAdmin()
            ? ($data['tenant_id'] ?? null)
            : $admin->tenant_id;

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'role'      => $data['role'],
            'tenant_id' => $tenantId,
            'is_active' => true,
        ]);

        AuditLogger::log('create_user', "Usuario creado: {$user->email}");

        return redirect()->route('admin.users.index')
            ->with('success', "Usuario {$user->name} creado correctamente.");
    }

    public function update(Request $request, User $user)
    {
        $admin = auth()->user();
        abort_unless($user->isManageableBy($admin), 403);

        $rules = [
            'name'  => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'role'  => 'required|in:super_admin,analyst',
        ];
        if ($admin->isGlobalAdmin()) {
            $rules['tenant_id'] = ['nullable', Rule::exists('tenants', 'id')];
        }
        $data = $request->validate($rules);

        // El admin de institución no puede mover usuarios a otra institución
        // ni crear administradores globales.
        $tenantId = $admin->isGlobalAdmin()
            ? ($data['tenant_id'] ?? null)
            : $admin->tenant_id;

        // Salvaguarda: no cambiar tu propio rol/institución (evita autobloqueo).
        if ($user->id === $admin->id && ($data['role'] !== $user->role || (int) $tenantId !== (int) $user->tenant_id)) {
            return back()->with('error', 'No puedes cambiar tu propio rol o institución.');
        }

        // Salvaguarda: no dejar el sistema sin administrador global.
        $stillGlobal = $data['role'] === 'super_admin' && $tenantId === null;
        if ($user->isGlobalAdmin() && !$stillGlobal &&
            User::where('role', 'super_admin')->whereNull('tenant_id')->count() <= 1) {
            return back()->with('error', 'No puedes quitar el último administrador global.');
        }

        $user->update([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'role'      => $data['role'],
            'tenant_id' => $tenantId,
            // No permitir desactivarte a ti mismo desde la edición.
            'is_active' => $user->id === $admin->id ? $user->is_active : $request->boolean('is_active', $user->is_active),
        ]);

        AuditLogger::log('update_user', "Usuario actualizado: {$user->email}");

        return redirect()->route('admin.users.index')
            ->with('success', "Usuario {$user->name} actualizado correctamente.");
    }

    public function destroy(User $user)
    {
        $admin = auth()->user();
        abort_unless($user->isManageableBy($admin), 403);

        if ($user->id === $admin->id) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta.');
        }
        if ($user->isGlobalAdmin() &&
            User::where('role', 'super_admin')->whereNull('tenant_id')->count() <= 1) {
            return back()->with('error', 'No puedes eliminar el último administrador global.');
        }

        $email = $user->email;

        try {
            // Limpiar dependencias que bloquearían el borrado.
            $user->modulePermissions()->delete();
            AuditLog::withoutGlobalScope('tenant')->where('user_id', $user->id)->update(['user_id' => null]);

            $user->delete();
        } catch (QueryException $e) {
            return back()->with('error', 'No se puede eliminar: el usuario tiene datos asociados (sábanas, GPS, etc.). Desactívalo en su lugar.');
        }

        AuditLogger::log('delete_user', "Usuario eliminado: {$email}");

        return back()->with('success', 'Usuario eliminado correctamente.');
    }

    public function toggleActive(User $user)
    {
        abort_unless($user->isManageableBy(auth()->user()), 403);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'No puedes desactivarte a ti mismo.');
        }

        $user->update(['is_active' => !$user->is_active]);
        $estado = $user->is_active ? 'activado' : 'desactivado';

        AuditLogger::log('toggle_user', "Usuario {$user->email} {$estado}");

        return back()->with('success', "Usuario {$estado} correctamente.");
    }

    public function resetPassword(Request $request, User $user)
    {
        abort_unless($user->isManageableBy(auth()->user()), 403);

        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update(['password' => $data['password']]);
        AuditLogger::log('reset_password', "Contraseña reseteada para: {$user->email}");

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }
}
