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

        $topContacts = CdrRecord::selectRaw('number_b, COUNT(*) as calls, SUM(duration) as total_duration')
            ->whereNotNull('number_b')
            ->groupBy('number_b')
            ->orderByDesc('calls')
            ->limit(20)
            ->get();

        $enrichedContacts = PhoneContact::whereNotNull('name')
            ->orWhereNotNull('alias')
            ->orWhereNotNull('notes')
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

        // Network graph snapshot (saved from the network view)
        $networkSnapshot     = \Storage::disk('public')->exists('snapshots/network.png')
            ? asset('storage/snapshots/network.png')
            : null;
        // Absolute path for DomPDF (isRemoteEnabled: false needs a local file)
        $networkSnapshotPath = \Storage::disk('public')->exists('snapshots/network.png')
            ? \Storage::disk('public')->path('snapshots/network.png')
            : null;

        return compact(
            'targetNumber', 'stats', 'topContacts',
            'enrichedContacts', 'topVoiceCalls', 'imeiList',
            'pernoctaAntenna', 'nocturnalVoice',
            'networkSnapshot', 'networkSnapshotPath'
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
