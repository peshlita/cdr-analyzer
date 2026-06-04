@extends('layouts.app')

@section('title', 'Gestión de Usuarios')
@section('page-title', 'Gestión de Usuarios')
@section('page-subtitle', 'Administra los usuarios del sistema')

@section('content')
<div class="space-y-6">

    @if(session('success'))
        <div class="px-4 py-3 rounded-lg text-sm text-green-300 flex items-center gap-2" style="background-color:#052e16; border:1px solid #14532d;">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="px-4 py-3 rounded-lg text-sm text-red-300 flex items-center gap-2" style="background-color:#450a0a; border:1px solid #7f1d1d;">
            <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
        </div>
    @endif

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-white font-semibold">Usuarios registrados ({{ $users->count() }})</h2>
        </div>
        <button onclick="document.getElementById('modal-create').classList.remove('hidden')"
            class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white"
            style="background-color:#3b82f6;">
            <i class="fas fa-plus"></i> Nuevo Usuario
        </button>
    </div>

    <!-- Tabla -->
    <div class="rounded-xl overflow-hidden" style="background-color:#1e293b; border:1px solid #334155;">
        <table class="w-full text-sm">
            <thead>
                <tr style="background-color:#0f172a; border-bottom:1px solid #334155;">
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">Usuario</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">Rol</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">Estado</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">2FA</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">Último acceso</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">IP</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                @foreach($users as $user)
                <tr class="hover:bg-slate-700/30">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white"
                                style="background-color:{{ $user->isSuperAdmin() ? '#dc2626' : '#3b82f6' }};">
                                {{ $user->initials }}
                            </div>
                            <div>
                                <p class="text-white font-medium">{{ $user->name }}</p>
                                <p class="text-slate-400 text-xs">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded text-xs font-medium {{ $user->isSuperAdmin() ? 'bg-red-900 text-red-300' : 'bg-blue-900 text-blue-300' }}">
                            {{ $user->role_label }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <form method="POST" action="{{ route('admin.users.toggle', $user) }}" class="inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="flex items-center gap-1.5 text-xs font-medium px-2 py-0.5 rounded {{ $user->is_active ? 'bg-green-900 text-green-300' : 'bg-slate-700 text-slate-400' }}">
                                <i class="fas fa-circle text-xs"></i>
                                {{ $user->is_active ? 'Activo' : 'Inactivo' }}
                            </button>
                        </form>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs {{ $user->two_factor_enabled ? 'text-green-400' : 'text-slate-500' }}">
                            <i class="fas {{ $user->two_factor_enabled ? 'fa-shield-alt' : 'fa-shield' }} mr-1"></i>
                            {{ $user->two_factor_enabled ? 'Activo' : 'Inactivo' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-400 text-xs">
                        {{ $user->last_login_at ? $user->last_login_at->format('d/m/Y H:i') : 'Nunca' }}
                    </td>
                    <td class="px-4 py-3 text-slate-400 text-xs font-mono">
                        {{ $user->last_login_ip ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.users.permissions', $user) }}"
                                class="text-xs px-2 py-1 rounded text-purple-300 hover:text-white"
                                style="background-color:#581c87;" title="Permisos">
                                <i class="fas fa-key"></i>
                            </a>
                            <button onclick="openResetModal({{ $user->id }}, '{{ $user->name }}')"
                                class="text-xs px-2 py-1 rounded text-yellow-300 hover:text-white"
                                style="background-color:#78350f;" title="Reset contraseña">
                                <i class="fas fa-lock"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Crear usuario -->
<div id="modal-create" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background-color:rgba(0,0,0,0.7);">
    <div class="w-full max-w-md rounded-2xl p-6 shadow-2xl" style="background-color:#1e293b; border:1px solid #334155;">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-white font-semibold text-lg">Nuevo Usuario</h3>
            <button onclick="document.getElementById('modal-create').classList.add('hidden')" class="text-slate-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm text-slate-300 mb-1">Nombre completo</label>
                <input type="text" name="name" required class="w-full px-3 py-2 rounded-lg text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500" style="background-color:#0f172a; border:1px solid #334155;">
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Correo electrónico</label>
                <input type="email" name="email" required class="w-full px-3 py-2 rounded-lg text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500" style="background-color:#0f172a; border:1px solid #334155;">
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Contraseña</label>
                <input type="password" name="password" required minlength="8" class="w-full px-3 py-2 rounded-lg text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500" style="background-color:#0f172a; border:1px solid #334155;">
            </div>
            <div>
                <label class="block text-sm text-slate-300 mb-1">Rol</label>
                <select name="role" required class="w-full px-3 py-2 rounded-lg text-sm text-white focus:outline-none" style="background-color:#0f172a; border:1px solid #334155;">
                    <option value="analyst">Analista</option>
                    <option value="super_admin">Super Admin</option>
                </select>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modal-create').classList.add('hidden')"
                    class="flex-1 py-2 rounded-lg text-sm text-slate-300 hover:text-white" style="background-color:#334155;">
                    Cancelar
                </button>
                <button type="submit" class="flex-1 py-2 rounded-lg text-sm font-medium text-white" style="background-color:#3b82f6;">
                    Crear Usuario
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Reset contraseña -->
<div id="modal-reset" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background-color:rgba(0,0,0,0.7);">
    <div class="w-full max-w-sm rounded-2xl p-6 shadow-2xl" style="background-color:#1e293b; border:1px solid #334155;">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-white font-semibold">Reset de Contraseña</h3>
            <button onclick="document.getElementById('modal-reset').classList.add('hidden')" class="text-slate-400 hover:text-white"><i class="fas fa-times"></i></button>
        </div>
        <p class="text-slate-400 text-sm mb-4">Cambiando contraseña de: <strong id="reset-user-name" class="text-white"></strong></p>
        <form id="reset-form" method="POST" class="space-y-3">
            @csrf @method('PATCH')
            <input type="password" name="password" required minlength="8" placeholder="Nueva contraseña"
                class="w-full px-3 py-2 rounded-lg text-sm text-white focus:outline-none focus:ring-2 focus:ring-yellow-500" style="background-color:#0f172a; border:1px solid #334155;">
            <input type="password" name="password_confirmation" required placeholder="Confirmar contraseña"
                class="w-full px-3 py-2 rounded-lg text-sm text-white focus:outline-none focus:ring-2 focus:ring-yellow-500" style="background-color:#0f172a; border:1px solid #334155;">
            <div class="flex gap-3 pt-1">
                <button type="button" onclick="document.getElementById('modal-reset').classList.add('hidden')"
                    class="flex-1 py-2 rounded-lg text-sm text-slate-300" style="background-color:#334155;">Cancelar</button>
                <button type="submit" class="flex-1 py-2 rounded-lg text-sm font-medium text-white" style="background-color:#d97706;">
                    Actualizar
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function openResetModal(userId, userName) {
    document.getElementById('reset-user-name').textContent = userName;
    document.getElementById('reset-form').action = '/admin/users/' + userId + '/reset-password';
    document.getElementById('modal-reset').classList.remove('hidden');
}
</script>
@endpush
@endsection
