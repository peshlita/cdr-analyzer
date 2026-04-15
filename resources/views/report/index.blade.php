@extends('layouts.app')

@section('title', 'Reporte')
@section('page-title', 'Reporte de Análisis')
@section('page-subtitle', 'Resumen forense del expediente CDR')

@section('content')
<div class="space-y-6 max-w-5xl">

    <!-- Header card -->
    <div class="rounded-xl border border-slate-700 p-6" style="background-color:#1e293b;">
        <div class="flex items-start justify-between">
            <div>
                <h2 class="text-white font-bold text-lg">Informe de Análisis CDR</h2>
                <p class="text-slate-400 text-sm mt-1">Generado el {{ now()->format('d/m/Y H:i') }}</p>
                @if($targetNumber)
                <div class="mt-3 flex items-center gap-2">
                    <span class="text-xs text-slate-400">Número objetivo:</span>
                    <span class="px-2 py-0.5 rounded text-xs font-mono font-bold" style="background-color:#f59e0b20; color:#f59e0b;">
                        {{ $targetNumber }}
                    </span>
                </div>
                @endif
            </div>
            <a href="/report/pdf"
               class="flex items-center gap-2 px-5 py-2.5 rounded-lg text-white text-sm font-medium transition-all hover:opacity-90"
               style="background-color:#ef4444;">
                <i class="fas fa-file-pdf"></i> Descargar PDF
            </a>
        </div>
    </div>

    @if($stats['total'] === 0)
    <div class="rounded-xl border border-slate-700 p-12 text-center" style="background-color:#1e293b;">
        <i class="fas fa-database text-5xl text-slate-600 mb-4 block"></i>
        <p class="text-slate-400">No hay datos. Importa un CSV primero.</p>
    </div>
    @else

    <!-- Summary stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        @php $statCards = [
            ['label'=>'Período', 'value'=> \Carbon\Carbon::parse($stats['dateMin'])->format('d/m/Y').' → '.\Carbon\Carbon::parse($stats['dateMax'])->format('d/m/Y'), 'icon'=>'fas fa-calendar', 'color'=>'#3b82f6'],
            ['label'=>'Eventos totales', 'value'=>number_format($stats['total']), 'icon'=>'fas fa-database', 'color'=>'#10b981'],
            ['label'=>'Duración total', 'value'=> round($stats['totalSecs']/3600,1).' h', 'icon'=>'fas fa-clock', 'color'=>'#8b5cf6'],
            ['label'=>'IMEIs detectados', 'value'=>$stats['imeis'], 'icon'=>'fas fa-mobile-alt', 'color'=>'#f59e0b'],
        ]; @endphp
        @foreach($statCards as $c)
        <div class="rounded-xl border border-slate-700 p-4" style="background-color:#1e293b;">
            <div class="flex items-center gap-2 mb-2">
                <i class="{{ $c['icon'] }} text-xs" style="color:{{ $c['color'] }};"></i>
                <p class="text-xs text-slate-400">{{ $c['label'] }}</p>
            </div>
            <p class="text-lg font-bold text-white">{{ $c['value'] }}</p>
        </div>
        @endforeach
    </div>

    <!-- Network snapshot -->
    @if($networkSnapshot)
    <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
        <div class="px-5 py-4 border-b border-slate-700 flex items-center justify-between">
            <h3 class="text-white font-semibold text-sm">
                <i class="fas fa-project-diagram mr-2 text-blue-400"></i>Red de Llamadas (captura guardada)
            </h3>
            <a href="/network" class="text-xs text-blue-400 hover:text-blue-300 transition-colors">
                <i class="fas fa-external-link-alt mr-1"></i>Abrir y actualizar
            </a>
        </div>
        <div class="p-4" style="background-color:#0f172a;">
            <img src="{{ $networkSnapshot }}" alt="Red de llamadas"
                 class="w-full rounded-lg object-contain" style="max-height:480px;">
        </div>
    </div>
    @else
    <div class="rounded-xl border border-dashed border-slate-600 p-8 text-center" style="background-color:#1e293b;">
        <i class="fas fa-project-diagram text-3xl text-slate-600 mb-3 block"></i>
        <p class="text-slate-400 text-sm mb-3">No hay captura de la red guardada</p>
        <a href="/network"
           class="inline-flex items-center gap-2 text-xs px-4 py-2 rounded-lg text-white transition-all hover:opacity-90"
           style="background-color:#3b82f6;">
            <i class="fas fa-camera"></i> Ir a la red y guardar captura
        </a>
    </div>
    @endif

    <!-- Call breakdown -->
    <div class="grid grid-cols-2 gap-4">
        <div class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
            <h3 class="text-white font-semibold text-sm mb-4">Por Tipo</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-300 flex items-center gap-2">
                        <i class="fas fa-phone text-green-400 w-4"></i> Voz
                    </span>
                    <div class="flex items-center gap-3">
                        <div class="w-32 h-2 rounded-full bg-slate-700 overflow-hidden">
                            <div class="h-full rounded-full bg-green-500" style="width:{{ $stats['total'] ? round($stats['voice']/$stats['total']*100) : 0 }}%"></div>
                        </div>
                        <span class="text-white text-sm font-medium w-16 text-right">{{ number_format($stats['voice']) }}</span>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-300 flex items-center gap-2">
                        <i class="fas fa-wifi text-purple-400 w-4"></i> Datos
                    </span>
                    <div class="flex items-center gap-3">
                        <div class="w-32 h-2 rounded-full bg-slate-700 overflow-hidden">
                            <div class="h-full rounded-full bg-purple-500" style="width:{{ $stats['total'] ? round($stats['data']/$stats['total']*100) : 0 }}%"></div>
                        </div>
                        <span class="text-white text-sm font-medium w-16 text-right">{{ number_format($stats['data']) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
            <h3 class="text-white font-semibold text-sm mb-4">Por Dirección</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-300 flex items-center gap-2">
                        <i class="fas fa-arrow-up text-green-400 w-4"></i> Salientes
                    </span>
                    <div class="flex items-center gap-3">
                        <div class="w-32 h-2 rounded-full bg-slate-700 overflow-hidden">
                            <div class="h-full rounded-full bg-green-500" style="width:{{ $stats['total'] ? round($stats['outgoing']/$stats['total']*100) : 0 }}%"></div>
                        </div>
                        <span class="text-white text-sm font-medium w-16 text-right">{{ number_format($stats['outgoing']) }}</span>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-slate-300 flex items-center gap-2">
                        <i class="fas fa-arrow-down text-red-400 w-4"></i> Entrantes
                    </span>
                    <div class="flex items-center gap-3">
                        <div class="w-32 h-2 rounded-full bg-slate-700 overflow-hidden">
                            <div class="h-full rounded-full bg-red-500" style="width:{{ $stats['total'] ? round($stats['incoming']/$stats['total']*100) : 0 }}%"></div>
                        </div>
                        <span class="text-white text-sm font-medium w-16 text-right">{{ number_format($stats['incoming']) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top contacts table -->
    <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
        <div class="px-5 py-4 border-b border-slate-700">
            <h3 class="text-white font-semibold text-sm">Top 20 Números Contactados</h3>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-slate-400 uppercase tracking-wider border-b border-slate-700">
                    <th class="text-left px-4 py-3">#</th>
                    <th class="text-left px-4 py-3">Número</th>
                    <th class="text-left px-4 py-3">Nombre / Alias</th>
                    <th class="text-right px-4 py-3">Llamadas</th>
                    <th class="text-right px-4 py-3">Duración</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                @foreach($topContacts as $i => $contact)
                @php
                    $pc = \App\Models\PhoneContact::where('phone_number', $contact->number_b)->first();
                @endphp
                <tr class="hover:bg-slate-700/20 transition-colors">
                    <td class="px-4 py-3 text-slate-500 text-xs">{{ $i+1 }}</td>
                    <td class="px-4 py-3 font-mono text-slate-200">{{ $contact->number_b }}</td>
                    <td class="px-4 py-3">
                        @if($pc?->name)
                            <span class="text-white">{{ $pc->name }}</span>
                        @elseif($pc?->alias)
                            <span class="text-yellow-400">{{ $pc->alias }}</span>
                        @else
                            <span class="text-slate-500">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right text-blue-400 font-medium">{{ number_format($contact->calls) }}</td>
                    <td class="px-4 py-3 text-right text-slate-400 text-xs">{{ gmdate('H:i:s', $contact->total_duration) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Enriched contacts -->
    @if($enrichedContacts->count() > 0)
    <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
        <div class="px-5 py-4 border-b border-slate-700">
            <h3 class="text-white font-semibold text-sm">Contactos Identificados ({{ $enrichedContacts->count() }})</h3>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-slate-400 uppercase tracking-wider border-b border-slate-700">
                    <th class="text-left px-4 py-3">Número</th>
                    <th class="text-left px-4 py-3">Nombre</th>
                    <th class="text-left px-4 py-3">Alias</th>
                    <th class="text-left px-4 py-3">Notas</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                @foreach($enrichedContacts as $c)
                <tr>
                    <td class="px-4 py-2.5 font-mono text-slate-300 text-xs">{{ $c->phone_number }}</td>
                    <td class="px-4 py-2.5 text-white">{{ $c->name ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-yellow-400">{{ $c->alias ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-slate-400 text-xs">{{ $c->notes ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- IMEI list -->
    @if($imeiList->count() > 0)
    <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
        <div class="px-5 py-4 border-b border-slate-700">
            <h3 class="text-white font-semibold text-sm">IMEIs Detectados ({{ $imeiList->count() }})</h3>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-slate-400 uppercase tracking-wider border-b border-slate-700">
                    <th class="text-left px-4 py-3">IMEI</th>
                    <th class="text-left px-4 py-3">IMSI</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                @foreach($imeiList as $item)
                <tr>
                    <td class="px-4 py-2.5 font-mono text-slate-300">{{ $item->imei_a }}</td>
                    <td class="px-4 py-2.5 font-mono text-slate-400 text-xs">{{ $item->imsi_a ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <!-- Pernocta antenna -->
    @if(isset($pernoctaAntenna) && $pernoctaAntenna)
    <div class="rounded-xl border border-orange-700/40 p-5" style="background-color:#1e293b;">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background-color:#f9731620;">
                <i class="fas fa-moon text-orange-400"></i>
            </div>
            <h3 class="text-white font-semibold text-sm">Antena de Pernocta</h3>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            <div class="rounded-lg p-3" style="background-color:#0f172a;">
                <p class="text-slate-400 mb-1">Latitud</p>
                <p class="text-white font-mono font-bold">{{ number_format($pernoctaAntenna->lat_a, 6) }}</p>
            </div>
            <div class="rounded-lg p-3" style="background-color:#0f172a;">
                <p class="text-slate-400 mb-1">Longitud</p>
                <p class="text-white font-mono font-bold">{{ number_format($pernoctaAntenna->lon_a, 6) }}</p>
            </div>
            <div class="rounded-lg p-3" style="background-color:#0f172a;">
                <p class="text-slate-400 mb-1">Duración nocturna</p>
                <p class="text-orange-400 font-bold">{{ gmdate('H:i:s', $pernoctaAntenna->total_duration) }}</p>
            </div>
            <div class="rounded-lg p-3" style="background-color:#0f172a;">
                <p class="text-slate-400 mb-1">Sesiones</p>
                <p class="text-white font-bold">{{ $pernoctaAntenna->sessions }}</p>
            </div>
        </div>
    </div>
    @endif

    <!-- Nocturnal voice calls -->
    @if(isset($nocturnalVoice) && $nocturnalVoice->count() > 0)
    <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
        <div class="px-5 py-4 border-b border-slate-700">
            <h3 class="text-white font-semibold text-sm">🌙 Comunicaciones Nocturnas (23:00 — 07:00)</h3>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-slate-400 uppercase tracking-wider border-b border-slate-700">
                    <th class="text-left px-4 py-3">Número A</th>
                    <th class="text-left px-4 py-3">Número B</th>
                    <th class="text-left px-4 py-3">Dirección</th>
                    <th class="text-right px-4 py-3">Llamadas</th>
                    <th class="text-right px-4 py-3">Duración</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                @foreach($nocturnalVoice as $r)
                <tr class="hover:bg-slate-700/20">
                    <td class="px-4 py-2.5 font-mono text-slate-300 text-xs">{{ $r->number_a }}</td>
                    <td class="px-4 py-2.5 font-mono text-slate-300 text-xs">{{ $r->number_b }}</td>
                    <td class="px-4 py-2.5">
                        @if($r->direction === 'Outgoing')
                            <span class="text-xs text-green-400">⟶ Saliente</span>
                        @elseif($r->direction === 'Incoming')
                            <span class="text-xs text-red-400">⟵ Entrante</span>
                        @else
                            <span class="text-xs text-slate-500">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-right text-yellow-400 font-bold">🌙 {{ $r->calls }}</td>
                    <td class="px-4 py-2.5 text-right text-slate-400 text-xs">{{ gmdate('H:i:s', $r->total_duration) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @endif
</div>
@endsection
