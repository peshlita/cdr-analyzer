@extends('layouts.app')

@section('title', 'Instituciones')
@section('page-title', 'Instituciones')
@section('page-subtitle', 'Gestión de instituciones (tenants) del sistema')

@section('content')
<div class="space-y-6">

    {{-- Los mensajes flash (success/error) los muestra el layout una sola vez. --}}
    @if($errors->any())
        <div class="px-4 py-3 rounded-lg text-sm text-red-300" style="background-color:#450a0a; border:1px solid #7f1d1d;">
            <i class="fas fa-exclamation-circle"></i> {{ $errors->first() }}
        </div>
    @endif

    <!-- Header -->
    <div class="flex items-center justify-between">
        <h2 class="text-white font-semibold">Instituciones registradas ({{ $tenants->count() }})</h2>
        <button onclick="document.getElementById('modal-create-tenant').classList.remove('hidden')"
            class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white"
            style="background-color:#3b82f6;">
            <i class="fas fa-building"></i> Nueva Institución
        </button>
    </div>

    <!-- Tabla -->
    <div class="rounded-xl overflow-hidden" style="background-color:#1e293b; border:1px solid #334155;">
        <table class="w-full text-sm">
            <thead>
                <tr style="background-color:#0f172a; border-bottom:1px solid #334155;">
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">Nombre</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">Slug</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">Usuarios</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">GPS</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">Estado</th>
                    <th class="px-4 py-3 text-right text-slate-400 font-medium">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tenants as $tenant)
                <tr style="border-bottom:1px solid #334155;">
                    <td class="px-4 py-3 text-white font-medium">
                        {{ $tenant->name }}
                        @if($tenant->contact_email)
                        <div class="text-xs text-slate-500">{{ $tenant->contact_email }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-400 font-mono text-xs">{{ $tenant->slug }}</td>
                    <td class="px-4 py-3 text-slate-300">{{ $tenant->users_count }}</td>
                    <td class="px-4 py-3 text-slate-300">{{ $tenant->gps_units_count }}</td>
                    <td class="px-4 py-3">
                        @if($tenant->is_active)
                        <span class="px-2 py-1 rounded text-xs" style="background-color:#052e16; color:#4ade80;">Activo</span>
                        @else
                        <span class="px-2 py-1 rounded text-xs" style="background-color:#450a0a; color:#f87171;">Inactivo</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button onclick="document.getElementById('modal-edit-{{ $tenant->id }}').classList.remove('hidden')"
                            class="text-blue-400 hover:text-blue-300 mr-3" title="Editar">
                            <i class="fas fa-pen"></i>
                        </button>
                        <form action="{{ route('admin.tenants.toggle', $tenant) }}" method="POST" class="inline">
                            @csrf @method('PATCH')
                            <button type="submit" class="{{ $tenant->is_active ? 'text-amber-400 hover:text-amber-300' : 'text-green-400 hover:text-green-300' }}"
                                title="{{ $tenant->is_active ? 'Desactivar' : 'Activar' }}">
                                <i class="fas fa-power-off"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">Sin instituciones registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Modal crear institución -->
<div id="modal-create-tenant" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background-color:rgba(0,0,0,0.7);">
    <div class="rounded-xl w-full max-w-lg mx-4" style="background-color:#1e293b; border:1px solid #334155;">
        <form action="{{ route('admin.tenants.store') }}" method="POST">
            @csrf
            <div class="px-6 py-4 flex items-center justify-between" style="border-bottom:1px solid #334155;">
                <h3 class="text-white font-semibold">Nueva Institución</h3>
                <button type="button" onclick="document.getElementById('modal-create-tenant').classList.add('hidden')" class="text-slate-400 hover:text-white">×</button>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Nombre de la institución *</label>
                    <input name="name" required placeholder="Secretaría de Seguridad X"
                        class="w-full px-3 py-2 rounded-lg text-sm text-white" style="background-color:#0f172a; border:1px solid #334155;">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Slug (opcional)</label>
                        <input name="slug" placeholder="auto desde el nombre"
                            class="w-full px-3 py-2 rounded-lg text-sm text-white" style="background-color:#0f172a; border:1px solid #334155;">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Email de contacto</label>
                        <input name="contact_email" type="email"
                            class="w-full px-3 py-2 rounded-lg text-sm text-white" style="background-color:#0f172a; border:1px solid #334155;">
                    </div>
                </div>

                <div class="pt-2" style="border-top:1px solid #334155;">
                    <p class="text-xs text-slate-500 mb-3 mt-3">Administrador de la institución</p>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Nombre del admin *</label>
                            <input name="admin_name" required
                                class="w-full px-3 py-2 rounded-lg text-sm text-white" style="background-color:#0f172a; border:1px solid #334155;">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Email del admin *</label>
                                <input name="admin_email" type="email" required
                                    class="w-full px-3 py-2 rounded-lg text-sm text-white" style="background-color:#0f172a; border:1px solid #334155;">
                            </div>
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Contraseña temporal *</label>
                                <input name="admin_password" type="text" required minlength="8"
                                    class="w-full px-3 py-2 rounded-lg text-sm text-white" style="background-color:#0f172a; border:1px solid #334155;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 flex justify-end gap-3" style="border-top:1px solid #334155;">
                <button type="button" onclick="document.getElementById('modal-create-tenant').classList.add('hidden')"
                    class="px-4 py-2 rounded-lg text-sm text-slate-300" style="background-color:#334155;">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium text-white" style="background-color:#3b82f6;">Crear institución</button>
            </div>
        </form>
    </div>
</div>

<!-- Modales editar institución -->
@foreach($tenants as $tenant)
<div id="modal-edit-{{ $tenant->id }}" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background-color:rgba(0,0,0,0.7);">
    <div class="rounded-xl w-full max-w-md mx-4" style="background-color:#1e293b; border:1px solid #334155;">
        <form action="{{ route('admin.tenants.update', $tenant) }}" method="POST">
            @csrf @method('PUT')
            <div class="px-6 py-4 flex items-center justify-between" style="border-bottom:1px solid #334155;">
                <h3 class="text-white font-semibold">Editar — {{ $tenant->name }}</h3>
                <button type="button" onclick="document.getElementById('modal-edit-{{ $tenant->id }}').classList.add('hidden')" class="text-slate-400 hover:text-white">×</button>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Nombre *</label>
                    <input name="name" required value="{{ $tenant->name }}"
                        class="w-full px-3 py-2 rounded-lg text-sm text-white" style="background-color:#0f172a; border:1px solid #334155;">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Email de contacto</label>
                    <input name="contact_email" type="email" value="{{ $tenant->contact_email }}"
                        class="w-full px-3 py-2 rounded-lg text-sm text-white" style="background-color:#0f172a; border:1px solid #334155;">
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-300">
                    <input type="checkbox" name="is_active" value="1" {{ $tenant->is_active ? 'checked' : '' }}>
                    Institución activa
                </label>
            </div>
            <div class="px-6 py-4 flex justify-end gap-3" style="border-top:1px solid #334155;">
                <button type="button" onclick="document.getElementById('modal-edit-{{ $tenant->id }}').classList.add('hidden')"
                    class="px-4 py-2 rounded-lg text-sm text-slate-300" style="background-color:#334155;">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium text-white" style="background-color:#3b82f6;">Guardar</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endsection
