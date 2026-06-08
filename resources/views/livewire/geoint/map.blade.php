@section('title', 'GEOINT — Mapa')
@section('page-title', 'Mapa en Vivo')
@section('page-subtitle', 'Rastreo GPS en tiempo real')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #geoint-map { height: calc(100vh - 148px); min-height: 400px; border-radius: 0.75rem; }
    .unit-marker-dot {
        width: 14px; height: 14px; border-radius: 50%;
        border: 3px solid #fff; box-shadow: 0 0 6px rgba(0,0,0,.5);
    }
    @keyframes pulse-green {
        0%, 100% { box-shadow: 0 0 0 0 rgba(16,185,129,.6); }
        50%       { box-shadow: 0 0 0 8px rgba(16,185,129,0); }
    }
    .pulse-online { animation: pulse-green 2s infinite; }
</style>
@endpush

<div class="flex gap-4" style="height: calc(100vh - 138px);" wire:poll.10s>

    {{-- Mapa --}}
    <div class="flex-1 rounded-xl overflow-hidden border border-slate-700 relative">
        <div id="geoint-map"></div>

        @if($alertCount > 0)
        <a href="{{ route('geoint.alerts') }}"
           class="absolute top-3 right-3 z-[1000] flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold text-white shadow-lg animate-pulse"
           style="background-color:#ef4444; z-index:1000;">
            <i class="fas fa-bell"></i>
            {{ $alertCount }} alerta{{ $alertCount > 1 ? 's' : '' }} pendiente{{ $alertCount > 1 ? 's' : '' }}
        </a>
        @endif

        <div class="absolute bottom-4 left-4" style="z-index:1000;">
            <button wire:click="$toggle('showCovert')"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium text-white border border-slate-600 shadow"
                    style="background-color:#1e293b;">
                <i class="fas fa-{{ $showCovert ? 'eye' : 'eye-slash' }}"></i>
                {{ $showCovert ? 'Ocultar encubiertos' : 'Mostrar encubiertos' }}
            </button>
        </div>
    </div>

    {{-- Panel lateral --}}
    <div class="w-72 flex flex-col gap-3 overflow-y-auto flex-shrink-0">

        <div class="rounded-xl border border-slate-700 p-4" style="background-color:#1e293b;">
            <h3 class="text-white font-semibold text-sm flex items-center gap-2 mb-2">
                <i class="fas fa-satellite text-green-400"></i>
                Unidades ({{ count($units) }})
            </h3>
            <div class="flex gap-3 text-xs text-slate-400">
                <span><i class="fas fa-circle text-green-400 mr-1"></i>En línea</span>
                <span><i class="fas fa-circle text-yellow-400 mr-1"></i>Inactivo</span>
                <span><i class="fas fa-circle text-red-500 mr-1"></i>Offline</span>
            </div>
        </div>

        @forelse($units as $unit)
        @php
            $statusBg   = $unit['status'] === 'online'  ? '#052e16' : ($unit['status'] === 'idle' ? '#422006' : '#450a0a');
            $statusText = $unit['status'] === 'online'  ? '#4ade80' : ($unit['status'] === 'idle' ? '#fb923c' : '#f87171');
        @endphp
        @if($showCovert || $unit['unit_type'] !== 'covert')
        <div class="rounded-xl border border-slate-700 p-3 cursor-pointer hover:border-slate-500 transition-colors"
             style="background-color:#1e293b;"
             onclick="focusUnit({{ $unit['id'] }}, {{ $unit['lat'] ?? 'null' }}, {{ $unit['lon'] ?? 'null' }})">
            <div class="flex items-center gap-2 mb-1.5">
                <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color:{{ $unit['color'] }};"></div>
                <p class="text-white text-sm font-semibold flex-1 truncate">{{ $unit['name'] }}</p>
                <span class="text-xs px-2 py-0.5 rounded font-semibold"
                      style="background:{{ $statusBg }};color:{{ $statusText }};">
                    {{ $unit['status_label'] }}
                </span>
            </div>
            @if($unit['plate'])
            <p class="text-slate-500 text-xs mb-1.5">{{ $unit['plate'] }}</p>
            @endif
            <div class="flex gap-3 text-xs text-slate-400">
                <span><i class="fas fa-tachometer-alt mr-1"></i>{{ $unit['speed'] }} km/h</span>
                <span class="truncate"><i class="fas fa-clock mr-1"></i>{{ $unit['last_seen'] }}</span>
            </div>
        </div>
        @endif
        @empty
        <div class="rounded-xl border border-slate-700 p-8 text-center" style="background-color:#1e293b;">
            <i class="fas fa-car text-4xl text-slate-600 mb-3 block"></i>
            <p class="text-slate-500 text-sm">Sin unidades activas</p>
            <a href="{{ route('geoint.units') }}" class="text-blue-400 text-xs mt-2 block hover:underline">
                Crear unidades →
            </a>
        </div>
        @endforelse

        {{-- Alertas recientes --}}
        @if($pendingAlerts->count() > 0)
        <div class="rounded-xl border border-red-900 p-4" style="background-color:#1e293b;">
            <h3 class="text-red-400 font-semibold text-sm mb-3 flex items-center gap-2">
                <i class="fas fa-bell animate-pulse"></i> Alertas recientes
            </h3>
            <div class="space-y-2">
                @foreach($pendingAlerts->take(5) as $alert)
                <div class="flex items-start gap-2 text-xs p-2 rounded" style="background-color:#0f172a;">
                    <i class="fas fa-{{ $alert->alert_type === 'enter' ? 'sign-in-alt text-green-400' : 'sign-out-alt text-red-400' }} mt-0.5 flex-shrink-0"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-white truncate">{{ $alert->unit?->name }} — {{ $alert->geofence?->name }}</p>
                        <p class="text-slate-500">{{ $alert->triggered_at->locale('es')->diffForHumans() }}</p>
                    </div>
                    <button wire:click="acknowledgeAlert({{ $alert->id }})"
                            class="text-slate-500 hover:text-green-400 transition-colors flex-shrink-0"
                            title="Reconocer">
                        <i class="fas fa-check"></i>
                    </button>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    const units     = @json($units);
    const map       = L.map('geoint-map', { center: [24.1426, -110.3128], zoom: 13 });
    const markers   = {};
    const polylines = {};

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap', maxZoom: 19
    }).addTo(map);

    fetch('/api/geoint/geofences').then(r => r.json()).then(fences => {
        fences.forEach(f => {
            if (f.type === 'circle' && f.center_lat) {
                L.circle([f.center_lat, f.center_lon], {
                    radius: f.radius, color: f.color, fillColor: f.color, fillOpacity: 0.1, weight: 2,
                }).addTo(map).bindTooltip(f.name, { permanent: true, direction: 'center' });
            }
        });
    });

    function makeIcon(unit) {
        const border = unit.status === 'online' ? '#10b981' : (unit.status === 'idle' ? '#f59e0b' : '#6b7280');
        const pulse  = unit.status === 'online' ? 'pulse-online' : '';
        return L.divIcon({
            className: '',
            html: `<div class="unit-marker-dot ${pulse}" style="background:${unit.color};border-color:${border};"></div>`,
            iconSize: [14, 14], iconAnchor: [7, 7],
        });
    }

    function buildPopup(u) {
        return `<div style="background:#1e293b;color:#e2e8f0;padding:10px;border-radius:8px;min-width:180px;">
            <p style="font-weight:700;font-size:13px;">${u.name}${u.plate ? ' — ' + u.plate : ''}</p>
            <hr style="border-color:#334155;margin:6px 0;">
            <p style="font-size:12px;">Estado: <b style="color:${u.status_color}">${u.status_label}</b></p>
            <p style="font-size:12px;">Velocidad: ${u.speed} km/h</p>
            <p style="font-size:12px;">Últ. reporte: ${u.last_seen}</p>
            <button onclick="loadHistory(${u.id})"
                style="margin-top:8px;width:100%;padding:4px;background:#3b82f6;border:none;border-radius:4px;color:#fff;font-size:11px;cursor:pointer;">
                Ver historial de ruta
            </button></div>`;
    }

    units.forEach(u => {
        if (!u.lat || !u.lon) return;
        markers[u.id] = L.marker([u.lat, u.lon], { icon: makeIcon(u) })
            .addTo(map).bindPopup(buildPopup(u), { maxWidth: 220 });
    });

    window.focusUnit = function (id, lat, lon) {
        if (!lat || !lon) return;
        map.setView([lat, lon], 15, { animate: true });
        markers[id]?.openPopup();
    };

    window.loadHistory = function (unitId) {
        if (polylines[unitId]) { map.removeLayer(polylines[unitId]); delete polylines[unitId]; return; }
        fetch(`/api/geoint/unit/${unitId}/history?hours=24`).then(r => r.json()).then(pts => {
            if (!pts.length) return;
            polylines[unitId] = L.polyline(pts.map(p => [p.lat, p.lon]), { color: '#3b82f6', weight: 3 }).addTo(map);
            map.fitBounds(polylines[unitId].getBounds(), { padding: [20, 20] });
        });
    };

    // Actualizar marcadores tras poll de Livewire
    document.addEventListener('livewire:updated', function () {
        fetch('/api/geoint/units').then(r => r.json()).then(units => {
            units.forEach(u => {
                if (!u.lat || !u.lon) return;
                if (markers[u.id]) {
                    markers[u.id].setLatLng([u.lat, u.lon]).setIcon(makeIcon(u));
                } else {
                    markers[u.id] = L.marker([u.lat, u.lon], { icon: makeIcon(u) })
                        .addTo(map).bindPopup(buildPopup(u), { maxWidth: 220 });
                }
            });
        });
    });
})();
</script>
@endpush
