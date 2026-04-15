@section('title', 'Dashboard')
@section('page-title', 'Dashboard Forense')
@section('page-subtitle', 'Resumen del análisis CDR')

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
@endpush

<div class="space-y-6">

    @if($stats['total'] === 0)
    <!-- Empty state -->
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

    <!-- Stat cards -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        @php
        $cards = [
            ['icon'=>'fas fa-database',     'color'=>'#3b82f6', 'value'=> number_format($stats['total']),    'label'=>'Total Registros'],
            ['icon'=>'fas fa-phone',         'color'=>'#10b981', 'value'=> number_format($stats['voice']),    'label'=>'Llamadas Voz'],
            ['icon'=>'fas fa-wifi',          'color'=>'#8b5cf6', 'value'=> number_format($stats['data']),     'label'=>'Sesiones Datos'],
            ['icon'=>'fas fa-address-book',  'color'=>'#f59e0b', 'value'=> number_format($stats['contacts']), 'label'=>'Contactos Únicos'],
            ['icon'=>'fas fa-clock',         'color'=>'#ef4444', 'value'=> $stats['hours'].'h',               'label'=>'Duración Total'],
        ];
        @endphp
        @foreach($cards as $card)
        <div class="rounded-xl border border-slate-700 p-4" style="background-color:#1e293b;">
            <div class="flex items-start justify-between mb-3">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background-color:{{ $card['color'] }}20;">
                    <i class="{{ $card['icon'] }} text-sm" style="color:{{ $card['color'] }};"></i>
                </div>
            </div>
            <p class="text-2xl font-bold text-white">{{ $card['value'] }}</p>
            <p class="text-xs text-slate-400 mt-1">{{ $card['label'] }}</p>
        </div>
        @endforeach
    </div>

    <!-- Charts row -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

        <!-- Calls by day -->
        <div class="lg:col-span-2 rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
            <h3 class="text-white font-semibold text-sm mb-4">Llamadas por Día</h3>
            <div id="chartCallsByDay"></div>
        </div>

        <!-- Top 10 numbers -->
        <div class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
            <h3 class="text-white font-semibold text-sm mb-4">Top 10 Números Contactados</h3>
            <div id="chartTopNumbers"></div>
        </div>
    </div>

    <!-- Hourly activity -->
    <div class="rounded-xl border border-slate-700 p-5" style="background-color:#1e293b;">
        <h3 class="text-white font-semibold text-sm mb-4">Actividad por Hora del Día</h3>
        <div id="chartHourly"></div>
    </div>

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

    // Calls by day
    new ApexCharts(document.getElementById('chartCallsByDay'), {
        ...baseOpts,
        series: [{ name: 'Llamadas', data: @json($callsByDay['data']) }],
        chart: { ...baseOpts.chart, type: 'bar', height: 220 },
        colors: ['#3b82f6'],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
        xaxis: { ...baseOpts.xaxis, categories: @json($callsByDay['labels']) },
        dataLabels: { enabled: false },
    }).render();

    // Top numbers pie
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

    // Hourly activity
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
