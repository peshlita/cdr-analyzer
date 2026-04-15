@extends('layouts.app')

@section('title', 'Mapa GPS')
@section('page-title', 'Mapa GPS')
@section('page-subtitle', 'Ubicaciones geográficas de las comunicaciones')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
#map { width: 100%; height: 100%; }
.leaflet-popup-content-wrapper {
    background: #1e293b;
    color: #e2e8f0;
    border: 1px solid #334155;
    border-radius: 8px;
}
.leaflet-popup-tip { background: #1e293b; }
.leaflet-popup-content { margin: 10px 14px; font-size: 12px; }
</style>
@endpush

@section('content')
<div class="flex gap-4" style="height: calc(100vh - 120px);" x-data="mapApp()">

    <!-- Filters -->
    <div class="w-56 shrink-0 rounded-xl border border-slate-700 p-4 flex flex-col gap-4 overflow-y-auto" style="background-color:#1e293b;">
        <h3 class="text-white font-semibold text-sm">Filtros</h3>

        <div>
            <label class="text-xs text-slate-400 block mb-1">Número objetivo</label>
            <select x-model="filters.number" @change="loadMap()"
                    class="w-full text-xs rounded-lg px-2 py-1.5 text-white border border-slate-600 focus:outline-none"
                    style="background-color:#0f172a;">
                <option value="">Todos</option>
                @foreach($numbers as $num)
                <option value="{{ $num }}">{{ $num }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="text-xs text-slate-400 block mb-1">Desde</label>
            <input type="date" x-model="filters.date_from" @change="loadMap()"
                   value="{{ $dateRange['min'] }}"
                   class="w-full text-xs rounded-lg px-2 py-1.5 text-white border border-slate-600 focus:outline-none"
                   style="background-color:#0f172a;">
        </div>

        <div>
            <label class="text-xs text-slate-400 block mb-1">Hasta</label>
            <input type="date" x-model="filters.date_to" @change="loadMap()"
                   value="{{ $dateRange['max'] }}"
                   class="w-full text-xs rounded-lg px-2 py-1.5 text-white border border-slate-600 focus:outline-none"
                   style="background-color:#0f172a;">
        </div>

        <div class="flex items-center gap-2 text-xs">
            <input type="checkbox" id="showPath" x-model="showPath" @change="togglePath()" class="rounded">
            <label for="showPath" class="text-slate-300">Línea de movimiento</label>
        </div>

        <button @click="loadMap()"
                class="text-xs text-white py-1.5 rounded-lg transition-colors hover:opacity-90"
                style="background-color:#3b82f6;">
            <i class="fas fa-sync mr-1"></i> Actualizar
        </button>

        <!-- Stats -->
        <div class="border-t border-slate-700 pt-3 space-y-1 text-xs text-slate-400">
            <p><span class="text-white font-medium" x-text="markerCount"></span> ubicaciones</p>
            <p x-show="targetNumber"><span class="text-yellow-400" x-text="targetNumber"></span> (objetivo)</p>
            <p x-show="pernoctaInfo" class="text-orange-400">
                <i class="fas fa-moon mr-1"></i>
                Antena pernocta detectada
            </p>
        </div>

        <!-- Legend + Type filters -->
        <div class="border-t border-slate-700 pt-3 space-y-2 text-xs text-slate-400">
            <p class="font-medium text-slate-300">Leyenda / Filtro por tipo</p>
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" x-model="typeFilters.movement" @change="applyTypeFilter()" class="rounded accent-purple-500">
                <span class="w-3 h-3 rounded-full inline-block border border-white shrink-0" style="background:#8b5cf6;"></span>
                <span>Movimiento (datos)</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" x-model="typeFilters.voice_out" @change="applyTypeFilter()" class="rounded accent-emerald-500">
                <span class="w-3 h-3 rounded-full inline-block border border-white shrink-0" style="background:#10b981;"></span>
                <span>Voz saliente</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" x-model="typeFilters.voice_in" @change="applyTypeFilter()" class="rounded accent-red-500">
                <span class="w-3 h-3 rounded-full inline-block border border-white shrink-0" style="background:#ef4444;"></span>
                <span>Voz entrante</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer select-none">
                <input type="checkbox" x-model="typeFilters.pernocta" @change="applyTypeFilter()" class="rounded accent-orange-500">
                <span class="w-3 h-3 rounded-full inline-block border border-white shrink-0" style="background:#f97316;"></span>
                <span>Pernocta (antena nocturna)</span>
            </label>
        </div>
    </div>

    <!-- Map -->
    <div class="flex-1 rounded-xl border border-slate-700 relative overflow-hidden">
        <div x-show="loading" class="absolute inset-0 flex items-center justify-center z-50" style="background-color:#0f172a90;">
            <i class="fas fa-spinner fa-spin text-blue-400 text-3xl"></i>
        </div>
        <div id="map" class="absolute inset-0 rounded-xl"></div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
function mapApp() {
    return {
        loading: true,
        map: null,
        movementLayer: null,
        voiceOutLayer: null,
        voiceInLayer: null,
        pathLayer: null,
        pernoctaLayer: null,
        showPath: true,
        markerCount: 0,
        targetNumber: null,
        pernoctaInfo: null,
        typeFilters: {
            movement:  true,
            voice_out: true,
            voice_in:  true,
            pernocta:  true,
        },
        filters: {
            number: '',
            date_from: '{{ $dateRange["min"] ?? "" }}',
            date_to: '{{ $dateRange["max"] ?? "" }}',
        },

        init() {
            this.map = L.map('map', {
                center: [0, 0],
                zoom: 3,
                zoomControl: true,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                maxZoom: 19,
            }).addTo(this.map);

            this.movementLayer = L.layerGroup().addTo(this.map);
            this.voiceOutLayer = L.layerGroup().addTo(this.map);
            this.voiceInLayer  = L.layerGroup().addTo(this.map);
            this.pathLayer     = L.layerGroup().addTo(this.map);
            this.pernoctaLayer = L.layerGroup().addTo(this.map);

            this.loadMap();
        },

        applyTypeFilter() {
            const addOrRemove = (layer, visible) => {
                if (visible) {
                    layer.addTo(this.map);
                } else {
                    this.map.removeLayer(layer);
                }
            };
            addOrRemove(this.movementLayer, this.typeFilters.movement);
            addOrRemove(this.voiceOutLayer,  this.typeFilters.voice_out);
            addOrRemove(this.voiceInLayer,   this.typeFilters.voice_in);
            addOrRemove(this.pernoctaLayer,  this.typeFilters.pernocta);
            // path follows movement
            if (this.showPath) addOrRemove(this.pathLayer, this.typeFilters.movement);
        },

        async loadMap() {
            this.loading = true;
            this.movementLayer.clearLayers();
            this.voiceOutLayer.clearLayers();
            this.voiceInLayer.clearLayers();
            this.pathLayer.clearLayers();
            this.pernoctaLayer.clearLayers();

            const params = new URLSearchParams();
            Object.entries(this.filters).forEach(([k, v]) => { if (v) params.set(k, v); });

            const res = await fetch('/api/map-data?' + params.toString());
            const data = await res.json();

            this.targetNumber = data.targetNumber;
            this.markerCount  = data.markers.length;
            this.pernoctaInfo  = data.pernocta;
            // Re-apply type visibility after fresh load
            this.applyTypeFilter();

            const bounds = [];

            data.markers.forEach(m => {
                const color = m.color || '#3b82f6';
                const size = m.markerType === 'movement' ? 10 : 13;

                const icon = L.divIcon({
                    className: '',
                    html: `<div style="width:${size}px;height:${size}px;border-radius:50%;background:${color};border:2px solid white;box-shadow:0 0 4px rgba(0,0,0,.5);"></div>`,
                    iconSize: [size, size],
                    iconAnchor: [size/2, size/2],
                });

                const marker = L.marker([m.lat, m.lng], { icon });

                const azimuthBadge = m.azimuth
                    ? `<p><span style="color:#94a3b8">Azimuth:</span> ${m.azimuth}°</p>`
                    : '';

                let popupContent = '';
                if (m.markerType === 'movement') {
                    popupContent = `
                        <div style="min-width:180px;">
                            <p style="font-weight:bold;color:#fff;margin-bottom:6px;">${m.label}</p>
                            <p><span style="color:#94a3b8">Número:</span> ${m.number}</p>
                            <p><span style="color:#94a3b8">Fecha:</span> ${m.date} ${m.hour}</p>
                            <p><span style="color:#94a3b8">Tipo:</span> Datos (movimiento)</p>
                            ${azimuthBadge}
                        </div>`;
                } else {
                    // voice
                    const dur = m.duration ? this.formatDuration(m.duration) : '-';
                    popupContent = `
                        <div style="min-width:190px;">
                            <p style="font-weight:bold;color:#fff;margin-bottom:6px;">${m.label}</p>
                            <p><span style="color:#94a3b8">Número A:</span> ${m.number}</p>
                            <p><span style="color:#94a3b8">Número B:</span> ${m.numberB || '-'}</p>
                            <p><span style="color:#94a3b8">Dirección:</span> ${m.direction}</p>
                            <p><span style="color:#94a3b8">Fecha:</span> ${m.date} ${m.hour}</p>
                            <p><span style="color:#94a3b8">Duración:</span> ${dur}</p>
                            ${azimuthBadge}
                        </div>`;
                }

                marker.bindPopup(popupContent);
                if (m.markerType === 'movement') {
                    this.movementLayer.addLayer(marker);
                } else if (m.direction === 'Outgoing') {
                    this.voiceOutLayer.addLayer(marker);
                } else {
                    this.voiceInLayer.addLayer(marker);
                }
                bounds.push([m.lat, m.lng]);
            });

            // Movement path (data records)
            if (this.showPath && data.movementPath && data.movementPath.length > 1) {
                const polyline = L.polyline(data.movementPath, {
                    color: '#8b5cf6',
                    weight: 2,
                    opacity: 0.6,
                    dashArray: '6 4',
                });
                this.pathLayer.addLayer(polyline);
            }

            // Pernocta marker + circle
            if (data.pernocta) {
                const p = data.pernocta;
                const dur = this.formatDuration(p.total_duration);

                const pernoctaIcon = L.divIcon({
                    className: '',
                    html: `<div style="width:18px;height:18px;border-radius:50%;background:#f97316;border:3px solid white;box-shadow:0 0 8px rgba(249,115,22,0.7);"></div>`,
                    iconSize: [18, 18],
                    iconAnchor: [9, 9],
                });

                const pernoctaMarker = L.marker([p.lat, p.lng], { icon: pernoctaIcon });
                pernoctaMarker.bindPopup(`
                    <div style="min-width:190px;">
                        <p style="font-weight:bold;color:#f97316;margin-bottom:6px;">🌙 Antena de Pernocta</p>
                        <p><span style="color:#94a3b8">Coordenadas:</span> ${p.lat.toFixed(5)}, ${p.lng.toFixed(5)}</p>
                        <p><span style="color:#94a3b8">Duración total:</span> ${dur}</p>
                        <p><span style="color:#94a3b8">Sesiones nocturnas:</span> ${p.sessions}</p>
                        <p style="color:#94a3b8;font-size:10px;margin-top:4px;">Horario: 23:00 - 07:00 h</p>
                    </div>
                `);

                const circle = L.circle([p.lat, p.lng], {
                    radius: 200,
                    color: '#f97316',
                    fillColor: '#f97316',
                    fillOpacity: 0.15,
                    weight: 2,
                    dashArray: '4 4',
                });

                this.pernoctaLayer.addLayer(circle);
                this.pernoctaLayer.addLayer(pernoctaMarker);
                bounds.push([p.lat, p.lng]);
            }

            if (bounds.length > 0) {
                this.map.fitBounds(bounds, { padding: [40, 40] });
            }

            this.loading = false;
        },

        togglePath() {
            if (this.showPath && this.typeFilters.movement) {
                this.pathLayer.addTo(this.map);
            } else {
                this.map.removeLayer(this.pathLayer);
            }
        },

        formatDuration(secs) {
            if (!secs) return '0:00:00';
            const h = Math.floor(secs / 3600);
            const m = Math.floor((secs % 3600) / 60);
            const s = secs % 60;
            return `${h}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        }
    };
}
</script>
@endpush
