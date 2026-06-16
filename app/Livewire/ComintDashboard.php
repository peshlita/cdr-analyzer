<?php

namespace App\Livewire;

use App\Models\CdrBatch;
use App\Models\CdrRecord;
use App\Models\PhoneContact;
use App\Services\CdrFrequencyAnalyzer;
use Livewire\Component;
use Illuminate\Support\Facades\DB;

class ComintDashboard extends Component
{
    public array $stats = [];
    public array $callsByDay = [];
    public array $topNumbers = [];
    public array $hourlyActivity = [];

    // Análisis de frecuencia y cruces
    public int $topContactsLimit = 10;
    public $batches;
    public array $topContacts = [];
    public array $temporalPatterns = [];
    public array $crossAnalysis = [];

    public function mount(): void
    {
        $this->loadStats();
        $this->loadFrequencyAnalysis();
    }

    public function updatedTopContactsLimit(): void
    {
        $this->loadFrequencyAnalysis();
    }

    private function loadFrequencyAnalysis(): void
    {
        $this->batches = CdrBatch::orderBy('created_at', 'desc')->get();
        $analyzer = new CdrFrequencyAnalyzer();

        $this->topContacts      = [];
        $this->temporalPatterns = [];
        $this->crossAnalysis    = [];

        if ($this->batches->count() === 1) {
            $batchId = $this->batches->first()->batch_id;
            $this->topContacts = $analyzer->getTopContacts($batchId, $this->topContactsLimit);

            $this->temporalPatterns = collect($this->topContacts)
                ->take(3)
                ->map(fn ($c) => array_merge($c, [
                    'pattern' => $analyzer->getTemporalPattern($batchId, $c['number']),
                ]))->toArray();
        } elseif ($this->batches->count() >= 2) {
            $this->topContacts = $this->batches->map(function ($batch) use ($analyzer) {
                return [
                    'batch'    => $batch,
                    'contacts' => $analyzer->getTopContacts($batch->batch_id, $this->topContactsLimit),
                ];
            })->toArray();

            $batchIds = $this->batches->pluck('batch_id')->toArray();
            $this->crossAnalysis = $analyzer->getCrossAnalysisWithPattern($batchIds);
        }
    }

    private function loadStats(): void
    {
        $total     = CdrRecord::count();
        $voice     = CdrRecord::where('type', 'voice')->count();
        $data      = CdrRecord::where('type', 'data')->count();
        $contacts  = PhoneContact::count();
        $totalSecs = CdrRecord::sum('duration');

        $this->stats = [
            'total'    => $total,
            'voice'    => $voice,
            'data'     => $data,
            'contacts' => $contacts,
            'hours'    => round($totalSecs / 3600, 1),
        ];

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

        $targetNumbers = \App\Models\CdrRecord::query()
            ->selectRaw('source_file, number_a, COUNT(*) as cnt')
            ->whereNotNull('number_a')->whereNotNull('source_file')
            ->groupBy('source_file', 'number_a')
            ->get()->groupBy('source_file')
            ->map(fn($g) => $g->sortByDesc('cnt')->first()->number_a)
            ->values()->unique()->filter()->toArray();

        $own = \App\Models\CdrRecord::ownershipSql('cdr_records');
        $topRows = collect(DB::select("
            SELECT phone, SUM(cnt) AS cnt
            FROM (
                SELECT number_a AS phone, COUNT(*) AS cnt
                  FROM cdr_records WHERE number_a IS NOT NULL AND number_b IS NOT NULL {$own['sql']} GROUP BY number_a
                UNION ALL
                SELECT number_b AS phone, COUNT(*) AS cnt
                  FROM cdr_records WHERE number_a IS NOT NULL AND number_b IS NOT NULL {$own['sql']} GROUP BY number_b
            ) t
            GROUP BY phone
            ORDER BY cnt DESC
        ", array_merge($own['bindings'], $own['bindings'])));

        if (!empty($targetNumbers)) {
            $topRows = $topRows->filter(fn($r) => !in_array($r->phone, $targetNumbers));
        }

        $topRows = $topRows->take(10)->values();

        $this->topNumbers = [
            'labels' => $topRows->pluck('phone')->values()->toArray(),
            'data'   => $topRows->pluck('cnt')->map(fn($v) => (int)$v)->values()->toArray(),
        ];

        $hourly = CdrRecord::selectRaw("CAST(SUBSTR(hour, 1, 2) AS UNSIGNED) as h, COUNT(*) as cnt")
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
        return view('livewire.comint-dashboard')
            ->extends('layouts.app')
            ->section('content');
    }
}
