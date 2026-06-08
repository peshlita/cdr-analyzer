@section('title', 'GEOINT — Unidades GPS')
@section('page-title', 'Unidades GPS')
@section('page-subtitle', 'Gestión de dispositivos TK905')

<div class="space-y-6">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <p class="text-slate-400 text-sm">{{ $units->count() }} unidad(es) registrada(s)</p>
        <button wire:click="openCreate"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white transition-colors"
                style="background-color:#3b82f6;">
            <i class="fas fa-plus"></i> Nueva Unidad
        </button>
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="px-4 py-3 rounded-lg text-sm text-green-300 flex items-center gap-2" style="background-color:#052e16;border:1px solid #14532d;">
        <i class="fas fa-check-circle"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Formulario --}}
    @if($showForm)
    <div class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
        <h3 class="text-white font-semibold text-sm mb-4">
            <i class="fas fa-{{ $editingId ? 'edit' : 'plus' }} mr-2 text-blue-400"></i>
            {{ $editingId ? 'Editar Unidad' : 'Nueva Unidad' }}
        </h3>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="text-xs text-slate-400 block mb-1">Nombre *</label>
                <input wire:model="name" type="text" placeholder="Patrulla 01"
                       class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-blue-500">
                @error('name') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs text-slate-400 block mb-1">IMEI *</label>
                <input wire:model="imei" type="text" placeholder="864071234567890"
                       class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-blue-500">
                @error('imei') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Placa</label>
                <input wire:model="plate" type="text" placeholder="ABC-123"
                       class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Tipo de unidad</label>
                <select wire:model="unit_type"
                        class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-blue-500">
                    <option value="patrol">Patrulla</option>
                    <option value="covert">Encubierto</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Número SIM</label>
                <input wire:model="sim_number" type="text" placeholder="+52 612 123 4567"
                       class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label class="text-xs text-slate-400 block mb-1">Color en mapa</label>
                <div class="flex items-center gap-2">
                    <input wire:model="color" type="color" class="h-9 w-16 rounded border border-slate-600 bg-slate-900 cursor-pointer">
                    <span class="text-slate-400 text-xs">{{ $color }}</span>
                </div>
            </div>

            {{-- Selector de ícono --}}
            <div class="col-span-2">
                <label class="text-xs text-slate-400 block mb-2">Ícono en mapa</label>
                @php
                $icons = [
                    'fa-car'            => 'Automóvil',
                    'fa-truck'          => 'Camioneta',
                    'fa-motorcycle'     => 'Motocicleta',
                    'fa-bus'            => 'Autobús/Van',
                    'fa-helicopter'     => 'Helicóptero',
                    'fa-ship'           => 'Embarcación',
                    'fa-person-walking' => 'A pie',
                    'fa-bicycle'        => 'Bicicleta',
                ];
                @endphp
                <div class="grid grid-cols-4 sm:grid-cols-8 gap-2">
                    @foreach($icons as $faClass => $label)
                    <button type="button"
                            wire:click="$set('icon', '{{ $faClass }}')"
                            title="{{ $label }}"
                            class="flex flex-col items-center gap-1.5 p-3 rounded-lg border transition-all duration-150 cursor-pointer"
                            style="{{ $icon === $faClass
                                ? 'background-color:#1d4ed820;border-color:#3b82f6;'
                                : 'background-color:#0f172a;border-color:#334155;' }}">
                        <i class="fas {{ $faClass }} text-base"
                           style="color:{{ $icon === $faClass ? '#3b82f6' : '#64748b' }};"></i>
                        <span class="text-xs leading-tight text-center"
                              style="color:{{ $icon === $faClass ? '#93c5fd' : '#475569' }};">
                            {{ $label }}
                        </span>
                    </button>
                    @endforeach
                </div>
                <input type="hidden" wire:model="icon">
                @error('icon') <p class="text-red-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="col-span-2">
                <label class="text-xs text-slate-400 block mb-1">Notas</label>
                <textarea wire:model="notes" rows="2" placeholder="Observaciones opcionales..."
                          class="w-full px-3 py-2 rounded-lg text-sm text-white border border-slate-600 bg-slate-900 focus:outline-none focus:border-blue-500"></textarea>
            </div>
        </div>

        {{-- Preview del marcador --}}
        <div class="mt-4 flex items-center gap-4">
            <div>
                <p class="text-xs text-slate-500 mb-1">Preview del marcador:</p>
                <div class="w-10 h-10 rounded-full flex items-center justify-center border-2"
                     style="background-color:#1e293b; border-color:{{ $color }}; box-shadow: 0 0 8px {{ $color }}60;">
                    <i class="fas {{ $icon ?: 'fa-car' }}" style="color:{{ $color }};font-size:16px;"></i>
                </div>
            </div>
            <div class="flex gap-3">
                <button wire:click="save"
                        class="px-5 py-2 rounded-lg text-sm font-medium text-white"
                        style="background-color:#3b82f6;">
                    <i class="fas fa-save mr-1"></i> Guardar
                </button>
                <button wire:click="cancel"
                        class="px-5 py-2 rounded-lg text-sm font-medium text-slate-400 hover:text-white border border-slate-600">
                    Cancelar
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Tabla --}}
    <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
        @if($units->isEmpty())
        <div class="p-12 text-center">
            <i class="fas fa-car text-5xl text-slate-600 mb-4 block"></i>
            <p class="text-white font-semibold mb-2">Sin unidades registradas</p>
            <p class="text-slate-400 text-sm mb-4">Agrega unidades GPS para comenzar el rastreo.</p>
        </div>
        @else
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-700">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Unidad</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">IMEI</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Estado</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Último reporte</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700">
                @foreach($units as $unit)
                @php
                    $statusColors = ['online'=>'#10b981','idle'=>'#f59e0b','offline'=>'#6b7280'];
                    $statusLabels = ['online'=>'En línea','idle'=>'Inactivo','offline'=>'Offline'];
                    $st = $unit->status;
                @endphp
                <tr class="hover:bg-slate-800 transition-colors">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            {{-- Marcador preview --}}
                            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 border-2"
                                 style="background-color:#0f172a; border-color:{{ $statusColors[$st] ?? '#6b7280' }}; box-shadow: 0 0 6px {{ $statusColors[$st] ?? '#6b7280' }}40;">
                                <i class="fas {{ $unit->icon ?: 'fa-car' }} text-xs"
                                   style="color:{{ $unit->color }};"></i>
                            </div>
                            <div>
                                <p class="text-white font-medium">{{ $unit->name }}</p>
                                @if($unit->plate)<p class="text-slate-500 text-xs">{{ $unit->plate }}</p>@endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-slate-300">{{ $unit->imei }}</td>
                    <td class="px-4 py-3">
                        <span class="text-xs px-2 py-0.5 rounded {{ $unit->unit_type === 'patrol' ? 'bg-blue-900 text-blue-300' : 'bg-slate-700 text-slate-300' }}">
                            {{ $unit->unit_type === 'patrol' ? 'Patrulla' : 'Encubierto' }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($unit->is_active)
                        <span class="flex items-center gap-1.5 text-xs" style="color:{{ $statusColors[$st] }};">
                            <i class="fas fa-circle text-xs"></i> {{ $statusLabels[$st] }}
                        </span>
                        @else
                        <span class="text-xs text-slate-600">Desactivada</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-400">
                        {{ $unit->last_seen_at?->locale('es')->diffForHumans() ?? 'Nunca' }}
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <button wire:click="openEdit({{ $unit->id }})"
                                    class="text-slate-400 hover:text-blue-400 transition-colors text-xs px-2 py-1 rounded border border-slate-600 hover:border-blue-500">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button wire:click="toggleActive({{ $unit->id }})"
                                    class="text-xs px-2 py-1 rounded border transition-colors
                                           {{ $unit->is_active ? 'border-red-800 text-red-400 hover:bg-red-900' : 'border-green-800 text-green-400 hover:bg-green-900' }}">
                                {{ $unit->is_active ? 'Desactivar' : 'Activar' }}
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
