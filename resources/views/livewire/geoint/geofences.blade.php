@section('title', 'GEOINT — Geocercas')
@section('page-title', 'Geocercas')
@section('page-subtitle', 'Zonas de alerta geográfica')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@endpush

<div class="space-y-6">

    <div class="flex items-center justify-between">
        <p class="text-slate-400 text-sm">{{ $geofences->count() }} geocerca(s) configurada(s)</p>
        <button wire:click="openCreate"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white"
                style="background-color:#10b981;">
            <i class="fas fa-plus"></i> Nueva Geocerca
        </button>
    </div>

    @if(session('success'))
    <div class="px-4 py-3 rounded-lg text-sm text-green-300 flex items-center gap-2" style="background-color:#052e16;border:1px solid #14532d;">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Formulario --}}
    @if($showForm)
    <div class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
        <h3 class="text-white font-semibold text-sm mb-4">
            <i class="fas fa-draw-polygon mr-2 text-green-400"></i>
            {{ $editingId ? 'Editar Geocerca' : 'Nueva Geocerca Circular' }}
        </h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            {{-- Campos --}}
            <div class="space-y-4">
                <div>
                    <label class="text-xs text-slate-400 block mb-1">Nombre *</label>
                    <input wire:model="name" type="text" placeholder="Zona Centro"
                           class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-green-500">
                    @error('name') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs text-slate-400 block mb-1">Descripción</label>
                    <textarea wire:model="description" rows="2"
                              class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-green-500"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-slate-400 block mb-1">Latitud centro</label>
                        <input wire:model="center_lat" type="text" id="fieldLat" placeholder="24.1426"
                               class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-green-500">
                        @error('center_lat') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="text-xs text-slate-400 block mb-1">Longitud centro</label>
                        <input wire:model="center_lon" type="text" id="fieldLon" placeholder="-110.3128"
                               class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-green-500">
                        @error('center_lon') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div>
                    <label class="text-xs text-slate-400 block mb-1">Radio (metros)</label>
                    <input wire:model.live="radius" type="number" id="fieldRadius" min="10" max="50000" placeholder="500"
                           class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-green-500">
                    @error('radius') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="text-xs text-slate-400 block mb-1">Color</label>
                    <div class="flex items-center gap-2">
                        <input wire:model="color" type="color" class="h-9 w-16 rounded border border-slate-600 bg-slate-900 cursor-pointer">
                        <span class="text-slate-400 text-xs">{{ $color }}</span>
                    </div>
                </div>
                <div class="flex gap-4">
                    <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                        <input wire:model="alert_on_enter" type="checkbox" class="rounded">
                        Alerta al entrar
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer">
                        <input wire:model="alert_on_exit" type="checkbox" class="rounded">
                        Alerta al salir
                    </label>
                </div>
            </div>

            {{-- Mini mapa para seleccionar coordenadas --}}
            <div>
                <p class="text-xs text-slate-400 mb-2">
                    <i class="fas fa-mouse-pointer mr-1"></i> Click en el mapa para seleccionar el centro
                </p>
                <div wire:ignore id="map-wrapper">
                    <div id="picker-map"
                         style="height:400px;width:100%;border-radius:8px;border:1px solid #334155;background:#0f172a;"></div>
                </div>
            </div>
        </div>

        <div class="flex gap-3 mt-4">
            <button wire:click="save"
                    class="px-5 py-2 rounded-lg text-sm font-medium text-white"
                    style="background-color:#10b981;">
                <i class="fas fa-save mr-1"></i> Guardar
            </button>
            <button wire:click="cancel"
                    class="px-5 py-2 rounded-lg text-sm font-medium text-slate-400 hover:text-white border border-slate-600">
                Cancelar
            </button>
        </div>
    </div>
    @endif

    {{-- Tabla --}}
    <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
        @if($geofences->isEmpty())
        <div class="p-12 text-center">
            <i class="fas fa-draw-polygon text-5xl text-slate-600 mb-4 block"></i>
            <p class="text-white font-semibold mb-2">Sin geocercas configuradas</p>
            <p class="text-slate-400 text-sm">Crea una geocerca para recibir alertas de entrada/salida.</p>
        </div>
        @else
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-700">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Nombre</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Radio</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Alertas</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                @foreach($geofences as $fence)
                <tr class="hover:bg-slate-800 transition-colors">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full" style="background-color:{{ $fence->color }};"></div>
                            <span class="text-white font-medium">{{ $fence->name }}</span>
                        </div>
                        @if($fence->description)
                        <p class="text-slate-500 text-xs mt-0.5 pl-5">{{ Str::limit($fence->description, 50) }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-slate-300 text-xs capitalize">{{ $fence->type }}</td>
                    <td class="px-4 py-3 text-slate-300 text-xs">
                        {{ $fence->radius ? number_format($fence->radius) . ' m' : '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex gap-1">
                            @if($fence->alert_on_enter)<span class="text-xs bg-green-900 text-green-300 px-1.5 py-0.5 rounded">Entrada</span>@endif
                            @if($fence->alert_on_exit)<span class="text-xs bg-red-900 text-red-300 px-1.5 py-0.5 rounded">Salida</span>@endif
                        </div>
                        <p class="text-slate-500 text-xs mt-1">{{ $fence->alerts_count }} alertas</p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs px-2 py-0.5 rounded {{ $fence->is_active ? 'bg-green-900 text-green-300' : 'bg-slate-700 text-slate-400' }}">
                            {{ $fence->is_active ? 'Activa' : 'Inactiva' }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <button wire:click="openEdit({{ $fence->id }})"
                                    class="text-slate-400 hover:text-blue-400 transition-colors text-xs px-2 py-1 rounded border border-slate-600">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button wire:click="toggleActive({{ $fence->id }})"
                                    class="text-xs px-2 py-1 rounded border transition-colors
                                           {{ $fence->is_active ? 'border-red-800 text-red-400 hover:bg-red-900' : 'border-green-800 text-green-400 hover:bg-green-900' }}">
                                {{ $fence->is_active ? 'Desactivar' : 'Activar' }}
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

@script
<script>
    let pickerMap    = null;
    let pickerMarker = null;
    let pickerCircle = null;

    function initPickerMap() {
        // Destruir instancia anterior si existe
        if (pickerMap) {
            pickerMap.remove();
            pickerMap = pickerMarker = pickerCircle = null;
        }

        const mapDiv = document.getElementById('picker-map');
        if (!mapDiv) return;

        pickerMap = L.map('picker-map').setView([24.1426, -110.3128], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
            maxZoom: 19,
        }).addTo(pickerMap);

        // Forzar recálculo de tamaño tras inserción dinámica
        setTimeout(() => pickerMap.invalidateSize(), 100);

        pickerMap.on('click', function (e) {
            const lat = parseFloat(e.latlng.lat.toFixed(6));
            const lng = parseFloat(e.latlng.lng.toFixed(6));

            $wire.set('center_lat', String(lat));
            $wire.set('center_lon', String(lng));

            updateMapMarker(lat, lng);
        });

        // Si ya hay coordenadas (modo edición), dibujar círculo inicial
        const initLat = parseFloat(document.getElementById('fieldLat')?.value);
        const initLon = parseFloat(document.getElementById('fieldLon')?.value);
        if (!isNaN(initLat) && !isNaN(initLon)) {
            updateMapMarker(initLat, initLon);
        }
    }

    function updateMapMarker(lat, lng) {
        if (!pickerMap) return;

        if (pickerMarker) pickerMap.removeLayer(pickerMarker);
        if (pickerCircle) pickerMap.removeLayer(pickerCircle);

        const radius = parseInt(document.getElementById('fieldRadius')?.value) || 500;
        const color  = document.querySelector('input[type="color"]')?.value || '#ef4444';

        pickerMarker = L.marker([lat, lng]).addTo(pickerMap);
        pickerCircle = L.circle([lat, lng], {
            radius:      radius,
            color:       color,
            fillColor:   color,
            fillOpacity: 0.2,
            weight:      2,
        }).addTo(pickerMap);

        pickerMap.setView([lat, lng], 14);
    }

    // Abrir formulario (crear o editar) → inicializar mapa
    $wire.on('formOpened', () => {
        setTimeout(initPickerMap, 150);
    });

    // Cambios en radio o color → actualizar círculo sin mover el mapa
    document.addEventListener('input', function (e) {
        if (e.target.id === 'fieldRadius') {
            const lat = parseFloat(document.getElementById('fieldLat')?.value);
            const lon = parseFloat(document.getElementById('fieldLon')?.value);
            if (!isNaN(lat) && !isNaN(lon)) updateMapMarker(lat, lon);
        }
    });
</script>
@endscript
