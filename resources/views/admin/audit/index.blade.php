@extends('layouts.app')

@section('title', 'Log de Auditoría')
@section('page-title', 'Log de Auditoría')
@section('page-subtitle', 'Registro de todas las acciones del sistema')

@section('content')
<div class="space-y-5">

    <!-- Filtros -->
    <div class="rounded-xl p-4" style="background-color:#1e293b; border:1px solid #334155;">
        <form method="GET" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-36">
                <label class="block text-xs text-slate-400 mb-1">Usuario</label>
                <select name="user_id" class="w-full px-3 py-2 rounded-lg text-sm text-white focus:outline-none" style="background-color:#0f172a; border:1px solid #334155;">
                    <option value="">Todos</option>
                    @foreach($users as $u)
                        <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-36">
                <label class="block text-xs text-slate-400 mb-1">Módulo</label>
                <select name="module" class="w-full px-3 py-2 rounded-lg text-sm text-white focus:outline-none" style="background-color:#0f172a; border:1px solid #334155;">
                    <option value="">Todos</option>
                    @foreach(['comint','osint','incidencia','geoint','casos'] as $m)
                        <option value="{{ $m }}" {{ request('module') === $m ? 'selected' : '' }}>{{ strtoupper($m) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Desde</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                    class="px-3 py-2 rounded-lg text-sm text-white focus:outline-none" style="background-color:#0f172a; border:1px solid #334155;">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Hasta</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                    class="px-3 py-2 rounded-lg text-sm text-white focus:outline-none" style="background-color:#0f172a; border:1px solid #334155;">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium text-white" style="background-color:#3b82f6;">
                    <i class="fas fa-filter mr-1"></i> Filtrar
                </button>
                <a href="{{ route('admin.audit.index') }}" class="px-4 py-2 rounded-lg text-sm text-slate-400 hover:text-white" style="background-color:#334155;">
                    <i class="fas fa-times"></i>
                </a>
                <a href="{{ route('admin.audit.export') }}?{{ http_build_query(request()->all()) }}"
                    class="px-4 py-2 rounded-lg text-sm font-medium text-white flex items-center gap-1"
                    style="background-color:#dc2626;">
                    <i class="fas fa-file-pdf"></i> PDF
                </a>
            </div>
        </form>
    </div>

    <!-- Tabla -->
    <div class="rounded-xl overflow-hidden" style="background-color:#1e293b; border:1px solid #334155;">
        <table class="w-full text-sm">
            <thead>
                <tr style="background-color:#0f172a; border-bottom:1px solid #334155;">
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">Usuario</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">Acción</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">Módulo</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">Descripción</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">IP</th>
                    <th class="px-4 py-3 text-left text-slate-400 font-medium">Fecha</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                @forelse($logs as $log)
                <tr class="hover:bg-slate-700/30">
                    <td class="px-4 py-3">
                        <p class="text-white text-xs font-medium">{{ $log->user?->name ?? 'Sistema' }}</p>
                        <p class="text-slate-500 text-xs">{{ $log->user_email }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded text-xs font-mono
                            @if(str_contains($log->action,'login')) bg-green-900 text-green-300
                            @elseif(str_contains($log->action,'logout')) bg-slate-700 text-slate-300
                            @elseif(str_contains($log->action,'create')) bg-blue-900 text-blue-300
                            @elseif(str_contains($log->action,'delete')) bg-red-900 text-red-300
                            @else bg-slate-700 text-slate-300
                            @endif">
                            {{ $log->action }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-slate-400 text-xs uppercase">
                        {{ $log->module ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-slate-300 text-xs max-w-xs truncate">
                        {{ $log->description ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-slate-400 text-xs font-mono">{{ $log->ip_address ?? '—' }}</td>
                    <td class="px-4 py-3 text-slate-400 text-xs whitespace-nowrap">
                        {{ $log->created_at->format('d/m/Y H:i:s') }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-slate-500">
                        <i class="fas fa-inbox text-2xl mb-2 block"></i>
                        No hay registros de auditoría
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $logs->links() }}
</div>
@endsection
