@section('title', 'GEOINT — Vehículos')
@section('page-title', 'Vehículos')
@section('page-subtitle', 'Rastreo en vivo, historial importado y playback')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

<div class="flex" style="height: calc(100vh - 138px); overflow:hidden;">

    {{-- ══ PANEL IZQUIERDO ══ --}}
    <div style="width:260px; background:#1e293b; border-right:1px solid #334155;
                display:flex; flex-direction:column; overflow:hidden; flex-shrink:0;">

        {{-- Header panel --}}
        <div style="padding:12px; border-bottom:1px solid #334155;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <span style="color:#e2e8f0; font-size:13px; font-weight:500;">Vehículos</span>
                <div style="display:flex; gap:6px;">
                    <button wire:click="$set('showGpsForm', true)"
                        style="background:#10b981; color:white; border:none; padding:4px 10px;
                               border-radius:6px; font-size:11px; cursor:pointer;">
                        + GPS
                    </button>
                    <button wire:click="$set('showImportForm', true)"
                        style="background:#3b82f6; color:white; border:none; padding:4px 10px;
                               border-radius:6px; font-size:11px; cursor:pointer;">
                        + Importar
                    </button>
                </div>
            </div>
            <input type="text" placeholder="Buscar..."
                   oninput="filterVehicles(this.value)"
                   style="width:100%; background:#0f172a; border:1px solid #334155;
                          color:#e2e8f0; padding:5px 8px; border-radius:6px; font-size:12px;">
        </div>

        {{-- Lista scrolleable --}}
        <div style="overflow-y:auto; flex:1; padding:8px;" id="vehicle-list">

            {{-- Sección EN VIVO --}}
            <div style="font-size:10px; color:#64748b; padding:4px 6px; margin-bottom:4px;
                        text-transform:uppercase; letter-spacing:0.5px;">
                En vivo
            </div>

            @forelse($vehicles->where('source', 'live') as $v)
            @php $status = $v->gpsUnit?->extended_status ?? 'offline'; @endphp
            <div wire:click="selectVehicle({{ $v->id }})"
                 class="vehicle-item" data-name="{{ strtolower($v->name) }}"
                 style="background:{{ $selectedVehicleId === $v->id ? '#0f172a' : 'transparent' }};
                        border:{{ $selectedVehicleId === $v->id ? '1.5px solid #3b82f6' : '1px solid transparent' }};
                        border-radius:8px; padding:8px 10px; margin-bottom:4px; cursor:pointer;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:#e2e8f0; font-size:12px; font-weight:500;">{{ $v->name }}</span>
                    <span style="background:{{ $status === 'online' ? '#10b981' : '#6b7280' }};
                                 color:white; font-size:9px; padding:2px 6px; border-radius:10px;">
                        {{ $status === 'online' ? 'En línea' : 'Offline' }}
                    </span>
                </div>
                <div style="color:#64748b; font-size:11px; margin-top:2px;">
                    {{ $v->gpsUnit?->imei ?? '' }}
                    @if($v->gpsUnit?->last_speed)
                    · {{ round($v->gpsUnit->last_speed) }} km/h
                    @endif
                </div>
            </div>
            @empty
            <div style="color:#475569; font-size:11px; padding:4px 6px;">Sin unidades en vivo</div>
            @endforelse

            {{-- Sección IMPORTADOS --}}
            @if($vehicles->where('source', 'imported')->count() > 0)
            <div style="font-size:10px; color:#64748b; padding:4px 6px; margin:8px 0 4px;
                        text-transform:uppercase; letter-spacing:0.5px;">
                Historial importado
            </div>

            @foreach($vehicles->where('source', 'imported') as $v)
            <div wire:click="selectVehicle({{ $v->id }})"
                 class="vehicle-item" data-name="{{ strtolower($v->name) }}"
                 style="background:{{ $selectedVehicleId === $v->id ? '#0f172a' : 'transparent' }};
                        border:{{ $selectedVehicleId === $v->id ? '1.5px solid #3b82f6' : '1px solid transparent' }};
                        border-radius:8px; padding:8px 10px; margin-bottom:4px; cursor:pointer;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="color:#e2e8f0; font-size:12px; font-weight:500;">{{ $v->name }}</span>
                    <span style="color:{{ $v->source_format === 'xls' ? '#f59e0b' : '#8b5cf6' }};
                                 border:1px solid {{ $v->source_format === 'xls' ? '#f59e0b' : '#8b5cf6' }};
                                 font-size:9px; padding:2px 6px; border-radius:10px; text-transform:uppercase;">
                        {{ $v->source_format }}
                    </span>
                </div>
                <div style="color:#64748b; font-size:11px; margin-top:2px;">
                    {{ number_format($v->total_points) }} pts
                    @if($v->date_from)
                    · {{ \Carbon\Carbon::parse($v->date_from)->format('d/m') }}
                    – {{ \Carbon\Carbon::parse($v->date_to)->format('d/m') }}
                    @endif
                </div>
                <div style="display:flex; justify-content:flex-end; margin-top:4px;">
                    <button wire:click.stop="deleteVehicle({{ $v->id }})"
                        wire:confirm="¿Eliminar este vehículo importado?"
                        style="background:transparent; color:#ef4444; border:none;
                               font-size:10px; cursor:pointer;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            @endforeach
            @endif
        </div>
    </div>

    {{-- ══ PANEL DERECHO — MAPA + PLAYBACK ══ --}}
    <div style="flex:1; display:flex; flex-direction:column; background:#0f172a; overflow:hidden;">

        @if($selectedVehicle)

        {{-- Info del vehículo seleccionado --}}
        <div style="background:#1e293b; padding:10px 16px; border-bottom:1px solid #334155;
                    display:flex; justify-content:space-between; align-items:center;">
            <div>
                <span style="color:#e2e8f0; font-size:14px; font-weight:500;">
                    {{ $selectedVehicle->name }}
                </span>
                <span style="color:#64748b; font-size:12px; margin-left:8px;">
                    {{ $selectedVehicle->source === 'live'
                       ? 'GPS en vivo'
                       : 'Historial importado · ' . $selectedVehicle->source_format }}
                </span>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
                <span style="color:#64748b; font-size:12px;">
                    {{ count($points) }} puntos cargados
                </span>
                @if($selectedVehicle->source === 'live')
                <span style="background:#1e3a5f; color:#60a5fa; font-size:10px;
                             padding:3px 8px; border-radius:10px;">
                    <i class="fas fa-circle" style="font-size:6px; color:#10b981;"></i>
                    Últimas posiciones
                </span>
                @endif
            </div>
        </div>

        {{-- MAPA --}}
        <div wire:ignore style="flex:1; position:relative; min-height:300px;">
            <div id="vehicle-map" style="width:100%; height:100%; min-height:300px;"></div>

            {{-- Overlay info punto actual --}}
            <div id="point-info" style="position:absolute; top:10px; left:10px;
                 background:rgba(15,23,42,0.9); padding:10px 14px; border-radius:8px;
                 color:white; display:none; z-index:1000; min-width:200px;">
                <div style="font-size:10px; color:#64748b; margin-bottom:4px;">Punto actual</div>
                <div style="font-family:monospace; font-size:12px;" id="info-coords">—</div>
                <div style="display:flex; gap:12px; margin-top:4px;">
                    <span style="color:#10b981; font-size:12px;" id="info-speed">—</span>
                    <span style="color:#94a3b8; font-size:12px;" id="info-time">—</span>
                </div>
                <div style="color:#f59e0b; font-size:11px; margin-top:2px;" id="info-battery"></div>
            </div>
        </div>

        {{-- CONTROLES DE PLAYBACK --}}
        <div style="background:#1e293b; padding:12px 16px; border-top:1px solid #334155;">

            {{-- Barra de progreso clickeable --}}
            <div id="progress-container"
                 style="background:#334155; border-radius:4px; height:6px; cursor:pointer;
                        margin-bottom:6px; overflow:hidden; position:relative;">
                <div id="progress-bar"
                     style="background:#3b82f6; height:100%; width:0%; border-radius:4px;
                            transition:width 0.1s linear;"></div>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:10px;">
                <span style="font-size:10px; color:#64748b;" id="progress-text">0 / 0 puntos</span>
                <span style="font-size:10px; color:#64748b;" id="progress-time">—</span>
            </div>

            {{-- Botones --}}
            <div style="display:flex; align-items:center; justify-content:center; gap:6px;">
                <button onclick="goFirst()"
                    style="background:#334155; color:#94a3b8; border:none; padding:7px 11px;
                           border-radius:6px; cursor:pointer; font-size:13px;">⏮</button>
                <button onclick="stepPrev()"
                    style="background:#334155; color:#94a3b8; border:none; padding:7px 11px;
                           border-radius:6px; cursor:pointer; font-size:13px;">⏪</button>
                <button id="btn-play" onclick="playPause()"
                    style="background:#3b82f6; color:white; border:none; padding:8px 22px;
                           border-radius:8px; cursor:pointer; font-size:14px; font-weight:500;
                           min-width:90px;">
                    ▶ Play
                </button>
                <button onclick="stepNext()"
                    style="background:#334155; color:#94a3b8; border:none; padding:7px 11px;
                           border-radius:6px; cursor:pointer; font-size:13px;">⏩</button>
                <button onclick="goLast()"
                    style="background:#334155; color:#94a3b8; border:none; padding:7px 11px;
                           border-radius:6px; cursor:pointer; font-size:13px;">⏭</button>

                {{-- Velocidades --}}
                <div style="margin-left:12px; display:flex; gap:3px;">
                    @foreach(['1x','2x','5x','10x'] as $spd)
                    <button onclick="changeSpeed('{{ $spd }}')" id="speed-{{ $spd }}"
                        style="background:{{ $spd === '1x' ? '#3b82f6' : '#334155' }};
                               color:{{ $spd === '1x' ? 'white' : '#94a3b8' }};
                               border:none; padding:5px 8px; border-radius:6px;
                               cursor:pointer; font-size:11px;">{{ $spd }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        @else
        {{-- Estado vacío --}}
        <div style="flex:1; display:flex; align-items:center; justify-content:center;
                    flex-direction:column; gap:12px; color:#475569;">
            <i class="fas fa-car" style="font-size:48px; color:#334155;"></i>
            <div style="font-size:14px;">Selecciona un vehículo para ver su ruta</div>
            <div style="font-size:12px; color:#334155;">o importa un archivo .xls .kml .kmz</div>
        </div>
        @endif
    </div>

    {{-- ══ MODAL IMPORTAR ══ --}}
    @if($showImportForm)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:9999;
                display:flex; align-items:center; justify-content:center;">
        <div style="background:#1e293b; border-radius:12px; padding:24px; width:480px;
                    border:1px solid #334155;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="color:#e2e8f0; font-size:16px; margin:0;">Importar ruta de vehículo</h3>
                <button wire:click="$set('showImportForm', false)"
                    style="background:transparent; color:#64748b; border:none; font-size:18px; cursor:pointer;">×</button>
            </div>

            <div style="display:grid; gap:12px;">
                <div>
                    <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:4px;">
                        Nombre del vehículo *
                    </label>
                    <input wire:model="name" placeholder="ej. TK905-43178 / Objetivo A"
                           style="width:100%; background:#0f172a; border:1px solid #334155;
                                  color:#e2e8f0; padding:8px 12px; border-radius:6px; font-size:13px;">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:4px;">Tipo</label>
                        <select wire:model="unit_type"
                            style="width:100%; background:#0f172a; border:1px solid #334155;
                                   color:#e2e8f0; padding:8px; border-radius:6px; font-size:13px;">
                            <option value="covert">Encubierto</option>
                            <option value="patrol">Patrulla</option>
                        </select>
                    </div>
                    <div>
                        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:4px;">Color en mapa</label>
                        <input type="color" wire:model="color"
                               style="width:100%; height:38px; background:#0f172a;
                                      border:1px solid #334155; border-radius:6px; cursor:pointer;">
                    </div>
                </div>

                <div>
                    <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:4px;">Archivo de ruta</label>
                    <input type="file" wire:model="uploadedFile" accept=".xls,.kml,.kmz,.gpx"
                           style="width:100%; background:#0f172a; border:1px dashed #334155;
                                  color:#94a3b8; padding:12px; border-radius:6px; font-size:12px; cursor:pointer;">
                    <div wire:loading wire:target="uploadedFile" style="color:#60a5fa; font-size:11px; margin-top:4px;">
                        Procesando archivo...
                    </div>
                    <div style="color:#475569; font-size:11px; margin-top:4px;">
                        Formatos soportados: .xls (TK905), .kml, .kmz, .gpx
                    </div>
                </div>

                @if(!empty($importPreview))
                <div style="background:#0f172a; border-radius:6px; padding:10px; border:1px solid #334155;">
                    <div style="color:#10b981; font-size:11px; margin-bottom:6px;">
                        ✓ Archivo válido — preview primeros puntos:
                    </div>
                    @foreach($importPreview as $pt)
                    <div style="color:#94a3b8; font-size:11px; font-family:monospace;">
                        {{ $pt['recorded_at'] }} — {{ $pt['lat'] }}, {{ $pt['lon'] }}
                        @if($pt['speed']) · {{ $pt['speed'] }} km/h @endif
                    </div>
                    @endforeach
                </div>
                @endif

                @if($importError)
                <div style="background:#450a0a; color:#fca5a5; padding:10px; border-radius:6px; font-size:12px;">
                    {{ $importError }}
                </div>
                @endif
            </div>

            <div style="display:flex; gap:10px; margin-top:20px; justify-content:flex-end;">
                <button wire:click="$set('showImportForm', false)"
                    style="background:#334155; color:#94a3b8; border:none; padding:8px 18px;
                           border-radius:6px; cursor:pointer; font-size:13px;">
                    Cancelar
                </button>
                <button wire:click="importVehicle" wire:loading.attr="disabled" wire:target="importVehicle"
                    style="background:#3b82f6; color:white; border:none; padding:8px 20px;
                           border-radius:6px; cursor:pointer; font-size:13px; font-weight:500;">
                    <span wire:loading.remove wire:target="importVehicle">Importar ruta</span>
                    <span wire:loading wire:target="importVehicle">Importando...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ══ MODAL REGISTRAR GPS TK905 ══ --}}
    @if($showGpsForm)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:9999;
                display:flex; align-items:center; justify-content:center;">
        <div style="background:#1e293b; border-radius:12px; padding:24px; width:500px;
                    max-height:90vh; overflow-y:auto; border:1px solid #334155;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="color:#e2e8f0; font-size:16px; margin:0;">Registrar GPS TK905</h3>
                <button wire:click="$set('showGpsForm', false)"
                    style="background:transparent; color:#64748b; border:none; font-size:18px; cursor:pointer;">×</button>
            </div>

            <div style="display:grid; gap:12px;">
                <div>
                    <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:4px;">Nombre del vehículo *</label>
                    <input wire:model="gps_name" placeholder="ej. Patrulla 12 / Objetivo A"
                           style="width:100%; background:#0f172a; border:1px solid #334155;
                                  color:#e2e8f0; padding:8px 12px; border-radius:6px; font-size:13px;">
                    @error('gps_name') <div style="color:#fca5a5; font-size:11px; margin-top:3px;">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:4px;">IMEI del dispositivo *</label>
                    <input wire:model="gps_imei" placeholder="IMEI exacto impreso en el TK905"
                           style="width:100%; background:#0f172a; border:1px solid #334155;
                                  color:#e2e8f0; padding:8px 12px; border-radius:6px; font-size:13px; font-family:monospace;">
                    @error('gps_imei') <div style="color:#fca5a5; font-size:11px; margin-top:3px;">{{ $message }}</div> @enderror
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                    <div>
                        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:4px;">Placa</label>
                        <input wire:model="gps_plate"
                               style="width:100%; background:#0f172a; border:1px solid #334155;
                                      color:#e2e8f0; padding:8px 12px; border-radius:6px; font-size:13px;">
                    </div>
                    <div>
                        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:4px;">Tipo</label>
                        <select wire:model="gps_unit_type"
                            style="width:100%; background:#0f172a; border:1px solid #334155;
                                   color:#e2e8f0; padding:8px; border-radius:6px; font-size:13px;">
                            <option value="patrol">Patrulla</option>
                            <option value="covert">Encubierto</option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;">
                    <div>
                        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:4px;">Color</label>
                        <input type="color" wire:model="gps_color"
                               style="width:100%; height:38px; background:#0f172a;
                                      border:1px solid #334155; border-radius:6px; cursor:pointer;">
                    </div>
                    <div>
                        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:4px;">Ícono</label>
                        <select wire:model="gps_icon"
                            style="width:100%; background:#0f172a; border:1px solid #334155;
                                   color:#e2e8f0; padding:8px; border-radius:6px; font-size:13px;">
                            <option value="fa-car">Auto</option>
                            <option value="fa-truck">Camioneta</option>
                            <option value="fa-motorcycle">Moto</option>
                            <option value="fa-user">Persona</option>
                        </select>
                    </div>
                    <div>
                        <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:4px;">N° SIM</label>
                        <input wire:model="gps_sim" placeholder="referencia"
                               style="width:100%; background:#0f172a; border:1px solid #334155;
                                      color:#e2e8f0; padding:8px 12px; border-radius:6px; font-size:13px;">
                    </div>
                </div>

                <div>
                    <label style="color:#94a3b8; font-size:12px; display:block; margin-bottom:4px;">Notas</label>
                    <input wire:model="gps_notes"
                           style="width:100%; background:#0f172a; border:1px solid #334155;
                                  color:#e2e8f0; padding:8px 12px; border-radius:6px; font-size:13px;">
                </div>
            </div>

            <div style="display:flex; gap:10px; margin-top:20px; justify-content:flex-end;">
                <button wire:click="$set('showGpsForm', false)"
                    style="background:#334155; color:#94a3b8; border:none; padding:8px 18px;
                           border-radius:6px; cursor:pointer; font-size:13px;">Cancelar</button>
                <button wire:click="registerGps" wire:loading.attr="disabled" wire:target="registerGps"
                    style="background:#10b981; color:white; border:none; padding:8px 20px;
                           border-radius:6px; cursor:pointer; font-size:13px; font-weight:500;">
                    <span wire:loading.remove wire:target="registerGps">Registrar dispositivo</span>
                    <span wire:loading wire:target="registerGps">Registrando...</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ══ MODAL INSTRUCCIONES CONFIGURACIÓN TK905 ══ --}}
    @if($showGpsInstructions)
    <div style="position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:9999;
                display:flex; align-items:center; justify-content:center;">
        <div style="background:#1e293b; border-radius:12px; padding:24px; width:520px;
                    max-height:90vh; overflow-y:auto; border:1px solid #334155;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                <h3 style="color:#10b981; font-size:16px; margin:0;">
                    <i class="fas fa-check-circle"></i> {{ $registeredName }} registrado
                </h3>
                <button wire:click="$set('showGpsInstructions', false)"
                    style="background:transparent; color:#64748b; border:none; font-size:18px; cursor:pointer;">×</button>
            </div>

            <p style="color:#94a3b8; font-size:13px; margin-bottom:14px;">
                Configure el TK905 enviando estos SMS al número de la SIM del dispositivo:
            </p>

            <div style="display:grid; gap:10px;">
                <div style="background:#0f172a; border:1px solid #334155; border-radius:6px; padding:10px;">
                    <div style="color:#64748b; font-size:11px; margin-bottom:4px;">1. Configurar APN</div>
                    <div style="color:#e2e8f0; font-family:monospace; font-size:13px;">apn,[nombre_apn]</div>
                </div>
                <div style="background:#0f172a; border:1px solid #334155; border-radius:6px; padding:10px;">
                    <div style="color:#64748b; font-size:11px; margin-bottom:4px;">2. Configurar servidor</div>
                    <div style="color:#e2e8f0; font-family:monospace; font-size:13px;">adminip,{{ $serverHost }},{{ $serverPort }}</div>
                </div>
                <div style="background:#0f172a; border:1px solid #334155; border-radius:6px; padding:10px;">
                    <div style="color:#64748b; font-size:11px; margin-bottom:4px;">3. Activar GPRS</div>
                    <div style="color:#e2e8f0; font-family:monospace; font-size:13px;">gprs</div>
                </div>
                <div style="background:#0f172a; border:1px solid #334155; border-radius:6px; padding:10px;">
                    <div style="color:#64748b; font-size:11px; margin-bottom:4px;">4. Verificar conexión</div>
                    <div style="color:#e2e8f0; font-family:monospace; font-size:13px;">gprs?</div>
                </div>
            </div>

            <div style="background:#1e3a5f; color:#93c5fd; padding:10px; border-radius:6px;
                        font-size:12px; margin-top:14px;">
                <i class="fas fa-info-circle"></i>
                El dispositivo comenzará a reportar en aproximadamente 2 minutos.
                Servidor: <strong>{{ $serverHost }}:{{ $serverPort }}</strong>
            </div>

            <div style="display:flex; justify-content:flex-end; margin-top:18px;">
                <button wire:click="$set('showGpsInstructions', false)"
                    style="background:#3b82f6; color:white; border:none; padding:8px 20px;
                           border-radius:6px; cursor:pointer; font-size:13px; font-weight:500;">Entendido</button>
            </div>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function () {
    let map = null, points = [], currentIndex = 0, isPlaying = false,
        playInterval = null, routeLine = null, progressLine = null,
        playMarker = null, currentSpeed = '1x', vehicleColor = '#94a3b8';
    const speeds = { '1x': 600, '2x': 300, '5x': 120, '10x': 60 };

    // El contenedor #vehicle-map solo existe cuando hay un vehículo seleccionado,
    // así que el mapa se inicializa de forma diferida.
    function ensureMap() {
        const el = document.getElementById('vehicle-map');
        if (!el) return false;
        if (map && map.getContainer() === el) return true;
        if (map) { try { map.remove(); } catch (e) {} map = null; }

        map = L.map(el).setView([24.1426, -110.3128], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap', maxZoom: 19,
        }).addTo(map);
        setTimeout(() => { if (map) map.invalidateSize(); }, 60);
        return true;
    }

    function createPlaybackMarker() {
        return L.divIcon({
            className: '',
            html: `<div style="background:white;border:3px solid #ef4444;border-radius:50%;
                   width:20px;height:20px;display:flex;align-items:center;justify-content:center;
                   box-shadow:0 0 10px #ef444480;">
                   <div style="width:8px;height:8px;background:#ef4444;border-radius:50%;"></div></div>`,
            iconSize: [20, 20], iconAnchor: [10, 10],
        });
    }

    function updateMarker(index) {
        if (!map || !points[index]) return;
        const p = points[index];
        const lat = parseFloat(p.lat);
        const lon = parseFloat(p.lon);

        if (playMarker) map.removeLayer(playMarker);
        playMarker = L.marker([lat, lon], { icon: createPlaybackMarker(), zIndexOffset: 1000 }).addTo(map);

        if (progressLine) map.removeLayer(progressLine);
        if (index > 0) {
            const traveled = points.slice(0, index + 1).map(pt => [parseFloat(pt.lat), parseFloat(pt.lon)]);
            progressLine = L.polyline(traveled, { color: '#3b82f6', weight: 3, opacity: 0.85 }).addTo(map);
        }

        const coordsEl = document.getElementById('info-coords');
        const speedEl  = document.getElementById('info-speed');
        const timeEl   = document.getElementById('info-time');
        const battEl   = document.getElementById('info-battery');
        if (coordsEl) coordsEl.textContent = lat.toFixed(6) + ', ' + lon.toFixed(6);
        if (speedEl)  speedEl.textContent  = p.speed ? parseFloat(p.speed).toFixed(1) + ' km/h' : '— km/h';
        if (timeEl)   timeEl.textContent   = p.recorded_at_formatted || p.recorded_at || '—';
        if (battEl)   battEl.textContent   = p.battery ? '🔋 ' + p.battery : '';
    }

    function updateProgressBar() {
        const pct = points.length > 1 ? (currentIndex / (points.length - 1) * 100) : 0;
        const bar = document.getElementById('progress-bar');
        const txt = document.getElementById('progress-text');
        const tim = document.getElementById('progress-time');
        if (bar) bar.style.width = pct.toFixed(1) + '%';
        if (txt) txt.textContent = (points.length ? currentIndex + 1 : 0) + ' / ' + points.length + ' puntos';
        if (tim && points[currentIndex]) {
            tim.textContent = points[currentIndex].recorded_at_formatted
                            || points[currentIndex].recorded_at || '—';
        }
    }

    function stopPlayback() {
        clearInterval(playInterval);
        isPlaying = false;
        const btn = document.getElementById('btn-play');
        if (btn) btn.innerHTML = '▶ Play';
    }

    function tick() {
        if (currentIndex < points.length - 1) {
            currentIndex++;
            updateMarker(currentIndex);
            updateProgressBar();
        } else {
            stopPlayback();
        }
    }

    // ── Funciones globales (usadas por los onclick del Blade) ──
    window.goTo = function (index) {
        if (!points.length) return;
        currentIndex = Math.max(0, Math.min(index, points.length - 1));
        updateMarker(currentIndex);
        updateProgressBar();
    };

    // Estos wrappers se definen dentro del IIFE para que vean `currentIndex`
    // y `points` (los onclick del Blade corren en ámbito global y no los ven).
    window.goFirst = function () { window.goTo(0); };
    window.goLast  = function () { window.goTo(points.length - 1); };
    window.stepPrev = function () { window.goTo(currentIndex - 1); };
    window.stepNext = function () { window.goTo(currentIndex + 1); };

    window.playPause = function () {
        if (!points.length) return;
        if (isPlaying) {
            stopPlayback();
        } else {
            if (currentIndex >= points.length - 1) currentIndex = 0;
            isPlaying = true;
            const btn = document.getElementById('btn-play');
            if (btn) btn.innerHTML = '⏸ Pausa';
            playInterval = setInterval(tick, speeds[currentSpeed]);
        }
    };

    window.changeSpeed = function (speed) {
        currentSpeed = speed;
        ['1x', '2x', '5x', '10x'].forEach(s => {
            const btn = document.getElementById('speed-' + s);
            if (!btn) return;
            btn.style.background = (s === speed) ? '#3b82f6' : '#334155';
            btn.style.color      = (s === speed) ? 'white'   : '#94a3b8';
        });
        if (isPlaying) {
            clearInterval(playInterval);
            playInterval = setInterval(tick, speeds[speed]);
        }
    };

    window.filterVehicles = function (term) {
        term = (term || '').toLowerCase();
        document.querySelectorAll('.vehicle-item').forEach(el => {
            const name = el.getAttribute('data-name') || '';
            el.style.display = name.includes(term) ? '' : 'none';
        });
    };

    function loadVehicle(pts, vehicle) {
        points = pts || [];
        currentIndex = 0;
        stopPlayback();

        if (!ensureMap() || points.length === 0) {
            updateProgressBar();
            return;
        }

        vehicleColor = (vehicle && vehicle.color) ? vehicle.color : '#94a3b8';

        if (routeLine)    { map.removeLayer(routeLine);    routeLine = null; }
        if (progressLine) { map.removeLayer(progressLine); progressLine = null; }
        if (playMarker)   { map.removeLayer(playMarker);   playMarker = null; }

        const coords = points.map(p => [parseFloat(p.lat), parseFloat(p.lon)]);
        routeLine = L.polyline(coords, { color: vehicleColor, weight: 2.5, opacity: 0.4 }).addTo(map);

        try { map.fitBounds(routeLine.getBounds(), { padding: [40, 40] }); } catch (e) {}

        const info = document.getElementById('point-info');
        if (info) info.style.display = 'block';

        window.goTo(0);
        updateProgressBar();
    }

    // Click en la barra de progreso (delegado: el elemento se recrea con Livewire)
    document.addEventListener('click', function (e) {
        const c = e.target.closest ? e.target.closest('#progress-container') : null;
        if (!c || !points.length) return;
        const rect = c.getBoundingClientRect();
        const pct  = (e.clientX - rect.left) / rect.width;
        window.goTo(Math.floor(pct * (points.length - 1)));
    });

    // Escuchar el evento de Livewire cuando se selecciona un vehículo
    function bindLivewire() {
        if (!window.Livewire) return;
        Livewire.on('vehicleSelected', (e) => {
            const data = Array.isArray(e) ? e[0] : e;
            if (data) loadVehicle(data.points, data.vehicle);
        });
    }
    if (window.Livewire) {
        bindLivewire();
    } else {
        document.addEventListener('livewire:init', bindLivewire);
    }
})();
</script>
@endpush
