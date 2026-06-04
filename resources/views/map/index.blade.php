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
        <!-- Casilla para activar carga de datos -->
        <label class="flex items-center gap-2.5 cursor-pointer select-none py-1 px-2 rounded-lg border transition-all"
               :class="dataLoaded
                   ? 'border-blue-500 bg-blue-900/20'
                   : 'border-slate-600 hover:border-slate-500'">
            <input type="checkbox"
                   :checked="dataLoaded"
                   @change="toggleDataLoad($event.target.checked)"
                   class="rounded accent-blue-500">
            <span class="text-sm font-medium" :class="dataLoaded ? 'text-blue-300' : 'text-slate-300'">
                <i class="fas fa-map-marked-alt mr-1 text-xs"></i>
                Cargar ubicaciones
            </span>
        </label>

        <h3 class="text-white font-semibold text-sm">Filtros</h3>

        <div>
            <label class="text-xs text-slate-400 block mb-1">Filtrar por sábana</label>
            <select x-model="filters.number" @change="if(dataLoaded) loadMap()"
                    class="w-full text-xs rounded-lg px-2 py-1.5 text-white border border-slate-600 focus:outline-none"
                    style="background-color:#0f172a;">
                <option value="">Todas las sábanas</option>
                @foreach($targetsByFile as $t)
                <option value="{{ $t['phone'] }}">{{ $t['phone'] }}</option>
                @endforeach
            </select>
            @if(count($targetsByFile) > 1)
            <p class="text-xs text-slate-600 mt-0.5">{{ count($targetsByFile) }} sábanas cargadas</p>
            @endif
        </div>

        <div>
            <label class="text-xs text-slate-400 block mb-1">Desde</label>
            <input type="date" x-model="filters.date_from" @change="if(dataLoaded) loadMap()"
                   class="w-full text-xs rounded-lg px-2 py-1.5 text-white border border-slate-600 focus:outline-none"
                   style="background-color:#0f172a;">
        </div>

        <div>
            <label class="text-xs text-slate-400 block mb-1">Hasta</label>
            <input type="date" x-model="filters.date_to" @change="if(dataLoaded) loadMap()"
                   class="w-full text-xs rounded-lg px-2 py-1.5 text-white border border-slate-600 focus:outline-none"
                   style="background-color:#0f172a;">
            @if($dateRange['min'])
            <p class="text-xs text-slate-600 mt-0.5">Rango: {{ $dateRange['min'] }} → {{ $dateRange['max'] }}</p>
            @endif
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

        <!-- Save map snapshots for report -->
        <div class="border-t border-slate-700 pt-3 shrink-0 px-0">
            <button @click="saveMapSnapshots()"
                    :disabled="mapSaving"
                    :class="mapSaved
                        ? 'border-green-600 text-green-300 bg-green-900/20'
                        : 'border-slate-600 text-slate-300 hover:border-slate-500'"
                    class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-lg text-xs font-medium border transition-all disabled:opacity-50">
                <template x-if="mapSaving">
                    <span><i class="fas fa-spinner fa-spin mr-1"></i>
                        Guardando <span x-text="mapSavingLabel"></span>...</span>
                </template>
                <template x-if="mapSaved && !mapSaving">
                    <span><i class="fas fa-check mr-1"></i> Mapas guardados para reporte</span>
                </template>
                <template x-if="!mapSaving && !mapSaved">
                    <span><i class="fas fa-camera mr-1"></i> Guardar mapas para reporte</span>
                </template>
            </button>
            <p class="text-xs text-slate-500 text-center mt-1">Guarda 3 mapas: datos, voz y pernocta</p>
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
        <!-- Placeholder cuando no se han cargado datos -->
        <div x-show="!dataLoaded && !loading"
             class="absolute inset-0 flex flex-col items-center justify-center z-10 rounded-xl"
             style="background-color:#0f172a;">
            <i class="fas fa-map-marked-alt text-5xl text-slate-700 mb-4"></i>
            <p class="text-slate-400 font-medium text-sm mb-1">Mapa sin datos</p>
            <p class="text-slate-600 text-xs">Activa <span class="text-blue-400 font-medium">Cargar ubicaciones</span> en el panel izquierdo</p>
        </div>

        <div id="map" class="absolute inset-0 rounded-xl"></div>
    </div>

    <!-- Modal de captura de mapas -->
    <div x-show="mapSaving" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center"
         style="background: rgba(0,0,0,0.75);">
        <div class="rounded-2xl p-8 flex flex-col items-center gap-5 shadow-2xl text-center"
             style="background:#1e293b; border:1px solid #334155; min-width:280px;">
            <!-- Spinner animado -->
            <div class="relative w-16 h-16">
                <div class="absolute inset-0 rounded-full border-4 border-slate-700"></div>
                <div class="absolute inset-0 rounded-full border-4 border-t-blue-500 animate-spin"></div>
                <div class="absolute inset-0 flex items-center justify-center">
                    <i class="fas fa-camera text-blue-400 text-lg"></i>
                </div>
            </div>
            <div>
                <p class="text-white font-semibold text-base mb-1">Guardando mapas para el reporte</p>
                <p class="text-slate-400 text-sm">
                    Capturando mapa de
                    <span class="text-blue-300 font-medium" x-text="mapSavingLabel"></span>...
                </p>
            </div>
            <div class="w-full space-y-2 text-xs text-left">
                <div class="flex items-center gap-2"
                     :class="['Datos','Voz','Pernocta'].indexOf(mapSavingLabel) > 0 ? 'text-green-400' : (mapSavingLabel === 'Datos' ? 'text-blue-300' : 'text-slate-500')">
                    <i class="fas fa-check-circle w-4"></i> Mapa de Datos
                </div>
                <div class="flex items-center gap-2"
                     :class="['Pernocta'].indexOf(mapSavingLabel) > 0 ? 'text-green-400' : (mapSavingLabel === 'Voz' ? 'text-blue-300' : 'text-slate-500')">
                    <i class="fas fa-check-circle w-4"></i> Mapa de Voz
                </div>
                <div class="flex items-center gap-2"
                     :class="mapSavingLabel === 'Pernocta' ? 'text-blue-300' : 'text-slate-500'">
                    <i class="fas fa-check-circle w-4"></i> Mapa de Pernocta
                </div>
            </div>
            <p class="text-slate-500 text-xs">Por favor espere, no cierre esta ventana</p>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
function mapApp() {
    return {
        loading: false,
        dataLoaded: false,
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
        mapSaving: false,
        mapSaved: false,
        mapSavingLabel: '',
        // Bounds collected per type during loadMap — used to fit before capture
        markerBounds: { movement: [], voice: [], pernocta: [] },
        typeFilters: {
            movement:  true,
            voice_out: true,
            voice_in:  true,
            pernocta:  true,
        },
        filters: {
            number: '',
            date_from: '',
            date_to: '',
        },

        init() {
            // preferCanvas: true → todos los circleMarker se renderizan en <canvas>
            // mucho más rápido que SVG/DOM para grandes volúmenes
            this.map = L.map('map', {
                center: [0, 0],
                zoom: 3,
                zoomControl: true,
                preferCanvas: true,
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
            // No cargar datos automáticamente — espera la casilla
        },

        toggleDataLoad(enabled) {
            if (enabled) {
                this.loadMap();
            } else {
                // Limpiar capas pero mantener el mapa base
                this.movementLayer.clearLayers();
                this.voiceOutLayer.clearLayers();
                this.voiceInLayer.clearLayers();
                this.pathLayer.clearLayers();
                this.pernoctaLayer.clearLayers();
                this.markerCount  = 0;
                this.pernoctaInfo = null;
                this.dataLoaded   = false;
                this.markerBounds = { movement: [], voice: [], pernocta: [] };
                this.map.setView([0, 0], 3);
            }
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
            this.pernoctaInfo = data.pernocta;
            this.dataLoaded   = true;
            // Reset per-type bounds
            this.markerBounds = { movement: [], voice: [], pernocta: [] };
            // Re-apply type visibility after fresh load
            this.applyTypeFilter();

            const bounds = [];

            // circleMarker (canvas) es 10-20× más rápido que divIcon+L.marker (DOM)
            data.markers.forEach(m => {
                const color  = m.color || '#3b82f6';
                const radius = m.markerType === 'movement' ? 5 : 7;

                const marker = L.circleMarker([m.lat, m.lng], {
                    radius,
                    fillColor:   color,
                    color:       'rgba(255,255,255,0.8)',
                    weight:      1.5,
                    opacity:     1,
                    fillOpacity: 0.9,
                });

                // Popup con contenido lazy (sólo se genera al hacer click)
                if (m.markerType === 'movement') {
                    marker.bindPopup(() => {
                        const az = m.azimuth ? `<p><span style="color:#94a3b8">Azimuth:</span> ${m.azimuth}°</p>` : '';
                        return `<div style="min-width:180px;font-size:12px;">
                            <p style="font-weight:bold;color:#fff;margin-bottom:6px;">${m.label}</p>
                            <p><span style="color:#94a3b8">Número:</span> ${m.number}</p>
                            <p><span style="color:#94a3b8">Fecha:</span> ${m.date} ${m.hour}</p>
                            <p><span style="color:#94a3b8">Tipo:</span> Datos (movimiento)</p>${az}</div>`;
                    });
                    this.movementLayer.addLayer(marker);
                    this.markerBounds.movement.push([m.lat, m.lng]);
                } else {
                    marker.bindPopup(() => {
                        const dur = m.duration ? this.formatDuration(m.duration) : '-';
                        const az  = m.azimuth ? `<p><span style="color:#94a3b8">Azimuth:</span> ${m.azimuth}°</p>` : '';
                        return `<div style="min-width:190px;font-size:12px;">
                            <p style="font-weight:bold;color:#fff;margin-bottom:6px;">${m.label}</p>
                            <p><span style="color:#94a3b8">Número A:</span> ${m.number}</p>
                            <p><span style="color:#94a3b8">Número B:</span> ${m.numberB || '-'}</p>
                            <p><span style="color:#94a3b8">Dirección:</span> ${m.direction}</p>
                            <p><span style="color:#94a3b8">Fecha:</span> ${m.date} ${m.hour}</p>
                            <p><span style="color:#94a3b8">Duración:</span> ${dur}</p>${az}</div>`;
                    });
                    if (m.direction === 'Outgoing') {
                        this.voiceOutLayer.addLayer(marker);
                    } else {
                        this.voiceInLayer.addLayer(marker);
                    }
                    this.markerBounds.voice.push([m.lat, m.lng]);
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
                this.markerBounds.pernocta.push([p.lat, p.lng]);
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
        },

        async saveMapSnapshots() {
            if (this.mapSaving) return;
            this.mapSaving = true;
            this.mapSaved  = false;

            const csrf = document.querySelector('meta[name="csrf-token"]').content;

            const types = [
                {
                    key:        'map_data',
                    label:      'Datos',
                    boundsKey:  'movement',
                    filters:    { movement: true,  voice_out: false, voice_in: false, pernocta: false },
                    hidePath:   true,   // no mostrar línea de trayectoria en la captura
                },
                {
                    key:        'map_voice',
                    label:      'Voz',
                    boundsKey:  'voice',
                    filters:    { movement: false, voice_out: true,  voice_in: true,  pernocta: false },
                    hidePath:   false,
                },
                {
                    key:        'map_pernocta',
                    label:      'Pernocta',
                    boundsKey:  'pernocta',
                    filters:    { movement: false, voice_out: false, voice_in: false, pernocta: true  },
                    hidePath:   false,
                },
            ];

            const savedFilters  = { ...this.typeFilters };
            const savedShowPath = this.showPath;

            try {
                for (const t of types) {
                    this.mapSavingLabel = t.label;

                    // Ocultar línea si aplica (mapa de datos)
                    this.showPath = t.hidePath ? false : savedShowPath;

                    // Aplicar visibilidad de capas
                    this.typeFilters = { ...t.filters };
                    this.applyTypeFilter();

                    // Ajustar zoom a los puntos visibles de este tipo
                    const pts = this.markerBounds[t.boundsKey] || [];
                    if (pts.length === 1) {
                        this.map.setView(pts[0], 14);
                    } else if (pts.length > 1) {
                        this.map.fitBounds(pts, { padding: [50, 50], maxZoom: 15 });
                    }

                    this.map.invalidateSize();
                    await new Promise(r => setTimeout(r, 800));

                    // Capturar
                    const canvas = await html2canvas(document.getElementById('map'), {
                        useCORS:         true,
                        allowTaint:      true,
                        backgroundColor: '#0f172a',
                        logging:         false,
                    });
                    const png = canvas.toDataURL('image/png');

                    // Subir al servidor
                    await fetch('/api/map-snapshot', {
                        method:  'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
                        body:    JSON.stringify({ image: png, type: t.key }),
                    });
                }

                this.mapSaved = true;
                setTimeout(() => { this.mapSaved = false; }, 5000);
            } catch (e) {
                console.error('Error saving map snapshots', e);
            } finally {
                // Restaurar estado original
                this.showPath    = savedShowPath;
                this.typeFilters = savedFilters;
                this.applyTypeFilter();
                this.map.fitBounds(
                    [...this.markerBounds.movement, ...this.markerBounds.voice, ...this.markerBounds.pernocta],
                    { padding: [40, 40] }
                );
                this.mapSaving = false;
            }
        },
    };
}
</script>
@endpush
