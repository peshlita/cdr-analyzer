<?php

namespace App\Console\Commands;

use App\Services\GpsTcpListener;
use Illuminate\Console\Command;

class GpsListenerStart extends Command
{
    protected $signature = 'gps:listen {--port=5001 : Puerto TCP} {--host=0.0.0.0 : Host a escuchar}';
    protected $description = 'Iniciar servidor TCP para recepción de GPS TK905';

    public function handle(): void
    {
        $port = (int) $this->option('port') ?: (int) env('GPS_TCP_PORT', 5001);
        $host = $this->option('host') ?: env('GPS_TCP_HOST', '0.0.0.0');

        $this->info("╔══════════════════════════════════════╗");
        $this->info("║     CDR-Analizer — GPS TK905 TCP     ║");
        $this->info("╚══════════════════════════════════════╝");
        $this->line('');
        $this->info("Iniciando servidor en <fg=cyan>{$host}:{$port}</>");
        $this->line('Presiona Ctrl+C para detener.');
        $this->line('');

        $listener = new GpsTcpListener($host, $port);

        $listener->start(function (string $msg) {
            $this->line('[' . now()->format('H:i:s') . '] ' . $msg);
        });
    }
}
