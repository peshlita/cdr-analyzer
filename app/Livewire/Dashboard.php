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

        // Top 10 numbers contacted
        $topB = CdrRecord::selectRaw("number_b, COUNT(*) as cnt")
            ->whereNotNull('number_b')
            ->groupBy('number_b')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        $this->topNumbers = [
            'labels' => $topB->pluck('number_b')->values()->toArray(),
            'data'   => $topB->pluck('cnt')->values()->toArray(),
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
