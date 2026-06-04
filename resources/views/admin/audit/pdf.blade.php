<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Log de Auditoría</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        p.sub { font-size: 9px; color: #64748b; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e293b; color: white; padding: 6px 8px; text-align: left; font-size: 9px; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) { background: #f8fafc; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 4px; font-size: 8px; font-weight: bold; }
        .badge-login { background: #dcfce7; color: #166534; }
        .badge-default { background: #f1f5f9; color: #475569; }
    </style>
</head>
<body>
    <h1>Log de Auditoría — CDR-Analizer</h1>
    <p class="sub">Generado: {{ now()->format('d/m/Y H:i:s') }} | Total registros: {{ $logs->count() }}</p>
    <table>
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Acción</th>
                <th>Módulo</th>
                <th>Descripción</th>
                <th>IP</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            @foreach($logs as $log)
            <tr>
                <td>{{ $log->user_email }}</td>
                <td><span class="badge {{ str_contains($log->action,'login') ? 'badge-login' : 'badge-default' }}">{{ $log->action }}</span></td>
                <td>{{ $log->module ?? '—' }}</td>
                <td>{{ $log->description ?? '—' }}</td>
                <td>{{ $log->ip_address ?? '—' }}</td>
                <td>{{ $log->created_at->format('d/m/Y H:i') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
