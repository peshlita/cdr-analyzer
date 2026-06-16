<?php

namespace App\Livewire\Geoint;

use App\Models\GpsPosition;
use App\Models\GpsUnit;
use App\Models\GpsVehicle;
use App\Models\GpsVehiclePoint;
use App\Services\GpsRouteParser;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;

class GeointVehicles extends Component
{
    use WithFileUploads;

    public ?int $selectedVehicleId = null;
    public ?GpsVehicle $selectedVehicle = null;
    public array $points = [];

    // Modal de importación
    public bool $showImportForm = false;
    public string $name = '';
    public string $description = '';
    public string $color = '#f59e0b';
    public string $unit_type = 'covert';
    public string $icon = 'fa-car';
    public ?int $gps_unit_id = null;

    public $uploadedFile = null;
    public bool $importing = false;
    public string $importError = '';
    public array $importPreview = [];

    // Registro de GPS TK905 en vivo
    public bool $showGpsForm = false;
    public string $gps_name = '';
    public string $gps_imei = '';
    public string $gps_plate = '';
    public string $gps_unit_type = 'patrol';
    public string $gps_color = '#3b82f6';
    public string $gps_icon = 'fa-car';
    public string $gps_sim = '';
    public string $gps_notes = '';
    public string $gpsError = '';

    // Modal de instrucciones de configuración tras registrar
    public bool $showGpsInstructions = false;
    public string $registeredName = '';

    public function mount(): void
    {
        // Auto-crear vehículos "live" a partir de las unidades TCP existentes
        GpsUnit::active()->get()->each(function (GpsUnit $unit) {
            GpsVehicle::firstOrCreate(
                ['gps_unit_id' => $unit->id],
                [
                    'name'        => $unit->name,
                    'unit_type'   => $unit->unit_type,
                    'color'       => $unit->color,
                    'icon'        => $unit->icon,
                    'source'      => 'live',
                    'imported_by' => auth()->id(),
                ]
            );
        });
    }

    public function selectVehicle(int $id): void
    {
        $vehicle = GpsVehicle::with('gpsUnit')->find($id);
        if (!$vehicle) {
            return;
        }

        $this->selectedVehicle   = $vehicle;
        $this->selectedVehicleId = $id;

        if ($vehicle->source === 'live') {
            $positions = GpsPosition::where('gps_unit_id', $vehicle->gps_unit_id)
                ->orderBy('received_at', 'desc')
                ->limit(2000)
                ->get()
                ->reverse()
                ->map(fn ($p) => [
                    'lat'                   => $p->lat,
                    'lon'                   => $p->lon,
                    'speed'                 => $p->speed,
                    'heading'               => $p->heading,
                    'battery'               => null,
                    'recorded_at'           => (string) $p->received_at,
                    'recorded_at_formatted' => Carbon::parse($p->received_at)->format('d/m/Y H:i:s'),
                ]);
        } else {
            $positions = GpsVehiclePoint::where('vehicle_id', $id)
                ->orderBy('recorded_at')
                ->limit(2000)
                ->get()
                ->map(fn ($p) => [
                    'lat'                   => $p->lat,
                    'lon'                   => $p->lon,
                    'speed'                 => $p->speed,
                    'heading'               => $p->heading,
                    'battery'               => $p->battery,
                    'recorded_at'           => (string) $p->recorded_at,
                    'recorded_at_formatted' => Carbon::parse($p->recorded_at)->format('d/m/Y H:i:s'),
                ]);
        }

        $this->points = $positions->values()->toArray();

        $this->dispatch(
            'vehicleSelected',
            points: $this->points,
            vehicle: $vehicle->toArray()
        );
    }

    public function updatedUploadedFile(): void
    {
        $this->importError   = '';
        $this->importPreview = [];

        if (!$this->uploadedFile) {
            return;
        }

        try {
            $ext     = strtolower($this->uploadedFile->getClientOriginalExtension());
            $tmpPath = $this->uploadedFile->getRealPath();
            $parser  = new GpsRouteParser();
            $pts     = $parser->parse($tmpPath, $ext);

            if (empty($pts)) {
                $this->importError = 'No se encontraron puntos válidos en el archivo.';
                return;
            }

            $this->importPreview = array_slice($pts, 0, 3);
            $this->dispatch('previewReady', count: count($pts));
        } catch (\Exception $e) {
            $this->importError = 'Error al leer el archivo: ' . $e->getMessage();
        }
    }

    public function importVehicle(): void
    {
        $this->importError = '';

        if (trim($this->name) === '') {
            $this->importError = 'El nombre del vehículo es obligatorio.';
            return;
        }
        if (!$this->uploadedFile) {
            $this->importError = 'Selecciona un archivo de ruta.';
            return;
        }

        $this->importing = true;

        try {
            $ext          = strtolower($this->uploadedFile->getClientOriginalExtension());
            $originalName = $this->uploadedFile->getClientOriginalName();
            $tmpPath      = $this->uploadedFile->getRealPath();
            $parser       = new GpsRouteParser();
            $pts          = $parser->parse($tmpPath, $ext);

            if (empty($pts)) {
                $this->importError = 'No se encontraron puntos válidos.';
                return;
            }

            $dates = collect($pts)->pluck('recorded_at');

            $vehicle = GpsVehicle::create([
                'name'          => $this->name,
                'description'   => $this->description ?: null,
                'color'         => $this->color ?: '#f59e0b',
                'unit_type'     => $this->unit_type ?: 'covert',
                'icon'          => $this->icon ?: 'fa-car',
                'source'        => 'imported',
                'source_file'   => $originalName,
                'source_format' => $ext,
                'total_points'  => count($pts),
                'date_from'     => $dates->min(),
                'date_to'       => $dates->max(),
                'imported_by'   => auth()->id(),
            ]);

            // insert() no dispara el creating hook → tenant_id y user_id explícitos.
            collect($pts)->chunk(500)->each(fn ($chunk) =>
                GpsVehiclePoint::insert(
                    $chunk->map(fn ($p) => array_merge($p, [
                        'vehicle_id' => $vehicle->id,
                        'tenant_id'  => $vehicle->tenant_id,
                        'user_id'    => $vehicle->user_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]))->toArray()
                )
            );

            $this->showImportForm = false;
            $this->reset(['name', 'description', 'uploadedFile', 'importPreview', 'importError']);
            $this->color     = '#f59e0b';
            $this->unit_type = 'covert';

            $this->selectVehicle($vehicle->id);
        } catch (\Exception $e) {
            $this->importError = 'Error: ' . $e->getMessage();
        } finally {
            $this->importing = false;
        }
    }

    public function deleteVehicle(int $id): void
    {
        $v = GpsVehicle::find($id);
        if ($v && $v->source === 'imported') {
            $v->points()->delete();
            $v->delete();

            if ($this->selectedVehicleId === $id) {
                $this->selectedVehicleId = null;
                $this->selectedVehicle   = null;
                $this->points            = [];
            }
        }
    }

    public function registerGps(): void
    {
        $this->gpsError = '';

        $data = $this->validate([
            'gps_name'      => 'required|string|max:100',
            'gps_imei'      => 'required|string|max:30|unique:gps_units,imei',
            'gps_plate'     => 'nullable|string|max:20',
            'gps_unit_type' => 'required|in:patrol,covert',
            'gps_color'     => 'required|string|max:20',
            'gps_icon'      => 'required|string|max:50',
            'gps_sim'       => 'nullable|string|max:20',
            'gps_notes'     => 'nullable|string|max:500',
        ], [], [
            'gps_name'  => 'nombre',
            'gps_imei'  => 'IMEI',
        ]);

        // El trait BelongsToTenant asigna tenant_id automáticamente desde el usuario.
        $unit = GpsUnit::create([
            'name'       => $data['gps_name'],
            'imei'       => $data['gps_imei'],
            'plate'      => $data['gps_plate'] ?: null,
            'unit_type'  => $data['gps_unit_type'],
            'color'      => $data['gps_color'],
            'icon'       => $data['gps_icon'],
            'sim_number' => $data['gps_sim'] ?: null,
            'notes'      => $data['gps_notes'] ?: null,
            'is_active'  => true,
        ]);

        // Crear su vehículo "live" asociado (mismo tenant vía trait).
        GpsVehicle::firstOrCreate(
            ['gps_unit_id' => $unit->id],
            [
                'name'        => $unit->name,
                'unit_type'   => $unit->unit_type,
                'color'       => $unit->color,
                'icon'        => $unit->icon,
                'source'      => 'live',
                'imported_by' => auth()->id(),
            ]
        );

        $this->registeredName = $unit->name;
        $this->showGpsForm = false;
        $this->reset([
            'gps_name', 'gps_imei', 'gps_plate', 'gps_sim', 'gps_notes', 'gpsError',
        ]);
        $this->gps_unit_type = 'patrol';
        $this->gps_color     = '#3b82f6';
        $this->gps_icon      = 'fa-car';

        // Mostrar instrucciones de configuración del TK905.
        $this->showGpsInstructions = true;
    }

    public function render()
    {
        $vehicles = GpsVehicle::with('gpsUnit')
            ->where('is_active', true)
            ->orderBy('source')
            ->orderBy('name')
            ->get();

        return view('livewire.geoint.geoint-vehicles', [
            'vehicles'   => $vehicles,
            'serverHost' => config('gps.tcp_public_host'),
            'serverPort' => config('gps.tcp_port'),
        ])
            ->extends('layouts.app')
            ->section('content');
    }
}
