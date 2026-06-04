<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->get();
        return view('admin.users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => 'required|in:super_admin,analyst',
        ]);

        $user = User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'role'      => $data['role'],
            'is_active' => true,
        ]);

        AuditLogger::log('create_user', "Usuario creado: {$user->email}");

        return redirect()->route('admin.users.index')
            ->with('success', "Usuario {$user->name} creado correctamente.");
    }

    public function toggleActive(User $user)
    {
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
        $data = $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update(['password' => $data['password']]);
        AuditLogger::log('reset_password', "Contraseña reseteada para: {$user->email}");

        return back()->with('success', 'Contraseña actualizada correctamente.');
    }
}
