<?php

namespace App\Livewire\Geoint;

use App\Models\GeofenceAlert;
use App\Models\GpsUnit;
use Livewire\Component;

class GeointMap extends Component
{
    public bool $showCovert = true;
    public ?int $selectedUnit = null;

    public function getUnitsData(): array
    {
        $query = GpsUnit::active();

        if (!$this->showCovert) {
            $query->patrol();
        }

        return $query->get()->map(fn(GpsUnit $u) => [
            'id'          => $u->id,
            'name'        => $u->name,
            'plate'       => $u->plate,
            'unit_type'   => $u->unit_type,
            'color'       => $u->color,
            'status'      => $u->status,
            'status_color'=> $u->status_color,
            'status_label'=> $u->status_label,
            'lat'         => $u->last_lat,
            'lon'         => $u->last_lon,
            'speed'       => $u->last_speed ?? 0,
            'last_seen'   => $u->last_seen_at?->locale('es')->diffForHumans() ?? 'Sin datos',
        ])->toArray();
    }

    public function getUnitHistory(int $unitId, int $hours = 24): array
    {
        return \App\Models\GpsPosition::where('gps_unit_id', $unitId)
            ->where('received_at', '>=', now()->subHours($hours))
            ->orderBy('received_at')
            ->get(['lat', 'lon', 'speed', 'received_at'])
            ->map(fn($p) => [
                'lat'   => $p->lat,
                'lon'   => $p->lon,
                'speed' => $p->speed,
                'time'  => $p->received_at->format('H:i:s'),
            ])->toArray();
    }

    public function acknowledgeAlert(int $alertId): void
    {
        GeofenceAlert::where('id', $alertId)
            ->where('acknowledged', false)
            ->update([
                'acknowledged'    => true,
                'acknowledged_by' => auth()->id(),
                'acknowledged_at' => now(),
            ]);
    }

    public function selectUnit(?int $id): void
    {
        $this->selectedUnit = $id;
    }

    public function render()
    {
        $pendingAlerts = GeofenceAlert::where('acknowledged', false)
            ->with(['unit', 'geofence'])
            ->latest('triggered_at')
            ->limit(10)
            ->get();

        return view('livewire.geoint.map', [
            'units'         => $this->getUnitsData(),
            'pendingAlerts' => $pendingAlerts,
            'alertCount'    => GeofenceAlert::where('acknowledged', false)->count(),
        ])->extends('layouts.app')->section('content');
    }
}
