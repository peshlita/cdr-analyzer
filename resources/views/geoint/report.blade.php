@extends('layouts.app')
@section('title', 'GEOINT — Reporte de Ruta')
@section('page-title', 'Reporte de Ruta')
@section('page-subtitle', 'Análisis de movimientos por unidad y periodo')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
#report-map { height: 100%; min-height: 480px; border-radius: 0.75rem; }
.timeline-bar {
    position: relative; height: 14px; border-radius: 7px;
    background: #0f172a; border: 1px solid #334155;
    margin-bottom: 28px;
}
.tl-dot {
    position: absolute; top: 50%; transform: translate(-50%, -50%);
    width: 13px; height: 13px; border-radius: 50%;
    border: 2px solid #0f172a; cursor: pointer;
    transition: transform .15s;
    z-index: 2;
}
.tl-dot:hover { transform: translate(-50%, -50%) scale(1.5); }
.tl-dot-label {
    position: absolute; top: 18px; transform: translateX(-50%);
    white-space: nowrap; font-size: 10px; color: #94a3b8;
    pointer-events: none;
}
</style>
@endpush

@section('content')
<div class="space-y-5">

{{-- FORM --}}
<form method="GET" action="{{ route('geoint.report') }}"
      class="rounded-xl border border-slate-700 p-4" style="background-color:#1e293b;">
    <div class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[180px]">
            <label class="text-xs text-slate-400 block mb-1">Unidad GPS</label>
            <select name="unit_id"
                    class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-green-500">
                <option value="">— Seleccionar —</option>
                @foreach($units as $u)
                <option value="{{ $u->id }}" {{ (string)request('unit_id') === (string)$u->id ? 'selected' : '' }}>
                    {{ $u->name }}{{ $u->plate ? ' ('.$u->plate.')' : '' }}
                </option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[160px]">
            <label class="text-xs text-slate-400 block mb-1">Desde</label>
            <input type="datetime-local" name="from"
                   value="{{ request('from', now()->startOfDay()->format('Y-m-d\TH:i')) }}"
                   class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-green-500">
        </div>
        <div class="flex-1 min-w-[160px]">
            <label class="text-xs text-slate-400 block mb-1">Hasta</label>
            <input type="datetime-local" name="to"
                   value="{{ request('to', now()->format('Y-m-d\TH:i')) }}"
                   class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-green-500">
        </div>
        <div class="flex gap-2 flex-shrink-0">
            <button type="submit"
                    class="px-5 py-2 rounded-lg text-sm font-medium text-white"
                    style="background-color:#10b981;">
                <i class="fas fa-search mr-1"></i> Generar
            </button>
            @if(request('unit_id') && $unit && $positions->isNotEmpty())
            <button type="button" id="btn-pdf-export" onclick="generatePdfReport()"
               class="px-4 py-2 rounded-lg text-sm font-medium text-red-400 border border-red-800 hover:border-red-500 hover:text-red-300 transition-colors">
                <span id="btn-pdf-label"><i class="fas fa-file-pdf mr-1"></i> PDF</span>
            </button>
            @endif
        </div>
    </div>
</form>

@if($unit && $positions->isNotEmpty())

{{-- SUMMARY CARDS --}}
@php
$h = intdiv($summary['moving_time'], 60);
$m = $summary['moving_time'] % 60;
$activeLabel = ($h > 0 ? "{$h}h " : '') . "{$m}min";
$cards = [
    ['icon'=>'fa-road',           'color'=>'#3b82f6', 'value'=>$summary['totalKm'].' km',    'label'=>'Distancia recorrida'],
    ['icon'=>'fa-clock',          'color'=>'#10b981', 'value'=>$activeLabel,                  'label'=>'Tiempo en movimiento'],
    ['icon'=>'fa-map-pin',        'color'=>'#f59e0b', 'value'=>$summary['stops'],             'label'=>'Paradas detectadas'],
    ['icon'=>'fa-tachometer-alt', 'color'=>'#ef4444', 'value'=>$summary['maxSpeed'].' km/h', 'label'=>'Velocidad máxima'],
    ['icon'=>'fa-moon',           'color'=>'#8b5cf6', 'value'=>$summary['pernoctas'],         'label'=>'Pernoctas'],
];
@endphp
<div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
    @foreach($cards as $c)
    <div class="rounded-xl border border-slate-700 p-4" style="background-color:#1e293b;">
        <div class="w-8 h-8 rounded-lg flex items-center justify-center mb-2"
             style="background-color:{{ $c['color'] }}20;">
            <i class="fas {{ $c['icon'] }} text-xs" style="color:{{ $c['color'] }};"></i>
        </div>
        <p class="text-xl font-bold text-white">{{ $c['value'] }}</p>
        <p class="text-xs text-slate-400 mt-0.5">{{ $c['label'] }}</p>
    </div>
    @endforeach
</div>

{{-- MAP 70% + STOPS PANEL 30% --}}
<div class="flex gap-4" style="height:520px;">

    <div class="rounded-xl border border-slate-700 overflow-hidden" style="flex:7;">
        <div id="report-map"></div>
    </div>

    <div class="rounded-xl border border-slate-700 flex flex-col overflow-hidden flex-shrink-0"
         style="flex:3;background-color:#1e293b;">
        <div class="px-4 py-3 border-b border-slate-700 flex-shrink-0">
            <h3 class="text-white font-semibold text-sm flex items-center gap-2">
                <i class="fas fa-map-pin text-yellow-400"></i>
                Paradas ({{ count($stops) }})
            </h3>
        </div>
        <div class="flex-1 overflow-y-auto">
            @forelse($stops as $i => $stop)
            @php
            $sIcon  = match($stop['type']) { 'overnight'=>'fa-moon', 'long'=>'fa-clock', default=>'fa-map-pin' };
            $sColor = match($stop['type']) { 'overnight'=>'#8b5cf6', 'long'=>'#f59e0b', default=>'#3b82f6' };
            $sLabel = $stop['type_label'];
            $sh = intdiv($stop['duration'], 60); $sm = $stop['duration'] % 60;
            $sDur = ($sh > 0 ? "{$sh}h " : '') . "{$sm}min";
            @endphp
            <div class="px-4 py-3 border-b border-slate-800 cursor-pointer hover:bg-slate-800 transition-colors"
                 onclick="flyToStop({{ $stop['lat'] }}, {{ $stop['lon'] }}, {{ $i }})">
                <div class="flex items-center gap-2 mb-1">
                    <i class="fas {{ $sIcon }} text-xs" style="color:{{ $sColor }};"></i>
                    <span class="text-xs font-semibold text-white">{{ $sLabel }}</span>
                    <span class="text-xs text-slate-500 ml-auto font-mono">{{ substr($stop['start'],-5) }}–{{ substr($stop['end'],-5) }}</span>
                </div>
                <div class="flex items-center gap-3 text-xs text-slate-400">
                    <span><i class="fas fa-clock mr-1"></i>{{ $sDur }}</span>
                    <span class="font-mono text-slate-600 text-xs">{{ number_format($stop['lat'], 4) }}, {{ number_format($stop['lon'], 4) }}</span>
                </div>
            </div>
            @empty
            <div class="p-8 text-center text-slate-500 text-sm">
                <i class="fas fa-check-circle text-green-500 text-2xl block mb-2"></i>
                Sin paradas detectadas
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- 24H TIMELINE BAR --}}
<div class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
    <h3 class="text-white font-semibold text-sm mb-5 flex items-center gap-2">
        <i class="fas fa-bars-staggered text-blue-400"></i>
        Línea de tiempo — {{ $from->format('d/m/Y') }}
    </h3>

    <div class="timeline-bar">
        @foreach($timeline as $event)
        @php
        $parts = explode(':', $event['time']);
        $pct   = round(((int)$parts[0] * 60 + (int)$parts[1]) / 1440 * 100, 2);
        $dotColor = match($event['type']) {
            'start'     => '#10b981',
            'end'       => '#ef4444',
            'overnight' => '#8b5cf6',
            'long'      => '#f59e0b',
            'short'     => '#3b82f6',
            'speed'     => '#f97316',
            default     => '#6b7280',
        };
        @endphp
        <div class="tl-dot"
             title="{{ $event['time'] }} — {{ $event['label'] }}"
             style="left:{{ $pct }}%;background:{{ $dotColor }};"
             onclick="flyToEvent({{ $event['lat'] }}, {{ $event['lon'] }})">
        </div>
        @endforeach

        {{-- Hour labels --}}
        <div class="flex justify-between" style="position:absolute;left:0;right:0;top:18px;">
            @for($hr = 0; $hr <= 24; $hr += 4)
            <span class="text-slate-600" style="font-size:10px;">{{ str_pad($hr, 2, '0', STR_PAD_LEFT) }}:00</span>
            @endfor
        </div>
    </div>

    {{-- Event list --}}
    <div class="space-y-0.5 max-h-48 overflow-y-auto mt-2">
        @foreach($timeline as $event)
        @php
        [$evIcon, $evColor] = match($event['type']) {
            'start'     => ['fa-play',           '#10b981'],
            'end'       => ['fa-flag-checkered', '#ef4444'],
            'overnight' => ['fa-moon',           '#8b5cf6'],
            'long'      => ['fa-clock',          '#f59e0b'],
            'short'     => ['fa-map-pin',        '#3b82f6'],
            'speed'     => ['fa-bolt',           '#f97316'],
            default     => ['fa-circle',         '#6b7280'],
        };
        @endphp
        <div class="flex items-center gap-3 text-xs cursor-pointer hover:bg-slate-800 px-2 py-1.5 rounded transition-colors"
             onclick="flyToEvent({{ $event['lat'] }}, {{ $event['lon'] }})">
            <span class="font-mono text-slate-400 w-12 flex-shrink-0">{{ $event['time'] }}</span>
            <i class="fas {{ $evIcon }} flex-shrink-0" style="color:{{ $evColor }};width:12px;text-align:center;"></i>
            <span class="text-slate-300">{{ $event['label'] }}</span>
        </div>
        @endforeach
    </div>
</div>

{{-- STOPS TABLE --}}
@if(count($stops) > 0)
<div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
    <div class="px-4 py-3 border-b border-slate-700 flex items-center justify-between">
        <h3 class="text-white font-semibold text-sm">
            Detalle de paradas
        </h3>
        <span class="text-xs text-slate-500">
            {{ min(count($stops), 50) }}{{ count($stops) > 50 ? ' de '.count($stops) : '' }} paradas
        </span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b border-slate-700 text-slate-400 uppercase tracking-wider text-left">
                    <th class="px-4 py-2">#</th>
                    <th class="px-4 py-2">Tipo</th>
                    <th class="px-4 py-2">Inicio</th>
                    <th class="px-4 py-2">Fin</th>
                    <th class="px-4 py-2">Duración</th>
                    <th class="px-4 py-2">Coordenadas</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @foreach(array_slice($stops, 0, 50) as $i => $stop)
                @php
                $tLabel = $stop['type_label'];
                $tColor = match($stop['type']) { 'overnight'=>'#8b5cf6', 'long'=>'#f59e0b', default=>'#3b82f6' };
                $th = intdiv($stop['duration'], 60); $tm = $stop['duration'] % 60;
                $tDur = ($th > 0 ? "{$th}h " : '') . "{$tm}min";
                @endphp
                <tr class="hover:bg-slate-800 text-slate-300 transition-colors">
                    <td class="px-4 py-2 text-slate-600">{{ $i + 1 }}</td>
                    <td class="px-4 py-2">
                        <span class="px-2 py-0.5 rounded text-xs font-medium"
                              style="background:{{ $tColor }}20;color:{{ $tColor }};">
                            {{ $tLabel }}
                        </span>
                    </td>
                    <td class="px-4 py-2 font-mono">{{ $stop['start'] }}</td>
                    <td class="px-4 py-2 font-mono">{{ $stop['end'] }}</td>
                    <td class="px-4 py-2 font-semibold text-white">{{ $tDur }}</td>
                    <td class="px-4 py-2 font-mono text-slate-500 text-xs">
                        {{ number_format($stop['lat'], 5) }}, {{ number_format($stop['lon'], 5) }}
                    </td>
                    <td class="px-4 py-2">
                        <button onclick="flyToStop({{ $stop['lat'] }}, {{ $stop['lon'] }}, {{ $i }})"
                                class="text-blue-400 hover:text-blue-300 transition-colors"
                                title="Ver en mapa">
                            <i class="fas fa-map-marker-alt"></i>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

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

@if($unit && $positions->isNotEmpty())
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
(function () {
    const routePoints = @json($positions->map(fn($p) => [(float)$p->lat, (float)$p->lon])->values());
    const stopPoints  = @json($stops);
    const unitId      = {{ (int) $unit->id }};

    // preferCanvas: la ruta se dibuja en <canvas> en vez de SVG. html2canvas
    // captura el canvas alineado con los tiles; con SVG la ruta se desfasa.
    const map = L.map('report-map', { center: [24.1426, -110.3128], zoom: 13, preferCanvas: true });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap', maxZoom: 19,
    }).addTo(map);

    let routeBounds = null;

    // Route polyline
    if (routePoints.length > 1) {
        const poly = L.polyline(routePoints, { color: '#3b82f6', weight: 3, opacity: 0.8 }).addTo(map);
        routeBounds = poly.getBounds();
        map.fitBounds(routeBounds, { padding: [30, 30] });
    } else if (routePoints.length === 1) {
        map.setView(routePoints[0], 15);
    }

    // Start / end circleMarkers
    if (routePoints.length > 0) {
        L.circleMarker(routePoints[0], {
            radius: 8, color: '#10b981', fillColor: '#10b981', fillOpacity: 1, weight: 2,
        }).addTo(map).bindPopup(
            '<div style="background:#1e293b;color:#e2e8f0;padding:8px 12px;border-radius:6px;">' +
            '<b style="color:#10b981;">Inicio</b></div>'
        );

        const last = routePoints[routePoints.length - 1];
        L.circleMarker(last, {
            radius: 8, color: '#ef4444', fillColor: '#ef4444', fillOpacity: 1, weight: 2,
        }).addTo(map).bindPopup(
            '<div style="background:#1e293b;color:#e2e8f0;padding:8px 12px;border-radius:6px;">' +
            '<b style="color:#ef4444;">Fin</b></div>'
        );
    }

    // Stop markers
    function stopIcon(type) {
        const colors = { overnight: '#8b5cf6', long: '#f59e0b', short: '#3b82f6' };
        const icons  = { overnight: 'fa-moon',  long: 'fa-clock', short: 'fa-map-pin' };
        const c = colors[type] || '#6b7280';
        const i = icons[type]  || 'fa-map-pin';
        return L.divIcon({
            className: '',
            html: `<div style="width:28px;height:28px;background:white;border:2.5px solid ${c};
                               border-radius:50%;display:flex;align-items:center;
                               justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.35);">
                     <i class="fas ${i}" style="color:${c};font-size:11px;"></i>
                   </div>`,
            iconSize: [28, 28], iconAnchor: [14, 14], popupAnchor: [0, -18],
        });
    }

    const stopMarkers = [];
    stopPoints.forEach(function (stop, idx) {
        const labels = { overnight: 'Pernocta', long: 'Parada larga', short: 'Parada' };
        const h = Math.floor(stop.duration / 60);
        const m = stop.duration % 60;
        const dur = (h > 0 ? h + 'h ' : '') + m + 'min';
        const marker = L.marker([stop.lat, stop.lon], { icon: stopIcon(stop.type) })
            .addTo(map)
            .bindPopup(
                `<div style="background:#1e293b;color:#e2e8f0;padding:10px 14px;
                             border-radius:8px;min-width:160px;">
                   <b style="font-size:13px;">${labels[stop.type] || 'Parada'} #${idx + 1}</b><br>
                   <span style="color:#94a3b8;font-size:11px;">${stop.start} – ${stop.end} · ${dur}</span>
                 </div>`
            );
        stopMarkers.push(marker);
    });

    // Marcadores nítidos (canvas) SOLO para la captura del PDF. Los divIcon usan
    // box-shadow + centrado flex + ícono Font Awesome, que html2canvas renderiza
    // mal (fondos grises y glifos desplazados). Los circleMarker se capturan
    // limpios y alineados con la ruta, igual que los puntos de inicio/fin.
    const captureStopLayer = L.layerGroup();
    const stopColors = { overnight: '#8b5cf6', long: '#f59e0b', short: '#3b82f6' };
    stopPoints.forEach(function (stop) {
        const c = stopColors[stop.type] || '#6b7280';
        L.circleMarker([stop.lat, stop.lon], {
            radius: 9, color: '#ffffff', weight: 3, fillColor: c, fillOpacity: 1,
        }).addTo(captureStopLayer);
    });

    window.flyToStop = function (lat, lon, idx) {
        map.flyTo([lat, lon], 17, { animate: true, duration: 0.8 });
        if (stopMarkers[idx]) stopMarkers[idx].openPopup();
    };

    window.flyToEvent = function (lat, lon) {
        map.flyTo([lat, lon], 16, { animate: true, duration: 0.6 });
    };

    // ── Captura de mapas para el reporte PDF (igual que en el módulo COMINT) ──
    async function captureMap(type) {
        map.invalidateSize();
        await new Promise(r => setTimeout(r, 600));

        const canvas = await html2canvas(document.getElementById('report-map'), {
            useCORS:         true,
            allowTaint:      true,
            backgroundColor: '#0f172a',
            logging:         false,
        });

        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        await fetch('{{ route('geoint.report.snapshot') }}', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body:    JSON.stringify({ image: canvas.toDataURL('image/png'), type, unit_id: unitId }),
        });
    }

    window.generatePdfReport = async function () {
        const btn   = document.getElementById('btn-pdf-export');
        const label = document.getElementById('btn-pdf-label');
        if (!btn || btn.disabled) return;

        const originalLabel = label.innerHTML;
        const exportUrl = @json(route('geoint.report.export') . '?' . http_build_query(request()->only(['unit_id','from','to'])));

        btn.disabled = true;

        try {
            // Cambiar a marcadores nítidos (canvas) durante la captura
            stopMarkers.forEach(m => map.removeLayer(m));
            captureStopLayer.addTo(map);

            // Captura del mapa general de la ruta
            label.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Capturando mapa...';
            await captureMap('route');

            // Captura de cada parada con acercamiento
            for (let i = 0; i < stopPoints.length; i++) {
                label.innerHTML = `<i class="fas fa-spinner fa-spin mr-1"></i> Parada ${i + 1}/${stopPoints.length}...`;
                map.setView([stopPoints[i].lat, stopPoints[i].lon], 17, { animate: false });
                await captureMap(`stop_${i}`);
            }

            // Restaurar la vista original del mapa
            if (routeBounds) {
                map.fitBounds(routeBounds, { padding: [30, 30], animate: false });
            } else if (routePoints.length === 1) {
                map.setView(routePoints[0], 15, { animate: false });
            }

            label.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Generando PDF...';
            window.location.href = exportUrl;
        } catch (e) {
            console.error('Error generando capturas del mapa', e);
            alert('Ocurrió un error al capturar el mapa para el PDF.');
        } finally {
            // Restaurar los marcadores interactivos (divIcon) del mapa
            captureStopLayer.remove();
            stopMarkers.forEach(m => { if (!map.hasLayer(m)) m.addTo(map); });
            setTimeout(() => {
                btn.disabled = false;
                label.innerHTML = originalLabel;
            }, 2000);
        }
    };
})();
</script>
@endpush
@endif
