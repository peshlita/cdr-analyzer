<?php

namespace App\Livewire\Geoint;

use App\Models\GpsUnit;
use Livewire\Component;

class GeointUnits extends Component
{
    // Form fields
    public ?int $editingId = null;
    public string $name      = '';
    public string $imei      = '';
    public string $plate     = '';
    public string $unit_type = 'patrol';
    public string $sim_number= '';
    public string $color     = '#3b82f6';
    public string $icon      = 'fa-car';
    public string $notes     = '';
    public bool   $showForm  = false;

    protected function rules(): array
    {
        $uniqueImei = 'unique:gps_units,imei';
        if ($this->editingId) {
            $uniqueImei .= ',' . $this->editingId;
        }

        return [
            'name'      => 'required|string|max:100',
            'imei'      => "required|string|max:30|{$uniqueImei}",
            'plate'     => 'nullable|string|max:20',
            'unit_type' => 'required|in:patrol,covert',
            'sim_number'=> 'nullable|string|max:20',
            'color'     => 'required|string|max:20',
            'icon'      => 'required|string|max:50',
        ];
    }

    public function openCreate(): void
    {
        $this->reset(['editingId','name','imei','plate','unit_type','sim_number','color','icon','notes']);
        $this->color    = '#3b82f6';
        $this->icon     = 'fa-car';
        $this->unit_type= 'patrol';
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $unit = GpsUnit::findOrFail($id);
        $this->editingId  = $id;
        $this->name       = $unit->name;
        $this->imei       = $unit->imei;
        $this->plate      = $unit->plate ?? '';
        $this->unit_type  = $unit->unit_type;
        $this->sim_number = $unit->sim_number ?? '';
        $this->color      = $unit->color;
        $this->icon       = $unit->icon ?? 'fa-car';
        $this->notes      = $unit->notes ?? '';
        $this->showForm   = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        if ($this->editingId) {
            GpsUnit::findOrFail($this->editingId)->update($data);
            session()->flash('success', 'Unidad actualizada correctamente.');
        } else {
            GpsUnit::create($data);
            session()->flash('success', 'Unidad creada correctamente.');
        }

        $this->showForm = false;
        $this->reset(['editingId','name','imei','plate','unit_type','sim_number','color','icon','notes']);
    }

    public function toggleActive(int $id): void
    {
        $unit = GpsUnit::findOrFail($id);
        $unit->update(['is_active' => !$unit->is_active]);
    }

    public function cancel(): void
    {
        $this->showForm = false;
        $this->reset(['editingId','name','imei','plate','unit_type','sim_number','color','icon','notes']);
    }

    public function render()
    {
        return view('livewire.geoint.units', [
            'units' => GpsUnit::orderBy('name')->get(),
        ])->extends('layouts.app')->section('content');
    }
}
