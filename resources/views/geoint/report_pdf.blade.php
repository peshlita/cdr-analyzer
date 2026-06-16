<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 11px;
    color: #1e293b;
    padding: 28px 32px;
    background: #ffffff;
}
.header { margin-bottom: 18px; border-bottom: 2px solid #e2e8f0; padding-bottom: 12px; }
.header h1 { font-size: 17px; color: #0f172a; margin-bottom: 3px; }
.header p  { font-size: 10px; color: #64748b; }
.cards { display: table; width: 100%; border-spacing: 8px; margin-bottom: 18px; }
.cards-row { display: table-row; }
.card {
    display: table-cell; width: 20%;
    border: 1px solid #e2e8f0; border-radius: 8px;
    padding: 10px 12px; vertical-align: middle;
}
.card-value { font-size: 16px; font-weight: 700; color: #0f172a; }
.card-label { font-size: 9px; color: #64748b; margin-top: 2px; }
h2 { font-size: 13px; color: #0f172a; margin-bottom: 8px; margin-top: 4px; }
table { width: 100%; border-collapse: collapse; }
thead tr { background: #f1f5f9; }
th {
    text-align: left; padding: 6px 10px;
    font-size: 10px; font-weight: 700;
    color: #475569; border-bottom: 1px solid #e2e8f0;
    text-transform: uppercase; letter-spacing: .04em;
}
td {
    padding: 6px 10px; font-size: 10px;
    border-bottom: 1px solid #f1f5f9;
    color: #334155;
}
.badge {
    display: inline-block; padding: 2px 7px;
    border-radius: 4px; font-size: 9px; font-weight: 700;
}
.mono { font-family: 'Courier New', monospace; font-size: 9px; }
.footer {
    margin-top: 20px; font-size: 9px; color: #94a3b8;
    border-top: 1px solid #e2e8f0; padding-top: 8px;
    text-align: right;
}
.no-stops { text-align: center; color: #94a3b8; padding: 24px; }
</style>
</head>
<body>

<div class="header">
    <h1>Reporte de Ruta — {{ $unit->name }}{{ $unit->plate ? ' (' . $unit->plate . ')' : '' }}</h1>
    <p>Periodo: {{ $from->format('d/m/Y H:i') }} &mdash; {{ $to->format('d/m/Y H:i') }}</p>
</div>

@php
$h = intdiv($summary['active_min'], 60);
$m = $summary['active_min'] % 60;
$activeLabel = ($h > 0 ? "{$h}h " : '') . "{$m}min";
@endphp

<table class="cards">
<tr class="cards-row">
    <td class="card">
        <div class="card-value">{{ $summary['km'] }} km</div>
        <div class="card-label">Distancia recorrida</div>
    </td>
    <td class="card">
        <div class="card-value">{{ $activeLabel }}</div>
        <div class="card-label">Tiempo en movimiento</div>
    </td>
    <td class="card">
        <div class="card-value">{{ $summary['stops'] }}</div>
        <div class="card-label">Paradas detectadas</div>
    </td>
    <td class="card">
        <div class="card-value">{{ $summary['max_spd'] }} km/h</div>
        <div class="card-label">Velocidad máxima</div>
    </td>
    <td class="card">
        <div class="card-value">{{ $summary['pernoctas'] }}</div>
        <div class="card-label">Pernoctas</div>
    </td>
</tr>
</table>

@if(count($stops) > 0)
<h2>Paradas detectadas ({{ count($stops) }})</h2>
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Tipo</th>
            <th>Inicio</th>
            <th>Fin</th>
            <th>Duración</th>
            <th>Coordenadas</th>
        </tr>
    </thead>
    <tbody>
        @foreach($stops as $i => $stop)
        @php
        $tLabel = match($stop['type']) { 'overnight' => 'Pernocta', 'long' => 'Larga', default => 'Corta' };
        $tBg    = match($stop['type']) { 'overnight' => '#ede9fe', 'long' => '#fef3c7', default => '#dbeafe' };
        $tClr   = match($stop['type']) { 'overnight' => '#7c3aed', 'long' => '#d97706', default => '#2563eb' };
        $sh = intdiv($stop['duration'], 60); $sm = $stop['duration'] % 60;
        $sDur = ($sh > 0 ? "{$sh}h " : '') . "{$sm}min";
        @endphp
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>
                <span class="badge" style="background:{{ $tBg }};color:{{ $tClr }};">{{ $tLabel }}</span>
            </td>
            <td class="mono">{{ $stop['start'] }}</td>
            <td class="mono">{{ $stop['end'] }}</td>
            <td><strong>{{ $sDur }}</strong></td>
            <td class="mono">{{ number_format($stop['lat'], 5) }}, {{ number_format($stop['lon'], 5) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@else
<p class="no-stops">Sin paradas detectadas en este período.</p>
@endif

@if(count($timeline) > 0)
<h2 style="margin-top:18px;">Eventos del recorrido</h2>
<table>
    <thead>
        <tr>
            <th>Hora</th>
            <th>Evento</th>
        </tr>
    </thead>
    <tbody>
        @foreach($timeline as $event)
        <tr>
            <td class="mono" style="width:60px;">{{ $event['time'] }}</td>
            <td>{{ $event['label'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<div class="footer">
    Generado el {{ now()->format('d/m/Y H:i') }} &middot; CDR-Analyzer
</div>

</body>
</html>
