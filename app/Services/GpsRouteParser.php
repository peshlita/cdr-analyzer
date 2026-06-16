<?php

namespace App\Services;

use Carbon\Carbon;

class GpsRouteParser
{
    /**
     * Detecta el formato y delega al parser correspondiente.
     * Retorna un array de puntos:
     *   [['lat','lon','speed','heading','battery','recorded_at'], ...]
     */
    public function parse(string $filePath, string $format): array
    {
        return match (strtolower($format)) {
            'xls', 'xlsx' => $this->parseXls($filePath),
            'kml'         => $this->parseKml($filePath),
            'kmz'         => $this->parseKmz($filePath),
            'gpx'         => $this->parseGpx($filePath),
            default       => [],
        };
    }

    /**
     * El "XLS" del TK905 es en realidad HTML disfrazado (tabla <tr><th>).
     */
    public function parseXls(string $path): array
    {
        $html = @file_get_contents($path);
        if ($html === false || $html === '') {
            return [];
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML($html);
        libxml_clear_errors();

        $rows   = $dom->getElementsByTagName('tr');
        $points = [];
        $skip   = 3; // título, período y encabezados

        foreach ($rows as $i => $row) {
            if ($i < $skip) {
                continue;
            }

            // El TK905 usa <th> en las filas de datos; aceptamos <td> como respaldo.
            $cols = $row->getElementsByTagName('th');
            if ($cols->length < 7) {
                $cols = $row->getElementsByTagName('td');
            }
            if ($cols->length < 7) {
                continue;
            }

            $recorded   = trim($cols->item(1)->textContent);
            $lat        = (float) trim($cols->item(2)->textContent);
            $lon        = (float) trim($cols->item(3)->textContent);
            $speedKnots = (float) trim($cols->item(4)->textContent);
            $heading    = (float) trim($cols->item(5)->textContent);
            $battery    = trim($cols->item(6)->textContent);

            if (!$lat || !$lon) {
                continue;
            }

            try {
                $recordedAt = Carbon::createFromFormat('Y-m-d H:i:s', $recorded)
                    ->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                try {
                    $recordedAt = Carbon::parse($recorded)->format('Y-m-d H:i:s');
                } catch (\Exception $e2) {
                    continue;
                }
            }

            $points[] = [
                'lat'         => $lat,
                'lon'         => $lon,
                'speed'       => round($speedKnots * 1.852, 2), // nudos → km/h
                'heading'     => $heading,
                'battery'     => $battery !== '' ? $battery : null,
                'recorded_at' => $recordedAt,
            ];
        }

        return $points;
    }

    public function parseKml(string $path): array
    {
        $xml = @simplexml_load_file($path);
        if ($xml === false) {
            return [];
        }

        $placemarks = $xml->xpath('//*[local-name()="Placemark"]');
        if (!$placemarks) {
            return [];
        }

        $points = [];
        foreach ($placemarks as $pm) {
            $name   = (string) $pm->name; // timestamp: "dd/mm/yyyy HH:MM"
            $coords = null;

            // Point/coordinates puede estar bajo namespaces; usar local-name.
            $coordNodes = $pm->xpath('.//*[local-name()="coordinates"]');
            if ($coordNodes && isset($coordNodes[0])) {
                $coords = trim((string) $coordNodes[0]);
            }
            if (!$coords) {
                continue;
            }

            // Una ruta puede venir como un solo Placemark con muchas coordenadas
            // (LineString) o como Placemark por punto. Manejamos ambos.
            $tuples = preg_split('/\s+/', trim($coords));
            $isTrack = count($tuples) > 1;

            foreach ($tuples as $tuple) {
                $parts = explode(',', trim($tuple));
                if (count($parts) < 2) {
                    continue;
                }

                $lon = (float) $parts[0]; // KML: LON,LAT,ALT
                $lat = (float) $parts[1];
                if (!$lat || !$lon) {
                    continue;
                }

                try {
                    $recorded = Carbon::createFromFormat('d/m/Y H:i', trim($name))
                        ->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    $recorded = now()->format('Y-m-d H:i:s');
                }

                $points[] = [
                    'lat'         => $lat,
                    'lon'         => $lon,
                    'speed'       => null,
                    'heading'     => null,
                    'battery'     => null,
                    'recorded_at' => $recorded,
                ];

                // Si es un track (LineString) el timestamp del name no aplica a
                // cada vértice; dejamos el mismo recorded_at para todos.
            }
        }

        return $points;
    }

    public function parseKmz(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $kmlContent = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_ends_with(strtolower($name), '.kml')) {
                $kmlContent = $zip->getFromIndex($i);
                break;
            }
        }
        $zip->close();

        if (!$kmlContent) {
            return [];
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'kml_');
        file_put_contents($tmpFile, $kmlContent);
        $points = $this->parseKml($tmpFile);
        @unlink($tmpFile);

        return $points;
    }

    public function parseGpx(string $path): array
    {
        $xml = @simplexml_load_file($path);
        if ($xml === false) {
            return [];
        }

        // trkpt (track) o wpt (waypoint), bajo namespace gpx → local-name.
        $nodes = $xml->xpath('//*[local-name()="trkpt"]');
        if (!$nodes) {
            $nodes = $xml->xpath('//*[local-name()="wpt"]');
        }
        if (!$nodes) {
            return [];
        }

        $points = [];
        foreach ($nodes as $pt) {
            $lat = (float) $pt['lat'];
            $lon = (float) $pt['lon'];
            if (!$lat || !$lon) {
                continue;
            }

            $timeNodes = $pt->xpath('.//*[local-name()="time"]');
            $recorded  = now()->format('Y-m-d H:i:s');
            if ($timeNodes && isset($timeNodes[0])) {
                try {
                    $recorded = Carbon::parse((string) $timeNodes[0])
                        ->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    // dejar default
                }
            }

            $points[] = [
                'lat'         => $lat,
                'lon'         => $lon,
                'speed'       => null,
                'heading'     => null,
                'battery'     => null,
                'recorded_at' => $recorded,
            ];
        }

        return $points;
    }
}
