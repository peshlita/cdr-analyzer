<?php

namespace App\Livewire;

use App\Models\CdrRecord;
use App\Models\PhoneContact;
use Livewire\Component;
use Livewire\WithFileUploads;

class CsvUploader extends Component
{
    use WithFileUploads;

    public $csvFile = null;
    public $status = 'idle'; // idle | uploading | done | error
    public $message = '';
    public $imported = 0;
    public $skipped = 0;
    public $progress = 0;

    protected $rules = [
        'csvFile' => 'required|file|mimes:csv,txt|max:51200',
    ];

    public function updatedCsvFile(): void
    {
        $errors = $this->getErrorBag();
        $errors->forget('csvFile');

        $this->validateOnly('csvFile');
        $this->upload();
    }

    public function upload()
    {
        $this->validate();

        $this->status = 'uploading';
        $this->message = 'Procesando archivo...';
        $this->imported = 0;
        $this->skipped = 0;

        try {
            $path = $this->csvFile->getRealPath();
            $handle = fopen($path, 'r');

            if (!$handle) {
                throw new \Exception('No se pudo abrir el archivo.');
            }

            // Skip first 4 metadata lines
            for ($i = 0; $i < 4; $i++) {
                fgetcsv($handle);
            }

            // Read header row
            $headers = fgetcsv($handle);
            if (!$headers) {
                throw new \Exception('No se encontró encabezado en la fila 5.');
            }

            // Normalize headers
            $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

            // Clear existing records
            CdrRecord::truncate();

            $chunk = [];
            $chunkSize = 500;

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 3) {
                    $this->skipped++;
                    continue;
                }

                $data = array_combine($headers, array_pad($row, count($headers), null));

                $record = $this->mapRow($data);
                if ($record) {
                    $chunk[] = $record;
                    if (count($chunk) >= $chunkSize) {
                        CdrRecord::insert($chunk);
                        $this->imported += count($chunk);
                        $chunk = [];
                    }
                } else {
                    $this->skipped++;
                }
            }

            if (!empty($chunk)) {
                CdrRecord::insert($chunk);
                $this->imported += count($chunk);
            }

            fclose($handle);

            // Sync unique phone numbers to phone_contacts
            $this->syncContacts();

            $this->status = 'done';
            $this->message = "Importación completada. {$this->imported} registros importados, {$this->skipped} omitidos.";
        } catch (\Exception $e) {
            $this->status = 'error';
            $this->message = 'Error: ' . $e->getMessage();
        }
    }

    private function mapRow(array $data): ?array
    {
        $now = now()->toDateTimeString();

        $parseDate = function (?string $val): ?string {
            if (!$val || $val === '-') return null;
            try {
                return \Carbon\Carbon::createFromFormat('d/m/Y', trim($val))->format('Y-m-d');
            } catch (\Exception) {
                try {
                    return \Carbon\Carbon::parse(trim($val))->format('Y-m-d');
                } catch (\Exception) {
                    return null;
                }
            }
        };

        $parseCoord = function (?string $val): ?float {
            if (!$val || trim($val) === '-' || trim($val) === '') return null;
            $v = floatval(str_replace(',', '.', $val));
            return ($v == 0.0) ? null : $v;
        };

        $parseInt = function (?string $val): int {
            if (!$val || trim($val) === '-') return 0;
            return (int) $val;
        };

        return [
            'number_a'  => $data['number_a'] ?? null,
            'lat_a'     => $parseCoord($data['lat_a'] ?? null),
            'lon_a'     => $parseCoord($data['lon_a'] ?? null),
            'azimuth_a' => $parseCoord($data['azimuth_a'] ?? null),
            'imei_a'    => (!empty($data['imei_a']) && $data['imei_a'] !== '-') ? trim($data['imei_a']) : null,
            'imsi_a'    => (!empty($data['imsi_a']) && $data['imsi_a'] !== '-') ? trim($data['imsi_a']) : null,
            'number_b'  => (!empty($data['number_b']) && $data['number_b'] !== '-') ? trim($data['number_b']) : null,
            'lat_b'     => $parseCoord($data['lat_b'] ?? null),
            'lon_b'     => $parseCoord($data['lon_b'] ?? null),
            'azimuth_b' => $parseCoord($data['azimuth_b'] ?? null),
            'imei_b'    => (!empty($data['imei_b']) && $data['imei_b'] !== '-') ? trim($data['imei_b']) : null,
            'imsi_b'    => (!empty($data['imsi_b']) && $data['imsi_b'] !== '-') ? trim($data['imsi_b']) : null,
            'type'      => (!empty($data['type']) && $data['type'] !== '-') ? trim($data['type']) : null,
            'direction' => (!empty($data['direction']) && $data['direction'] !== '-') ? trim($data['direction']) : null,
            'duration'  => $parseInt($data['duration'] ?? null),
            'date'      => $parseDate($data['date'] ?? null),
            'hour'      => (!empty($data['hour']) && $data['hour'] !== '-') ? trim($data['hour']) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function syncContacts(): void
    {
        $numbers = CdrRecord::selectRaw('DISTINCT number_a as phone')
            ->whereNotNull('number_a')
            ->pluck('phone')
            ->merge(
                CdrRecord::selectRaw('DISTINCT number_b as phone')
                    ->whereNotNull('number_b')
                    ->pluck('phone')
            )
            ->unique()
            ->filter();

        foreach ($numbers as $number) {
            PhoneContact::firstOrCreate(['phone_number' => $number]);
        }

        // Auto-detect target number (most frequent in number_a)
        $target = CdrRecord::selectRaw('number_a, COUNT(*) as cnt')
            ->whereNotNull('number_a')
            ->groupBy('number_a')
            ->orderByDesc('cnt')
            ->value('number_a');
        if ($target) {
            \App\Models\Setting::set('target_number', $target);
        }
    }

    public function render()
    {
        return view('livewire.csv-uploader')
            ->extends('layouts.app')
            ->section('content');
    }
}
