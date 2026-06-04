<?php

namespace App\Livewire;

use App\Models\CdrRecord;
use App\Models\PhoneContact;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

class Dashboard extends Component
{
    public array $stats = [];
    public array $callsByDay = [];
    public array $topNumbers = [];
    public array $hourlyActivity = [];

    public function mount(): void
    {
        $this->loadStats();
    }

    private function loadStats(): void
    {
        $total     = CdrRecord::count();
        $voice     = CdrRecord::where('type', 'voice')->count();
        $data      = CdrRecord::where('type', 'data')->count();
        $contacts  = PhoneContact::count();
        $totalSecs = CdrRecord::sum('duration');

        $this->stats = [
            'total'     => $total,
            'voice'     => $voice,
            'data'      => $data,
            'contacts'  => $contacts,
            'hours'     => round($totalSecs / 3600, 1),
        ];

        // Calls by day (last 30 days)
        $byDay = CdrRecord::selectRaw("date, COUNT(*) as cnt")
            ->whereNotNull('date')
            ->groupBy('date')
            ->orderBy('date')
            ->limit(30)
            ->get();

        $this->callsByDay = [
            'labels' => $byDay->pluck('date')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m'))->values()->toArray(),
            'data'   => $byDay->pluck('cnt')->values()->toArray(),
        ];

        // Números objetivo (el number_a más frecuente por sábana)
        $targetNumbers = DB::table('cdr_records')
            ->selectRaw('source_file, number_a, COUNT(*) as cnt')
            ->whereNotNull('number_a')->whereNotNull('source_file')
            ->groupBy('source_file', 'number_a')
            ->get()->groupBy('source_file')
            ->map(fn($g) => $g->sortByDesc('cnt')->first()->number_a)
            ->values()->unique()->filter()->toArray();

        // Top 10 numbers (both columns, excluding targets)
        $topRows = collect(DB::select("
            SELECT phone, SUM(cnt) AS cnt
            FROM (
                SELECT number_a AS phone, COUNT(*) AS cnt
                  FROM cdr_records WHERE number_a IS NOT NULL AND number_b IS NOT NULL GROUP BY number_a
                UNION ALL
                SELECT number_b AS phone, COUNT(*) AS cnt
                  FROM cdr_records WHERE number_a IS NOT NULL AND number_b IS NOT NULL GROUP BY number_b
            ) t
            GROUP BY phone
            ORDER BY cnt DESC
        "));

        if (!empty($targetNumbers)) {
            $topRows = $topRows->filter(fn($r) => !in_array($r->phone, $targetNumbers));
        }

        $topRows = $topRows->take(10)->values();

        $this->topNumbers = [
            'labels' => $topRows->pluck('phone')->values()->toArray(),
            'data'   => $topRows->pluck('cnt')->map(fn($v) => (int)$v)->values()->toArray(),
        ];

        // Hourly activity
        $hourly = CdrRecord::selectRaw("CAST(SUBSTR(hour, 1, 2) AS INTEGER) as h, COUNT(*) as cnt")
            ->whereNotNull('hour')
            ->groupBy('h')
            ->orderBy('h')
            ->get()
            ->keyBy('h');

        $hours = [];
        $hData = [];
        for ($i = 0; $i < 24; $i++) {
            $hours[] = sprintf('%02d:00', $i);
            $hData[]  = $hourly->get($i)?->cnt ?? 0;
        }

        $this->hourlyActivity = [
            'labels' => $hours,
            'data'   => $hData,
        ];
    }

    public function render()
    {
        return view('livewire.dashboard')
            ->extends('layouts.app')
            ->section('content');
    }
}
