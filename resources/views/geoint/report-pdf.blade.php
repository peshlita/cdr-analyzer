<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1e293b; background: #fff; }

.header { background: #0f172a; padding: 18px 24px; margin-bottom: 16px; }
.header-title { font-size: 17px; font-weight: bold; color: #fff; }
.header-sub   { font-size: 10px; color: #94a3b8; margin-top: 3px; }
.header-badge { background: #1e3a5f; color: #60a5fa; padding: 5px 12px; border-radius: 12px;
                font-size: 10px; font-weight: bold; display: inline-block; }
.header-date  { color: #64748b; font-size: 9px; margin-top: 5px; }

.section { margin: 0 24px 16px 24px; }
.section-title { font-size: 12px; font-weight: bold; color: #1e293b;
                 border-left: 4px solid #3b82f6; padding-left: 10px; margin-bottom: 10px; }

/* ── MAP ── */
.map-img { width: 100%; height: auto; display: block; border-radius: 4px; }
.map-no-img { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;
              padding: 28px; text-align: center; color: #94a3b8; font-size: 11px; }
.map-legend-row td { font-size: 9px; color: #64748b; padding: 5px 10px; }
.legend-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%;
              vertical-align: middle; margin-right: 3px; }

/* ── TABLES ── */
.data-table { width: 100%; border-collapse: collapse; font-size: 10px; }
.data-table th { background: #1e293b; color: #fff; padding: 7px 10px;
                 text-align: left; font-size: 10px; font-weight: bold; }
.data-table td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9;
                 color: #334155; vertical-align: middle; }
.data-table tr:nth-child(even) td { background: #f8fafc; }
.badge { display: inline-block; padding: 2px 7px; border-radius: 8px;
         font-size: 9px; font-weight: bold; }

/* ── TIMELINE ── */
.tl-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; vertical-align: middle; }
.tl-coords { font-size: 9px; color: #94a3b8; margin-top: 2px; }

/* ── FOOTER ── */
.footer { background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 8px 24px; margin-top: 16px; }
.confidential { display: inline-block; background: #fef2f2; color: #dc2626;
                padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 9px; }
</style>
</head>
<body>

{{-- ══ HEADER ══ --}}
<div class="header">
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="vertical-align:top;">
                <div class="header-title">
                    Reporte de Ruta &mdash; {{ $unit->name }}
                    @if($unit->plate) ({{ $unit->plate }}) @endif
                </div>
                <div class="header-sub">
                    IMEI: {{ $unit->imei ?? '—' }}
                    &nbsp;|&nbsp;
                    Período: {{ $from->format('d/m/Y H:i') }} &mdash; {{ $to->format('d/m/Y H:i') }}
                </div>
                <div class="header-sub" style="margin-top:3px;">
                    Tipo:
                    @if($unit->unit_type === 'patrol') Patrulla Institucional
                    @elseif($unit->unit_type === 'covert') Vehículo Encubierto
                    @else Unidad GPS @endif
                </div>
            </td>
            <td style="text-align:right;vertical-align:top;width:130px;">
                <div class="header-badge">CDR-ANALYZER</div>
                <div class="header-date">{{ now()->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>
</div>

{{-- ══ METRIC CARDS (fully inline, DomPDF-safe) ══ --}}
@if(!empty($summary))
@php
$movH = intdiv($summary['moving_time'] ?? 0, 60);
$movM = ($summary['moving_time'] ?? 0) % 60;
$movLabel = ($movH > 0 ? "{$movH}h " : '') . "{$movM}min";
@endphp
<table style="width:calc(100% - 48px);margin:0 24px 16px 24px;border-collapse:separate;border-spacing:6px;">
    <tr>
        <td style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;
                   padding:12px;text-align:center;width:20%;vertical-align:middle;">
            <div style="font-size:20px;font-weight:bold;color:#3b82f6;">{{ $summary['totalKm'] ?? 0 }} km</div>
            <div style="font-size:9px;color:#64748b;margin-top:2px;">Distancia</div>
        </td>
        <td style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
                   padding:12px;text-align:center;width:20%;vertical-align:middle;">
            <div style="font-size:20px;font-weight:bold;color:#10b981;">{{ $movLabel }}</div>
            <div style="font-size:9px;color:#64748b;margin-top:2px;">En movimiento</div>
        </td>
        <td style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;
                   padding:12px;text-align:center;width:20%;vertical-align:middle;">
            <div style="font-size:20px;font-weight:bold;color:#d97706;">{{ count($stops) }}</div>
            <div style="font-size:9px;color:#64748b;margin-top:2px;">Zonas frecuentes</div>
        </td>
        <td style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;
                   padding:12px;text-align:center;width:20%;vertical-align:middle;">
            <div style="font-size:20px;font-weight:bold;color:#ef4444;">{{ $summary['maxSpeed'] ?? 0 }} km/h</div>
            <div style="font-size:9px;color:#64748b;margin-top:2px;">Vel. máxima</div>
        </td>
        <td style="background:#faf5ff;border:1px solid #e9d5ff;border-radius:8px;
                   padding:12px;text-align:center;width:20%;vertical-align:middle;">
            <div style="font-size:20px;font-weight:bold;color:#8b5cf6;">{{ $summary['pernoctas'] ?? 0 }}</div>
            <div style="font-size:9px;color:#64748b;margin-top:2px;">Pernoctas</div>
        </td>
    </tr>
</table>
@endif

{{-- ══ MAPA SVG ══ --}}
<div class="section">
    <div class="section-title">Ruta recorrida y zonas frecuentes</div>

    @if(!empty($mapPng))
        {{-- Captura del mapa Leaflet (html2canvas) --}}
        <img src="{{ $mapPng }}" width="700" height="320" style="display:block;border:1px solid #e2e8f0;" />

        {{-- Legend --}}
        <table style="width:100%;border-collapse:collapse;background:#f8fafc;border:1px solid #e2e8f0;border-top:none;">
            <tr>
                <td style="padding:5px 8px;font-size:9px;color:#64748b;">
                    <span class="legend-dot" style="background:#10b981;"></span><strong>A</strong> Inicio
                </td>
                <td style="padding:5px 8px;font-size:9px;color:#64748b;">
                    <span class="legend-dot" style="background:#ef4444;"></span><strong>B</strong> Fin
                </td>
                <td style="padding:5px 8px;font-size:9px;color:#64748b;">
                    <span class="legend-dot" style="background:#3b82f6;"></span><strong>1,2…</strong> Zona frecuente
                </td>
                <td style="padding:5px 8px;font-size:9px;color:#64748b;">
                    <span class="legend-dot" style="background:#f59e0b;"></span>Parada larga
                </td>
                <td style="padding:5px 8px;font-size:9px;color:#64748b;">
                    <span class="legend-dot" style="background:#8b5cf6;"></span>Pernocta
                </td>
            </tr>
        </table>
    @else
        <table class="data-table">
            <thead><tr><th>Punto</th><th>Latitud</th><th>Longitud</th><th>Hora</th></tr></thead>
            <tbody>
                @if($positions->count() > 0)
                <tr>
                    <td><strong>A Inicio</strong></td>
                    <td style="font-family:monospace;">{{ $positions->first()->lat }}</td>
                    <td style="font-family:monospace;">{{ $positions->first()->lon }}</td>
                    <td>{{ $positions->first()->received_at->format('H:i') }}</td>
                </tr>
                @foreach($stops as $i => $stop)
                <tr>
                    <td>{{ ($i + 1) }}. {{ $stop['type_label'] }}</td>
                    <td style="font-family:monospace;">{{ $stop['lat'] }}</td>
                    <td style="font-family:monospace;">{{ $stop['lon'] }}</td>
                    <td>{{ $stop['start'] }}</td>
                </tr>
                @endforeach
                <tr>
                    <td><strong>B Fin</strong></td>
                    <td style="font-family:monospace;">{{ $positions->last()->lat }}</td>
                    <td style="font-family:monospace;">{{ $positions->last()->lon }}</td>
                    <td>{{ $positions->last()->received_at->format('H:i') }}</td>
                </tr>
                @endif
            </tbody>
        </table>
    @endif
</div>

{{-- ══ STOPS TABLE ══ --}}
@if(count($stops) > 0)
<div class="section">
    <div class="section-title">Zonas frecuentes detectadas ({{ count($stops) }})</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:28px;">#</th>
                <th style="width:100px;">Tipo</th>
                <th>Primer registro</th>
                <th>Último registro</th>
                <th style="width:70px;">Duración</th>
                <th style="width:55px;">Visitas</th>
                <th>Coordenadas</th>
            </tr>
        </thead>
        <tbody>
            @foreach($stops as $i => $stop)
            @php
            $bgColor = $stop['type'] === 'overnight' ? '#ede9fe' : ($stop['type'] === 'long' ? '#fef3c7' : '#dbeafe');
            $fgColor = $stop['type'] === 'overnight' ? '#7c3aed' : ($stop['type'] === 'long' ? '#d97706' : '#2563eb');
            $sh = intdiv($stop['duration'], 60); $sm = $stop['duration'] % 60;
            $sDur = ($sh > 0 ? "{$sh}h " : '') . "{$sm}min";
            @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>
                    <span class="badge" style="background:{{ $bgColor }};color:{{ $fgColor }};">
                        {{ $stop['type_label'] }}
                    </span>
                </td>
                <td style="font-family:monospace;font-size:9px;">{{ $stop['start'] }}</td>
                <td style="font-family:monospace;font-size:9px;">{{ $stop['end'] }}</td>
                <td><strong>{{ $sDur }}</strong></td>
                <td style="text-align:center;font-weight:bold;">{{ $stop['count'] }}</td>
                <td style="font-family:monospace;font-size:9px;">
                    {{ number_format($stop['lat'], 5) }}, {{ number_format($stop['lon'], 5) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ══ STOP DETAIL MAPS ══ --}}
@if(count($stops) > 0 && !empty($stopMaps))
<div class="section">
    <div class="section-title">Detalle de ubicaciones detectadas</div>
    @php $chunks = array_chunk($stops, 2, true); @endphp
    @foreach($chunks as $chunk)
    <table style="width:100%;border-collapse:collapse;margin-bottom:8px;">
        <tr>
            @foreach($chunk as $idx => $stop)
            @php
            $sc  = $stop['type'] === 'overnight' ? '#8b5cf6' : ($stop['type'] === 'long' ? '#f59e0b' : '#3b82f6');
            $sbg = $stop['type'] === 'overnight' ? '#ede9fe' : ($stop['type'] === 'long' ? '#fef3c7' : '#dbeafe');
            $sh  = intdiv($stop['duration'], 60); $sm = $stop['duration'] % 60;
            $sDur = ($sh > 0 ? "{$sh}h " : '') . "{$sm}min";
            @endphp
            <td style="width:49%;vertical-align:top;padding:0 4px 0 0;border:none;">
                <div style="border:1px solid #e2e8f0;">
                    {{-- Captura del mapa Leaflet con acercamiento a la parada (html2canvas) --}}
                    @if(!empty($stopMaps[$idx]))
                    <img src="{{ $stopMaps[$idx] }}" width="330" height="200" style="display:block;" />
                    @endif
                    {{-- Stop info below the map --}}
                    <div style="padding:5px 8px;border-top:2px solid {{ $sc }};background:#ffffff;">
                        <div style="font-size:10px;font-weight:bold;color:{{ $sc }};">
                            <span style="background:{{ $sbg }};padding:1px 6px;">{{ ($idx + 1) }}. {{ $stop['type_label'] }}</span>
                        </div>
                        <div style="font-size:9px;color:#64748b;margin-top:3px;">
                            {{ $stop['start'] }} &rarr; {{ $stop['end'] }}
                        </div>
                        <div style="font-size:9px;color:#334155;margin-top:2px;">
                            Duración: <strong>{{ $sDur }}</strong> &nbsp;&middot;&nbsp; {{ $stop['count'] }} registros
                        </div>
                        <div style="font-size:8px;color:#94a3b8;margin-top:2px;font-family:monospace;">
                            {{ number_format($stop['lat'], 6) }}, {{ number_format($stop['lon'], 6) }}
                        </div>
                    </div>
                </div>
            </td>
            @endforeach
            @if(count($chunk) === 1)
            <td style="width:49%;"></td>
            @endif
        </tr>
    </table>
    @endforeach
</div>
@endif

{{-- ══ TIMELINE ══ --}}
@if(count($timeline) > 0)
<div class="section">
    <div class="section-title">Cronología del recorrido</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width:55px;">Hora</th>
                <th style="width:18px;"></th>
                <th>Evento</th>
                <th>Coordenadas</th>
            </tr>
        </thead>
        <tbody>
            @foreach($timeline as $event)
            @php
            $dotColor = match($event['type']) {
                'start'     => '#10b981',
                'end'       => '#ef4444',
                'overnight' => '#8b5cf6',
                'long'      => '#f59e0b',
                'speed'     => '#f97316',
                default     => '#3b82f6',
            };
            @endphp
            <tr>
                <td style="font-family:monospace;color:#64748b;font-weight:bold;">{{ $event['time'] }}</td>
                <td style="text-align:center;">
                    <span class="tl-dot" style="background:{{ $dotColor }};"></span>
                </td>
                <td><strong>{{ $event['label'] }}</strong></td>
                <td style="font-family:monospace;font-size:9px;color:#94a3b8;">
                    @if(isset($event['lat'])) {{ number_format($event['lat'], 5) }}, {{ number_format($event['lon'], 5) }} @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ══ FOOTER ══ --}}
<div class="footer">
    <table style="width:100%;border-collapse:collapse;">
        <tr>
            <td style="font-size:9px;color:#94a3b8;">CDR-Analyzer &mdash; Plataforma de Inteligencia</td>
            <td style="text-align:center;"><span class="confidential">CONFIDENCIAL</span></td>
            <td style="text-align:right;font-size:9px;color:#94a3b8;">Generado: {{ now()->format('d/m/Y H:i:s') }}</td>
        </tr>
    </table>
</div>

</body>
</html>
