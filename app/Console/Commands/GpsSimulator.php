<?php

namespace App\Console\Commands;

use App\Models\GpsUnit;
use Illuminate\Console\Command;
use React\EventLoop\Loop;

class GpsSimulator extends Command
{
    protected $signature = 'gps:simulate
        {--units=5     : Cantidad de unidades a simular}
        {--interval=10 : Intervalo en segundos entre tramas}
        {--port=5001   : Puerto del listener TCP}
        {--host=127.0.0.1 : Host del listener TCP}';

    protected $description = 'Simular unidades GPS TK905 enviando tramas de prueba';

    // La Paz, BCS, México
    private float $baseLat = 24.1426;
    private float $baseLon = -110.3128;

    public function handle(): void
    {
        $count    = (int) $this->option('units');
        $interval = (int) $this->option('interval');
        $port     = (int) $this->option('port');
        $host     = $this->option('host');

        $this->info("Simulando {$count} unidades GPS cada {$interval}s → {$host}:{$port}");

        // Crear unidades de prueba si no existen
        $units = $this->ensureUnits($count);

        // Posiciones actuales (drift desde base)
        $positions = [];
        foreach ($units as $unit) {
            $positions[$unit->imei] = [
                'lat' => $this->baseLat + (rand(-500, 500) / 10000),
                'lon' => $this->baseLon + (rand(-500, 500) / 10000),
            ];
        }

        $loop = Loop::get();

        $loop->addPeriodicTimer($interval, function () use ($units, &$positions, $host, $port) {
            foreach ($units as $unit) {
                // Mover la unidad un poco
                $positions[$unit->imei]['lat'] += (rand(-50, 50) / 100000);
                $positions[$unit->imei]['lon'] += (rand(-50, 50) / 100000);

                $lat     = $positions[$unit->imei]['lat'];
                $lon     = $positions[$unit->imei]['lon'];
                $speed   = rand(0, 80);
                $heading = rand(0, 359);
                $alt     = rand(10, 50);

                $frame = $this->buildFrame($unit->imei, $lat, $lon, $speed, $heading, $alt);
                $this->sendFrame($frame, $host, $port);

                $this->line('[' . now()->format('H:i:s') . "] {$unit->name} ({$unit->imei}): {$lat},{$lon} @ {$speed}km/h");
            }
        });

        // Envío inmediato al iniciar
        foreach ($units as $unit) {
            $lat     = $positions[$unit->imei]['lat'];
            $lon     = $positions[$unit->imei]['lon'];
            $frame   = $this->buildFrame($unit->imei, $lat, $lon, 0, 0, 20);
            $this->sendFrame($frame, $host, $port);
        }

        $this->info('Simulador activo. Presiona Ctrl+C para detener.');
        $loop->run();
    }

    private function ensureUnits(int $count): \Illuminate\Support\Collection
    {
        $existing = GpsUnit::where('imei', 'like', 'SIM%')->limit($count)->get();

        if ($existing->count() >= $count) {
            return $existing->take($count);
        }

        $types  = ['patrol', 'covert'];
        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444'];

        for ($i = $existing->count() + 1; $i <= $count; $i++) {
            GpsUnit::firstOrCreate(
                ['imei' => "SIM{$i}000000000000"],
                [
                    'name'      => "Simulador {$i}",
                    'plate'     => "SIM-{$i}000",
                    'unit_type' => $types[$i % 2],
                    'color'     => $colors[($i - 1) % count($colors)],
                    'is_active' => true,
                ]
            );
        }

        return GpsUnit::where('imei', 'like', 'SIM%')->limit($count)->get();
    }

    private function buildFrame(string $imei, float $lat, float $lon, int $speed, int $heading, int $alt): string
    {
        $ns   = $lat >= 0 ? 'N' : 'S';
        $ew   = $lon >= 0 ? 'E' : 'W';
        $date = now()->format('Ymd');
        $time = now()->format('His');

        return sprintf(
            'imei:%s,tracker,%s,%s,%s,%s,%s,%s,%d,%d,%d;',
            $imei, $date, $time,
            abs($lat), $ns,
            abs($lon), $ew,
            $speed, $heading, $alt
        );
    }

    private function sendFrame(string $frame, string $host, int $port): void
    {
        $socket = @fsockopen($host, $port, $errno, $errstr, 2);
        if ($socket) {
            fwrite($socket, $frame);
            fclose($socket);
        } else {
            $this->warn("No se pudo conectar a {$host}:{$port} — ¿el listener está corriendo?");
        }
    }
}
