<?php

namespace App\Http\Controllers;

use App\Models\Geofence;
use App\Models\GeofenceAlert;
use App\Models\GpsPosition;
use App\Models\GpsUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        return view('geoint.report', compact('units'));
    }

    public function apiUnits(): JsonResponse
    {
        $units = GpsUnit::active()
            ->with(['positions' => fn($q) => $q->latest('received_at')->limit(1)])
            ->get()
            ->map(fn(GpsUnit $u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'plate'       => $u->plate,
                'unit_type'   => $u->unit_type,
                'color'       => $u->color,
                'icon'            => $u->icon ?: 'fa-car',
                'status'          => $u->extended_status,
                'status_color'    => $u->status_color,
                'status_label'    => $u->status_label,
                'lat'             => $u->last_lat,
                'lon'             => $u->last_lon,
                'speed'           => $u->last_speed ?? 0,
                'heading'         => $u->positions->first()?->heading,
                'last_seen'       => $u->last_seen_at?->locale('es')->diffForHumans() ?? 'Sin datos',
            ]);

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
}
