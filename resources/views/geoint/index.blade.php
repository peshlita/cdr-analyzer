@extends('layouts.app')
@section('title', 'GEOINT — Mapa en Vivo')
@section('page-title', 'Mapa en Vivo')
@section('page-subtitle', 'Rastreo GPS en tiempo real')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>
    #geoint-map { height: calc(100vh - 140px); min-height: 400px; border-radius: 0.75rem; }
    .leaflet-container { background: #0f172a; }
    .leaflet-popup-content-wrapper { background: #1e293b; border: 1px solid #334155; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,.5); }
    .leaflet-popup-tip { background: #1e293b; }
    .leaflet-popup-content { margin: 0; }
    .leaflet-tooltip { background: #1e293b; border: 1px solid #334155; color: #e2e8f0; font-size: 11px; }

    /* Marcadores */
    .unit-marker {
        background: #1e293b;
        border-radius: 50%;
        width: 36px; height: 36px;
        display: flex; align-items: center; justify-content: center;
        border: 2px solid #6b7280;
        box-shadow: 0 2px 8px rgba(0,0,0,.6);
        transition: border-color .3s, box-shadow .3s;
    }

    /* Pulso al actualizar posición */
    @keyframes pulse-marker {
        0%   { box-shadow: 0 0 0 0 rgba(16,185,129,0.7); }
        70%  { box-shadow: 0 0 0 10px rgba(16,185,129,0); }
        100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); }
    }
    .marker-pulse { animation: pulse-marker 1s ease-out; }

    /* Badge de estado sobre marcador */
    .marker-badge {
        position: absolute; top: -4px; right: -4px;
        border-radius: 50%; width: 15px; height: 15px;
        font-size: 8px; font-weight: 700; color: #fff;
        display: flex; align-items: center; justify-content: center;
        border: 1.5px solid #0f172a;
    }

    /* Tarjetas del panel */
    .unit-card {
        background: #1e293b;
        border: 1px solid #334155;
        border-radius: 10px;
        padding: 10px 12px;
        cursor: pointer;
        transition: border-color .15s, background .15s;
    }
    .unit-card:hover { border-color: #475569; background: #253348; }
    .unit-card.active { border-color: #3b82f6; background: #1e3a5f20; }

    /* Barra de estado */
    #statusBar {
        position: absolute; top: 12px; left: 12px; z-index: 1000;
        display: flex; gap: 8px;
        background: rgba(15,23,42,.85); backdrop-filter: blur(6px);
        border: 1px solid #334155; border-radius: 8px;
        padding: 6px 12px; font-size: 12px; color: #e2e8f0;
    }

    /* Panel de historial */
    #historyPanel {
        display: none;
        background: #1e293b;
        border: 1px solid #3b82f6;
        border-radius: 10px;
        padding: 12px;
        font-size: 12px;
    }
    #historyPanel.visible { display: block; }
</style>
@endpush

@section('content')
<div class="flex gap-4" style="height: calc(100vh - 130px);">

    {{-- ── Mapa ── --}}
    <div class="flex-1 rounded-xl overflow-hidden border border-slate-700 relative" style="min-width:0;">
        <div id="geoint-map"></div>

        {{-- Barra de estado (top-left) --}}
        <div id="statusBar">
            <span id="statOnline"    style="display:flex;align-items:center;gap:5px;"><i class="fas fa-circle" style="font-size:7px;color:#10b981;"></i> <span>0</span> en línea</span>
            <span style="color:#334155;">|</span>
            <span id="statParked"    style="display:flex;align-items:center;gap:5px;"><i class="fas fa-circle" style="font-size:7px;color:#3b82f6;"></i> <span>0</span> estacionados</span>
            <span style="color:#334155;">|</span>
            <span id="statOvernight" style="display:flex;align-items:center;gap:5px;"><i class="fas fa-moon"   style="font-size:7px;color:#8b5cf6;"></i> <span>0</span> pernocta</span>
            <span style="color:#334155;">|</span>
            <span id="statIdle"      style="display:flex;align-items:center;gap:5px;"><i class="fas fa-circle" style="font-size:7px;color:#f59e0b;"></i> <span>0</span> inactivos</span>
            <span style="color:#334155;">|</span>
            <span id="statOffline"   style="display:flex;align-items:center;gap:5px;"><i class="fas fa-circle" style="font-size:7px;color:#6b7280;"></i> <span>0</span> offline</span>
        </div>

        {{-- Contador alertas (top-right) --}}
        @if($pendingAlerts > 0)
        <a href="{{ route('geoint.alerts') }}"
           class="absolute top-3 right-3 flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-semibold text-white shadow-lg"
           style="background-color:#ef4444; z-index:1000;">
            <i class="fas fa-bell animate-bounce"></i>
            {{ $pendingAlerts }} alerta{{ $pendingAlerts > 1 ? 's' : '' }}
        </a>
        @endif

        {{-- Toggle encubiertos (bottom-left) --}}
        <div class="absolute bottom-4 left-4" style="z-index:1000;">
            <button id="toggleCovert"
                    class="flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium text-white shadow-lg border border-slate-600"
                    style="background-color:rgba(30,41,59,.9); backdrop-filter:blur(4px);">
                <i class="fas fa-eye" id="covertIcon"></i>
                <span id="covertLabel">Ocultar encubiertos</span>
            </button>
        </div>
    </div>

    {{-- ── Panel lateral ── --}}
    <div class="w-72 flex flex-col gap-3 overflow-y-auto flex-shrink-0">

        {{-- Header --}}
        <div class="rounded-xl border border-slate-700 p-4" style="background-color:#1e293b;">
            <div class="flex items-center justify-between">
                <h3 class="text-white font-semibold text-sm flex items-center gap-2">
                    <i class="fas fa-satellite text-green-400"></i> Unidades GPS
                </h3>
                <a href="{{ route('geoint.units') }}"
                   class="text-xs text-slate-500 hover:text-blue-400 transition-colors">
                    <i class="fas fa-cog"></i>
                </a>
            </div>
            <p id="unitCount" class="text-xs text-slate-500 mt-1">Conectando…</p>
        </div>

        {{-- Panel de historial (oculto por defecto) --}}
        <div id="historyPanel">
            <div class="flex items-center justify-between mb-2">
                <p class="text-blue-400 font-semibold text-xs flex items-center gap-1.5">
                    <i class="fas fa-route"></i>
                    <span id="historyTitle">Historial de ruta</span>
                </p>
                <button onclick="closeHistory()"
                        class="text-slate-500 hover:text-red-400 transition-colors text-xs">
                    <i class="fas fa-times"></i> Cerrar
                </button>
            </div>
            <p id="historyInfo" class="text-slate-400 text-xs"></p>
        </div>

        {{-- Lista de unidades --}}
        <div id="unitsList" class="space-y-2"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {

    // ── Init mapa ─────────────────────────────────────────────
    const map = L.map('geoint-map', { center: [24.1426, -110.3128], zoom: 13 });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap', maxZoom: 19,
    }).addTo(map);

    const markers      = {};   // id → L.Marker
    const markerData   = {};   // id → last unit data (para detectar cambios de posición)
    const polylines    = {};   // id → L.Polyline
    const historyMarkers = []; // marcadores de inicio/fin del historial
    let showCovert     = true;
    let activeHistoryUnit = null;

    // ── Geocercas ─────────────────────────────────────────────
    fetch('/api/geoint/geofences')
        .then(r => r.json())
        .then(fences => {
            fences.forEach(f => {
                if (f.type === 'circle' && f.center_lat) {
                    L.circle([f.center_lat, f.center_lon], {
                        radius: f.radius, color: f.color, fillColor: f.color, fillOpacity: 0.1, weight: 2,
                    }).addTo(map).bindTooltip(f.name, { permanent: true, direction: 'center' });
                }
            });
        });

    // ── Helpers ───────────────────────────────────────────────
    const STATUS_CFG = {
        online:    { color: '#10b981', bg: '#052e16', text: '#4ade80', label: 'En línea',    pulse: true  },
        parked:    { color: '#3b82f6', bg: '#0c1d40', text: '#93c5fd', label: 'Estacionado', pulse: false },
        overnight: { color: '#8b5cf6', bg: '#1e1040', text: '#c4b5fd', label: 'Pernocta',   pulse: false },
        idle:      { color: '#f59e0b', bg: '#422006', text: '#fb923c', label: 'Inactivo',   pulse: false },
        offline:   { color: '#6b7280', bg: '#1a1a1a', text: '#9ca3af', label: 'Offline',    pulse: false },
    };

    function cfg(status) { return STATUS_CFG[status] || STATUS_CFG.offline; }

    // Convierte grados de rumbo a texto
    function headingText(deg) {
        if (deg === null || deg === undefined) return null;
        const dirs = ['Norte','Noreste','Este','Sureste','Sur','Suroeste','Oeste','Noroeste','Norte'];
        return dirs[Math.round((deg % 360) / 45)];
    }

    function createUnitIcon(unit) {
        const c     = cfg(unit.status);
        const icon  = unit.icon || 'fa-car';
        const glow  = c.pulse ? `0 0 10px ${c.color}80` : `0 2px 8px rgba(0,0,0,.6)`;
        const opacity = unit.status === 'offline' ? '0.5' : '1';

        let badge = '';
        if (unit.status === 'parked') {
            badge = `<div class="marker-badge" style="background:${c.color};">P</div>`;
        } else if (unit.status === 'overnight') {
            badge = `<div class="marker-badge" style="background:${c.color};"><i class="fas fa-moon" style="font-size:6px;"></i></div>`;
        }

        return L.divIcon({
            className: '',
            html: `<div style="position:relative;width:36px;height:36px;">
                       <div class="unit-marker" data-marker-id="${unit.id}"
                            style="border-color:${c.color};box-shadow:${glow};opacity:${opacity};">
                           <i class="fas ${icon}" style="color:${c.color};font-size:14px;"></i>
                       </div>
                       ${badge}
                   </div>`,
            iconSize: [36, 36],
            iconAnchor: [18, 18],
            popupAnchor: [0, -22],
        });
    }

    function buildPopup(unit) {
        const c  = cfg(unit.status);
        const hd = headingText(unit.heading);
        const speedLine = unit.speed > 0
            ? `🧭 ${unit.speed} km/h${hd ? ' dirección ' + hd : ''}`
            : (unit.status === 'parked'    ? `<i class="fas fa-parking" style="color:${c.color};"></i> Estacionado`
            : unit.status === 'overnight'  ? `<i class="fas fa-moon" style="color:${c.color};"></i> Pernocta`
            : `Sin movimiento`);

        const statusExtra = (unit.status === 'parked' || unit.status === 'overnight')
            ? `<div style="font-size:11px;color:${c.color};margin-top:2px;">
                   ${unit.status === 'overnight' ? '🌙' : '🅿'} desde ${unit.last_seen}
               </div>`
            : '';

        return `
        <div style="padding:12px 14px;min-width:200px;font-family:inherit;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <div style="position:relative;">
                    <div style="width:36px;height:36px;border-radius:50%;background:#0f172a;
                                border:2px solid ${c.color};display:flex;align-items:center;
                                justify-content:center;box-shadow:0 0 8px ${c.color}60;">
                        <i class="fas ${unit.icon || 'fa-car'}" style="color:${c.color};font-size:14px;"></i>
                    </div>
                    ${unit.status === 'parked'    ? `<div class="marker-badge" style="background:${c.color};">P</div>` : ''}
                    ${unit.status === 'overnight' ? `<div class="marker-badge" style="background:${c.color};"><i class="fas fa-moon" style="font-size:6px;"></i></div>` : ''}
                </div>
                <div style="flex:1;min-width:0;">
                    <p style="color:#fff;font-weight:700;font-size:13px;line-height:1.2;">${unit.name}</p>
                    ${unit.plate ? `<p style="color:#64748b;font-size:11px;">${unit.plate}</p>` : ''}
                </div>
            </div>
            <div style="border-top:1px solid #334155;padding-top:8px;display:grid;gap:5px;">
                <div style="display:flex;justify-content:space-between;align-items:center;font-size:11px;">
                    <span style="color:#94a3b8;">Estado</span>
                    <span style="color:${c.color};font-weight:700;">${c.label}</span>
                </div>
                ${statusExtra}
                <div style="font-size:11px;color:#e2e8f0;">${speedLine}</div>
                <div style="display:flex;justify-content:space-between;font-size:11px;">
                    <span style="color:#94a3b8;">Último reporte</span>
                    <span style="color:#cbd5e1;">${unit.last_seen}</span>
                </div>
                ${unit.lat ? `<div style="font-size:10px;color:#475569;font-family:monospace;">
                    ${Number(unit.lat).toFixed(5)}, ${Number(unit.lon).toFixed(5)}
                </div>` : ''}
            </div>
            <button onclick="loadHistory(${unit.id}, '${unit.name.replace(/'/g,"\\'")}', '${unit.color}')"
                    style="margin-top:10px;width:100%;padding:6px;background:#1d4ed8;border:none;
                           border-radius:6px;color:#fff;font-size:11px;cursor:pointer;
                           display:flex;align-items:center;justify-content:center;gap:6px;">
                <i class="fas fa-route"></i> Ver historial de ruta
            </button>
        </div>`;
    }

    // ── Tarjeta del panel lateral ─────────────────────────────
    function buildCard(unit) {
        const card = document.createElement('div');
        card.className = 'unit-card';
        card.id = `card-${unit.id}`;

        const c    = cfg(unit.status);
        const icon = unit.icon || 'fa-car';

        const badgeHtml = unit.status === 'parked'
            ? `<span style="font-size:9px;padding:1px 5px;border-radius:3px;background:${c.bg};color:${c.color};margin-left:4px;">P</span>`
            : unit.status === 'overnight'
            ? `<span style="font-size:9px;padding:1px 5px;border-radius:3px;background:${c.bg};color:${c.color};margin-left:4px;"><i class="fas fa-moon"></i></span>`
            : '';

        const extraLine = (unit.status === 'parked' || unit.status === 'overnight')
            ? `<div style="font-size:10px;color:${c.color};padding-left:44px;margin-top:3px;">
                   ${c.label} desde ${unit.last_seen}
               </div>`
            : '';

        card.innerHTML = `
        <div style="display:flex;align-items:center;gap:10px;">
            <div style="position:relative;flex-shrink:0;">
                <div style="width:34px;height:34px;border-radius:50%;background:#0f172a;
                            border:2px solid ${c.color};display:flex;align-items:center;
                            justify-content:center;box-shadow:0 0 6px ${c.color}50;
                            opacity:${unit.status === 'offline' ? '.5' : '1'};">
                    <i class="fas ${icon}" style="color:${unit.color};font-size:13px;"></i>
                </div>
                ${unit.status === 'parked'    ? `<div class="marker-badge" style="background:${c.color};top:-3px;right:-3px;">P</div>` : ''}
                ${unit.status === 'overnight' ? `<div class="marker-badge" style="background:${c.color};top:-3px;right:-3px;"><i class="fas fa-moon" style="font-size:5px;"></i></div>` : ''}
            </div>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <p style="color:#fff;font-size:13px;font-weight:600;
                               white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
                               max-width:120px;">${unit.name}${badgeHtml}</p>
                    <span style="font-size:10px;padding:2px 7px;border-radius:4px;
                                 background:${c.bg};color:${c.text};font-weight:600;
                                 white-space:nowrap;flex-shrink:0;">${c.label}</span>
                </div>
                ${unit.plate ? `<p style="color:#475569;font-size:11px;margin-top:1px;">${unit.plate}</p>` : ''}
            </div>
        </div>
        <div style="display:flex;gap:14px;margin-top:7px;padding-left:44px;">
            <span style="font-size:11px;color:#94a3b8;">
                <i class="fas fa-bolt" style="color:#f59e0b;margin-right:3px;"></i>${unit.speed} km/h
            </span>
            <span style="font-size:11px;color:#94a3b8;">
                <i class="fas fa-clock" style="color:#64748b;margin-right:3px;"></i>${unit.last_seen}
            </span>
        </div>
        ${extraLine}`;

        card.addEventListener('click', () => {
            document.querySelectorAll('.unit-card').forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            if (unit.lat && unit.lon) {
                map.setView([unit.lat, unit.lon], 16, { animate: true });
                markers[unit.id]?.openPopup();
            }
        });

        return card;
    }

    // ── Pulso en marcador ─────────────────────────────────────
    function pulseMarker(id) {
        const el = document.querySelector(`[data-marker-id="${id}"]`);
        if (!el) return;
        el.classList.remove('marker-pulse');
        void el.offsetWidth; // reflow
        el.classList.add('marker-pulse');
        setTimeout(() => el.classList.remove('marker-pulse'), 1100);
    }

    // ── Renderizar lista completa ─────────────────────────────
    function renderUnits(units) {
        const counts = { online: 0, parked: 0, overnight: 0, idle: 0, offline: 0 };

        const list = document.getElementById('unitsList');
        list.innerHTML = '';

        units.forEach(unit => {
            counts[unit.status] = (counts[unit.status] || 0) + 1;

            if (!showCovert && unit.unit_type === 'covert') return;

            const newIcon = createUnitIcon(unit);

            if (unit.lat && unit.lon) {
                if (markers[unit.id]) {
                    const prev = markerData[unit.id];
                    if (prev && (prev.lat !== unit.lat || prev.lon !== unit.lon)) {
                        markers[unit.id].setLatLng([unit.lat, unit.lon]);
                        pulseMarker(unit.id);
                    }
                    markers[unit.id].setIcon(newIcon);
                    markers[unit.id].getPopup()?.setContent(buildPopup(unit));
                } else {
                    markers[unit.id] = L.marker([unit.lat, unit.lon], { icon: newIcon })
                        .addTo(map)
                        .bindPopup(buildPopup(unit), { maxWidth: 240 });
                }
                markers[unit.id]._unitType = unit.unit_type;
            }

            markerData[unit.id] = { lat: unit.lat, lon: unit.lon };
            list.appendChild(buildCard(unit));
        });

        // Barra de estado — 5 contadores
        document.querySelector('#statOnline span').textContent    = counts.online    || 0;
        document.querySelector('#statParked span').textContent    = counts.parked    || 0;
        document.querySelector('#statOvernight span').textContent = counts.overnight || 0;
        document.querySelector('#statIdle span').textContent      = counts.idle      || 0;
        document.querySelector('#statOffline span').textContent   = counts.offline   || 0;
        document.getElementById('unitCount').textContent =
            `${units.length} unidad${units.length !== 1 ? 'es' : ''}`;
    }

    // ── Historial de ruta ─────────────────────────────────────
    window.loadHistory = function (unitId, unitName, unitColor) {
        // Si ya está mostrado para esta unidad, cerrar
        if (activeHistoryUnit === unitId) { closeHistory(); return; }

        // Limpiar historial previo
        clearHistoryLayers();

        fetch(`/api/geoint/unit/${unitId}/history?hours=24`)
            .then(r => r.json())
            .then(pts => {
                if (!pts.length) {
                    document.getElementById('historyInfo').textContent =
                        'Sin posiciones en las últimas 24 horas.';
                    showHistoryPanel(unitName, pts.length);
                    return;
                }

                const color   = unitColor || '#3b82f6';
                const latlngs = pts.map(p => [p.lat, p.lon]);

                // Polyline con color de la unidad
                polylines[unitId] = L.polyline(latlngs, {
                    color, weight: 3, opacity: 0.85, dashArray: null,
                }).addTo(map);

                // Marcador de inicio (círculo hueco)
                const startPt = pts[0];
                const startIcon = L.divIcon({
                    className: '',
                    html: `<div style="width:12px;height:12px;border-radius:50%;
                                       border:3px solid ${color};background:transparent;"></div>`,
                    iconSize: [12, 12], iconAnchor: [6, 6],
                });
                historyMarkers.push(
                    L.marker([startPt.lat, startPt.lon], { icon: startIcon })
                        .addTo(map)
                        .bindTooltip(`Inicio: ${startPt.received_at || ''}`, { direction: 'top' })
                );

                // Marcador de fin (punto sólido)
                const endPt = pts[pts.length - 1];
                const endIcon = L.divIcon({
                    className: '',
                    html: `<div style="width:14px;height:14px;border-radius:50%;
                                       background:${color};border:2px solid #fff;
                                       box-shadow:0 0 6px ${color}80;"></div>`,
                    iconSize: [14, 14], iconAnchor: [7, 7],
                });
                historyMarkers.push(
                    L.marker([endPt.lat, endPt.lon], { icon: endIcon })
                        .addTo(map)
                        .bindTooltip(`Último: ${endPt.received_at || ''}`, { direction: 'top' })
                );

                map.fitBounds(polylines[unitId].getBounds(), { padding: [30, 30] });
                activeHistoryUnit = unitId;
                showHistoryPanel(unitName, pts.length);
            });
    };

    function showHistoryPanel(name, count) {
        const panel = document.getElementById('historyPanel');
        document.getElementById('historyTitle').textContent = name;
        document.getElementById('historyInfo').textContent  =
            count > 0
                ? `Mostrando ${count} posiciones · últimas 24 h`
                : 'Sin datos en las últimas 24 h';
        panel.classList.add('visible');
    }

    function clearHistoryLayers() {
        Object.keys(polylines).forEach(id => { map.removeLayer(polylines[id]); delete polylines[id]; });
        historyMarkers.forEach(m => map.removeLayer(m));
        historyMarkers.length = 0;
    }

    window.closeHistory = function () {
        clearHistoryLayers();
        activeHistoryUnit = null;
        document.getElementById('historyPanel').classList.remove('visible');
        document.querySelectorAll('.unit-card').forEach(c => c.classList.remove('active'));
    };

    // ── Toggle encubiertos ────────────────────────────────────
    document.getElementById('toggleCovert').addEventListener('click', function () {
        showCovert = !showCovert;
        document.getElementById('covertLabel').textContent  = showCovert ? 'Ocultar encubiertos' : 'Mostrar encubiertos';
        document.getElementById('covertIcon').className     = showCovert ? 'fas fa-eye' : 'fas fa-eye-slash';
        Object.entries(markers).forEach(([id, m]) => {
            if (m._unitType === 'covert') {
                showCovert ? m.addTo(map) : map.removeLayer(m);
            }
        });
    });

    // ── Polling cada 10 segundos ──────────────────────────────
    function refresh() {
        fetch('/api/geoint/units')
            .then(r => r.json())
            .then(renderUnits)
            .catch(() => {});
    }

    refresh();
    setInterval(refresh, 10000);

})();
</script>
@endpush
