<?php

namespace App\Livewire\Geoint;

use App\Models\GeofenceAlert;
use App\Models\Geofence;
use App\Models\GpsUnit;
use Livewire\Component;
use Livewire\WithPagination;

class GeointAlerts extends Component
{
    use WithPagination;

    public ?int $filterUnit     = null;
    public ?int $filterGeofence = null;
    public bool $showAcknowledged = false;

    public function acknowledgeAlert(int $id): void
    {
        GeofenceAlert::where('id', $id)->update([
            'acknowledged'    => true,
            'acknowledged_by' => auth()->id(),
            'acknowledged_at' => now(),
        ]);
    }

    public function acknowledgeAll(): void
    {
        GeofenceAlert::where('acknowledged', false)->update([
            'acknowledged'    => true,
            'acknowledged_by' => auth()->id(),
            'acknowledged_at' => now(),
        ]);
    }

    public function updatingFilterUnit(): void    { $this->resetPage(); }
    public function updatingFilterGeofence(): void { $this->resetPage(); }
    public function updatingShowAcknowledged(): void { $this->resetPage(); }

    public function render()
    {
        $query = GeofenceAlert::with(['unit', 'geofence', 'acknowledgedByUser'])
            ->latest('triggered_at');

        if ($this->filterUnit) {
            $query->where('gps_unit_id', $this->filterUnit);
        }
        if ($this->filterGeofence) {
            $query->where('geofence_id', $this->filterGeofence);
        }
        if (!$this->showAcknowledged) {
            $query->where('acknowledged', false);
        }

        return view('livewire.geoint.alerts', [
            'alerts'       => $query->paginate(20),
            'units'        => GpsUnit::orderBy('name')->get(),
            'geofences'    => Geofence::orderBy('name')->get(),
            'pendingCount' => GeofenceAlert::where('acknowledged', false)->count(),
        ])->extends('layouts.app')->section('content');
    }
}
