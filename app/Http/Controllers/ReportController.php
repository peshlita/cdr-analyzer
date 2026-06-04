<?php

namespace App\Http\Controllers;

use App\Models\CdrRecord;
use App\Models\PhoneContact;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    private function buildReportData(): array
    {
        $targetNumber = CdrRecord::getTargetNumber();

        $stats = [
            'total'       => CdrRecord::count(),
            'voice'       => CdrRecord::where('type', 'voice')->count(),
            'data'        => CdrRecord::where('type', 'data')->count(),
            'outgoing'    => CdrRecord::where('direction', 'Outgoing')->count(),
            'incoming'    => CdrRecord::where('direction', 'Incoming')->count(),
            'totalSecs'   => CdrRecord::sum('duration'),
            'dateMin'     => CdrRecord::min('date'),
            'dateMax'     => CdrRecord::max('date'),
            'imeis'       => CdrRecord::whereNotNull('imei_a')->distinct('imei_a')->count('imei_a'),
        ];

        // Top contactos: todos los números de voz que aparecen como number_b,
        // usando el mismo criterio del módulo de red (ambas columnas, sin duplicar el objetivo).
        $targetNumbers = \DB::table('cdr_records')
            ->selectRaw('source_file, number_a, COUNT(*) as cnt')
            ->whereNotNull('number_a')->whereNotNull('source_file')
            ->groupBy('source_file', 'number_a')
            ->get()->groupBy('source_file')
            ->map(fn($g) => $g->sortByDesc('cnt')->first()->number_a)
            ->values()->unique()->filter()->toArray();

        $topContacts = CdrRecord::selectRaw('number_b as phone, COUNT(*) as calls, SUM(duration) as total_duration')
            ->whereNotNull('number_b')
            ->when(!empty($targetNumbers), fn($q) => $q->whereNotIn('number_b', $targetNumbers))
            ->groupBy('number_b')
            ->orderByDesc('calls')
            ->limit(20)
            ->get();

        // Only contacts whose number appears in the current CDR dataset
        $cdrNumbers = CdrRecord::selectRaw('number_a as phone')->whereNotNull('number_a')
            ->union(CdrRecord::selectRaw('number_b as phone')->whereNotNull('number_b'))
            ->pluck('phone')
            ->unique();

        $enrichedContacts = PhoneContact::whereIn('phone_number', $cdrNumbers)
            ->where(function ($q) {
                $q->whereNotNull('name')
                  ->orWhereNotNull('alias')
                  ->orWhereNotNull('notes');
            })
            ->orderBy('name')
            ->get();

        $topVoiceCalls = CdrRecord::where('type', 'voice')
            ->whereNotNull('number_b')
            ->orderBy('date')
            ->orderBy('hour')
            ->limit(50)
            ->get();

        $imeiList = CdrRecord::whereNotNull('imei_a')
            ->selectRaw('DISTINCT imei_a, imsi_a')
            ->get();

        // Pernocta antenna
        $pernoctaAntenna = CdrRecord::where('type', 'data')
            ->whereNotNull('lat_a')->whereNotNull('lon_a')
            ->where('lat_a', '!=', 0)->where('lon_a', '!=', 0)
            ->whereNotNull('hour')
            ->whereRaw("(hour >= '23:00:00' OR hour <= '07:00:00')")
            ->selectRaw('lat_a, lon_a, SUM(duration) as total_duration, COUNT(*) as sessions')
            ->groupBy('lat_a', 'lon_a')
            ->orderByDesc('total_duration')
            ->first();

        // Nocturnal voice calls
        $nocturnalVoice = CdrRecord::where('type', 'voice')
            ->whereNotNull('number_b')
            ->whereNotNull('hour')
            ->whereRaw("(hour >= '23:00:00' OR hour <= '07:00:00')")
            ->selectRaw('number_a, number_b, direction, COUNT(*) as calls, SUM(duration) as total_duration')
            ->groupBy('number_a', 'number_b', 'direction')
            ->orderByDesc('calls')
            ->limit(30)
            ->get();

        // Network graph snapshot
        $networkSnapshot = \Storage::disk('public')->exists('snapshots/network.png')
            ? asset('storage/snapshots/network.png')
            : null;
        $networkSnapshotPath = null;
        if (\Storage::disk('public')->exists('snapshots/network.png')) {
            $raw = \Storage::disk('public')->get('snapshots/network.png');
            $networkSnapshotPath = 'data:image/png;base64,' . base64_encode($raw);
        }

        // Map snapshots (3 types)
        $mapSnapshots = [];
        foreach (['map_data', 'map_voice', 'map_pernocta'] as $type) {
            if (\Storage::disk('public')->exists("snapshots/{$type}.png")) {
                $raw = \Storage::disk('public')->get("snapshots/{$type}.png");
                $mapSnapshots[$type] = 'data:image/png;base64,' . base64_encode($raw);
            }
        }

        return compact(
            'targetNumber', 'stats', 'topContacts',
            'enrichedContacts', 'topVoiceCalls', 'imeiList',
            'pernoctaAntenna', 'nocturnalVoice',
            'networkSnapshot', 'networkSnapshotPath',
            'mapSnapshots'
        );
    }

    public function index()
    {
        $data = $this->buildReportData();
        return view('report.index', $data);
    }

    public function pdf()
    {
        $data = $this->buildReportData();

        $pdf = Pdf::loadView('pdf.report', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont'   => 'sans-serif',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'chroot' => public_path(),
            ]);

        $filename = 'CDR_Report_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }
}
