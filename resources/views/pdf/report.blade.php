<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Informe CDR</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1e293b; background: #fff; }

    /* Cover — layout de bloque simple, compatible con DomPDF */
    .cover {
        page-break-after: always;
        text-align: center;
        background: #0f172a;
        color: white;
        /* Altura fija A4 menos margen de footer DomPDF (~20px) */
        height: 277mm;
        padding: 60px 50px 40px 50px;
    }
    .cover-badge { display: inline-block; background: #3b82f6; color: white; padding: 5px 16px; border-radius: 20px; font-size: 9pt; margin-bottom: 16px; }
    .cover h1 { font-size: 24pt; font-weight: bold; color: white; margin-bottom: 8px; }
    .cover .subtitle { font-size: 11pt; color: #94a3b8; margin-bottom: 28px; }
    /* Meta box — tabla simple para dos columnas */
    .cover .meta-box { background: #1e293b; border: 1px solid #334155; border-radius: 6px; padding: 16px 24px; text-align: left; }
    .cover .meta-table { width: 100%; border-collapse: collapse; font-size: 9pt; }
    .cover .meta-table td { padding: 4px 6px; border: none; background: transparent; }
    .cover .meta-label { color: #64748b; width: 150px; }
    .cover .meta-value { color: #e2e8f0; font-weight: bold; }
    /* Número objetivo */
    .cover .target-box { margin-top: 20px; border: 1px solid #f59e0b; border-radius: 6px; padding: 10px 20px; display: inline-block; }
    .cover .target-label { font-size: 8pt; color: #f59e0b; margin-bottom: 4px; }
    .cover .target-value { font-size: 15pt; font-weight: bold; color: #f59e0b; letter-spacing: 1px; }

    /* Section */
    .section { margin-bottom: 24px; page-break-inside: avoid; }
    .section-title { font-size: 11pt; font-weight: bold; color: #1e40af; border-bottom: 2px solid #3b82f6; padding-bottom: 4px; margin-bottom: 12px; }

    /* Stats grid */
    .stats-grid { display: table; width: 100%; margin-bottom: 16px; }
    .stat-card { display: table-cell; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px; text-align: center; width: 25%; }
    .stat-value { font-size: 14pt; font-weight: bold; color: #1e40af; }
    .stat-label { font-size: 7pt; color: #64748b; margin-top: 2px; }

    /* Tables */
    table { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 8pt; }
    th { background: #1e293b; color: white; padding: 6px 8px; text-align: left; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.5px; }
    td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
    tr:nth-child(even) td { background: #f8fafc; }
    .mono { font-family: DejaVu Sans Mono, monospace; font-size: 7.5pt; }
    .badge-voice { background: #dcfce7; color: #166534; padding: 1px 6px; border-radius: 10px; font-size: 7pt; }
    .badge-data  { background: #ede9fe; color: #5b21b6; padding: 1px 6px; border-radius: 10px; font-size: 7pt; }
    .badge-out   { background: #dcfce7; color: #166534; padding: 1px 6px; border-radius: 10px; font-size: 7pt; }
    .badge-in    { background: #fee2e2; color: #991b1b; padding: 1px 6px; border-radius: 10px; font-size: 7pt; }

    /* Footer */
    .footer { position: fixed; bottom: 0; left: 0; right: 0; text-align: center; font-size: 7pt; color: #94a3b8; padding: 6px; border-top: 1px solid #e2e8f0; background: white; }
    .page-num::after { content: counter(page); }
    .no-break { page-break-inside: avoid; }
</style>
</head>
<body>

<!-- COVER PAGE -->
<div class="cover">
    <div class="cover-badge">DOCUMENTO CONFIDENCIAL</div>
    <h1>Informe de An&aacute;lisis CDR</h1>
    <p class="subtitle">Call Detail Records &mdash; An&aacute;lisis Forense Telef&oacute;nico</p>

    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td class="meta-label">Fecha de generaci&oacute;n:</td>
                <td class="meta-value">{{ now()->format('d/m/Y H:i:s') }}</td>
            </tr>
            <tr>
                <td class="meta-label">Per&iacute;odo analizado:</td>
                <td class="meta-value">
                    {{ $stats['dateMin'] ? \Carbon\Carbon::parse($stats['dateMin'])->format('d/m/Y') : 'N/D' }}
                    &mdash;
                    {{ $stats['dateMax'] ? \Carbon\Carbon::parse($stats['dateMax'])->format('d/m/Y') : 'N/D' }}
                </td>
            </tr>
            <tr>
                <td class="meta-label">Total registros:</td>
                <td class="meta-value">{{ number_format($stats['total']) }}</td>
            </tr>
            @if($stats['voice'] > 0)
            <tr>
                <td class="meta-label">Llamadas de voz:</td>
                <td class="meta-value">{{ number_format($stats['voice']) }}</td>
            </tr>
            @endif
            @if($stats['data'] > 0)
            <tr>
                <td class="meta-label">Sesiones datos:</td>
                <td class="meta-value">{{ number_format($stats['data']) }}</td>
            </tr>
            @endif
            <tr>
                <td class="meta-label">Duraci&oacute;n total:</td>
                <td class="meta-value">{{ round($stats['totalSecs']/3600, 1) }} horas</td>
            </tr>
        </table>
    </div>

    @if($targetNumber)
    <div class="target-box">
        <div class="target-label">N&Uacute;MERO OBJETIVO</div>
        <div class="target-value">{{ $targetNumber }}</div>
    </div>
    @endif
</div>

<!-- MAIN CONTENT -->
<div class="footer">
    CDR Analyzer — Informe Confidencial — Página <span class="page-num"></span>
</div>

<!-- 1. Summary stats -->
<div class="section">
    <div class="section-title">1. Resumen Estadístico</div>
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-value">{{ number_format($stats['total']) }}</div><div class="stat-label">Total Eventos</div></div>
        <div class="stat-card"><div class="stat-value">{{ number_format($stats['voice']) }}</div><div class="stat-label">Llamadas Voz</div></div>
        <div class="stat-card"><div class="stat-value">{{ number_format($stats['data']) }}</div><div class="stat-label">Sesiones Datos</div></div>
        <div class="stat-card"><div class="stat-value">{{ $stats['imeis'] }}</div><div class="stat-label">IMEIs Detectados</div></div>
    </div>
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-value">{{ number_format($stats['outgoing']) }}</div><div class="stat-label">Llamadas Salientes</div></div>
        <div class="stat-card"><div class="stat-value">{{ number_format($stats['incoming']) }}</div><div class="stat-label">Llamadas Entrantes</div></div>
        <div class="stat-card"><div class="stat-value">{{ round($stats['totalSecs']/3600, 1) }}h</div><div class="stat-label">Duración Total</div></div>
        <div class="stat-card"><div class="stat-value">{{ $enrichedContacts->count() }}</div><div class="stat-label">Contactos ID</div></div>
    </div>
</div>

<!-- Análisis de Frecuencia y Cruces -->
@if($freqBatches->count() > 0)
<div class="section no-break">
    <div class="section-title">Análisis de Frecuencia de Contactos</div>

    @if($freqBatches->count() === 1)
    <table>
        <thead>
            <tr>
                <th>#</th><th>Número</th><th>Total</th><th>Entrantes</th><th>Salientes</th><th>Duración</th><th>Periodo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($freqTopContacts as $i => $c)
            <tr>
                <td>{{ $i+1 }}</td>
                <td class="mono">{{ $c['number'] }}</td>
                <td style="text-align:center; font-weight:bold; color:#3b82f6;">{{ $c['total_events'] }}</td>
                <td style="text-align:center">{{ $c['incoming'] }}</td>
                <td style="text-align:center">{{ $c['outgoing'] }}</td>
                <td style="text-align:center">{{ gmdate('H:i:s', $c['total_duration']) }}</td>
                <td style="text-align:center">{{ $c['days_active'] }} días</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    @foreach($freqTopContacts as $batchData)
    <p style="font-weight:bold; color:#1e40af; margin-bottom:6px;">
        {{ $batchData['batch']->phone_main ?? $batchData['batch']->name }}
        <span style="font-size:7pt; color:#64748b; font-weight:normal;">
            ({{ number_format($batchData['batch']->total_records) }} registros)
        </span>
    </p>
    <table>
        <thead>
            <tr>
                <th>#</th><th>Número</th><th>Total</th><th>Entrantes</th><th>Salientes</th><th>Duración</th><th>Periodo</th>
            </tr>
        </thead>
        <tbody>
            @foreach($batchData['contacts'] as $i => $c)
            <tr>
                <td>{{ $i+1 }}</td>
                <td class="mono">{{ $c['number'] }}</td>
                <td style="text-align:center; font-weight:bold; color:#3b82f6;">{{ $c['total_events'] }}</td>
                <td style="text-align:center">{{ $c['incoming'] }}</td>
                <td style="text-align:center">{{ $c['outgoing'] }}</td>
                <td style="text-align:center">{{ gmdate('H:i:s', $c['total_duration']) }}</td>
                <td style="text-align:center">{{ $c['days_active'] }} días</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endforeach
    @endif
</div>

@if(count($freqCrossAnalysis) > 0)
<div class="section" style="page-break-inside:avoid;">
    <div class="section-title">Cruces Detectados Entre Sábanas</div>

    @foreach($freqCrossAnalysis as $cross)
    <div style="border:1px solid #e2e8f0;border-radius:6px;padding:10px;margin-bottom:10px;
                {{ $cross['relevance_score'] >= 130 ? 'border-left:4px solid #ef4444;' : ($cross['relevance_score'] >= 80 ? 'border-left:4px solid #f59e0b;' : 'border-left:4px solid #94a3b8;') }}">
        <div style="font-weight:bold;font-family:'DejaVu Sans Mono',monospace;margin-bottom:4px;">
            {{ $cross['contact_number'] }}
            <span style="background:{{ $cross['relevance_score'] >= 130 ? '#fef2f2' : ($cross['relevance_score'] >= 80 ? '#fffbeb' : '#f1f5f9') }};
                  color:{{ $cross['relevance_score'] >= 130 ? '#dc2626' : ($cross['relevance_score'] >= 80 ? '#d97706' : '#475569') }};
                  padding:2px 8px;border-radius:10px;font-size:7pt;float:right;">
                {{ $cross['relevance_score'] >= 130 ? 'Alta relevancia' : ($cross['relevance_score'] >= 80 ? 'Media relevancia' : 'Baja relevancia') }}
            </span>
        </div>
        <div style="font-size:8pt;color:#64748b;">
            Con {{ $cross['main_a'] }}: {{ $cross['events_with_a'] }} eventos
            | Con {{ $cross['main_b'] }}: {{ $cross['events_with_b'] }} eventos
        </div>
        @if($cross['closest_pair_hours'] !== null)
        <div style="font-size:8pt;color:#92400e;margin-top:4px;">
            ⚠ Comunicación más cercana en el tiempo: {{ $cross['closest_pair_hours'] }} horas de diferencia
            ({{ $cross['closest_pair_dates']['date_a'] }} ↔ {{ $cross['closest_pair_dates']['date_b'] }})
        </div>
        @endif
    </div>
    @endforeach
</div>
@endif
@endif

<!-- 2. Identified contacts -->
@if($enrichedContacts->count() > 0)
<div class="section no-break">
    <div class="section-title">2. Contactos Identificados ({{ $enrichedContacts->count() }})</div>
    <table>
        <thead>
            <tr><th>Número</th><th>Nombre</th><th>Alias</th><th>Notas</th></tr>
        </thead>
        <tbody>
            @foreach($enrichedContacts as $c)
            <tr>
                <td class="mono">{{ $c->phone_number }}</td>
                <td>{{ $c->name ?? '—' }}</td>
                <td>{{ $c->alias ?? '—' }}</td>
                <td>{{ $c->notes ? \Str::limit($c->notes, 60) : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<!-- 3. Network graph snapshot -->
@if($networkSnapshotPath)
<div class="section no-break">
    <div class="section-title">3. Red de Llamadas — Grafo Interactivo</div>
    <div style="text-align:center; border:1px solid #e2e8f0; border-radius:6px; overflow:hidden; background:#0f172a; padding:8px;">
        <img src="{{ $networkSnapshotPath }}"
             alt="Red de llamadas"
             style="max-width:100%; max-height:320px;">
    </div>
    <p style="font-size:7pt; color:#64748b; margin-top:4px; text-align:center;">
        Captura de la red organizada y filtrada desde el módulo de análisis
    </p>
</div>
@endif

<!-- 4. Map snapshots -->
@if(!empty($mapSnapshots))
@php
    $mapMeta = [
        'map_data'     => ['title' => 'Mapa de Movimiento (Datos GPS)',        'sub' => 'Trayectoria y sesiones de datos del dispositivo'],
        'map_voice'    => ['title' => 'Mapa de Llamadas (Voz — Entrantes y Salientes)', 'sub' => 'Ubicación de antenas durante llamadas de voz'],
        'map_pernocta' => ['title' => 'Mapa de Antena de Pernocta',            'sub' => 'Antena con mayor actividad nocturna (23:00 – 07:00)'],
    ];
@endphp
@foreach(['map_data','map_voice','map_pernocta'] as $mapKey)
@if(isset($mapSnapshots[$mapKey]))
<div class="section no-break">
    <div class="section-title">4{{ $loop->index === 0 ? 'a' : ($loop->index === 1 ? 'b' : 'c') }}. {{ $mapMeta[$mapKey]['title'] }}</div>
    <div style="text-align:center; border:1px solid #e2e8f0; border-radius:6px; overflow:hidden; padding:6px;">
        <img src="{{ $mapSnapshots[$mapKey] }}"
             alt="{{ $mapMeta[$mapKey]['title'] }}"
             style="max-width:100%; max-height:300px;">
    </div>
    <p style="font-size:7pt; color:#64748b; margin-top:4px; text-align:center;">
        {{ $mapMeta[$mapKey]['sub'] }}
    </p>
    {{-- Coordenadas de pernocta debajo del mapa --}}
    @if($mapKey === 'map_pernocta' && isset($pernoctaAntenna) && $pernoctaAntenna)
    <div style="margin-top:8px; padding:8px 12px; background:#fff8ed; border:1px solid #f59e0b; border-radius:6px; font-size:8pt;">
        <p style="font-weight:bold; color:#92400e; margin-bottom:4px;">📍 Coordenadas del punto de pernocta</p>
        <div class="stats-grid">
            <div class="stat-card" style="width:33%;">
                <div class="stat-value" style="font-size:9pt; color:#92400e;">{{ number_format((float)$pernoctaAntenna->lat_a, 6) }}</div>
                <div class="stat-label">Latitud</div>
            </div>
            <div class="stat-card" style="width:33%;">
                <div class="stat-value" style="font-size:9pt; color:#92400e;">{{ number_format((float)$pernoctaAntenna->lon_a, 6) }}</div>
                <div class="stat-label">Longitud</div>
            </div>
            <div class="stat-card" style="width:33%;">
                <div class="stat-value" style="font-size:9pt; color:#92400e;">{{ gmdate('H:i:s', $pernoctaAntenna->total_duration) }}</div>
                <div class="stat-label">Duración nocturna total</div>
            </div>
        </div>
        <p style="font-size:7pt; color:#78350f; margin-top:4px;">
            Antena con mayor tiempo de conexión en horario 23:00–07:00. Sesiones registradas: {{ $pernoctaAntenna->sessions }}.
        </p>
    </div>
    @endif
</div>
@endif
@endforeach
@endif

<!-- 5. Top contacts -->
<div class="section">
    <div class="section-title">5. Top Números Contactados (Top 20)</div>
    <table>
        <thead>
            <tr><th>#</th><th>Número</th><th>Nombre / Alias</th><th style="text-align:right">Llamadas</th><th style="text-align:right">Duración</th></tr>
        </thead>
        <tbody>
            @foreach($topContacts as $i => $c)
            @php $pc = \App\Models\PhoneContact::where('phone_number',$c->phone)->first(); @endphp
            <tr>
                <td>{{ $i+1 }}</td>
                <td class="mono">{{ $c->phone }}</td>
                <td>{{ $pc?->name ?? ($pc?->alias ?? '—') }}</td>
                <td style="text-align:right">{{ number_format($c->calls) }}</td>
                <td style="text-align:right">{{ gmdate('H:i:s', $c->total_duration) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<!-- 6. Voice calls (top 50) -->
<div class="section">
    <div class="section-title">6. Registro de Llamadas de Voz (Top 50)</div>
    <table>
        <thead>
            <tr><th>Fecha</th><th>Hora</th><th>Número A</th><th>Número B</th><th>Dirección</th><th style="text-align:right">Duración</th></tr>
        </thead>
        <tbody>
            @foreach($topVoiceCalls as $r)
            <tr>
                <td>{{ $r->date ? $r->date->format('d/m/Y') : '—' }}</td>
                <td class="mono">{{ $r->hour ?? '—' }}</td>
                <td class="mono">{{ $r->number_a ?? '—' }}</td>
                <td class="mono">{{ $r->number_b ?? '—' }}</td>
                <td>
                    @if($r->direction === 'Outgoing')
                        <span class="badge-out">Saliente</span>
                    @elseif($r->direction === 'Incoming')
                        <span class="badge-in">Entrante</span>
                    @else
                        —
                    @endif
                </td>
                <td style="text-align:right">{{ gmdate('H:i:s', $r->duration) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<!-- 7. IMEI list -->
@if($imeiList->count() > 0)
<div class="section no-break">
    <div class="section-title">7. IMEIs e IMSIs Detectados</div>
    <table>
        <thead>
            <tr><th>IMEI</th><th>IMSI</th></tr>
        </thead>
        <tbody>
            @foreach($imeiList as $item)
            <tr>
                <td class="mono">{{ $item->imei_a }}</td>
                <td class="mono">{{ $item->imsi_a ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<!-- 8. Antena de pernocta -->
@if(isset($pernoctaAntenna) && $pernoctaAntenna)
<div class="section no-break">
    <div class="section-title">8. Antena de Pernocta (23:00 — 07:00)</div>
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value" style="font-size:10pt;">{{ number_format($pernoctaAntenna->lat_a, 6) }}</div>
            <div class="stat-label">Latitud</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="font-size:10pt;">{{ number_format($pernoctaAntenna->lon_a, 6) }}</div>
            <div class="stat-label">Longitud</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ gmdate('H:i:s', $pernoctaAntenna->total_duration) }}</div>
            <div class="stat-label">Duración nocturna</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $pernoctaAntenna->sessions }}</div>
            <div class="stat-label">Sesiones de datos</div>
        </div>
    </div>
    <p style="font-size:7.5pt; color:#64748b; margin-top:6px;">
        Antena con mayor tiempo de conexión durante horario nocturno. Posible ubicación de residencia o zona de pernocta habitual.
    </p>
</div>
@endif

<!-- 9. Comunicaciones nocturnas -->
@if(isset($nocturnalVoice) && $nocturnalVoice->count() > 0)
<div class="section">
    <div class="section-title">9. Comunicaciones Nocturnas (23:00 — 07:00)</div>
    <table>
        <thead>
            <tr>
                <th>Número A</th>
                <th>Número B</th>
                <th>Dirección</th>
                <th style="text-align:right">Llamadas</th>
                <th style="text-align:right">Duración</th>
            </tr>
        </thead>
        <tbody>
            @foreach($nocturnalVoice as $r)
            <tr>
                <td class="mono">{{ $r->number_a }}</td>
                <td class="mono">{{ $r->number_b }}</td>
                <td>
                    @if($r->direction === 'Outgoing')
                        <span class="badge-out">Saliente</span>
                    @elseif($r->direction === 'Incoming')
                        <span class="badge-in">Entrante</span>
                    @else —
                    @endif
                </td>
                <td style="text-align:right; font-weight:bold;">{{ $r->calls }}</td>
                <td style="text-align:right;">{{ gmdate('H:i:s', $r->total_duration) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

<!-- 10. Contactos identificados (completos) -->
@if($enrichedContacts->count() > 0)
<div class="section no-break">
    <div class="section-title">10. Todos los Contactos Identificados ({{ $enrichedContacts->count() }})</div>
    <table>
        <thead>
            <tr><th>Número</th><th>Nombre</th><th>Alias</th><th>Notas</th></tr>
        </thead>
        <tbody>
            @foreach($enrichedContacts as $c)
            <tr>
                <td class="mono">{{ $c->phone_number }}</td>
                <td>{{ $c->name ?? '—' }}</td>
                <td>{{ $c->alias ?? '—' }}</td>
                <td style="font-size:7pt; color:#475569;">{{ $c->notes ? \Str::limit($c->notes, 80) : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

</body>
</html>
