<?php

namespace App\Http\Controllers;

use App\Models\CdrRecord;
use App\Models\PhoneContact;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function index()
    {
        $numbers = CdrRecord::selectRaw('DISTINCT number_a as phone')
            ->whereNotNull('number_a')
            ->pluck('phone')
            ->sort()
            ->values();

        $dateRange = [
            'min' => CdrRecord::whereNotNull('date')->min('date'),
            'max' => CdrRecord::whereNotNull('date')->max('date'),
        ];

        return view('map.index', compact('numbers', 'dateRange'));
    }

    public function data(Request $request)
    {
        $contacts = PhoneContact::all()->keyBy('phone_number');
        $targetNumber = CdrRecord::getTargetNumber();

        // Base filter closure
        $applyFilters = function ($q) use ($request) {
            if ($request->filled('number')) $q->where('number_a', $request->number);
            if ($request->filled('date_from')) $q->whereDate('date', '>=', $request->date_from);
            if ($request->filled('date_to'))   $q->whereDate('date', '<=', $request->date_to);
            return $q;
        };

        // 1. Movement points from DATA records (lat_a/lon_a valid)
        $dataQ = CdrRecord::where('type', 'data')
            ->whereNotNull('lat_a')->whereNotNull('lon_a')
            ->where('lat_a', '!=', 0)->where('lon_a', '!=', 0);
        $dataRecords = $applyFilters($dataQ)->orderBy('date')->orderBy('hour')->get();

        // 2. Voice call markers (lat_a when available)
        $voiceQ = CdrRecord::where('type', 'voice')
            ->whereNotNull('lat_a')->whereNotNull('lon_a')
            ->where('lat_a', '!=', 0)->where('lon_a', '!=', 0);
        $voiceRecords = $applyFilters($voiceQ)->orderBy('date')->orderBy('hour')->get();

        // 3. Pernocta antenna: data records 23:00-07:00, group by lat/lon, max duration
        $pernoctaQ = CdrRecord::where('type', 'data')
            ->whereNotNull('lat_a')->whereNotNull('lon_a')
            ->where('lat_a', '!=', 0)->where('lon_a', '!=', 0)
            ->whereNotNull('hour')
            ->whereRaw("(hour >= '23:00:00' OR hour <= '07:00:00')");
        if ($request->filled('number')) $pernoctaQ->where('number_a', $request->number);

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
        ]);
    }
}
