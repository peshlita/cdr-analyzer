<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Informe CDR</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #1e293b; background: #fff; }

    /* Cover */
    .cover { page-break-after: always; text-align: center; padding: 80px 40px; background: #0f172a; color: white; min-height: 297mm; display: flex; flex-direction: column; justify-content: center; }
    .cover-badge { display: inline-block; background: #3b82f6; color: white; padding: 6px 18px; border-radius: 20px; font-size: 10pt; margin-bottom: 20px; }
    .cover h1 { font-size: 28pt; font-weight: bold; color: white; margin-bottom: 10px; }
    .cover .subtitle { font-size: 12pt; color: #94a3b8; margin-bottom: 40px; }
    .cover .meta-box { display: inline-block; background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 20px 30px; margin: 0 auto; text-align: left; }
    .cover .meta-row { display: flex; gap: 10px; margin-bottom: 8px; font-size: 9pt; }
    .cover .meta-label { color: #64748b; width: 120px; }
    .cover .meta-value { color: #e2e8f0; font-weight: bold; }
    .cover .target-box { margin-top: 30px; background: #f59e0b20; border: 1px solid #f59e0b; border-radius: 8px; padding: 12px 24px; display: inline-block; }
    .cover .target-label { font-size: 8pt; color: #f59e0b; }
    .cover .target-value { font-size: 16pt; font-weight: bold; color: #f59e0b; letter-spacing: 1px; }

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
    <div>
        <div class="cover-badge">DOCUMENTO CONFIDENCIAL</div>
        <h1>Informe de Análisis CDR</h1>
        <p class="subtitle">Call Detail Records — Análisis Forense Telefónico</p>

        <div class="meta-box">
            <div class="meta-row">
                <span class="meta-label">Fecha de generación:</span>
                <span class="meta-value">{{ now()->format('d/m/Y H:i:s') }}</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Período analizado:</span>
                <span class="meta-value">
                    {{ $stats['dateMin'] ? \Carbon\Carbon::parse($stats['dateMin'])->format('d/m/Y') : 'N/D' }}
                    —
                    {{ $stats['dateMax'] ? \Carbon\Carbon::parse($stats['dateMax'])->format('d/m/Y') : 'N/D' }}
                </span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Total registros:</span>
                <span class="meta-value">{{ number_format($stats['total']) }}</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Llamadas de voz:</span>
                <span class="meta-value">{{ number_format($stats['voice']) }}</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Sesiones datos:</span>
                <span class="meta-value">{{ number_format($stats['data']) }}</span>
            </div>
            <div class="meta-row">
                <span class="meta-label">Duración total:</span>
                <span class="meta-value">{{ round($stats['totalSecs']/3600, 1) }} horas</span>
            </div>
        </div>

        @if($targetNumber)
        <div class="target-box" style="margin-top:30px;">
            <div class="target-label">NÚMERO OBJETIVO</div>
            <div class="target-value">{{ $targetNumber }}</div>
        </div>
        @endif
    </div>
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

<!-- 4. Top contacts -->
<div class="section">
    <div class="section-title">4. Top Números Contactados (Top 20)</div>
    <table>
        <thead>
            <tr><th>#</th><th>Número</th><th>Nombre / Alias</th><th style="text-align:right">Llamadas</th><th style="text-align:right">Duración</th></tr>
        </thead>
        <tbody>
            @foreach($topContacts as $i => $c)
            @php $pc = \App\Models\PhoneContact::where('phone_number',$c->number_b)->first(); @endphp
            <tr>
                <td>{{ $i+1 }}</td>
                <td class="mono">{{ $c->number_b }}</td>
                <td>{{ $pc?->name ?? ($pc?->alias ?? '—') }}</td>
                <td style="text-align:right">{{ number_format($c->calls) }}</td>
                <td style="text-align:right">{{ gmdate('H:i:s', $c->total_duration) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

<!-- 4. Voice calls (top 50) -->
<div class="section">
    <div class="section-title">4. Registro de Llamadas de Voz (Top 50)</div>
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

<!-- 5. IMEI list -->
@if($imeiList->count() > 0)
<div class="section no-break">
    <div class="section-title">5. IMEIs e IMSIs Detectados</div>
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

<!-- 6. Antena de pernocta -->
@if(isset($pernoctaAntenna) && $pernoctaAntenna)
<div class="section no-break">
    <div class="section-title">6. Antena de Pernocta (23:00 — 07:00)</div>
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

<!-- 7. Comunicaciones nocturnas -->
@if(isset($nocturnalVoice) && $nocturnalVoice->count() > 0)
<div class="section">
    <div class="section-title">7. Comunicaciones Nocturnas (23:00 — 07:00)</div>
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

<!-- 8. Contactos identificados (completos) -->
@if($enrichedContacts->count() > 0)
<div class="section no-break">
    <div class="section-title">8. Todos los Contactos Identificados ({{ $enrichedContacts->count() }})</div>
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
