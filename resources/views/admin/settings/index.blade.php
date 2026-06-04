@extends('layouts.app')

@section('title', 'Configuración General')
@section('page-title', 'Configuración General')
@section('page-subtitle', 'Parámetros globales del sistema')

@section('content')
<div class="max-w-2xl space-y-6">

    @if(session('success'))
        <div class="px-4 py-3 rounded-lg text-sm text-green-300 flex items-center gap-2" style="background-color:#052e16; border:1px solid #14532d;">
            <i class="fas fa-check-circle"></i> {{ session('success') }}
        </div>
    @endif

    <div class="rounded-xl p-6" style="background-color:#1e293b; border:1px solid #334155;">
        <h2 class="text-white font-semibold mb-5 flex items-center gap-2">
            <i class="fas fa-cog text-blue-400"></i> Parámetros del Sistema
        </h2>

        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-5">
            @csrf @method('PUT')

            <!-- Nombre institución -->
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">
                    <i class="fas fa-building mr-1 text-slate-400"></i> Nombre de la Institución
                </label>
                <input type="text" name="institution_name"
                    value="{{ $settings['institution_name'] ?? 'Mi Institución' }}"
                    class="w-full px-4 py-2.5 rounded-lg text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    style="background-color:#0f172a; border:1px solid #334155;">
                <p class="text-xs text-slate-500 mt-1">Se muestra en los reportes PDF exportados.</p>
            </div>

            <!-- Tiempo de sesión -->
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-2">
                    <i class="fas fa-clock mr-1 text-slate-400"></i> Tiempo de Sesión (minutos)
                </label>
                <input type="number" name="session_lifetime" min="5" max="480"
                    value="{{ $settings['session_lifetime'] ?? 30 }}"
                    class="w-full px-4 py-2.5 rounded-lg text-sm text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    style="background-color:#0f172a; border:1px solid #334155;">
                <p class="text-xs text-slate-500 mt-1">Tiempo de inactividad antes de cerrar sesión automáticamente.</p>
            </div>

            <!-- Forzar 2FA -->
            <div class="flex items-center justify-between px-4 py-4 rounded-xl" style="background-color:#0f172a; border:1px solid #334155;">
                <div>
                    <p class="text-sm font-medium text-white">Forzar 2FA para todos los usuarios</p>
                    <p class="text-xs text-slate-400 mt-0.5">Obliga a todos los analistas a usar Google Authenticator.</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="force_2fa" value="1"
                        {{ ($settings['force_2fa'] ?? '0') === '1' ? 'checked' : '' }}
                        class="sr-only peer">
                    <div class="w-11 h-6 peer-checked:bg-blue-600 rounded-full relative transition-colors" style="background-color:#334155;">
                        <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full transition-transform peer-checked:translate-x-5"></div>
                    </div>
                </label>
            </div>

            <button type="submit"
                class="w-full py-2.5 rounded-lg text-sm font-semibold text-white hover:opacity-90"
                style="background-color:#3b82f6;">
                <i class="fas fa-save mr-2"></i> Guardar Configuración
            </button>
        </form>
    </div>
</div>
@endsection
