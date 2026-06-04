<?php

namespace App\Http\Controllers;

use App\Models\CdrRecord;
use App\Models\PhoneContact;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function index()
    {
        // Solo mostrar los números objetivo (uno por sábana cargada),
        // usando el mismo criterio que los demás módulos.
        $targetsByFile = \DB::table('cdr_records')
            ->selectRaw('source_file, number_a, COUNT(*) as cnt')
            ->whereNotNull('number_a')->whereNotNull('source_file')
            ->groupBy('source_file', 'number_a')
            ->get()
            ->groupBy('source_file')
            ->map(fn($g) => $g->sortByDesc('cnt')->first())
            ->map(fn($r) => ['phone' => $r->number_a, 'source_file' => $r->source_file])
            ->values();

        $dateRange = [
            'min' => CdrRecord::whereNotNull('date')->min('date'),
            'max' => CdrRecord::whereNotNull('date')->max('date'),
        ];

        return view('map.index', compact('targetsByFile', 'dateRange'));
    }

    public function saveSnapshot(Request $request)
    {
        $dataUri = $request->input('image', '');
        $type    = $request->input('type', '');

        $allowed = ['map_data', 'map_voice', 'map_pernocta'];

        if (!in_array($type, $allowed) || !str_starts_with($dataUri, 'data:image/png;base64,')) {
            return response()->json(['success' => false, 'error' => 'Invalid request'], 422);
        }

        $png = base64_decode(str_replace('data:image/png;base64,', '', $dataUri));
        \Storage::disk('public')->makeDirectory('snapshots');
        \Storage::disk('public')->put("snapshots/{$type}.png", $png);

        return response()->json(['success' => true]);
    }

    public function data(Request $request)
    {
        $contacts = PhoneContact::all()->keyBy('phone_number');
        $targetNumber = CdrRecord::getTargetNumber();

        // Resolución de sábana por número objetivo (mismo criterio que los demás módulos)
        $sourceFileForNumber = null;
        if ($request->filled('number')) {
            $sourceFileForNumber = \DB::table('cdr_records')
                ->where('number_a', $request->number)
                ->whereNotNull('source_file')
                ->value('source_file');
        }

        // Base filter closure
        $applyFilters = function ($q) use ($request, $sourceFileForNumber) {
            if ($sourceFileForNumber) {
                // Filtrar por sábana completa (no solo number_a = target,
                // para incluir sesiones de datos y todos los registros GPS del archivo)
                $q->where('source_file', $sourceFileForNumber);
            }
            if ($request->filled('date_from')) {
                $q->where(function ($inner) use ($request) {
                    $inner->whereDate('date', '>=', $request->date_from)
                          ->orWhereNull('date');
                });
            }
            if ($request->filled('date_to')) {
                $q->where(function ($inner) use ($request) {
                    $inner->whereDate('date', '<=', $request->date_to)
                          ->orWhereNull('date');
                });
            }
            return $q;
        };

        // 1. Movement points from DATA records (lat_a/lon_a valid)
        // Solo traemos las columnas necesarias — reduce la memoria y el tiempo de serialización
        $dataQ = CdrRecord::where('type', 'data')
            ->whereNotNull('lat_a')->whereNotNull('lon_a')
            ->where('lat_a', '!=', 0)->where('lon_a', '!=', 0);
        $dataRecords = $applyFilters($dataQ)
            ->orderBy('date')->orderBy('hour')
            ->get(['lat_a','lon_a','number_a','date','hour','azimuth_a']);

        // 2. Voice call markers (lat_a when available)
        $voiceQ = CdrRecord::where('type', 'voice')
            ->whereNotNull('lat_a')->whereNotNull('lon_a')
            ->where('lat_a', '!=', 0)->where('lon_a', '!=', 0);
        $voiceRecords = $applyFilters($voiceQ)
            ->orderBy('date')->orderBy('hour')
            ->get(['lat_a','lon_a','number_a','number_b','date','hour','direction','duration','azimuth_a']);

        // 3. Pernocta antenna: data records 23:00-07:00, group by lat/lon, max duration
        $pernoctaQ = CdrRecord::where('type', 'data')
            ->whereNotNull('lat_a')->whereNotNull('lon_a')
            ->where('lat_a', '!=', 0)->where('lon_a', '!=', 0)
            ->whereNotNull('hour')
            ->whereRaw("(hour >= '23:00:00' OR hour <= '07:00:00')");
        if ($sourceFileForNumber) $pernoctaQ->where('source_file', $sourceFileForNumber);

        $pernoctaData = $pernoctaQ
            ->selectRaw('lat_a, lon_a, SUM(duration) as total_duration, COUNT(*) as sessions')
            ->groupBy('lat_a', 'lon_a')
            ->orderByDesc('total_duration')
            ->first();

        // Build markers
        $markers = [];
        $movementPath = [];

        // Movement markers (data)
        foreach ($dataRecords as $r) {
            $c = $contacts->get($r->number_a);
            $label = $c?->name ?? $c?->alias ?? $r->number_a;
            $markers[] = [
                'lat'        => $r->lat_a,
                'lng'        => $r->lon_a,
                'color'      => '#8b5cf6',
                'markerType' => 'movement',
                'number'     => $r->number_a,
                'label'      => $label,
                'date'       => $r->date ? $r->date->format('d/m/Y') : '-',
                'hour'       => $r->hour ?? '-',
                'azimuth'    => $r->azimuth_a,
            ];
            $movementPath[] = [$r->lat_a, $r->lon_a];
        }

        // Voice markers
        foreach ($voiceRecords as $r) {
            $c = $contacts->get($r->number_a);
            $label = $c?->name ?? $c?->alias ?? $r->number_a;
            $markers[] = [
                'lat'        => $r->lat_a,
                'lng'        => $r->lon_a,
                'color'      => ($r->direction === 'Outgoing') ? '#10b981' : '#ef4444',
                'markerType' => 'voice',
                'number'     => $r->number_a,
                'label'      => $label,
                'date'       => $r->date ? $r->date->format('d/m/Y') : '-',
                'hour'       => $r->hour ?? '-',
                'direction'  => $r->direction ?? '-',
                'numberB'    => $r->number_b,
                'duration'   => $r->duration,
                'azimuth'    => $r->azimuth_a,
            ];
        }

        $pernocta = $pernoctaData ? [
            'lat'            => (float) $pernoctaData->lat_a,
            'lng'            => (float) $pernoctaData->lon_a,
            'total_duration' => (int) $pernoctaData->total_duration,
            'sessions'       => (int) $pernoctaData->sessions,
        ] : null;

        return response()->json([
            'markers'      => $markers,
            'movementPath' => $movementPath,
            'pernocta'     => $pernocta,
            'targetNumber' => $targetNumber,
            'totalMarkers' => $dataRecords->count() + $voiceRecords->count(),
        ]);
    }
}
