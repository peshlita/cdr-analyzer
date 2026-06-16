@extends('layouts.app')

@section('title', 'Permisos de ' . $user->name)
@section('page-title', 'Permisos por Módulo')
@section('page-subtitle', 'Configurando acceso para: ' . $user->name)

@section('content')
<div class="max-w-3xl space-y-6">

    {{-- Los mensajes flash los muestra el layout una sola vez. --}}

    <!-- Usuario info -->
    <div class="flex items-center gap-4 px-4 py-3 rounded-xl" style="background-color:#1e293b; border:1px solid #334155;">
        <div class="w-12 h-12 rounded-full flex items-center justify-center font-bold text-white text-lg"
            style="background-color:{{ $user->isSuperAdmin() ? '#dc2626' : '#3b82f6' }};">
            {{ $user->initials }}
        </div>
        <div>
            <p class="text-white font-semibold">{{ $user->name }}</p>
            <p class="text-slate-400 text-sm">{{ $user->email }} — <span class="{{ $user->isSuperAdmin() ? 'text-red-400' : 'text-blue-400' }}">{{ $user->role_label }}</span></p>
        </div>
    </div>

    @if($user->isSuperAdmin())
        <div class="px-4 py-3 rounded-lg text-sm text-yellow-300 flex items-center gap-2" style="background-color:#451a03; border:1px solid #78350f;">
            <i class="fas fa-crown"></i> El Super Admin tiene acceso completo a todos los módulos.
        </div>
    @else
        <form method="POST" action="{{ route('admin.users.permissions.update', $user) }}">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 gap-4">
                @foreach($modules as $slug => $module)
                @php $enabled = $permissions[$slug]?->enabled ?? false; @endphp
                <div class="flex items-center justify-between px-5 py-4 rounded-xl transition-all {{ $enabled ? '' : 'opacity-60' }}"
                    style="background-color:#1e293b; border:1px solid {{ $enabled ? '#3b82f6' : '#334155' }};">
                    <div class="flex items-center gap-4">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center"
                            style="background-color:{{ $enabled ? '#1e3a5f' : '#1e293b' }}; border:1px solid #334155;">
                            <i class="{{ $module['icon'] }} text-lg {{ $enabled ? 'text-blue-400' : 'text-slate-500' }}"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-white">{{ $module['name'] }}</p>
                            <p class="text-xs text-slate-400">{{ $module['desc'] }}</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="modules[]" value="{{ $slug }}"
                            {{ $enabled ? 'checked' : '' }}
                            class="sr-only peer"
                            onchange="
                                const card = this.closest('.transition-all');
                                card.style.borderColor = this.checked ? '#3b82f6' : '#334155';
                                card.classList.toggle('opacity-60', !this.checked);">
                        <div class="w-11 h-6 rounded-full bg-slate-600 peer-checked:bg-blue-500 transition-colors
                                    after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:bg-white
                                    after:rounded-full after:h-5 after:w-5 after:transition-all
                                    peer-checked:after:translate-x-full"></div>
                    </label>
                </div>
                @endforeach
            </div>

            <div class="flex gap-3 mt-6">
                <a href="{{ route('admin.users.index') }}"
                    class="flex-1 text-center py-2.5 rounded-lg text-sm text-slate-300 hover:text-white transition-colors"
                    style="background-color:#334155;">
                    <i class="fas fa-arrow-left mr-2"></i>Cancelar
                </a>
                <button type="submit"
                    class="flex-1 py-2.5 rounded-lg text-sm font-semibold text-white hover:opacity-90"
                    style="background-color:#3b82f6;">
                    <i class="fas fa-save mr-2"></i>Guardar Permisos
                </button>
            </div>
        </form>
    @endif
</div>
@endsection
