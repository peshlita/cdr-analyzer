@section('title', 'COMINT — Dashboard')
@section('page-title', 'Dashboard COMINT')
@section('page-subtitle', 'Análisis de registros CDR')

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
@endpush

<div class="space-y-6">

    @if($stats['total'] === 0)
    <div class="rounded-xl border border-slate-700 p-12 text-center" style="background-color:#1e293b;">
        <i class="fas fa-file-csv text-5xl text-slate-600 mb-4 block"></i>
        <h2 class="text-white font-semibold text-lg mb-2">Sin datos cargados</h2>
        <p class="text-slate-400 text-sm mb-5">Importa un archivo CSV para comenzar el análisis forense.</p>
        <a href="/upload" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-white text-sm font-medium"
           style="background-color:#3b82f6;">
            <i class="fas fa-upload"></i> Importar CSV
        </a>
    </div>
    @else

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @php
        $cards = [
            ['icon'=>'fas fa-database',    'color'=>'#3b82f6', 'raw'=>$stats['total'],    'value'=>number_format($stats['total']),    'label'=>'Total Registros'],
            ['icon'=>'fas fa-phone',        'color'=>'#10b981', 'raw'=>$stats['voice'],    'value'=>number_format($stats['voice']),    'label'=>'Llamadas Voz'],
            ['icon'=>'fas fa-wifi',         'color'=>'#8b5cf6', 'raw'=>$stats['data'],     'value'=>number_format($stats['data']),     'label'=>'Sesiones Datos'],
            ['icon'=>'fas fa-address-book', 'color'=>'#f59e0b', 'raw'=>$stats['contacts'], 'value'=>number_format($stats['contacts']), 'label'=>'Contactos Únicos'],
            ['icon'=>'fas fa-clock',        'color'=>'#ef4444', 'raw'=>$stats['hours'],    'value'=>$stats['hours'].'h',               'label'=>'Duración Total'],
        ];
        @endphp
        @foreach($cards as $card)
        @if($card['raw'] > 0)
        <div class="rounded-xl border border-slate-700 p-4" style="background-color:#1e293b;">
            <div class="flex items-start justify-between mb-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background-color:{{ $card['color'] }}20;">
                    <i class="{{ $card['icon'] }} text-sm" style="color:{{ $card['color'] }};"></i>
                </div>
            </div>
            <p class="text-2xl font-bold text-white">{{ $card['value'] }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ $card['label'] }}</p>
        </div>
        @endif
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
            <h3 class="text-white font-semibold text-sm mb-4">Llamadas por Día</h3>
            <div id="chartCallsByDay"></div>
        </div>
        <div class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
            <h3 class="text-white font-semibold text-sm mb-4">Top 10 Números Contactados</h3>
            <div id="chartTopNumbers"></div>
        </div>
    </div>

    <div class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
        <h3 class="text-white font-semibold text-sm mb-4">Actividad por Hora del Día</h3>
        <div id="chartHourly"></div>
    </div>

    @endif

    {{-- ═══════════════ Análisis de Frecuencia y Cruces ═══════════════ --}}
    @if($batches->count() > 0)

    <!-- Selector Top N -->
    <div class="flex items-center gap-2">
        <label class="text-slate-400 text-sm">Mostrar top</label>
        <select wire:model.live="topContactsLimit"
            class="rounded-lg px-2 py-1 text-sm text-white focus:outline-none" style="background-color:#1e293b; border:1px solid #334155;">
            <option value="5">5</option>
            <option value="10">10</option>
            <option value="20">20</option>
            <option value="50">50</option>
        </select>
        <span class="text-slate-400 text-sm">contactos</span>
    </div>

    @if($batches->count() === 1)
    {{-- ── 1 sábana: ranking de contactos + patrón temporal ── --}}
    <div class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
        <h3 class="text-white font-semibold text-sm mb-4">
            <i class="fas fa-users text-blue-400 mr-1"></i> Contactos más frecuentes
        </h3>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-slate-400 text-left" style="border-bottom:1px solid #334155;">
                    <th class="py-2 pr-2">#</th>
                    <th class="py-2 pr-2">Número</th>
                    <th class="py-2 text-center">Total</th>
                    <th class="py-2 text-center">Entrantes</th>
                    <th class="py-2 text-center">Salientes</th>
                    <th class="py-2 text-center">Duración</th>
                    <th class="py-2 text-center">Periodo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topContacts as $i => $c)
                <tr style="border-bottom:1px solid #334155;">
                    <td class="py-2 text-slate-500">{{ $i+1 }}</td>
                    <td class="py-2 text-white font-mono">{{ $c['number'] }}</td>
                    <td class="py-2 text-center text-blue-400 font-bold">{{ $c['total_events'] }}</td>
                    <td class="py-2 text-center text-green-400">{{ $c['incoming'] }}</td>
                    <td class="py-2 text-center text-yellow-400">{{ $c['outgoing'] }}</td>
                    <td class="py-2 text-center text-slate-300">{{ gmdate('H:i:s', $c['total_duration']) }}</td>
                    <td class="py-2 text-center text-slate-400 text-xs">{{ $c['days_active'] }} días</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>

    <!-- Patrón temporal de los top 3 -->
    @foreach($temporalPatterns as $tp)
    <div class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
        <h4 class="text-white font-medium text-sm mb-3">
            <i class="fas fa-clock text-purple-400 mr-1"></i> Patrón de actividad: <span class="font-mono">{{ $tp['number'] }}</span>
        </h4>
        <div class="grid grid-cols-3 gap-3 mb-3 text-sm">
            <div class="rounded-lg p-2" style="background-color:#0f172a;">
                <div class="text-slate-400 text-xs">Hora más activa</div>
                <div class="text-white font-bold">
                    {{ $tp['pattern']['most_active_hour'] !== null ? $tp['pattern']['most_active_hour'].':00 hrs' : '—' }}
                </div>
            </div>
            <div class="rounded-lg p-2" style="background-color:#0f172a;">
                <div class="text-slate-400 text-xs">Intervalo promedio</div>
                <div class="text-white font-bold">{{ $tp['pattern']['avg_gap_hours'] ?? '—' }} hrs</div>
            </div>
            <div class="rounded-lg p-2" style="background-color:#0f172a;">
                <div class="text-slate-400 text-xs">Días activos</div>
                <div class="text-white font-bold">{{ $tp['days_active'] }}</div>
            </div>
        </div>

        <!-- Gráfica de barras por hora del día (0-23) -->
        <div class="flex items-end gap-1 h-20">
            @for($h = 0; $h < 24; $h++)
            @php
                $hourKey = sprintf('%02d', $h);
                $count = $tp['pattern']['by_hour'][$hourKey] ?? 0;
                $max = $tp['pattern']['by_hour']->max() ?: 1;
                $height = $max > 0 ? ($count / $max * 100) : 0;
            @endphp
            <div class="flex-1 flex flex-col items-center">
                <div class="w-full rounded-t" style="height: {{ $height }}%; min-height: 2px; background-color:#a855f7;"
                     title="{{ $h }}:00 - {{ $count }} eventos"></div>
                @if($h % 4 === 0)
                <span class="text-xs text-slate-500 mt-1">{{ $h }}</span>
                @endif
            </div>
            @endfor
        </div>
    </div>
    @endforeach

    @elseif($batches->count() >= 2)
    {{-- ── 2+ sábanas: ranking por sábana + cruces ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        @foreach($topContacts as $batchData)
        <div class="rounded-xl border border-slate-700 p-4" style="background-color:#1e293b;">
            <h4 class="text-white font-medium text-sm mb-2">
                <i class="fas fa-sim-card text-blue-400 mr-1"></i> {{ $batchData['batch']->phone_main ?? $batchData['batch']->name }}
                <span class="text-slate-500 text-xs ml-2">({{ number_format($batchData['batch']->total_records) }} registros)</span>
            </h4>
            <table class="w-full text-xs">
                @foreach(array_slice($batchData['contacts'], 0, 5) as $i => $c)
                <tr style="border-bottom:1px solid rgba(51,65,85,0.5);">
                    <td class="py-1 text-slate-500">{{ $i+1 }}</td>
                    <td class="py-1 text-white font-mono">{{ $c['number'] }}</td>
                    <td class="py-1 text-right text-blue-400">{{ $c['total_events'] }} eventos</td>
                </tr>
                @endforeach
            </table>
        </div>
        @endforeach
    </div>

    <!-- Sección de CRUCES -->
    @if(count($crossAnalysis) > 0)
    <div class="rounded-xl p-5" style="background-color:#1e293b; border:1px solid #334155; border-left:4px solid #f59e0b;">
        <h3 class="text-white font-semibold text-sm mb-4">
            <i class="fas fa-link text-amber-400 mr-1"></i> Cruces detectados entre sábanas ({{ count($crossAnalysis) }})
        </h3>

        @foreach($crossAnalysis as $cross)
        <div class="rounded-lg p-3 mb-3" style="background-color:#0f172a;">
            <div class="flex justify-between items-start mb-2">
                <div>
                    <span class="text-white font-mono font-bold">{{ $cross['contact_number'] }}</span>
                    <span class="text-slate-400 text-xs ml-2">contacto en común</span>
                </div>
                @php
                    $score = $cross['relevance_score'];
                    $badgeColor = $score >= 130 ? '#ef4444' : ($score >= 80 ? '#f59e0b' : '#475569');
                    $badgeLabel = $score >= 130 ? 'Alta relevancia' : ($score >= 80 ? 'Media relevancia' : 'Baja relevancia');
                @endphp
                <span class="text-white text-xs px-2 py-1 rounded" style="background-color: {{ $badgeColor }};">{{ $badgeLabel }}</span>
            </div>

            <div class="grid grid-cols-2 gap-3 text-sm mb-2">
                <div>
                    <span class="text-slate-400">Con {{ $cross['main_a'] }}:</span>
                    <span class="text-blue-400 font-bold ml-1">{{ $cross['events_with_a'] }} eventos</span>
                    <div class="text-xs text-slate-500">
                        {{ $cross['date_range_a']['from'] ? \Carbon\Carbon::parse($cross['date_range_a']['from'])->format('d/m/Y') : '—' }}
                        —
                        {{ $cross['date_range_a']['to'] ? \Carbon\Carbon::parse($cross['date_range_a']['to'])->format('d/m/Y') : '—' }}
                    </div>
                </div>
                <div>
                    <span class="text-slate-400">Con {{ $cross['main_b'] }}:</span>
                    <span class="text-green-400 font-bold ml-1">{{ $cross['events_with_b'] }} eventos</span>
                    <div class="text-xs text-slate-500">
                        {{ $cross['date_range_b']['from'] ? \Carbon\Carbon::parse($cross['date_range_b']['from'])->format('d/m/Y') : '—' }}
                        —
                        {{ $cross['date_range_b']['to'] ? \Carbon\Carbon::parse($cross['date_range_b']['to'])->format('d/m/Y') : '—' }}
                    </div>
                </div>
            </div>

            @if($cross['closest_pair_hours'] !== null)
            <div class="rounded p-2 text-xs" style="background-color:#1e293b;">
                <i class="fas fa-exclamation-triangle text-amber-400"></i>
                Comunicación más cercana en el tiempo:
                <span class="text-white font-bold">{{ $cross['closest_pair_hours'] }} horas</span> de diferencia
                <div class="text-slate-400 mt-1">{{ $cross['closest_pair_dates']['date_a'] }} ↔ {{ $cross['closest_pair_dates']['date_b'] }}</div>
            </div>
            @endif
        </div>
        @endforeach
    </div>
    @else
    <div class="rounded-xl border border-slate-700 p-4 text-center text-slate-400" style="background-color:#1e293b;">
        <i class="fas fa-info-circle mr-1"></i> No se detectaron contactos en común entre las sábanas importadas.
    </div>
    @endif
    @endif

    @endif
</div>

@push('scripts')
@if($stats['total'] > 0)
<script>
(function() {
    const darkText = '#94a3b8';
    const gridColor = '#334155';
    const bgColor = '#1e293b';

    const baseOpts = {
        chart: { background: bgColor, toolbar: { show: false }, fontFamily: 'inherit' },
        theme: { mode: 'dark' },
        grid: { borderColor: gridColor, strokeDashArray: 3 },
        tooltip: { theme: 'dark' },
        xaxis: { labels: { style: { colors: darkText, fontSize: '11px' } }, axisBorder: { show: false } },
        yaxis: { labels: { style: { colors: darkText, fontSize: '11px' } } },
    };

    new ApexCharts(document.getElementById('chartCallsByDay'), {
        ...baseOpts,
        series: [{ name: 'Llamadas', data: @json($callsByDay['data']) }],
        chart: { ...baseOpts.chart, type: 'bar', height: 220 },
        colors: ['#3b82f6'],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
        xaxis: { ...baseOpts.xaxis, categories: @json($callsByDay['labels']) },
        dataLabels: { enabled: false },
    }).render();

    new ApexCharts(document.getElementById('chartTopNumbers'), {
        ...baseOpts,
        series: @json($topNumbers['data']),
        chart: { ...baseOpts.chart, type: 'donut', height: 220 },
        labels: @json($topNumbers['labels']),
        colors: ['#3b82f6','#10b981','#8b5cf6','#f59e0b','#ef4444','#06b6d4','#ec4899','#84cc16','#f97316','#a78bfa'],
        legend: { position: 'bottom', labels: { colors: darkText }, fontSize: '10px' },
        dataLabels: { enabled: false },
        plotOptions: { pie: { donut: { size: '65%' } } },
    }).render();

    new ApexCharts(document.getElementById('chartHourly'), {
        ...baseOpts,
        series: [{ name: 'Eventos', data: @json($hourlyActivity['data']) }],
        chart: { ...baseOpts.chart, type: 'area', height: 160 },
        colors: ['#10b981'],
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
        stroke: { curve: 'smooth', width: 2 },
        xaxis: { ...baseOpts.xaxis, categories: @json($hourlyActivity['labels']) },
        dataLabels: { enabled: false },
    }).render();
})();
</script>
@endif
@endpush
