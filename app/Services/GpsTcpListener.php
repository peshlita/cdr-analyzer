<?php

namespace App\Services;

use App\Models\GeofenceAlert;
use App\Models\Geofence;
use App\Models\GpsPosition;
use App\Models\GpsUnit;
use Illuminate\Support\Facades\Log;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use React\Socket\ConnectionInterface;

class GpsTcpListener
{
    private string $host;
    private int $port;

    public function __construct(string $host = '0.0.0.0', int $port = 5001)
    {
        $this->host = $host;
        $this->port = $port;
    }

    public function start(callable $onLog = null): void
    {
        $log = $onLog ?? fn(string $msg) => Log::info('[GPS] ' . $msg);

        $loop   = Loop::get();
        $server = new SocketServer("{$this->host}:{$this->port}", [], $loop);

        $log("Servidor TCP activo en {$this->host}:{$this->port}");

        $server->on('connection', function (ConnectionInterface $conn) use ($log) {
            $remote = $conn->getRemoteAddress() ?? 'unknown';
            $log("Conexión entrante: {$remote}");

            $buffer = '';

            $conn->on('data', function (string $data) use ($conn, $remote, $log, &$buffer) {
                $buffer .= $data;

                // Procesar tramas completas (terminan en ; o \n)
                while (($pos = strpbrk($buffer, ";\n")) !== false) {
                    $end    = strpos($buffer, $pos[0]);
                    $frame  = substr($buffer, 0, $end + 1);
                    $buffer = substr($buffer, $end + 1);
                    $this->processFrame(trim($frame), $conn, $log);
                }
            });

            $conn->on('error', function (\Exception $e) use ($remote, $log) {
                $log("Error en {$remote}: " . $e->getMessage());
            });

            $conn->on('close', function () use ($remote, $log) {
                $log("Conexión cerrada: {$remote}");
            });
        });

        $server->on('error', function (\Exception $e) use ($log) {
            $log("Error del servidor: " . $e->getMessage());
        });

        $loop->run();
    }

    private function processFrame(string $frame, ConnectionInterface $conn, callable $log): void
    {
        if (empty($frame)) {
            return;
        }

        // TK905 handshake: ##,imei:IMEI,A;
        if (str_starts_with($frame, '##')) {
            if (preg_match('/##,imei:(\d+),A/', $frame, $m)) {
                $conn->write('LOAD');
                $log("Handshake IMEI: {$m[1]}");
            }
            return;
        }

        // TK905 heartbeat
        if (str_contains($frame, '##')) {
            $conn->write('ON');
            return;
        }

        // Trama de posición:
        // imei:IMEI,tracker,YYYYMMDD,HHMMSS,LAT,N/S,LON,E/W,SPEED,HEADING,ALTITUDE;
        if (preg_match(
            '/imei:([^,]+),tracker,(\d{8}),(\d{6}),(\d+\.?\d*),([NS]),(\d+\.?\d*),([EW]),(\d+\.?\d*),(\d+\.?\d*),(\d+\.?\d*)/i',
            $frame,
            $m
        )) {
            [, $imei, $date, $time, $lat, $ns, $lon, $ew, $speed, $heading, $altitude] = $m;

            $lat = (float)$lat * ($ns === 'S' ? -1 : 1);
            $lon = (float)$lon * ($ew === 'W' ? -1 : 1);

            $receivedAt = \Carbon\Carbon::createFromFormat(
                'Ymd His',
                "{$date} {$time}",
                'UTC'
            );

            $this->savePosition($imei, $lat, $lon, (float)$speed, (float)$heading, (float)$altitude, $receivedAt, $frame, $log);

            $conn->write('ON');
        } else {
            $log("Trama no reconocida: " . substr($frame, 0, 80));
        }
    }

    private function savePosition(
        string $imei,
        float $lat,
        float $lon,
        float $speed,
        float $heading,
        float $altitude,
        \Carbon\Carbon $receivedAt,
        string $rawData,
        callable $log
    ): void {
        // El listener corre sin usuario autenticado; withoutGlobalScope deja
        // explícito que la búsqueda es global (entre todas las instituciones).
        $unit = GpsUnit::withoutGlobalScope('tenant')->where('imei', $imei)->first();

        if (!$unit) {
            // Multi-tenant: no se autocrean unidades (quedarían sin institución).
            // El IMEI debe registrarse previamente por el admin de su tenant.
            $log("IMEI no registrado — trama rechazada: {$imei}");
            return;
        }

        if (!$unit->is_active) {
            $log("Unidad inactiva ignorada: {$imei}");
            return;
        }

        // Guardar posición heredando tenant_id Y user_id (propietario) de la unidad
        $position = GpsPosition::create([
            'gps_unit_id' => $unit->id,
            'tenant_id'   => $unit->tenant_id,
            'user_id'     => $unit->user_id,
            'lat'         => $lat,
            'lon'         => $lon,
            'speed'       => $speed,
            'heading'     => $heading,
            'altitude'    => $altitude,
            'raw_data'    => $rawData,
            'received_at' => $receivedAt,
        ]);

        // Actualizar última posición en la unidad
        $unit->update([
            'last_lat'     => $lat,
            'last_lon'     => $lon,
            'last_speed'   => $speed,
            'last_seen_at' => $receivedAt,
        ]);

        $log("Posición guardada — {$unit->name} ({$imei}): {$lat},{$lon} @ {$speed}km/h");

        // Verificar geocercas
        $this->checkGeofences($unit, $position, $log);
    }

    private function checkGeofences(GpsUnit $unit, GpsPosition $position, callable $log): void
    {
        // Solo geocercas del MISMO propietario que la unidad (aislamiento por usuario).
        $geofences = Geofence::withoutGlobalScope('tenant')
            ->where('user_id', $unit->user_id)
            ->where('is_active', true)
            ->get();

        foreach ($geofences as $fence) {
            $inside = $fence->containsPoint($position->lat, $position->lon);

            // Buscar último estado del vehículo en esta geocerca
            $lastAlert = GeofenceAlert::withoutGlobalScope('tenant')
                ->where('gps_unit_id', $unit->id)
                ->where('geofence_id', $fence->id)
                ->latest('triggered_at')
                ->first();

            $wasInside = $lastAlert && $lastAlert->alert_type === 'enter';

            if ($inside && !$wasInside && $fence->alert_on_enter) {
                GeofenceAlert::create([
                    'geofence_id'  => $fence->id,
                    'gps_unit_id'  => $unit->id,
                    'tenant_id'    => $unit->tenant_id,
                    'user_id'      => $unit->user_id,
                    'alert_type'   => 'enter',
                    'lat'          => $position->lat,
                    'lon'          => $position->lon,
                    'triggered_at' => $position->received_at,
                ]);
                $log("ALERTA ENTRADA — {$unit->name} entró a {$fence->name}");
            }

            if (!$inside && $wasInside && $fence->alert_on_exit) {
                GeofenceAlert::create([
                    'geofence_id'  => $fence->id,
                    'gps_unit_id'  => $unit->id,
                    'tenant_id'    => $unit->tenant_id,
                    'user_id'      => $unit->user_id,
                    'alert_type'   => 'exit',
                    'lat'          => $position->lat,
                    'lon'          => $position->lon,
                    'triggered_at' => $position->received_at,
                ]);
                $log("ALERTA SALIDA — {$unit->name} salió de {$fence->name}");
            }
        }
    }
}
