@section('title', 'Importar CSV')
@section('page-title', 'Importar Registros CDR')
@section('page-subtitle', 'Carga sábanas CDR (Telcel/Itelcel y compatibles) para análisis cruzado')

@php
$reqFields = ['phone_main','record_type','number_a','number_b','date','lat','lon'];
@endphp

<div class="max-w-3xl mx-auto space-y-6">

    {{-- Stepper --}}
    <div class="flex items-center justify-center gap-2 text-xs">
        @foreach(['Subir','Revisar','Importar','Listo'] as $i => $label)
        @php $n = $i + 1; @endphp
        <div class="flex items-center gap-2">
            <span class="w-6 h-6 rounded-full flex items-center justify-center font-bold
                {{ $step >= $n ? 'text-white' : 'text-slate-500' }}"
                style="background-color:{{ $step >= $n ? '#3b82f6' : '#334155' }};">{{ $n }}</span>
            <span class="{{ $step >= $n ? 'text-white' : 'text-slate-500' }}">{{ $label }}</span>
            @if($n < 4)<span class="text-slate-600 mx-1">—</span>@endif
        </div>
        @endforeach
    </div>

    @if($importError)
    <div class="px-4 py-3 rounded-lg text-sm text-red-300" style="background-color:#450a0a; border:1px solid #7f1d1d;">
        <i class="fas fa-exclamation-circle mr-1"></i> {{ $importError }}
    </div>
    @endif

    {{-- ══ PASO 1 — SUBIR ══ --}}
    @if($step === 1)
    <div class="rounded-xl border border-slate-700 p-6" style="background-color:#1e293b;">
        <div class="mb-4">
            <label class="block text-sm text-slate-300 mb-1">Nombre de esta sábana</label>
            <input type="text" wire:model="batchName" placeholder="ej. Objetivo A — Telcel mayo 2026"
                class="w-full px-3 py-2 rounded-lg text-sm text-white" style="background-color:#0f172a; border:1px solid #334155;">
        </div>

        <label for="cdr-file"
            class="block border-2 border-dashed rounded-xl p-10 text-center cursor-pointer transition-colors hover:border-blue-500"
            style="border-color:#334155; background-color:#0f172a;">
            <div wire:loading.remove wire:target="file">
                <i class="fas fa-file-csv text-4xl text-blue-400 mb-3 block"></i>
                <p class="text-white font-medium">Arrastra el archivo aquí o haz clic para seleccionar</p>
                <p class="text-slate-500 text-xs mt-2">Formatos: .csv, .txt — Telcel/Itelcel, AT&amp;T, Movistar · máx. 50 MB</p>
            </div>
            <div wire:loading wire:target="file" class="text-blue-400">
                <i class="fas fa-spinner fa-spin text-3xl mb-2 block"></i>
                Procesando archivo...
            </div>
            <input id="cdr-file" type="file" wire:model="file" accept=".csv,.txt" class="hidden">
        </label>
        @error('file') <p class="text-red-400 text-xs mt-2">{{ $message }}</p> @enderror
    </div>
    @endif

    {{-- ══ PASO 2 — PREVIEW ══ --}}
    @if($step === 2)
    <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
        <div class="px-5 py-4" style="border-bottom:1px solid #334155;">
            <div class="flex items-center gap-2 text-green-400 font-semibold">
                <i class="fas fa-check-circle"></i> Archivo procesado
            </div>
            <p class="text-slate-400 text-xs mt-1">
                Encoding: <span class="text-slate-200">{{ $encoding }}</span> ·
                Total de registros: <span class="text-slate-200 font-semibold">{{ number_format($totalRows) }}</span>
            </p>
        </div>

        <div class="p-5 space-y-5">
            {{-- Número principal --}}
            @if(($mainDetection['source'] ?? '') === 'frequency' && !empty($mainDetection['number']))
            <div class="rounded-lg p-3 flex items-start gap-2" style="background:#1e1b4b;border:1px solid #4338ca;">
                <i class="fas fa-wand-magic-sparkles text-indigo-300 mt-0.5"></i>
                <div class="text-xs">
                    <span class="text-indigo-200 font-semibold">Número principal detectado automáticamente:</span>
                    <span class="text-white font-mono">{{ $mainDetection['number'] }}</span>
                    <span class="text-slate-400">(aparece en {{ number_format($mainDetection['appearances']) }} de {{ number_format($mainDetection['total']) }} registros)</span>
                </div>
            </div>
            @elseif(($mainDetection['source'] ?? '') === 'header' && !empty($mainDetection['number']))
            <div class="rounded-lg p-3 flex items-start gap-2" style="background:#0f172a;border:1px solid #334155;">
                <i class="fas fa-check-circle text-green-400 mt-0.5"></i>
                <div class="text-xs">
                    <span class="text-slate-300 font-semibold">Número principal (por columna):</span>
                    <span class="text-white font-mono">{{ $mainDetection['number'] }}</span>
                </div>
            </div>
            @endif

            {{-- Cabeceras detectadas --}}
            <div>
                <h4 class="text-white text-sm font-semibold mb-2">Cabeceras detectadas</h4>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    @foreach($reqFields as $f)
                    <div class="flex items-center gap-2">
                        @if(isset($detectedFields[$f]))
                            <i class="fas fa-check text-green-400"></i>
                            <span class="text-slate-300">{{ $fieldLabels[$f] ?? $f }}</span>
                            <span class="text-slate-500">→ {{ $detectedFields[$f] }}</span>
                        @else
                            <i class="fas fa-times text-slate-600"></i>
                            <span class="text-slate-500">{{ $fieldLabels[$f] ?? $f }} (no detectada)</span>
                        @endif
                    </div>
                    @endforeach
                </div>
                @if(($summary['without_location'] ?? 0) > 0)
                <p class="text-amber-400 text-xs mt-2">
                    <i class="fas fa-exclamation-triangle"></i>
                    {{ number_format($summary['without_location']) }} registros sin coordenadas (se importan igualmente)
                </p>
                @endif
                @foreach($warnings as $w)
                <p class="text-amber-400 text-xs mt-1"><i class="fas fa-exclamation-triangle"></i> {{ $w }}</p>
                @endforeach
            </div>

            {{-- Distribución por tipo --}}
            <div>
                <h4 class="text-white text-sm font-semibold mb-2">Distribución por tipo</h4>
                <div class="flex gap-2 text-xs">
                    <span class="px-3 py-1 rounded-full" style="background:#1e3a5f;color:#93c5fd;">DATOS: {{ number_format($summary['data'] ?? 0) }}</span>
                    <span class="px-3 py-1 rounded-full" style="background:#052e16;color:#6ee7b7;">VOZ: {{ number_format($summary['voice'] ?? 0) }}</span>
                    <span class="px-3 py-1 rounded-full" style="background:#3b1d5f;color:#d8b4fe;">MSG: {{ number_format($summary['sms'] ?? 0) }}</span>
                    @if(($summary['other'] ?? 0) > 0)
                    <span class="px-3 py-1 rounded-full" style="background:#334155;color:#cbd5e1;">OTRO: {{ number_format($summary['other']) }}</span>
                    @endif
                </div>
            </div>

            {{-- Preview --}}
            <div>
                <h4 class="text-white text-sm font-semibold mb-2">Preview (primeros registros)</h4>
                <div class="overflow-x-auto rounded-lg" style="border:1px solid #334155;">
                    <table class="w-full text-xs">
                        <thead>
                            <tr style="background:#0f172a;">
                                <th class="px-2 py-2 text-left text-slate-400">Tipo</th>
                                <th class="px-2 py-2 text-left text-slate-400">Núm A</th>
                                <th class="px-2 py-2 text-left text-slate-400">Núm B</th>
                                <th class="px-2 py-2 text-left text-slate-400">Contacto</th>
                                <th class="px-2 py-2 text-left text-slate-400">Fecha/Hora</th>
                                <th class="px-2 py-2 text-left text-slate-400">Coordenadas</th>
                                <th class="px-2 py-2 text-left text-slate-400">IMEI</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($previewRows as $row)
                            <tr style="border-top:1px solid #334155;">
                                <td class="px-2 py-1.5 text-slate-300">{{ $row['record_type'] }}</td>
                                <td class="px-2 py-1.5 font-mono text-slate-300">{{ $row['number_a'] ?? '—' }}</td>
                                <td class="px-2 py-1.5 font-mono text-slate-300">{{ $row['number_b'] ?? '—' }}</td>
                                <td class="px-2 py-1.5 font-mono text-slate-400">{{ $row['contact_number'] ?? '—' }}</td>
                                <td class="px-2 py-1.5 text-slate-400">{{ $row['call_datetime'] ?? '—' }}</td>
                                <td class="px-2 py-1.5 font-mono text-slate-400">
                                    @if($row['lat'] !== null) {{ number_format($row['lat'],5) }}, {{ number_format($row['lon'],5) }}
                                    @else <span class="text-slate-600">sin ubicación</span> @endif
                                </td>
                                <td class="px-2 py-1.5 font-mono text-slate-500">{{ $row['imei'] ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="px-5 py-4 flex justify-end gap-3" style="border-top:1px solid #334155;">
            <button wire:click="backToUpload" class="px-4 py-2 rounded-lg text-sm text-slate-300" style="background-color:#334155;">Cancelar</button>
            <button wire:click="import" class="px-5 py-2 rounded-lg text-sm font-medium text-white" style="background-color:#10b981;">
                <i class="fas fa-database mr-1"></i> Confirmar importación
            </button>
        </div>
    </div>
    @endif

    {{-- ══ PASO 3 — IMPORTANDO ══ --}}
    @if($step === 3)
    <div class="rounded-xl border border-slate-700 p-12 text-center" style="background-color:#1e293b;">
        <i class="fas fa-spinner fa-spin text-4xl text-blue-400 mb-4 block"></i>
        <p class="text-white font-medium">Importando {{ number_format($totalRows) }} registros...</p>
        <p class="text-slate-500 text-xs mt-2">Insertando en bloques y analizando cruces. No cierres esta ventana.</p>
    </div>
    @endif

    {{-- ══ PASO 4 — RESULTADO ══ --}}
    @if($step === 4)
    <div class="rounded-xl border border-slate-700 p-6" style="background-color:#1e293b;">
        <div class="text-center mb-5">
            <i class="fas fa-check-circle text-5xl text-green-400 mb-3 block"></i>
            <h3 class="text-white font-semibold text-lg">Importación completada</h3>
        </div>

        <div class="grid grid-cols-2 gap-3 mb-5">
            <div class="rounded-lg p-4 text-center" style="background:#052e16;border:1px solid #14532d;">
                <p class="text-2xl font-bold text-green-400">{{ number_format($importedCount) }}</p>
                <p class="text-xs text-slate-400">registros importados</p>
            </div>
            <div class="rounded-lg p-4 text-center" style="background:#0f172a;border:1px solid #334155;">
                <p class="text-2xl font-bold text-slate-300">{{ number_format($skippedCount) }}</p>
                <p class="text-xs text-slate-400">omitidos (sin número)</p>
            </div>
        </div>

        {{-- Cruces detectados --}}
        @if(!empty($crossResult['common_contacts']))
        @php $totalCommon = collect($crossResult['common_contacts'])->sum('count'); @endphp
        <div class="rounded-lg p-4 mb-3" style="background:#052e16;border:1px solid #14532d;">
            <p class="text-green-300 text-sm font-medium">
                <i class="fas fa-bolt"></i> Se detectaron {{ $totalCommon }} números en común con otras sábanas
            </p>
            <a href="{{ route('network') }}" class="text-green-400 text-xs underline">Ver análisis de red de contactos</a>
        </div>
        @endif

        @if(!empty($crossResult['direct_communication']))
        <div class="rounded-lg p-4 mb-3" style="background:#450a0a;border:1px solid #7f1d1d;">
            <p class="text-red-300 text-sm font-medium">
                <i class="fas fa-circle-exclamation"></i> Los números principales se comunicaron directamente
            </p>
            @foreach($crossResult['direct_communication'] as $dc)
            <p class="text-red-400 text-xs font-mono mt-1">{{ $dc['from_number'] }} → {{ $dc['to_number'] }}</p>
            @endforeach
        </div>
        @endif

        <div class="flex justify-center gap-3 mt-5">
            <a href="{{ route('comint.dashboard') }}" class="px-5 py-2 rounded-lg text-sm font-medium text-white" style="background-color:#3b82f6;">
                <i class="fas fa-chart-pie mr-1"></i> Ver análisis
            </a>
            <button wire:click="importAnother" class="px-5 py-2 rounded-lg text-sm text-slate-300" style="background-color:#334155;">
                <i class="fas fa-plus mr-1"></i> Importar otra sábana
            </button>
        </div>
    </div>
    @endif

    {{-- ══ SÁBANAS IMPORTADAS ══ --}}
    @if(in_array($step, [1, 4]) && $batches->count() > 0)
    <div class="rounded-xl border border-slate-700 overflow-hidden" style="background-color:#1e293b;">
        <div class="px-5 py-4 flex items-center justify-between" style="border-bottom:1px solid #334155;">
            <h3 class="text-white font-semibold text-sm">
                <i class="fas fa-layer-group text-blue-400 mr-1"></i> Sábanas importadas ({{ $batches->count() }})
            </h3>
            <button wire:click="clearAll" wire:confirm="¿Eliminar TODAS tus sábanas y registros? Esta acción no se puede deshacer."
                class="text-xs text-red-400 hover:text-red-300">Limpiar todo</button>
        </div>
        <div class="divide-y divide-slate-800">
            @foreach($batches as $b)
            <div class="px-5 py-3 flex items-center justify-between hover:bg-slate-800/40">
                <div>
                    <p class="text-white text-sm font-medium">{{ $b->name }}</p>
                    <p class="text-slate-500 text-xs">
                        {{ number_format($b->total_records) }} reg. ·
                        VOZ {{ number_format($b->voice_count) }} / MSG {{ number_format($b->sms_count) }} / DATOS {{ number_format($b->data_count) }}
                        @if($b->phone_main) · <span class="font-mono">{{ $b->phone_main }}</span> @endif
                        @if($b->date_from) · {{ \Carbon\Carbon::parse($b->date_from)->format('d/m/y') }}–{{ \Carbon\Carbon::parse($b->date_to)->format('d/m/y') }} @endif
                    </p>
                </div>
                <button wire:click="deleteBatch({{ $b->id }})" wire:confirm="¿Eliminar la sábana '{{ $b->name }}' y sus registros?"
                    class="text-slate-500 hover:text-red-400 text-sm" title="Eliminar">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
