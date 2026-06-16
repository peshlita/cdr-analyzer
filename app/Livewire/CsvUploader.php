<?php

namespace App\Livewire;

use App\Models\CdrBatch;
use App\Models\CdrRecord;
use App\Models\PhoneContact;
use App\Models\Setting;
use App\Services\CdrCrossAnalyzer;
use App\Services\CdrParser;
use Livewire\Component;
use Livewire\WithFileUploads;

class CsvUploader extends Component
{
    use WithFileUploads;

    public $file = null;

    // 1=subir, 2=preview, 3=importando, 4=resultado
    public int $step = 1;
    public string $batchName = '';

    // Resultado del parser (resumen liviano para el preview; las filas se
    // re-parsean en import() para no serializar miles de filas en Livewire).
    public array $detectedFields = [];
    public array $previewRows = [];
    public array $warnings = [];
    public array $summary = [];
    public array $mainDetection = [];
    public string $encoding = '';
    public int $totalRows = 0;

    public int $importedCount = 0;
    public int $skippedCount = 0;
    public string $importError = '';
    public ?string $lastBatchId = null;
    public array $crossResult = ['common_contacts' => [], 'direct_communication' => []];

    protected array $fieldLabels = [
        'phone_main'  => 'Número principal',
        'record_type' => 'Tipo',
        'number_a'    => 'Número A',
        'number_b'    => 'Número B',
        'date'        => 'Fecha',
        'time'        => 'Hora',
        'duration'    => 'Duración',
        'imei'        => 'IMEI',
        'lat'         => 'Latitud',
        'lon'         => 'Longitud',
        'azimuth'     => 'Azimuth',
    ];

    protected $rules = [
        'file' => 'required|file|mimes:csv,txt|max:51200',
    ];

    public function updatedFile(): void
    {
        $this->getErrorBag()->forget('file');
        $this->importError = '';

        try {
            $this->validateOnly('file');
        } catch (\Throwable $e) {
            return;
        }

        try {
            $parser = new CdrParser();
            $result = $parser->parseFile($this->file->getRealPath());

            $this->encoding  = $result['encoding'];
            $this->totalRows = $result['total'];
            $this->summary   = $this->normalizeSummary($result['summary']);
            $this->warnings  = $result['warnings'];

            $this->mainDetection = [
                'number'      => $result['summary']['main_number'] ?? null,
                'source'      => $result['summary']['main_source'] ?? 'none',
                'appearances' => $result['summary']['main_appearances'] ?? 0,
                'total'       => $result['total'],
            ];

            // Campos detectados → etiqueta de la cabecera original
            $this->detectedFields = [];
            foreach ($result['mapping'] as $idx => $internal) {
                $this->detectedFields[$internal] = $result['headers_found'][$idx] ?? $internal;
            }

            // Preview: primeras 5 filas normalizadas (Carbon → string)
            $this->previewRows = array_map(function ($r) {
                $r['call_datetime'] = $r['call_datetime']?->format('d/m/Y H:i:s');
                return $r;
            }, array_slice($result['rows'], 0, 5));

            if ($this->batchName === '') {
                $this->batchName = pathinfo($this->file->getClientOriginalName(), PATHINFO_FILENAME);
            }

            $this->step = 2;
        } catch (\Throwable $e) {
            $this->importError = 'Error al procesar el archivo: ' . $e->getMessage();
            $this->step = 1;
        }
    }

    public function import(): void
    {
        if (!$this->file) {
            $this->importError = 'No hay archivo cargado.';
            return;
        }
        if (trim($this->batchName) === '') {
            $this->importError = 'Asigna un nombre a la sábana.';
            return;
        }

        $this->step = 3;
        $this->importError = '';

        try {
            $parser = new CdrParser();
            $result = $parser->parseFile($this->file->getRealPath());

            $rows      = $result['rows'];
            $batchId   = $result['batch_id'];
            $tenantId  = auth()->user()?->tenant_id;
            $userId    = auth()->id();
            $now       = now()->toDateTimeString();
            $source    = trim($this->batchName);

            $imported = 0;
            $skipped  = 0;
            $phoneMain = null;
            $contacts  = [];
            $chunk = [];

            foreach ($rows as $r) {
                // Datos mínimos: al menos un número
                if (empty($r['number_a']) && empty($r['number_b'])) {
                    $skipped++;
                    continue;
                }
                $phoneMain = $phoneMain ?: $r['phone_main'];

                foreach ([$r['number_a'], $r['number_b'], $r['contact_number']] as $n) {
                    $c = $this->cleanPhone($n);
                    if ($c !== '') $contacts[$c] = true;
                }

                $chunk[] = $this->toDbRow($r, $parser, $source, $tenantId, $userId, $now);
                $imported++;

                if (count($chunk) >= 500) {
                    CdrRecord::insert($chunk);
                    $chunk = [];
                }
            }
            if (!empty($chunk)) {
                CdrRecord::insert($chunk);
            }

            $s = $this->normalizeSummary($result['summary']);

            // Registrar la sábana
            $batch = CdrBatch::create([
                'tenant_id'             => $tenantId,
                'imported_by'           => $userId,
                'batch_id'              => $batchId,
                'name'                  => $source,
                'phone_main'            => $phoneMain,
                'filename'              => $this->file->getClientOriginalName(),
                'encoding'              => $result['encoding'],
                'total_records'         => $imported,
                'records_with_location' => $s['with_location'],
                'voice_count'           => $s['voice'],
                'sms_count'             => $s['sms'],
                'data_count'            => $s['data'],
                'date_from'             => $s['date_from'],
                'date_to'               => $s['date_to'],
            ]);

            // Contactos (aislados por usuario vía trait)
            foreach (array_keys($contacts) as $number) {
                PhoneContact::firstOrCreate(['phone_number' => $number]);
            }

            // Número objetivo por usuario (compatibilidad con módulos de análisis)
            if ($phoneMain) {
                Setting::setUser('target_number', $this->cleanPhone($phoneMain));
            }

            // Análisis de cruce contra las demás sábanas del usuario
            $allBatchIds = CdrBatch::pluck('batch_id')->all();
            $cross = (new CdrCrossAnalyzer())->analyzeCross($allBatchIds);
            $batch->update(['cross_analysis' => $cross]);

            $this->crossResult   = $cross;
            $this->importedCount = $imported;
            $this->skippedCount  = $skipped;
            $this->lastBatchId   = $batchId;
            $this->step = 4;
        } catch (\Throwable $e) {
            $this->importError = 'Error al importar: ' . $e->getMessage();
            $this->step = 2;
        }
    }

    public function deleteBatch(int $id): void
    {
        $batch = CdrBatch::find($id);
        if (!$batch) return;

        CdrRecord::where('batch_id', $batch->batch_id)->delete();
        $batch->delete();
    }

    public function clearAll(): void
    {
        // delete() respeta el scope (solo lo propio / del tenant); truncate() no.
        CdrRecord::query()->delete();
        CdrBatch::query()->delete();
        PhoneContact::query()->delete();
        Setting::setUser('target_number', null);
        $this->resetImport();
    }

    public function importAnother(): void
    {
        $this->resetImport();
    }

    public function backToUpload(): void
    {
        $this->reset(['file', 'detectedFields', 'previewRows', 'warnings', 'summary', 'mainDetection', 'encoding', 'totalRows', 'importError']);
        $this->step = 1;
    }

    private function resetImport(): void
    {
        $this->reset([
            'file', 'batchName', 'detectedFields', 'previewRows', 'warnings',
            'summary', 'mainDetection', 'encoding', 'totalRows', 'importedCount', 'skippedCount',
            'importError', 'lastBatchId', 'crossResult',
        ]);
        $this->crossResult = ['common_contacts' => [], 'direct_communication' => []];
        $this->step = 1;
    }

    /** Mapea una fila normalizada → fila de BD (columnas nuevas + legacy). */
    private function toDbRow(array $r, CdrParser $parser, string $source, $tenantId, $userId, string $now): array
    {
        $dt     = $r['call_datetime']; // Carbon|null
        $family = $parser->legacyFamily($r['record_type']); // voice|sms|data|null

        // Dirección legacy (Incoming/Outgoing/null) a partir del flujo nuevo.
        $legacyDir = match ($r['direction']) {
            'in'  => 'Incoming',
            'out' => 'Outgoing',
            'transit' => ($this->cleanPhone($r['number_a']) !== '' && $this->cleanPhone($r['number_a']) === $this->cleanPhone($r['phone_main']))
                            ? 'Outgoing' : 'Incoming',
            default => null,
        };

        return [
            'tenant_id'      => $tenantId,
            'user_id'        => $userId,
            // ── Normalizado ──
            'batch_id'       => $r['batch_id'],
            'phone_main'     => $r['phone_main'],
            'record_type'    => $r['record_type'],
            'contact_number' => $this->cleanPhone($r['contact_number']) ?: null,
            'flow'           => $r['direction'],
            'call_datetime'  => $dt?->format('Y-m-d H:i:s'),
            'lat'            => $r['lat'],
            'lon'            => $r['lon'],
            'raw_lat'        => $r['raw_lat'],
            'raw_lon'        => $r['raw_lon'],
            'imei'           => $r['imei'],
            'azimuth'        => $r['azimuth'],
            // ── Legacy (compatibilidad con módulos existentes) ──
            'number_a'       => $this->cleanPhone($r['number_a']) ?: null,
            'number_b'       => $this->cleanPhone($r['number_b']) ?: null,
            'type'           => $family,
            'direction'      => $legacyDir,
            'duration'       => $r['duration'],
            'date'           => $dt?->format('Y-m-d'),
            'hour'           => $dt?->format('H:i:s'),
            'lat_a'          => $r['lat'],
            'lon_a'          => $r['lon'],
            'azimuth_a'      => $r['azimuth'],
            'imei_a'         => $r['imei'],
            'source_file'    => $source,
            'created_at'     => $now,
            'updated_at'     => $now,
        ];
    }

    private function normalizeSummary(array $s): array
    {
        return [
            'voice'         => $s['voice'] ?? 0,
            'sms'           => $s['sms'] ?? 0,
            'data'          => $s['data'] ?? 0,
            'other'         => $s['other'] ?? 0,
            'with_location' => $s['with_location'] ?? 0,
            'without_location' => $s['without_location'] ?? 0,
            'date_from'     => isset($s['date_from']) && $s['date_from'] ? (is_string($s['date_from']) ? $s['date_from'] : $s['date_from']->format('Y-m-d H:i:s')) : null,
            'date_to'       => isset($s['date_to']) && $s['date_to'] ? (is_string($s['date_to']) ? $s['date_to'] : $s['date_to']->format('Y-m-d H:i:s')) : null,
        ];
    }

    private function cleanPhone(?string $val): string
    {
        return preg_replace('/[^0-9]/', '', (string) $val) ?? '';
    }

    public function render()
    {
        $batches = CdrBatch::with('importer')->latest()->get();

        return view('livewire.csv-uploader', [
            'batches'     => $batches,
            'fieldLabels' => $this->fieldLabels,
        ])->extends('layouts.app')->section('content');
    }
}
