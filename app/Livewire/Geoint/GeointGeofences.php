<?php

namespace App\Livewire\Geoint;

use App\Models\Geofence;
use Livewire\Component;

class GeointGeofences extends Component
{
    public ?int $editingId       = null;
    public string $name          = '';
    public string $description   = '';
    public string $type          = 'circle';
    public string $center_lat    = '';
    public string $center_lon    = '';
    public string $radius        = '500';
    public string $color         = '#ef4444';
    public bool   $alert_on_enter= true;
    public bool   $alert_on_exit = true;
    public bool   $showForm      = false;

    protected function rules(): array
    {
        return [
            'name'          => 'required|string|max:100',
            'description'   => 'nullable|string',
            'type'          => 'required|in:circle,polygon',
            'center_lat'    => 'required_if:type,circle|nullable|numeric',
            'center_lon'    => 'required_if:type,circle|nullable|numeric',
            'radius'        => 'required_if:type,circle|nullable|numeric|min:10',
            'color'         => 'required|string|max:20',
            'alert_on_enter'=> 'boolean',
            'alert_on_exit' => 'boolean',
        ];
    }

    public function openCreate(): void
    {
        $this->reset(['editingId','name','description','center_lat','center_lon']);
        $this->type           = 'circle';
        $this->radius         = '500';
        $this->color          = '#ef4444';
        $this->alert_on_enter = true;
        $this->alert_on_exit  = true;
        $this->showForm       = true;
        $this->dispatch('formOpened');
    }

    public function openEdit(int $id): void
    {
        $f = Geofence::findOrFail($id);
        $this->editingId      = $id;
        $this->name           = $f->name;
        $this->description    = $f->description ?? '';
        $this->type           = $f->type;
        $this->center_lat     = (string)($f->center_lat ?? '');
        $this->center_lon     = (string)($f->center_lon ?? '');
        $this->radius         = (string)($f->radius ?? '500');
        $this->color          = $f->color;
        $this->alert_on_enter = $f->alert_on_enter;
        $this->alert_on_exit  = $f->alert_on_exit;
        $this->showForm       = true;
        $this->dispatch('formOpened');
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            Geofence::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Geocerca actualizada.');
        } else {
            Geofence::create($data);
            session()->flash('success', 'Geocerca creada.');
        }

        $this->showForm = false;
        $this->reset(['editingId','name','description','center_lat','center_lon','radius']);
    }

    public function toggleActive(int $id): void
    {
        $f = Geofence::findOrFail($id);
        $f->update(['is_active' => !$f->is_active]);
    }

    public function setCoordinates(float $lat, float $lon): void
    {
        $this->center_lat = (string)round($lat, 6);
        $this->center_lon = (string)round($lon, 6);
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->reset(['editingId','name','description','center_lat','center_lon','radius']);
    }

    public function render()
    {
        return view('livewire.geoint.geofences', [
            'geofences' => Geofence::withCount('alerts')->orderBy('name')->get(),
        ])->extends('layouts.app')->section('content');
    }
}
