<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Cerebro del módulo CDR. Soporta dos familias de formato:
 *  - Telcel/Itelcel: una coordenada, Tipo lleva la dirección ("VOZ SALIENTE"),
 *    columna Telefono (número principal explícito).
 *  - call_log: cabeceras con sufijo _A/_B, Type genérico ("voice","data") +
 *    columna Direction separada, sin número principal (se infiere por frecuencia),
 *    coordenadas _A y _B, valores con espacios finales y "0" como placeholder.
 *
 * Solo PHP nativo (sin librerías externas).
 */
class CdrParser
{
    /** Variantes de cabecera → campo interno. Comparadas ya normalizadas
     *  (minúsculas, guiones/underscore → espacio, espacios colapsados). */
    private array $headerMap = [
        'phone_main'  => ['telefono', 'teléfono', 'numero principal', 'msisdn', 'numero', 'número', 'tel'],
        'record_type' => ['tipo', 'type', 'tipo de evento', 'event type', 'clase'],
        'number_a'    => ['numero a', 'número a', 'number a', 'num a', 'numbera', 'a number', 'calling number', 'origen', 'calling', 'llamante'],
        'number_b'    => ['numero b', 'número b', 'number b', 'num b', 'numberb', 'b number', 'called number', 'destino', 'called', 'llamado'],
        'date'        => ['fecha', 'date', 'fecha llamada', 'call date'],
        'time'        => ['hora', 'hour', 'time', 'hora llamada', 'call time'],
        'duration'    => ['durac. seg.', 'durac seg', 'duracion', 'duración', 'duration', 'segundos', 'secs', 'dur seg', 'dur. seg'],
        'imei'        => ['imei', 'imei a', 'imei number', 'device id'],
        'imei_b'      => ['imei b'],
        'lat'         => ['latitud', 'latitude', 'lat', 'lat a', 'latitud a', 'latitude a', 'coordenada n', 'coord lat'],
        'lat_b'       => ['lat b', 'latitud b', 'latitude b'],
        'lon'         => ['longitud', 'longitude', 'lon', 'lng', 'lon a', 'lng a', 'longitud a', 'longitude a', 'coordenada w', 'coord lon'],
        'lon_b'       => ['lon b', 'lng b', 'longitud b', 'longitude b'],
        'azimuth'     => ['azimuth', 'azimut', 'azimuth a', 'azimut a', 'angulo antena', 'ángulo antena', 'sector'],
        'azimuth_b'   => ['azimuth b', 'azimut b'],
        'direction'   => ['direction', 'dir', 'sentido', 'direccion', 'dirección'],
    ];

    /** Tipos COMBINADOS conocidos (la dirección va en el propio tipo) + data. */
    private array $typeMap = [
        'voz entrante'     => 'VOZ_ENTRANTE',
        'voz saliente'     => 'VOZ_SALIENTE',
        'voz transito'     => 'VOZ_TRANSITO',
        'voz tránsito'     => 'VOZ_TRANSITO',
        'llamada entrante' => 'VOZ_ENTRANTE',
        'llamada saliente' => 'VOZ_SALIENTE',
        'mensaje entrante' => 'MSG_ENTRANTE',
        'mensaje saliente' => 'MSG_SALIENTE',
        'mensajeria entrante' => 'MSG_ENTRANTE',
        'mensajeria saliente' => 'MSG_SALIENTE',
        'sms entrante'     => 'MSG_ENTRANTE',
        'sms saliente'     => 'MSG_SALIENTE',
        'datos'            => 'DATOS',
        'data'             => 'DATOS',
        'gprs'             => 'DATOS',
    ];

    public function detectEncoding(string $filePath): string
    {
        $content = file_get_contents($filePath, false, null, 0, 8192);
        if ($content === false || $content === '') {
            return 'UTF-8';
        }
        foreach (['UTF-8', 'UTF-16', 'ISO-8859-1', 'Windows-1252'] as $enc) {
            if (@mb_detect_encoding($content, $enc, true)) {
                return $enc;
            }
        }
        return 'ISO-8859-1';
    }

    public function detectHeaderRow(string $filePath, string $encoding): int
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return 0;
        }
        $lineNum = 0;
        while (($line = fgets($handle)) !== false && $lineNum < 20) {
            $line = mb_convert_encoding($line, 'UTF-8', $encoding);
            $n = strtolower(trim($line));
            if (str_contains($n, 'numero') || str_contains($n, 'número') ||
                str_contains($n, 'telefono') || str_contains($n, 'teléfono') ||
                str_contains($n, 'fecha') || str_contains($n, 'tipo') ||
                str_contains($n, 'number') || str_contains($n, 'date')) {
                fclose($handle);
                return $lineNum;
            }
            $lineNum++;
        }
        fclose($handle);
        return 0;
    }

    /** minúsculas, _/- → espacio, colapsa espacios, trim. */
    public function normalizeHeaderName(string $header): string
    {
        $h = strtolower(trim($header));
        $h = str_replace(['_', '-'], ' ', $h);
        $h = preg_replace('/\s+/', ' ', $h);
        return trim($h);
    }

    /**
     * Mapea índices del CSV → campo interno. Dos pasadas: primero coincidencia
     * EXACTA (para distinguir _A de _B), luego por contención con variantes de
     * 4+ caracteres (evita que 'lat' capture 'lat b', etc.).
     */
    public function mapHeaders(array $headers): array
    {
        $norm = [];
        foreach ($headers as $i => $h) {
            $norm[$i] = $this->normalizeHeaderName((string) $h);
        }

        $mapping = [];
        $used    = [];

        // Pasada 1 — exacta
        foreach ($norm as $i => $n) {
            if ($n === '' || isset($mapping[$i])) continue;
            foreach ($this->headerMap as $field => $variants) {
                if (isset($used[$field])) continue;
                if (in_array($n, $variants, true)) {
                    $mapping[$i] = $field;
                    $used[$field] = true;
                    break;
                }
            }
        }

        // Pasada 2 — por contención (solo variantes largas)
        foreach ($norm as $i => $n) {
            if ($n === '' || isset($mapping[$i])) continue;
            foreach ($this->headerMap as $field => $variants) {
                if (isset($used[$field])) continue;
                foreach ($variants as $v) {
                    if (strlen($v) >= 4 && str_contains($n, $v)) {
                        $mapping[$i] = $field;
                        $used[$field] = true;
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }

    /** DMS ("21°2'34.44N") o decimal ("-105.240128") → float decimal. */
    public function dmsToDecimal(?string $coord): ?float
    {
        if ($coord === null || trim($coord) === '' || trim($coord) === '-') {
            return null;
        }
        $coord = trim($coord, " \t\n\r\0\x0B\"'");

        if (preg_match('/^-?\d+(\.\d+)?$/', $coord)) {
            $f = (float) $coord;
            return $f == 0.0 ? null : round($f, 6);
        }
        if (preg_match("/(\d+)[°º]\s*(\d+)['′\s]\s*([\d.]+)[\"″'\s]*([NSEWnsew])/u", $coord, $m)) {
            $dec = (float) $m[1] + (float) $m[2] / 60 + (float) $m[3] / 3600;
            if (in_array(strtoupper($m[4]), ['S', 'W'])) $dec = -$dec;
            return round($dec, 6);
        }
        if (preg_match("/(\d+)[°º]\s*(\d+)['′\s]*([NSEWnsew])/u", $coord, $m)) {
            $dec = (float) $m[1] + (float) $m[2] / 60;
            if (in_array(strtoupper($m[3]), ['S', 'W'])) $dec = -$dec;
            return round($dec, 6);
        }
        return null;
    }

    /** "3.54986E+13" → "35498600000000". */
    public function normalizeImei(?string $imei): ?string
    {
        if ($imei === null || trim($imei) === '' || trim($imei) === '-') {
            return null;
        }
        $imei = trim($imei);
        if (preg_match('/[\d.]+E[+\-]?\d+/i', $imei)) {
            return (string) (int) ((float) $imei);
        }
        $digits = preg_replace('/[^0-9]/', '', $imei);
        return $digits !== '' ? $digits : null;
    }

    public function normalizeType(?string $type): string
    {
        if ($type === null || trim($type) === '') {
            return 'DESCONOCIDO';
        }
        $lower = preg_replace('/\s+/', ' ', strtolower(trim($type)));
        return $this->typeMap[$lower] ?? strtoupper(trim($type));
    }

    /**
     * Combina Tipo + Direction. Si el tipo ya es combinado (Telcel) lo resuelve
     * por typeMap; si es genérico ("voice"/"sms") usa la columna Direction.
     */
    public function normalizeTypeWithDirection(?string $type, ?string $direction = null): string
    {
        $t = preg_replace('/\s+/', ' ', strtolower(trim($type ?? '')));
        $d = strtolower(trim($direction ?? ''));
        if ($t === '') return 'DESCONOCIDO';

        if (isset($this->typeMap[$t])) {
            return $this->typeMap[$t];
        }
        if (in_array($t, ['voice', 'voz', 'llamada', 'call'], true)) {
            if ($d === 'incoming' || $d === 'entrante') return 'VOZ_ENTRANTE';
            if ($d === 'outgoing' || $d === 'saliente') return 'VOZ_SALIENTE';
            return 'VOZ_TRANSITO';
        }
        if (in_array($t, ['sms', 'mensaje', 'mensajeria', 'mensajería', 'text'], true)) {
            if ($d === 'incoming' || $d === 'entrante') return 'MSG_ENTRANTE';
            if ($d === 'outgoing' || $d === 'saliente') return 'MSG_SALIENTE';
            return 'MSG_ENTRANTE';
        }
        if (in_array($t, ['data', 'datos', 'gprs'], true)) {
            return 'DATOS';
        }
        return strtoupper($t);
    }

    /** Familia legacy (voice|sms|data|null) a partir del record_type normalizado. */
    public function legacyFamily(string $recordType): ?string
    {
        if (str_starts_with($recordType, 'VOZ')) return 'voice';
        if (str_starts_with($recordType, 'MSG')) return 'sms';
        if ($recordType === 'DATOS') return 'data';
        return null;
    }

    /**
     * Dirección (in|out|transit|data) y número de contacto. Si hay columna
     * Direction explícita (Incoming/Outgoing) se prioriza; si no, se infiere
     * del tipo (formato Telcel).
     */
    public function analyzeDirection(string $phoneMain, string $numberA, string $numberB, string $type, ?string $explicitDirection = null): array
    {
        $d = strtolower(trim($explicitDirection ?? ''));
        $t = strtolower($type);

        if ($d === 'incoming' || $d === 'entrante') {
            return ['direction' => 'in', 'contact_number' => ($numberA !== '' && $numberA !== $phoneMain) ? $numberA : ($numberB ?: null)];
        }
        if ($d === 'outgoing' || $d === 'saliente') {
            return ['direction' => 'out', 'contact_number' => ($numberB !== '' && $numberB !== $phoneMain) ? $numberB : ($numberA ?: null)];
        }

        // Sin Direction explícita → inferir del tipo (Telcel).
        if (str_contains($t, 'saliente') || str_contains($t, 'outgoing')) {
            return ['direction' => 'out', 'contact_number' => $numberB ?: null];
        }
        if (str_contains($t, 'entrante') || str_contains($t, 'incoming')) {
            return ['direction' => 'in', 'contact_number' => ($numberA !== '' && $numberA !== $phoneMain) ? $numberA : ($numberB ?: null)];
        }
        if (str_contains($t, 'transito') || str_contains($t, 'tránsito') || str_contains($t, 'transit')) {
            return ['direction' => 'transit', 'contact_number' => ($numberA !== '' && $numberA !== $phoneMain) ? $numberA : ($numberB ?: null)];
        }
        if (str_contains($t, 'datos') || str_contains($t, 'data') || str_contains($t, 'gprs')) {
            return ['direction' => 'data', 'contact_number' => null];
        }
        return ['direction' => 'unknown', 'contact_number' => ($numberA !== '' && $numberA !== $phoneMain) ? $numberA : ($numberB ?: null)];
    }

    public function parseDateTime(?string $date, ?string $time): ?Carbon
    {
        if ($date === null || trim($date) === '' || trim($date) === '-') {
            return null;
        }
        $datetime = trim(trim($date) . ' ' . trim($time ?? ''));

        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d', 'm/d/Y H:i:s', 'm/d/Y H:i', 'm/d/Y'] as $format) {
            try {
                $c = Carbon::createFromFormat($format, $datetime);
                if ($c !== false) return $c;
            } catch (\Throwable $e) {
                continue;
            }
        }
        try { return Carbon::parse($datetime); } catch (\Throwable $e) { return null; }
    }

    /** Limpia un valor: trim, colapsa espacios; ''/'-' → null. Conserva '0'. */
    public function cleanValue(?string $value): ?string
    {
        if ($value === null) return null;
        $value = preg_replace('/\s+/', ' ', trim($value));
        return ($value === '' || $value === '-') ? null : $value;
    }

    /**
     * Detecta el número principal por FRECUENCIA (cuando no hay columna explícita):
     * el más repetido combinando number_a + number_b (primeras 2000 filas).
     */
    public function detectMainNumber(string $filePath, int $headerRow, array $mapping, string $encoding): ?string
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) return null;

        $currentLine = 0;
        $counts = [];
        while (($rawLine = fgets($handle)) !== false) {
            if ($currentLine <= $headerRow) { $currentLine++; continue; }
            $line = mb_convert_encoding($rawLine, 'UTF-8', $encoding);
            $cols = str_getcsv($line);

            foreach (['number_a', 'number_b'] as $field) {
                $val = $this->cleanValue($this->rawCol($cols, $mapping, $field));
                if ($val !== null && $val !== '0' && strlen(preg_replace('/[^0-9]/', '', $val)) >= 7) {
                    $counts[$val] = ($counts[$val] ?? 0) + 1;
                }
            }
            $currentLine++;
            if ($currentLine > $headerRow + 2000) break;
        }
        fclose($handle);

        if (empty($counts)) return null;
        arsort($counts);
        return (string) array_key_first($counts);
    }

    public function parseFile(string $filePath): array
    {
        $encoding  = $this->detectEncoding($filePath);
        $headerRow = $this->detectHeaderRow($filePath, $encoding);
        [$headers, $mapping] = $this->readHeaders($filePath, $headerRow, $encoding);

        $hasPhoneMain = in_array('phone_main', $mapping, true);
        $detectedMain = $hasPhoneMain ? null : $this->detectMainNumber($filePath, $headerRow, $mapping, $encoding);
        $mainDigits   = $detectedMain ? preg_replace('/[^0-9]/', '', $detectedMain) : null;

        $batchId  = Str::uuid()->toString();
        $rows     = $errors = [];
        $warnings = [];
        foreach (['number_a', 'number_b', 'date'] as $req) {
            if (!in_array($req, $mapping, true)) {
                $warnings[] = "Columna '{$req}' no detectada en las cabeceras.";
            }
        }

        $summary = [
            'voice' => 0, 'sms' => 0, 'data' => 0, 'other' => 0,
            'with_location' => 0, 'without_location' => 0,
            'date_from' => null, 'date_to' => null,
            'main_number' => null, 'main_source' => 'none', 'main_appearances' => 0,
        ];
        $headerMainValue = null;

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['batch_id' => $batchId, 'headers_found' => $headers, 'mapping' => $mapping,
                    'total' => 0, 'rows' => [], 'summary' => $summary, 'errors' => ['No se pudo abrir el archivo.'],
                    'warnings' => $warnings, 'encoding' => $encoding, 'header_row' => $headerRow];
        }

        $currentLine = 0;
        while (($rawLine = fgets($handle)) !== false) {
            if ($currentLine <= $headerRow) { $currentLine++; continue; }
            $line = mb_convert_encoding($rawLine, 'UTF-8', $encoding);
            $cols = str_getcsv($line);
            if (empty(array_filter($cols, fn ($c) => trim((string) $c) !== ''))) { $currentLine++; continue; }

            $get   = fn (string $f) => $this->cleanValue($this->rawCol($cols, $mapping, $f));
            $phone = function (string $f) use ($cols, $mapping) {
                $v = $this->cleanValue($this->rawCol($cols, $mapping, $f));
                return ($v === null || $v === '0' || preg_replace('/[^0-9]/', '', $v) === '') ? null : $v;
            };

            $rawType     = $get('record_type') ?? '';
            $explicitDir = $get('direction');
            $phoneMain   = $phone('phone_main') ?? $detectedMain ?? '';
            $numberA     = $phone('number_a') ?? '';
            $numberB     = $phone('number_b') ?? '';
            $headerMainValue = $headerMainValue ?: $phone('phone_main');

            // Coordenadas: _A y si no hay, _B
            $rawLat = $get('lat');
            if ($this->dmsToDecimal($rawLat) === null) $rawLat = $get('lat_b');
            $rawLon = $get('lon');
            if ($this->dmsToDecimal($rawLon) === null) $rawLon = $get('lon_b');
            $lat = $this->dmsToDecimal($rawLat);
            $lon = $this->dmsToDecimal($rawLon);

            $azimuth = $get('azimuth') ?? $get('azimuth_b');
            $imei    = $this->normalizeImei($get('imei') ?? $get('imei_b'));

            $recordType = $this->normalizeTypeWithDirection($rawType, $explicitDir);
            $dir        = $this->analyzeDirection($phoneMain, $numberA, $numberB, $rawType, $explicitDir);
            $dt         = $this->parseDateTime($get('date'), $get('time'));

            $rows[] = [
                'batch_id'       => $batchId,
                'phone_main'     => $phoneMain ?: null,
                'record_type'    => $recordType,
                'number_a'       => $numberA ?: null,
                'number_b'       => $numberB ?: null,
                'direction'      => $dir['direction'],
                'contact_number' => $dir['contact_number'],
                'call_datetime'  => $dt,
                'duration'       => is_numeric($get('duration')) ? (int) $get('duration') : null,
                'imei'           => $imei,
                'lat'            => $lat,
                'lon'            => $lon,
                'raw_lat'        => $rawLat,
                'raw_lon'        => $rawLon,
                'azimuth'        => is_numeric($azimuth) ? (int) round((float) $azimuth) : null,
            ];

            // Summary
            $summary[$this->legacyFamily($recordType) ?? 'other']++;
            if ($lat !== null && $lon !== null) $summary['with_location']++; else $summary['without_location']++;
            if ($dt) {
                if (!$summary['date_from'] || $dt->lt($summary['date_from'])) $summary['date_from'] = $dt->copy();
                if (!$summary['date_to'] || $dt->gt($summary['date_to'])) $summary['date_to'] = $dt->copy();
            }
            if ($mainDigits) {
                $na = $numberA !== '' ? preg_replace('/[^0-9]/', '', $numberA) : '';
                $nb = $numberB !== '' ? preg_replace('/[^0-9]/', '', $numberB) : '';
                if ($na === $mainDigits || $nb === $mainDigits) $summary['main_appearances']++;
            }

            $currentLine++;
        }
        fclose($handle);

        $summary['main_number'] = $hasPhoneMain ? $headerMainValue : $detectedMain;
        $summary['main_source'] = $hasPhoneMain ? 'header' : ($detectedMain ? 'frequency' : 'none');

        return [
            'batch_id'      => $batchId,
            'headers_found' => $headers,
            'mapping'       => $mapping,
            'total'         => count($rows),
            'rows'          => $rows,
            'summary'       => $summary,
            'errors'        => $errors,
            'warnings'      => $warnings,
            'encoding'      => $encoding,
            'header_row'    => $headerRow,
        ];
    }

    /** Lee solo la fila de cabeceras y devuelve [headers, mapping]. */
    private function readHeaders(string $filePath, int $headerRow, string $encoding): array
    {
        $handle = fopen($filePath, 'r');
        $headers = [];
        if ($handle) {
            $line = 0;
            while (($raw = fgets($handle)) !== false) {
                if ($line === $headerRow) {
                    $conv = mb_convert_encoding($raw, 'UTF-8', $encoding);
                    $headers = array_map(fn ($h) => trim((string) $h), str_getcsv($conv));
                    break;
                }
                $line++;
            }
            fclose($handle);
        }
        return [$headers, $this->mapHeaders($headers)];
    }

    /** Valor crudo de una columna mapeada (sin limpiar). */
    private function rawCol(array $cols, array $mapping, string $field): ?string
    {
        foreach ($mapping as $idx => $name) {
            if ($name === $field && isset($cols[$idx])) {
                return (string) $cols[$idx];
            }
        }
        return null;
    }
}
