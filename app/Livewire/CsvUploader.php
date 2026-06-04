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
    public $status  = 'idle'; // idle | uploading | done | error
    public $message = '';
    public $imported = 0;
    public $skipped  = 0;
    public $currentFile = '';

    /** Número objetivo cacheado al inicio de cada importación (para resolver VOZ TRANSITO). */
    private ?string $importTargetNumber = null;

    protected $rules = [
        'csvFile' => 'required|file|mimes:csv,txt|max:51200',
    ];

    public function updatedCsvFile(): void
    {
        $this->getErrorBag()->forget('csvFile');
        $this->validateOnly('csvFile');
        $this->upload();
    }

    public function upload(): void
    {
        $this->validate();

        $this->status      = 'uploading';
        $this->imported    = 0;
        $this->skipped     = 0;
        $this->currentFile = $this->csvFile->getClientOriginalName();
        $this->message     = "Procesando {$this->currentFile}...";

        try {
            $filename = $this->currentFile;
            $path     = $this->csvFile->getRealPath();
            $handle   = fopen($path, 'r');

            if (!$handle) throw new \Exception('No se pudo abrir el archivo.');

            // ── Detectar formato ─────────────────────────────────────────────
            // Formato A: 4 líneas de metadata del operador, cabeceras en fila 5.
            // Formato B: cabeceras directamente en fila 1 (inglés o español).
            //
            // Detección: si fila 1 contiene ≥ 3 cabeceras conocidas → Formato B.
            // En caso contrario se asume Formato A: se omiten filas 1–4 y las
            // cabeceras se toman de la fila 5.
            $knownHeaders = [
                // Inglés
                'number_a','num_a','msisdn_a','line_a',
                'number_b','num_b','msisdn_b','line_b',
                'type','direction','duration','date','hour',
                'lat_a','lon_a','lat_b','lon_b','azimuth_a','azimuth_b',
                'imei_a','imei_b','imsi_a','imsi_b',
                // Español
                'telefono','numero a','numero b','tipo','direccion',
                'fecha','hora','durac. seg.','imei','latitud','longitud','azimuth',
                // Variantes comunes
                'numero_a','numero_b','duracion','numero',
            ];

            $firstRow = fgetcsv($handle);
            $headers  = null;

            if ($firstRow) {
                $normalized = array_map(fn($h) => strtolower(trim($h)), $firstRow);
                $matches    = count(array_intersect($normalized, $knownHeaders));

                if ($matches >= 3) {
                    // ── Formato B: fila 1 = cabeceras, datos desde fila 2 ────
                    $headers = $normalized;
                } else {
                    // ── Formato A: filas 1–4 = metadata, fila 5 = cabeceras ──
                    fgetcsv($handle); // fila 2
                    fgetcsv($handle); // fila 3
                    fgetcsv($handle); // fila 4
                    $row5 = fgetcsv($handle); // fila 5 = cabeceras
                    if (!$row5) {
                        throw new \Exception(
                            'Formato no reconocido: la fila 1 no contiene cabeceras válidas ' .
                            'y no se encontró encabezado en fila 5 (Formato A).'
                        );
                    }
                    $headers = array_map(fn($h) => strtolower(trim($h)), $row5);
                }
            }

            if (!$headers || count($headers) < 3) {
                throw new \Exception('No se pudo leer los encabezados del archivo CSV.');
            }

            // Normalizar cabeceras de Formato B con nombres alternativos
            $headers = array_map([$this, 'normalizeHeader'], $headers);

            // Eliminar registros previos de este mismo archivo (reimportación)
            CdrRecord::where('source_file', $filename)->delete();

            // Cachear el número objetivo existente para resolver VOZ TRANSITO
            $this->importTargetNumber = \App\Models\Setting::get('target_number');

            $chunk     = [];
            $chunkSize = 500;

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 3) { $this->skipped++; continue; }

                $data   = array_combine($headers, array_pad($row, count($headers), null));
                $record = $this->mapRow($data, $filename);

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
            $this->syncContacts();

            $this->status  = 'done';
            $this->message = "{$filename}: {$this->imported} registros importados, {$this->skipped} omitidos.";

        } catch (\Exception $e) {
            $this->status  = 'error';
            $this->message = 'Error: ' . $e->getMessage();
        }
    }

    /**
     * Normaliza cabeceras de formatos alternativos al esquema canónico.
     * Cubre el Formato B español (Telefono, Tipo, Numero A/B, Fecha, Hora,
     * Durac. Seg., IMEI, LATITUD, LONGITUD, Azimuth) y otras variantes.
     */
    private function normalizeHeader(string $h): string
    {
        $map = [
            // ── Formato B español (cabeceras exactas) ──────────────────────
            'telefono'    => 'number_a',   // columna del teléfono analizado
            'numero a'    => 'number_a',
            'numero b'    => 'number_b',
            'tipo'        => 'type',
            'fecha'       => 'date',
            'hora'        => 'hour',
            'durac. seg.' => 'duration',
            'imei'        => 'imei_a',
            'latitud'     => 'lat_a',
            'longitud'    => 'lon_a',
            'azimuth'     => 'azimuth_a',

            // ── Variantes adicionales en español ───────────────────────────
            'numero_a'    => 'number_a', 'num_a'    => 'number_a',
            'msisdn_a'    => 'number_a', 'linea_a'  => 'number_a', 'line_a' => 'number_a',
            'numero_b'    => 'number_b', 'num_b'    => 'number_b',
            'msisdn_b'    => 'number_b', 'linea_b'  => 'number_b', 'line_b' => 'number_b',
            'call_type'   => 'type',     'event_type' => 'type',
            'direccion'   => 'direction','sentido'   => 'direction',
            'duracion'    => 'duration', 'duracion_seg' => 'duration',
            'dur'         => 'duration', 'duration_sec' => 'duration', 'segundos' => 'duration',
            'call_date'   => 'date',     'event_date'  => 'date',
            'time'        => 'hour',     'call_time'   => 'hour',    'event_time' => 'hour',
            'latitud_a'   => 'lat_a',    'latitude_a'  => 'lat_a',
            'longitud_a'  => 'lon_a',    'longitude_a' => 'lon_a',
            'azimut_a'    => 'azimuth_a','az_a'        => 'azimuth_a', 'bearing_a' => 'azimuth_a',
            'latitud_b'   => 'lat_b',    'latitude_b'  => 'lat_b',
            'longitud_b'  => 'lon_b',    'longitude_b' => 'lon_b',
            'azimut_b'    => 'azimuth_b','az_b'        => 'azimuth_b', 'bearing_b' => 'azimuth_b',
            'imei_b'      => 'imei_b',
            'imsi'        => 'imsi_a',   'imsi_a'   => 'imsi_a',   'subscriber_a' => 'imsi_a',
        ];

        return $map[$h] ?? $h;
    }

    /**
     * Limpia un valor de teléfono, convirtiendo notación científica a entero
     * (ej. "5.26643E+11" → "526643000000") y eliminando espacios.
     */
    private function cleanPhone(string $val): string
    {
        $val = trim($val);
        if ($val === '' || $val === '-') return '';

        // Notación científica (Excel exporta números grandes así)
        if (preg_match('/^[\d.]+e[+-]?\d+$/i', $val)) {
            return (string) (int) round((float) $val);
        }

        return $val;
    }

    public function removeFile(string $filename): void
    {
        CdrRecord::where('source_file', $filename)->delete();
        $this->syncContacts();
        $this->status  = 'idle';
        $this->message = '';
    }

    public function clearAll(): void
    {
        CdrRecord::truncate();
        PhoneContact::truncate();
        \App\Models\Setting::set('target_number', null);
        $this->status  = 'idle';
        $this->message = '';
    }

    /**
     * Normaliza el tipo de evento al valor canónico: 'voice', 'sms' o 'data'.
     *
     * Acepta tanto el campo separado del Formato A ("voice", "data") como el
     * campo combinado del Formato B que incluye dirección ("VOZ SALIENTE",
     * "MENSAJERIA ENTRANTE", "DATOS", etc.).
     */
    private function normalizeType(?string $val): ?string
    {
        if (!$val || trim($val) === '' || trim($val) === '-') return null;
        $v = strtolower(trim($val));

        // ── Formato A: valores simples ───────────────────────────────────────
        // ── Formato B: valores combinados que contienen "voz" ───────────────
        if (str_starts_with($v, 'voz') || in_array($v, [
            'voice', 'llamada', 'call', 'voice call', 'llamada de voz',
            'moc', 'mtc', 'originated call', 'terminated call',
            'outgoing call', 'incoming call',
        ])) return 'voice';

        // ── SMS ──────────────────────────────────────────────────────────────
        if (str_starts_with($v, 'mensajer') || str_starts_with($v, 'mensaje') || in_array($v, [
            'sms', 'text', 'mensaje de texto', 'mensaje corto',
            'mo sms', 'mt sms', 'sms mo', 'sms mt', 'texto',
        ])) return 'sms';

        // ── Datos ────────────────────────────────────────────────────────────
        if (in_array($v, [
            'data', 'datos', 'gprs', 'internet', 'data session',
        ])) return 'data';

        return trim($val);
    }

    /**
     * Extrae la dirección del campo combinado Tipo del Formato B.
     *
     * Formato B no tiene columna Direction separada; la dirección va embebida
     * en el Tipo (ej. "VOZ SALIENTE", "MENSAJERIA ENTRANTE").
     * Devuelve null si el valor no lleva dirección implícita.
     *
     * "VOZ TRANSITO" no se resuelve aquí — requiere contexto del número objetivo.
     */
    private function directionFromCombinedType(?string $val): ?string
    {
        if (!$val || trim($val) === '' || trim($val) === '-') return null;
        $v = strtolower(trim($val));

        // Saliente: voz saliente, mensaje saliente, mensajería saliente
        if (str_ends_with($v, 'saliente')) return 'Outgoing';

        // Entrante: voz entrante, mensajería entrante, mensaje entrante
        if (str_ends_with($v, 'entrante')) return 'Incoming';

        // Datos sin dirección
        if ($v === 'datos') return null;

        return null; // no hay dirección implícita (ej. Formato A → columna separada)
    }

    /**
     * Normaliza la columna Direction independiente del Formato A.
     * Acepta valores en inglés y español.
     */
    private function normalizeDirection(?string $val): ?string
    {
        if (!$val || trim($val) === '' || trim($val) === '-') return null;
        $v = strtolower(trim($val));

        if (in_array($v, [
            'outgoing', 'saliente', 'out', 'enviado', 'out-call',
            'originated', 'moc', 'mo', 'salida',
        ])) return 'Outgoing';

        if (in_array($v, [
            'incoming', 'entrante', 'in', 'recibido', 'in-call',
            'terminated', 'mtc', 'mt', 'entrada',
        ])) return 'Incoming';

        return null;
    }

    /**
     * Resuelve la dirección de una llamada "VOZ TRANSITO":
     * si el número objetivo está en number_a → Outgoing (él llamó),
     * si está en number_b → Incoming (le llamaron).
     * Requiere que $importTargetNumber esté cacheado.
     */
    private function resolveTransitDirection(?string $numA, ?string $numB): string
    {
        $target = $this->importTargetNumber;
        if (!$target) return 'Outgoing';

        $na = $this->cleanPhone(trim($numA ?? ''));
        $nb = $this->cleanPhone(trim($numB ?? ''));

        if ($na !== '' && $na === $target) return 'Outgoing';
        if ($nb !== '' && $nb === $target) return 'Incoming';

        return 'Outgoing'; // tránsito sin objetivo claro → saliente por defecto
    }

    private function mapRow(array $data, string $filename): ?array
    {
        $now = now()->toDateTimeString();

        $parseDate = function (?string $val): ?string {
            if (!$val || $val === '-') return null;
            try {
                return \Carbon\Carbon::createFromFormat('d/m/Y', trim($val))->format('Y-m-d');
            } catch (\Exception) {
                try { return \Carbon\Carbon::parse(trim($val))->format('Y-m-d'); }
                catch (\Exception) { return null; }
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

        $rawType = trim($data['type'] ?? '');
        $type    = $this->normalizeType($rawType);

        // ── Dirección ─────────────────────────────────────────────────────────
        // 1. Para "VOZ TRANSITO" (Formato B): resolver por posición del objetivo.
        // 2. Para otros valores del Formato B: extraer dirección del campo Tipo.
        // 3. Para Formato A: usar la columna Direction independiente.
        $numAForDir = $this->cleanPhone($data['number_a'] ?? '');
        $numBForDir = $this->cleanPhone($data['number_b'] ?? '');

        // Si el target no está cacheado, usar el primer number_a no vacío
        if (!$this->importTargetNumber && $numAForDir !== '') {
            $this->importTargetNumber = $numAForDir;
        }

        if (strtolower($rawType) === 'voz transito') {
            $direction = $this->resolveTransitDirection($numAForDir, $numBForDir);
        } else {
            $direction = $this->directionFromCombinedType($rawType)
                      ?? $this->normalizeDirection($data['direction'] ?? null);
        }

        return [
            'number_a'    => $numAForDir !== '' ? $numAForDir : null,
            'lat_a'       => $parseCoord($data['lat_a'] ?? null),
            'lon_a'       => $parseCoord($data['lon_a'] ?? null),
            'azimuth_a'   => $parseCoord($data['azimuth_a'] ?? null),
            'imei_a'      => (!empty($data['imei_a']) && $data['imei_a'] !== '-') ? trim($data['imei_a']) : null,
            'imsi_a'      => (!empty($data['imsi_a']) && $data['imsi_a'] !== '-') ? trim($data['imsi_a']) : null,
            'number_b'    => ($numBForDir !== '' && ($data['number_b'] ?? '') !== '-') ? $numBForDir : null,
            'lat_b'       => $parseCoord($data['lat_b'] ?? null),
            'lon_b'       => $parseCoord($data['lon_b'] ?? null),
            'azimuth_b'   => $parseCoord($data['azimuth_b'] ?? null),
            'imei_b'      => (!empty($data['imei_b']) && $data['imei_b'] !== '-') ? trim($data['imei_b']) : null,
            'imsi_b'      => (!empty($data['imsi_b']) && $data['imsi_b'] !== '-') ? trim($data['imsi_b']) : null,
            'type'        => $type,
            'direction'   => $direction,
            'duration'    => $parseInt($data['duration'] ?? null),
            'date'        => $parseDate($data['date'] ?? null),
            'hour'        => (!empty($data['hour']) && $data['hour'] !== '-') ? trim($data['hour']) : null,
            'source_file' => $filename,
            'created_at'  => $now,
            'updated_at'  => $now,
        ];
    }

    private function syncContacts(): void
    {
        $numbers = CdrRecord::selectRaw('DISTINCT number_a as phone')
            ->whereNotNull('number_a')->pluck('phone')
            ->merge(
                CdrRecord::selectRaw('DISTINCT number_b as phone')
                    ->whereNotNull('number_b')->pluck('phone')
            )->unique()->filter();

        foreach ($numbers as $number) {
            PhoneContact::firstOrCreate(['phone_number' => $number]);
        }

        // Auto-detect target: número_a más frecuente en el dataset completo
        $target = CdrRecord::selectRaw('number_a, COUNT(*) as cnt')
            ->whereNotNull('number_a')
            ->groupBy('number_a')
            ->orderByDesc('cnt')
            ->value('number_a');

        if ($target) \App\Models\Setting::set('target_number', $target);
    }

    public function render()
    {
        $loadedFiles = CdrRecord::selectRaw('source_file, COUNT(*) as total')
            ->whereNotNull('source_file')
            ->groupBy('source_file')
            ->orderBy('source_file')
            ->get();

        return view('livewire.csv-uploader', compact('loadedFiles'))
            ->extends('layouts.app')
            ->section('content');
    }
}
