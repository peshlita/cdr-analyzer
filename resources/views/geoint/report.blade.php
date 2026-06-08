@extends('layouts.app')
@section('title', 'GEOINT — Reporte de Ruta')
@section('page-title', 'Reporte de Ruta')
@section('page-subtitle', 'Historial de movimientos por unidad y periodo')

@section('content')
<div class="space-y-6">

    {{-- Formulario de búsqueda --}}
    <form method="GET" action="{{ route('geoint.report') }}" class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="text-xs text-slate-400 block mb-1">Unidad GPS</label>
                <select name="unit_id"
                        class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-green-500">
                    <option value="">— Seleccionar —</option>
                    @foreach($units as $unit)
                    <option value="{{ $unit->id }}" {{ request('unit_id') == $unit->id ? 'selected' : '' }}>
                        {{ $unit->name }}{{ $unit->plate ? ' ('.$unit->plate.')' : '' }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Fecha inicio</label>
                <input type="datetime-local" name="from"
                       value="{{ request('from', now()->startOfDay()->format('Y-m-d\TH:i')) }}"
                       class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-green-500">
            </div>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Fecha fin</label>
                <input type="datetime-local" name="to"
                       value="{{ request('to', now()->format('Y-m-d\TH:i')) }}"
                       class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-green-500">
            </div>
        </div>
        <div class="flex gap-3 mt-4">
            <button type="submit"
                    class="px-5 py-2 rounded-lg text-sm font-medium text-white"
                    style="background-color:#10b981;">
                <i class="fas fa-search mr-1"></i> Generar Reporte
            </button>
            @if(request('unit_id'))
            <a href="{{ route('geoint.report') }}?{{ http_build_query(array_merge(request()->all(), ['pdf'=>1])) }}"
               class="px-5 py-2 rounded-lg text-sm font-medium text-white border border-slate-600 hover:border-red-500 hover:text-red-400 transition-colors">
                <i class="fas fa-file-pdf mr-1"></i> Exportar PDF
            </a>
            @endif
        </div>
    </form>

    @php
        $unitId  = request('unit_id');
        $from    = request('from') ? \Carbon\Carbon::parse(request('from')) : null;
        $to      = request('to')   ? \Carbon\Carbon::parse(request('to'))   : null;

        $positions = collect();
        $selectedUnit = null;
        $summary = [];

        if ($unitId && $from && $to) {
            $selectedUnit = \App\Models\GpsUnit::find($unitId);
            $positions = \App\Models\GpsPosition::where('gps_unit_id', $unitId)
                ->whereBetween('received_at', [$from, $to])
                ->orderBy('received_at')
                ->get();

            // Calcular distancia aproximada (suma de segmentos)
            $totalKm = 0;
            $stops    = 0;
            $prevPos  = null;
            foreach ($positions as $pos) {
                if ($prevPos) {
                    $d = $pos->speed < 2 ? 0 : haversineKm($prevPos->lat, $prevPos->lon, $pos->lat, $pos->lon);
                    $totalKm += $d;
                    if ($pos->speed < 2) $stops++;
                }
                $prevPos = $pos;
            }

            $summary = [
                'total'   => $positions->count(),
                'km'      => round($totalKm, 2),
                'stops'   => $stops,
                'max_spd' => $positions->max('speed') ?? 0,
                'avg_spd' => $positions->where('speed', '>', 0)->avg('speed') ?? 0,
            ];
        }

        if (!function_exists('haversineKm')) {
            function haversineKm($lat1, $lon1, $lat2, $lon2): float {
                $R = 6371;
                $dLat = deg2rad($lat2 - $lat1);
                $dLon = deg2rad($lon2 - $lon1);
                $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2)**2;
                return $R * 2 * atan2(sqrt($a), sqrt(1-$a));
            }
        }
    @endphp

    {{-- Resumen --}}
    @if($selectedUnit && $positions->isNotEmpty())
    <div>
        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-widest mb-3">
            Resumen — {{ $selectedUnit->name }} | {{ $from->format('d/m/Y H:i') }} al {{ $to->format('d/m/Y H:i') }}
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
            @php
            $cards = [
                ['icon'=>'fas fa-map-marker-alt','color'=>'#10b981','value'=>number_format($summary['total']),'label'=>'Posiciones'],
                ['icon'=>'fas fa-road',           'color'=>'#3b82f6','value'=>$summary['km'].' km',            'label'=>'Distancia est.'],
                ['icon'=>'fas fa-parking',        'color'=>'#f59e0b','value'=>number_format($summary['stops']),'label'=>'Paradas detect.'],
                ['icon'=>'fas fa-tachometer-alt', 'color'=>'#8b5cf6','value'=>round($summary['max_spd']).' km/h','label'=>'Vel. máxima'],
                ['icon'=>'fas fa-gauge',          'color'=>'#06b6d4','value'=>round($summary['avg_spd']).' km/h','label'=>'Vel. promedio'],
            ];
            @endphp
            @foreach($cards as $c)
            <div class="rounded-xl border border-slate-700 p-4" style="background-color:#1e293b;">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center mb-2" style="background-color:{{ $c['color'] }}20;">
                    <i class="{{ $c['icon'] }} text-xs" style="color:{{ $c['color'] }};"></i>
                </div>
                <p class="text-xl font-bold text-white">{{ $c['value'] }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ $c['label'] }}</p>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Tabla de posiciones --}}
    <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
        <div class="px-4 py-3 border-b border-slate-700">
            <h3 class="text-white font-semibold text-sm">Movimientos registrados ({{ $positions->count() }})</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-slate-700 text-slate-400 uppercase tracking-wider">
                        <th class="px-4 py-2 text-left">#</th>
                        <th class="px-4 py-2 text-left">Fecha/Hora</th>
                        <th class="px-4 py-2 text-left">Latitud</th>
                        <th class="px-4 py-2 text-left">Longitud</th>
                        <th class="px-4 py-2 text-left">Velocidad</th>
                        <th class="px-4 py-2 text-left">Rumbo</th>
                        <th class="px-4 py-2 text-left">Altitud</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700">
                    @foreach($positions->take(500) as $i => $pos)
                    <tr class="hover:bg-slate-800 {{ $pos->speed < 2 ? 'text-yellow-600' : 'text-slate-300' }}">
                        <td class="px-4 py-1.5 text-slate-600">{{ $i + 1 }}</td>
                        <td class="px-4 py-1.5">{{ $pos->received_at->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-1.5 font-mono">{{ number_format($pos->lat, 6) }}</td>
                        <td class="px-4 py-1.5 font-mono">{{ number_format($pos->lon, 6) }}</td>
                        <td class="px-4 py-1.5">
                            @if($pos->speed < 2)
                                <span class="text-yellow-500">Parado</span>
                            @else
                                {{ round($pos->speed) }} km/h
                            @endif
                        </td>
                        <td class="px-4 py-1.5">{{ $pos->heading ? round($pos->heading).'°' : '—' }}</td>
                        <td class="px-4 py-1.5">{{ $pos->altitude ? round($pos->altitude).' m' : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @if($positions->count() > 500)
            <p class="text-slate-500 text-xs text-center py-3">Mostrando 500 de {{ $positions->count() }} registros. Exporta PDF para ver todos.</p>
            @endif
        </div>
    </div>

    @elseif($unitId && $from && $to)
    <div class="rounded-xl border border-slate-700 p-12 text-center" style="background-color:#1e293b;">
        <i class="fas fa-map-marked-alt text-5xl text-slate-600 mb-4 block"></i>
        <p class="text-white font-semibold mb-2">Sin datos en el período seleccionado</p>
        <p class="text-slate-400 text-sm">No se encontraron posiciones GPS para esta unidad en el rango de fechas.</p>
    </div>
    @elseif(!$unitId)
    <div class="rounded-xl border border-slate-700 p-12 text-center" style="background-color:#1e293b;">
        <i class="fas fa-satellite text-5xl text-slate-600 mb-4 block"></i>
        <p class="text-slate-400 text-sm">Selecciona una unidad y un rango de fechas para generar el reporte.</p>
    </div>
    @endif

</div>
@endsection
