<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use App\Models\GeofenceAlert;
use App\Models\GpsPosition;
use App\Models\GpsUnit;
use App\Models\GpsVehicle;
use App\Models\GpsVehiclePoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeointController extends Controller
{
    public function index()
    {
        $pendingAlerts = GeofenceAlert::where('acknowledged', false)->count();
        return view('geoint.index', compact('pendingAlerts'));
    }

    public function report()
    {
        $units = GpsUnit::active()->orderBy('name')->get();

        $unitId = request('unit_id');
        $from   = request('from') ? \Carbon\Carbon::parse(request('from')) : null;
        $to     = request('to')   ? \Carbon\Carbon::parse(request('to'))   : null;

        $unit        = null;
        $positions   = collect();
        $summary     = [];
        $stops       = [];
        $timeline    = [];
        $speedAlerts = collect();

        if ($unitId && $from && $to) {
            $data        = $this->computeReportData((int) $unitId, $from, $to);
            $unit        = $data['unit'];
            $positions   = $data['positions'];
            $summary     = $data['summary'];
            $stops       = $data['stops'];
            $timeline    = $data['timeline'];
            $speedAlerts = $data['speedAlerts'];
        }

        return view('geoint.report', compact(
            'units', 'unit', 'positions', 'summary', 'stops',
            'timeline', 'from', 'to', 'unitId', 'speedAlerts'
        ));
    }

    public function exportPdf()
    {
        $unitId = request('unit_id');
        $from   = request('from') ? \Carbon\Carbon::parse(request('from')) : null;
        $to     = request('to')   ? \Carbon\Carbon::parse(request('to'))   : null;

        if (!$unitId || !$from || !$to) {
            return redirect()->route('geoint.report');
        }

        $data      = $this->computeReportData((int) $unitId, $from, $to);
        $unit      = $data['unit'];
        $positions = $data['positions'];
        $summary   = $data['summary'];
        $stops     = $data['stops'];
        $timeline  = $data['timeline'];

        if (!$unit || $positions->isEmpty()) {
            return redirect()->route('geoint.report');
        }

        $mapPng = $this->loadSnapshot($unit->id, 'route');

        $stopMaps = [];
        foreach ($stops as $i => $stop) {
            $stopMaps[$i] = $this->loadSnapshot($unit->id, "stop_{$i}");
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'geoint.report-pdf',
            compact('unit', 'positions', 'summary', 'stops', 'timeline', 'from', 'to', 'mapPng', 'stopMaps')
        )->setPaper('letter', 'portrait');

        return $pdf->download('reporte_' . Str::slug($unit->name) . '_' . now()->format('Ymd') . '.pdf');
    }

    /**
     * Saves a Leaflet map screenshot (captured client-side via html2canvas)
     * for later embedding in the GEOINT route PDF report.
     */
    public function saveSnapshot(Request $request): JsonResponse
    {
        $dataUri = $request->input('image', '');
        $type    = (string) $request->input('type', '');
        $unitId  = (int) $request->input('unit_id', 0);

        if ($unitId <= 0
            || !preg_match('/^(route|stop_\d+)$/', $type)
            || !str_starts_with($dataUri, 'data:image/png;base64,')
        ) {
            return response()->json(['success' => false, 'error' => 'Invalid request'], 422);
        }

        $png = base64_decode(str_replace('data:image/png;base64,', '', $dataUri));
        Storage::disk('public')->makeDirectory('snapshots');
        Storage::disk('public')->put("snapshots/geoint_{$unitId}_{$type}.png", $png);

        return response()->json(['success' => true]);
    }

    private function loadSnapshot(int $unitId, string $type): string
    {
        $path = "snapshots/geoint_{$unitId}_{$type}.png";
        if (!Storage::disk('public')->exists($path)) {
            return '';
        }

        return 'data:image/png;base64,' . base64_encode(Storage::disk('public')->get($path));
    }

    private function computeReportData(int $unitId, \Carbon\Carbon $from, \Carbon\Carbon $to): array
    {
        $unit      = GpsUnit::find($unitId);
        $positions = GpsPosition::where('gps_unit_id', $unitId)
            ->whereBetween('received_at', [$from, $to])
            ->orderBy('received_at')
            ->get();

        $summary     = [];
        $stops       = [];
        $timeline    = [];
        $speedAlerts = collect();

        if ($positions->isNotEmpty()) {
            // Distance (haversine over all consecutive pairs)
            $totalKm = 0.0;
            $prevPos = null;
            foreach ($positions as $pos) {
                if ($prevPos) {
                    $totalKm += $this->haversineKm(
                        (float) $prevPos->lat, (float) $prevPos->lon,
                        (float) $pos->lat,     (float) $pos->lon
                    );
                }
                $prevPos = $pos;
            }

            $stops    = $this->calculateStops($positions);
            $timeline = $this->buildTimeline($positions, $stops);

            $speedAlerts     = $positions->filter(fn($p) => $p->speed > 100)->values();
            $maxSpeed        = $positions->max('speed') ?? 0;
            $movingPositions = $positions->filter(fn($p) => $p->speed >= 10);
            $movingMinutes   = $movingPositions->count() * 10 / 60;
            $pernoctas       = count(array_filter($stops, fn($s) => $s['type'] === 'overnight'));

            $summary = [
                'total'       => $positions->count(),
                'totalKm'     => round($totalKm, 2),
                'stops'       => count($stops),
                'maxSpeed'    => round($maxSpeed, 1),
                'avg_spd'     => round($positions->where('speed', '>', 0)->avg('speed') ?? 0, 1),
                'moving_time' => round($movingMinutes),
                'pernoctas'   => $pernoctas,
            ];
        }

        return compact('unit', 'positions', 'summary', 'stops', 'timeline', 'speedAlerts');
    }

    private function calculateStops($positions): array
    {
        $grid = [];

        foreach ($positions as $pos) {
            $gridLat = round($pos->lat / 0.003) * 0.003;
            $gridLon = round($pos->lon / 0.003) * 0.003;
            $key     = round($gridLat, 6) . ',' . round($gridLon, 6);

            if (!isset($grid[$key])) {
                $grid[$key] = [
                    'lat'       => round($gridLat, 6),
                    'lon'       => round($gridLon, 6),
                    'count'     => 0,
                    'positions' => [],
                ];
            }
            $grid[$key]['count']++;
            $grid[$key]['positions'][] = $pos;
        }

        $stops = [];
        foreach ($grid as $zone) {
            if ($zone['count'] >= 15) {
                $sorted   = collect($zone['positions'])->sortBy('received_at');
                $first    = $sorted->first();
                $last     = $sorted->last();
                $duration = (int) Carbon::parse($first->received_at)
                    ->diffInMinutes(Carbon::parse($last->received_at));
                $hour = (int) Carbon::parse($first->received_at)->hour;
                $type = ($duration >= 60 && ($hour >= 22 || $hour < 6))
                    ? 'overnight'
                    : ($duration >= 20 ? 'long' : 'short');

                $stops[] = [
                    'lat'        => $zone['lat'],
                    'lon'        => $zone['lon'],
                    'start'      => Carbon::parse($first->received_at)->format('d/m/Y H:i'),
                    'end'        => Carbon::parse($last->received_at)->format('d/m/Y H:i'),
                    'duration'   => $duration,
                    'count'      => $zone['count'],
                    'type'       => $type,
                    'type_label' => $type === 'overnight' ? 'Pernocta'
                                  : ($type === 'long' ? 'Parada larga' : 'Zona frecuente'),
                ];
            }
        }

        usort($stops, fn($a, $b) => $b['count'] - $a['count']);

        return array_slice($stops, 0, 20);
    }

    private function buildTimeline($positions, array $stops): array
    {
        $timeline = [];

        $first = $positions->first();
        if ($first) {
            $timeline[] = [
                'time'  => Carbon::parse($first->received_at)->format('H:i'),
                'type'  => 'start',
                'label' => 'Inicio del recorrido',
                'lat'   => (float) $first->lat,
                'lon'   => (float) $first->lon,
            ];
        }

        foreach ($stops as $stop) {
            $timeline[] = [
                'time'     => Carbon::createFromFormat('d/m/Y H:i', $stop['start'])->format('H:i'),
                'type'     => $stop['type'],
                'label'    => $stop['type_label'] . ' — ' . $stop['count'] . ' registros',
                'lat'      => $stop['lat'],
                'lon'      => $stop['lon'],
                'duration' => $stop['duration'],
            ];
        }

        $last = $positions->last();
        if ($last) {
            $timeline[] = [
                'time'  => Carbon::parse($last->received_at)->format('H:i'),
                'type'  => 'end',
                'label' => 'Fin del recorrido',
                'lat'   => (float) $last->lat,
                'lon'   => (float) $last->lon,
            ];
        }

        usort($timeline, fn($a, $b) => strcmp($a['time'], $b['time']));

        return $timeline;
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    public function apiUnits(): JsonResponse
    {
        // Caché por usuario: evita que un usuario reciba unidades cacheadas de otro.
        $units = Cache::remember('geoint_units_map_' . auth()->id(), 8, function () {
            return GpsUnit::active()
                ->with('latestPosition')
                ->get()
                ->map(fn(GpsUnit $u) => [
                    'id'           => $u->id,
                    'name'         => $u->name,
                    'imei'         => $u->imei,
                    'plate'        => $u->plate,
                    'unit_type'    => $u->unit_type,
                    'color'        => $u->color   ?: '#10b981',
                    'icon'         => $u->icon    ?: 'fa-car',
                    'status'       => $u->extended_status,
                    'status_color' => $u->status_color,
                    'status_label' => $u->status_label,
                    'lat'          => $u->last_lat,
                    'lon'          => $u->last_lon,
                    'speed'        => $u->last_speed ?? 0,
                    'heading'      => $u->latestPosition?->heading,
                    'last_seen'    => $u->last_seen_at?->locale('es')->diffForHumans() ?? 'Sin datos',
                ])
                ->values()
                ->all(); // plain array — avoids Collection serialization issue with serializableClasses=false
        });

        return response()->json($units);
    }

    public function apiUnitHistory(int $id): JsonResponse
    {
        $hours = request()->integer('hours', 24);

        $positions = GpsPosition::where('gps_unit_id', $id)
            ->where('received_at', '>=', now()->subHours($hours))
            ->orderBy('received_at')
            ->get(['lat', 'lon', 'speed', 'heading', 'received_at'])
            ->map(fn($p) => [
                'lat'         => $p->lat,
                'lon'         => $p->lon,
                'speed'       => $p->speed,
                'heading'     => $p->heading,
                'received_at' => $p->received_at->toIso8601String(),
            ]);

        return response()->json($positions);
    }

    public function apiGeofences(): JsonResponse
    {
        $fences = Geofence::where('is_active', true)
            ->get()
            ->map(fn(Geofence $f) => [
                'id'         => $f->id,
                'name'       => $f->name,
                'type'       => $f->type,
                'center_lat' => $f->center_lat,
                'center_lon' => $f->center_lon,
                'radius'     => $f->radius,
                'coordinates'=> $f->coordinates,
                'color'      => $f->color,
            ]);

        return response()->json($fences);
    }

    public function apiVehiclePoints(int $id): JsonResponse
    {
        $vehicle = GpsVehicle::findOrFail($id);

        if ($vehicle->source === 'live') {
            $pts = GpsPosition::where('gps_unit_id', $vehicle->gps_unit_id)
                ->orderBy('received_at')
                ->limit(2000)
                ->get()
                ->map(fn($p) => [
                    'lat'                   => $p->lat,
                    'lon'                   => $p->lon,
                    'speed'                 => $p->speed,
                    'heading'               => $p->heading,
                    'battery'               => null,
                    'recorded_at'           => (string) $p->received_at,
                    'recorded_at_formatted' => Carbon::parse($p->received_at)->format('d/m/Y H:i:s'),
                ]);
        } else {
            $pts = GpsVehiclePoint::where('vehicle_id', $id)
                ->orderBy('recorded_at')
                ->limit(2000)
                ->get()
                ->map(fn($p) => [
                    'lat'                   => $p->lat,
                    'lon'                   => $p->lon,
                    'speed'                 => $p->speed,
                    'heading'               => $p->heading,
                    'battery'               => $p->battery,
                    'recorded_at'           => (string) $p->recorded_at,
                    'recorded_at_formatted' => Carbon::parse($p->recorded_at)->format('d/m/Y H:i:s'),
                ]);
        }

        return response()->json($pts->values());
    }
}
