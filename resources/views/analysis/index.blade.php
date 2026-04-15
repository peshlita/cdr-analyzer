@extends('layouts.app')

@section('title', 'Análisis')
@section('page-title', 'Análisis de Comunicaciones')
@section('page-subtitle', 'Patrones, incidencia y comportamiento del objetivo')

@section('content')
<div class="space-y-5" x-data="{ tab: 'incidence' }">

    @if($empty ?? false)
    <div class="rounded-xl border border-slate-700 p-12 text-center" style="background-color:#1e293b;">
        <i class="fas fa-microscope text-5xl text-slate-600 mb-4 block"></i>
        <h2 class="text-white font-semibold text-lg mb-2">Sin datos cargados</h2>
        <p class="text-slate-400 text-sm mb-5">Importa un archivo CSV para comenzar el análisis.</p>
        <a href="/upload" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-white text-sm font-medium" style="background-color:#3b82f6;">
            <i class="fas fa-upload"></i> Importar CSV
        </a>
    </div>
    @else

    {{-- Header con objetivo --}}
    @if($targetNumber)
    <div class="flex items-center gap-3 px-5 py-3 rounded-xl border border-amber-700/40" style="background-color:#1e293b;">
        <i class="fas fa-crosshairs text-amber-400"></i>
        <span class="text-xs text-slate-400">Número objetivo:</span>
        <span class="font-mono font-bold text-amber-400 text-sm">{{ $targetNumber }}</span>
    </div>
    @endif

    {{-- Tab nav --}}
    <div class="flex gap-1 p-1 rounded-xl" style="background-color:#0f172a;">
        <button @click="tab='incidence'"
                :class="tab==='incidence' ? 'bg-blue-600 text-white shadow' : 'text-slate-400 hover:text-white'"
                class="flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-sm font-medium transition-all">
            <i class="fas fa-sort-amount-down text-xs"></i> Por Incidencia
        </button>
        <button @click="tab='duration'"
                :class="tab==='duration' ? 'bg-blue-600 text-white shadow' : 'text-slate-400 hover:text-white'"
                class="flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-sm font-medium transition-all">
            <i class="fas fa-clock text-xs"></i> Por Duración
        </button>
        <button @click="tab='nocturnal'"
                :class="tab==='nocturnal' ? 'bg-blue-600 text-white shadow' : 'text-slate-400 hover:text-white'"
                class="flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-sm font-medium transition-all">
            🌙 Nocturnas
        </button>
        <button @click="tab='patterns'"
                :class="tab==='patterns' ? 'bg-blue-600 text-white shadow' : 'text-slate-400 hover:text-white'"
                class="flex-1 flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-sm font-medium transition-all">
            <i class="fas fa-chart-bar text-xs"></i> Patrones
        </button>
    </div>

    {{-- SECCIÓN 1: Por mayor incidencia --}}
    <div x-show="tab==='incidence'" x-cloak>
        <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
            <div class="px-5 py-4 border-b border-slate-700 flex items-center justify-between">
                <h3 class="text-white font-semibold text-sm">
                    <i class="fas fa-sort-amount-down text-blue-400 mr-2"></i>Por Mayor Incidencia
                </h3>
                <span class="text-xs text-slate-500">{{ count($incidenceList) }} contactos</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-slate-400 uppercase tracking-wider border-b border-slate-700" style="background-color:#0f172a;">
                            <th class="text-left px-4 py-3 w-12">#</th>
                            <th class="text-left px-4 py-3">Número</th>
                            <th class="text-left px-4 py-3">Nombre</th>
                            <th class="text-center px-4 py-3">
                                <span class="text-red-400">⟵</span> Entrantes
                            </th>
                            <th class="text-center px-4 py-3">
                                <span class="text-green-400">⟶</span> Salientes
                            </th>
                            <th class="text-center px-4 py-3">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        @foreach($incidenceList as $i => $item)
                        <tr class="hover:bg-slate-700/20 transition-colors">
                            <td class="px-4 py-3 text-slate-500 text-xs">
                                {{ $i + 1 }}
                                @if($i === 0)<span class="ml-1">👑</span>@endif
                            </td>
                            <td class="px-4 py-3 font-mono text-slate-200 text-xs">{{ $item['number'] }}</td>
                            <td class="px-4 py-3 text-slate-300 text-xs">{{ $item['name'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($item['in'] > 0)
                                    <span class="text-red-400 font-medium">⟵ {{ $item['in'] }}</span>
                                @else
                                    <span class="text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($item['out'] > 0)
                                    <span class="text-green-400 font-medium">⟶ {{ $item['out'] }}</span>
                                @else
                                    <span class="text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($item['in'] > 0 && $item['out'] > 0)
                                    <span class="px-2 py-0.5 rounded text-xs font-bold" style="background-color:#8b5cf620; color:#a78bfa;">⟷ {{ $item['total'] }}</span>
                                @elseif($item['out'] > 0)
                                    <span class="px-2 py-0.5 rounded text-xs font-bold" style="background-color:#10b98120; color:#34d399;">⟶ {{ $item['total'] }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-xs font-bold" style="background-color:#ef444420; color:#f87171;">⟵ {{ $item['total'] }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- SECCIÓN 2: Por mayor duración --}}
    <div x-show="tab==='duration'" x-cloak>
        <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
            <div class="px-5 py-4 border-b border-slate-700 flex items-center justify-between">
                <h3 class="text-white font-semibold text-sm">
                    <i class="fas fa-clock text-purple-400 mr-2"></i>Por Mayor Duración
                </h3>
                <span class="text-xs text-slate-500">{{ count($byDuration) }} contactos</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-slate-400 uppercase tracking-wider border-b border-slate-700" style="background-color:#0f172a;">
                            <th class="text-left px-4 py-3 w-12">#</th>
                            <th class="text-left px-4 py-3">Número</th>
                            <th class="text-left px-4 py-3">Nombre</th>
                            <th class="text-left px-4 py-3">Duración</th>
                            <th class="text-left px-4 py-3 w-48">Proporción</th>
                            <th class="text-center px-4 py-3">Llamadas</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        @foreach($byDuration as $i => $item)
                        @php $pct = $maxDuration > 0 ? round($item['duration'] / $maxDuration * 100) : 0; @endphp
                        <tr class="hover:bg-slate-700/20 transition-colors">
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-mono text-slate-200 text-xs">{{ $item['number'] }}</td>
                            <td class="px-4 py-3 text-slate-300 text-xs">{{ $item['name'] ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-purple-400 text-xs font-medium">
                                {{ gmdate('H:i:s', $item['duration']) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="w-full h-2 rounded-full" style="background-color:#334155;">
                                    <div class="h-full rounded-full" style="width:{{ $pct }}%; background-color:#8b5cf6;"></div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center text-blue-400 text-xs font-medium">{{ $item['total'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- SECCIÓN 3: Comunicaciones nocturnas --}}
    <div x-show="tab==='nocturnal'" x-cloak>
        <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
            <div class="px-5 py-4 border-b border-slate-700 flex items-center justify-between">
                <h3 class="text-white font-semibold text-sm">
                    🌙 Comunicaciones Nocturnas
                    <span class="ml-2 text-xs text-slate-400 font-normal">23:00 — 07:00</span>
                </h3>
                <span class="text-xs text-slate-500">{{ count($nocturnalList) }} contactos</span>
            </div>
            @if(count($nocturnalList) === 0)
            <div class="p-10 text-center text-slate-500 text-sm">
                <i class="fas fa-moon text-2xl mb-2 block"></i>
                No se registraron comunicaciones en horario nocturno.
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-xs text-slate-400 uppercase tracking-wider border-b border-slate-700" style="background-color:#0f172a;">
                            <th class="text-left px-4 py-3 w-12">#</th>
                            <th class="text-left px-4 py-3">Número</th>
                            <th class="text-left px-4 py-3">Nombre</th>
                            <th class="text-center px-4 py-3">Llamadas nocturnas</th>
                            <th class="text-center px-4 py-3">Duración</th>
                            <th class="text-center px-4 py-3">Patrón</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/40">
                        @foreach($nocturnalList as $i => $item)
                        <tr class="hover:bg-slate-700/20 transition-colors {{ $item['only_nocturnal'] ? 'bg-red-900/10' : '' }}">
                            <td class="px-4 py-3 text-slate-500 text-xs">{{ $i + 1 }}</td>
                            <td class="px-4 py-3 font-mono text-slate-200 text-xs">{{ $item['number'] }}</td>
                            <td class="px-4 py-3 text-slate-300 text-xs">{{ $item['name'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-center text-yellow-400 font-bold">🌙 {{ $item['count'] }}</td>
                            <td class="px-4 py-3 text-center font-mono text-slate-400 text-xs">
                                {{ gmdate('H:i:s', $item['duration']) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($item['only_nocturnal'])
                                    <span class="px-2 py-1 rounded text-xs font-medium" style="background-color:#ef444420; color:#f87171;">
                                        ⚠️ Solo nocturno
                                    </span>
                                @else
                                    <span class="text-slate-600 text-xs">Normal</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>

    {{-- SECCIÓN 4: Patrones generales --}}
    <div x-show="tab==='patterns'" x-cloak>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

            {{-- Hora pico --}}
            <div class="rounded-xl border border-slate-700 p-6" style="background-color:#1e293b;">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color:#3b82f620;">
                        <i class="fas fa-clock text-blue-400"></i>
                    </div>
                    <p class="text-xs text-slate-400 uppercase tracking-wider">Hora Pico de Actividad</p>
                </div>
                <p class="text-3xl font-bold text-white">{{ $patterns['peakHour'] }}</p>
                <p class="text-xs text-slate-500 mt-1">Franja horaria con más llamadas de voz</p>
            </div>

            {{-- Día más activo --}}
            <div class="rounded-xl border border-slate-700 p-6" style="background-color:#1e293b;">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color:#10b98120;">
                        <i class="fas fa-calendar-day text-green-400"></i>
                    </div>
                    <p class="text-xs text-slate-400 uppercase tracking-wider">Día con Más Llamadas</p>
                </div>
                <p class="text-2xl font-bold text-white">{{ $patterns['peakDay'] }}</p>
                <p class="text-xs text-slate-500 mt-1">Mayor concentración de actividad</p>
            </div>

            {{-- Promedio diario --}}
            <div class="rounded-xl border border-slate-700 p-6" style="background-color:#1e293b;">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color:#8b5cf620;">
                        <i class="fas fa-chart-line text-purple-400"></i>
                    </div>
                    <p class="text-xs text-slate-400 uppercase tracking-wider">Promedio Diario</p>
                </div>
                <p class="text-3xl font-bold text-white">{{ $patterns['avgPerDay'] }}</p>
                <p class="text-xs text-slate-500 mt-1">Llamadas de voz por día (promedio)</p>
            </div>

            {{-- Número con mayor tiempo --}}
            @if($patterns['topDuration'])
            <div class="rounded-xl border border-slate-700 p-6" style="background-color:#1e293b;">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color:#f59e0b20;">
                        <i class="fas fa-trophy text-amber-400"></i>
                    </div>
                    <p class="text-xs text-slate-400 uppercase tracking-wider">Mayor Tiempo Acumulado</p>
                </div>
                <p class="font-mono text-amber-400 text-sm font-bold mb-1">
                    {{ $patterns['topDuration']['number'] }}
                </p>
                @if($patterns['topDuration']['name'])
                <p class="text-white text-sm mb-1">{{ $patterns['topDuration']['name'] }}</p>
                @endif
                <p class="text-2xl font-bold text-white">
                    {{ floor($patterns['topDuration']['duration'] / 60) }} min
                    {{ $patterns['topDuration']['duration'] % 60 }} seg
                </p>
                <p class="text-xs text-slate-500 mt-1">Tiempo total de comunicación</p>
            </div>
            @endif

        </div>
    </div>

    @endif
</div>
@endsection
