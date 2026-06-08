@section('title', 'GEOINT — Alertas')
@section('page-title', 'Alertas de Geocerca')
@section('page-subtitle', 'Entradas y salidas de zonas monitoreadas')

<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            @if($pendingCount > 0)
            <span class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-semibold text-white animate-pulse"
                  style="background-color:#ef444420; border:1px solid #ef4444; color:#f87171;">
                <i class="fas fa-bell"></i> {{ $pendingCount }} alerta{{ $pendingCount > 1 ? 's' : '' }} sin reconocer
            </span>
            @else
            <span class="text-green-400 text-sm flex items-center gap-2">
                <i class="fas fa-check-circle"></i> Sin alertas pendientes
            </span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            @if($pendingCount > 0)
            <button wire:click="acknowledgeAll"
                    wire:confirm="¿Reconocer todas las alertas pendientes?"
                    class="px-4 py-2 rounded-lg text-sm font-medium text-white border border-green-700 hover:bg-green-900 transition-colors text-green-300">
                <i class="fas fa-check-double mr-1"></i> Reconocer todas
            </button>
            @endif
        </div>
    </div>

    {{-- Filtros --}}
    <div class="flex flex-wrap gap-3 p-4 rounded-xl border border-slate-700" style="background-color:#1e293b;">
        <div>
            <label class="text-xs text-slate-400 block mb-1">Unidad</label>
            <select wire:model.live="filterUnit"
                    class="px-3 py-1.5 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-blue-500">
                <option value="">Todas</option>
                @foreach($units as $unit)
                <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="text-xs text-slate-400 block mb-1">Geocerca</label>
            <select wire:model.live="filterGeofence"
                    class="px-3 py-1.5 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-blue-500">
                <option value="">Todas</option>
                @foreach($geofences as $fence)
                <option value="{{ $fence->id }}">{{ $fence->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <label class="flex items-center gap-2 text-sm text-slate-300 cursor-pointer pb-1.5">
                <input wire:model.live="showAcknowledged" type="checkbox" class="rounded">
                Mostrar reconocidas
            </label>
        </div>
    </div>

    {{-- Tabla --}}
    <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
        @if($alerts->isEmpty())
        <div class="p-12 text-center">
            <i class="fas fa-bell-slash text-5xl text-slate-600 mb-4 block"></i>
            <p class="text-white font-semibold mb-2">Sin alertas</p>
            <p class="text-slate-400 text-sm">No hay alertas {{ $showAcknowledged ? '' : 'pendientes ' }}con los filtros seleccionados.</p>
        </div>
        @else
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-700">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Unidad</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Geocerca</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Coordenadas</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Disparada</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                @foreach($alerts as $alert)
                <tr class="hover:bg-slate-800 transition-colors {{ $alert->acknowledged ? 'opacity-50' : '' }}">
                    <td class="px-4 py-3">
                        @if($alert->alert_type === 'enter')
                        <span class="flex items-center gap-1.5 text-xs text-green-400">
                            <i class="fas fa-sign-in-alt"></i> Entrada
                        </span>
                        @else
                        <span class="flex items-center gap-1.5 text-xs text-red-400">
                            <i class="fas fa-sign-out-alt"></i> Salida
                        </span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            @if($alert->unit)
                            <div class="w-2.5 h-2.5 rounded-full" style="background-color:{{ $alert->unit->color }};"></div>
                            <span class="text-white text-xs">{{ $alert->unit->name }}</span>
                            @else
                            <span class="text-slate-500 text-xs">—</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-300 text-xs">
                        {{ $alert->geofence?->name ?? '—' }}
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-400">
                        {{ number_format($alert->lat, 6) }},<br>{{ number_format($alert->lon, 6) }}
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-400">
                        <p>{{ $alert->triggered_at->format('d/m/Y H:i:s') }}</p>
                        <p class="text-slate-600">{{ $alert->triggered_at->locale('es')->diffForHumans() }}</p>
                    </td>
                    <td class="px-4 py-3">
                        @if($alert->acknowledged)
                        <div class="text-xs text-green-400">
                            <p><i class="fas fa-check mr-1"></i>Reconocida</p>
                            <p class="text-slate-600">{{ $alert->acknowledgedByUser?->name ?? 'Sistema' }}</p>
                            <p class="text-slate-600">{{ $alert->acknowledged_at?->format('d/m H:i') }}</p>
                        </div>
                        @else
                        <span class="text-xs text-yellow-400 flex items-center gap-1">
                            <i class="fas fa-clock"></i> Pendiente
                        </span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if(!$alert->acknowledged)
                        <button wire:click="acknowledgeAlert({{ $alert->id }})"
                                class="text-xs px-3 py-1 rounded border border-green-700 text-green-400 hover:bg-green-900 transition-colors">
                            <i class="fas fa-check mr-1"></i>Reconocer
                        </button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-slate-700">
            {{ $alerts->links() }}
        </div>
        @endif
    </div>
</div>
