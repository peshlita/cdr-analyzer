@section('title', 'Importar CSV')
@section('page-title', 'Importar Registros CDR')
@section('page-subtitle', 'Carga una o varias sábanas CSV para análisis cruzado')

<div class="max-w-2xl mx-auto space-y-6">

    <!-- Sábanas cargadas -->
    @if($loadedFiles->count() > 0)
    <div class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
                <i class="fas fa-layer-group text-blue-400"></i>
                <h3 class="text-white font-semibold text-sm">
                    Sábanas cargadas ({{ $loadedFiles->count() }})
                </h3>
            </div>
            <button wire:click="clearAll"
                    wire:confirm="¿Eliminar TODOS los registros y sábanas?"
                    class="text-xs text-red-400 hover:text-red-300 transition-colors px-2 py-1 rounded hover:bg-red-900/20">
                <i class="fas fa-trash mr-1"></i> Limpiar todo
            </button>
        </div>

        <div class="space-y-2">
            @foreach($loadedFiles as $file)
            <div class="flex items-center gap-3 rounded-lg px-3 py-2.5" style="background-color:#0f172a; border:1px solid #334155;">
                <i class="fas fa-file-csv text-green-400 text-sm shrink-0"></i>
                <div class="flex-1 min-w-0">
                    <p class="text-white text-sm font-medium truncate">{{ $file->source_file }}</p>
                    <p class="text-slate-500 text-xs">{{ number_format($file->total) }} registros</p>
                </div>
                <button wire:click="removeFile('{{ $file->source_file }}')"
                        wire:confirm="¿Eliminar la sábana {{ $file->source_file }}?"
                        class="text-slate-500 hover:text-red-400 transition-colors px-2 py-1 rounded text-xs">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            @endforeach
        </div>

        @if($loadedFiles->count() >= 2)
        <div class="mt-3 flex items-center gap-2 rounded-lg px-3 py-2 text-xs"
             style="background-color:#1e3a5f20; border:1px solid #3b82f640;">
            <i class="fas fa-link text-blue-400"></i>
            <span class="text-blue-300">
                Los números que aparezcan en más de una sábana se marcarán como
                <strong>cruces</strong> en la red de llamadas.
            </span>
        </div>
        @endif
    </div>
    @endif

    <!-- Upload card -->
    <div class="rounded-xl border border-slate-700 p-8" style="background-color:#1e293b;">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background-color:#3b82f620;">
                <i class="fas fa-file-csv text-blue-400"></i>
            </div>
            <div>
                <h2 class="text-white font-semibold">
                    {{ $loadedFiles->count() > 0 ? 'Agregar otra sábana CSV' : 'Cargar sábana CSV' }}
                </h2>
                <p class="text-xs text-slate-400">El análisis inicia automáticamente al seleccionar el archivo</p>
            </div>
        </div>

        <div class="space-y-5">

            <!-- Drop zone -->
            <div x-data="{ dragging: false }"
                 @dragover.prevent="dragging = true"
                 @dragleave.prevent="dragging = false"
                 @drop.prevent="
                    dragging = false;
                    const file = $event.dataTransfer.files[0];
                    if (file) {
                        const input = document.getElementById('csvInput');
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        input.files = dt.files;
                        input.dispatchEvent(new Event('change'));
                    }
                 "
                 :class="dragging ? 'border-blue-400 bg-blue-900/10' : 'border-slate-600'"
                 class="border-2 border-dashed rounded-xl p-10 text-center transition-colors duration-200 cursor-pointer"
                 onclick="document.getElementById('csvInput').click()">

                <input id="csvInput" type="file" wire:model="csvFile" accept=".csv,.txt" class="hidden">

                @if($status === 'uploading')
                    <i class="fas fa-spinner fa-spin text-4xl text-blue-400 mb-3 block"></i>
                    <p class="text-blue-300 font-medium">Procesando {{ $currentFile }}...</p>
                    <p class="text-xs text-slate-500 mt-1">No cierres esta ventana</p>
                @elseif($status === 'done')
                    <i class="fas fa-check-circle text-4xl text-green-400 mb-3 block"></i>
                    <p class="text-green-400 font-medium">Importación completada</p>
                    <p class="text-xs text-slate-500 mt-1">Haz click para cargar otra sábana</p>
                @elseif($status === 'error')
                    <i class="fas fa-exclamation-circle text-4xl text-red-400 mb-3 block"></i>
                    <p class="text-red-400 font-medium">Error al procesar</p>
                    <p class="text-xs text-slate-500 mt-1">Haz click para intentar de nuevo</p>
                @else
                    <i class="fas fa-cloud-upload-alt text-4xl text-slate-500 mb-3 block"></i>
                    <p class="text-slate-300 font-medium">Arrastra tu CSV aquí o haz click para seleccionar</p>
                    <p class="text-xs text-slate-500 mt-1">Archivos .csv o .txt — máx. 50 MB</p>
                @endif

                <div wire:loading wire:target="csvFile" class="mt-3">
                    <i class="fas fa-spinner fa-spin text-blue-400"></i>
                    <span class="text-xs text-slate-400 ml-1">Cargando archivo...</span>
                </div>
            </div>

            @error('csvFile')
                <p class="text-red-400 text-sm flex items-center gap-1">
                    <i class="fas fa-exclamation-circle"></i> {{ $message }}
                </p>
            @enderror

            <!-- Formatos soportados -->
            <div class="rounded-lg p-4 text-xs space-y-3" style="background-color:#0f172a; border:1px solid #334155;">
                <p class="text-slate-300 font-semibold">
                    <i class="fas fa-info-circle mr-1 text-blue-400"></i>Formatos CSV soportados (detección automática):
                </p>

                {{-- Formato A --}}
                <div>
                    <p class="text-blue-400 font-medium mb-1">
                        <i class="fas fa-file-alt mr-1"></i>Formato A — Con metadata (operador)
                    </p>
                    <p class="text-slate-500">Líneas 1–4: metadata del operador (se omiten automáticamente)</p>
                    <p class="text-slate-500 mt-0.5 break-all font-mono">
                        Línea 5 (cabeceras): Number_A, Lat_A, Lon_A, Azimuth_A, IMEI_A, IMSI_A, Number_B, Lat_B, Lon_B, Azimuth_B, IMEI_B, IMSI_B, Type, Direction, Duration, Date, Hour
                    </p>
                </div>

                <div class="border-t border-slate-800"></div>

                {{-- Formato B --}}
                <div>
                    <p class="text-green-400 font-medium mb-1">
                        <i class="fas fa-file-alt mr-1"></i>Formato B — Cabeceras en fila 1
                    </p>
                    <p class="text-slate-500">Línea 1: cabeceras (en inglés o español, detección automática)</p>
                    <p class="text-slate-500 mt-0.5">Línea 2 en adelante: datos</p>
                    <p class="text-slate-500 mt-0.5 break-all font-mono">
                        Ej: Number_A / Numero_A / Telefono · Lat_A / Latitud · Date / Fecha · Hour / Hora · Type / Tipo · Direction / Direccion · Duration / Durac. Seg.…
                    </p>
                    <p class="text-slate-600 mt-1">Los alias de columnas se normalizan automáticamente.</p>
                </div>
            </div>

        </div>
    </div>

    <!-- Estado / Resultado -->
    @if($status !== 'idle')
    <div class="rounded-xl border p-6 {{ $status === 'done' ? 'border-green-700' : ($status === 'error' ? 'border-red-700' : 'border-blue-700') }}"
         style="background-color:#1e293b;">
        <div class="flex items-start gap-3">
            @if($status === 'done')
                <i class="fas fa-check-circle text-green-400 text-xl mt-0.5"></i>
            @elseif($status === 'error')
                <i class="fas fa-times-circle text-red-400 text-xl mt-0.5"></i>
            @else
                <i class="fas fa-spinner fa-spin text-blue-400 text-xl mt-0.5"></i>
            @endif
            <div class="flex-1">
                <p class="text-white font-medium text-sm">{{ $message }}</p>

                @if($status === 'done')
                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div class="rounded-lg p-3 text-center" style="background-color:#0f172a;">
                        <p class="text-2xl font-bold text-green-400">{{ number_format($imported) }}</p>
                        <p class="text-xs text-slate-400 mt-1">Registros importados</p>
                    </div>
                    <div class="rounded-lg p-3 text-center" style="background-color:#0f172a;">
                        <p class="text-2xl font-bold text-yellow-400">{{ number_format($skipped) }}</p>
                        <p class="text-xs text-slate-400 mt-1">Registros omitidos</p>
                    </div>
                </div>
                <div class="mt-4 flex gap-3">
                    <a href="/" class="flex-1 text-center text-sm py-2 rounded-lg font-medium transition-all hover:opacity-90"
                       style="background-color:#10b981; color:white;">
                        <i class="fas fa-chart-pie mr-1"></i> Ver Dashboard
                    </a>
                    <a href="/network" class="flex-1 text-center text-sm py-2 rounded-lg font-medium transition-all hover:opacity-90"
                       style="background-color:#3b82f6; color:white;">
                        <i class="fas fa-project-diagram mr-1"></i> Ver Red
                    </a>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endif

</div>
