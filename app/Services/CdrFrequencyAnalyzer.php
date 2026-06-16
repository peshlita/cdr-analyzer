<?php

namespace App\Services;

use App\Models\CdrBatch;
use App\Models\CdrRecord;
use Carbon\Carbon;

/**
 * Análisis de frecuencia de contactos y cruces entre sábanas (batches) CDR.
 * Las consultas usan el modelo CdrRecord/CdrBatch, por lo que respetan el
 * aislamiento por usuario/tenant vía BelongsToTenant.
 */
class CdrFrequencyAnalyzer
{
    /**
     * Ranking de contactos más frecuentes de una sábana.
     */
    public function getTopContacts(string $batchId, int $limit = 10): array
    {
        $contacts = CdrRecord::where('batch_id', $batchId)
            ->whereNotNull('contact_number')
            ->where('record_type', '!=', 'DATOS')
            ->select('contact_number')
            ->selectRaw('COUNT(*) as total_events')
            ->selectRaw("SUM(CASE WHEN record_type LIKE '%ENTRANTE%' THEN 1 ELSE 0 END) as incoming_count")
            ->selectRaw("SUM(CASE WHEN record_type LIKE '%SALIENTE%' THEN 1 ELSE 0 END) as outgoing_count")
            ->selectRaw("SUM(CASE WHEN record_type LIKE '%TRANSITO%' THEN 1 ELSE 0 END) as transit_count")
            ->selectRaw("SUM(CASE WHEN record_type LIKE 'VOZ%' THEN duration ELSE 0 END) as total_duration")
            ->selectRaw('MIN(call_datetime) as first_contact')
            ->selectRaw('MAX(call_datetime) as last_contact')
            ->groupBy('contact_number')
            ->orderByDesc('total_events')
            ->limit($limit)
            ->get();

        return $contacts->map(function ($c) {
            return [
                'number'         => $c->contact_number,
                'total_events'   => (int) $c->total_events,
                'incoming'       => (int) $c->incoming_count,
                'outgoing'       => (int) $c->outgoing_count,
                'transit'        => (int) $c->transit_count,
                'total_duration' => (int) $c->total_duration,
                'first_contact'  => $c->first_contact,
                'last_contact'   => $c->last_contact,
                'days_active'    => $c->first_contact && $c->last_contact
                    ? Carbon::parse($c->first_contact)->diffInDays(Carbon::parse($c->last_contact)) + 1
                    : 1,
            ];
        })->toArray();
    }

    /**
     * Patrón temporal de comunicación con un contacto dentro de una sábana.
     */
    public function getTemporalPattern(string $batchId, string $contactNumber): array
    {
        $records = CdrRecord::where('batch_id', $batchId)
            ->where('contact_number', $contactNumber)
            ->where('record_type', '!=', 'DATOS')
            ->orderBy('call_datetime')
            ->get(['call_datetime', 'record_type', 'duration']);

        $byDay  = $records->groupBy(fn ($r) => Carbon::parse($r->call_datetime)->format('Y-m-d'));
        $byHour = $records->groupBy(fn ($r) => Carbon::parse($r->call_datetime)->format('H'));

        $gaps = [];
        $prev = null;
        foreach ($records as $r) {
            $current = Carbon::parse($r->call_datetime);
            if ($prev) {
                $gaps[] = $prev->diffInHours($current);
            }
            $prev = $current;
        }

        return [
            'total_events'     => $records->count(),
            'date_range'       => [
                'from' => $records->first()?->call_datetime,
                'to'   => $records->last()?->call_datetime,
            ],
            'by_day'           => $byDay->map->count(),
            'by_hour'          => $byHour->map->count()->sortKeys(),
            'most_active_hour' => $byHour->map->count()->sortDesc()->keys()->first(),
            'avg_gap_hours'    => count($gaps) > 0 ? round(array_sum($gaps) / count($gaps), 1) : null,
            'min_gap_hours'    => count($gaps) > 0 ? min($gaps) : null,
            'events_timeline'  => $records->map(fn ($r) => [
                'datetime' => $r->call_datetime,
                'type'     => $r->record_type,
                'duration' => $r->duration,
            ])->values(),
        ];
    }

    /**
     * Cruces (contactos en común) entre 2+ sábanas, con patrón temporal
     * comparativo y score de relevancia para la investigación.
     */
    public function getCrossAnalysisWithPattern(array $batchIds): array
    {
        $batchMains = [];
        foreach ($batchIds as $bid) {
            $batch = CdrBatch::where('batch_id', $bid)->first();
            $batchMains[$bid] = $batch?->phone_main;
        }

        $batchContacts = [];
        foreach ($batchIds as $bid) {
            $batchContacts[$bid] = CdrRecord::where('batch_id', $bid)
                ->whereNotNull('contact_number')
                ->where('record_type', '!=', 'DATOS')
                ->pluck('contact_number')
                ->unique()
                ->filter(fn ($n) => preg_match('/\d{7,}/', $n))
                ->values();
        }

        $crosses = [];
        $batchList = array_keys($batchContacts);
        for ($i = 0; $i < count($batchList); $i++) {
            for ($j = $i + 1; $j < count($batchList); $j++) {
                $a = $batchList[$i];
                $b = $batchList[$j];
                $common = $batchContacts[$a]->intersect($batchContacts[$b]);

                foreach ($common as $contact) {
                    $patternA = $this->getTemporalPattern($a, $contact);
                    $patternB = $this->getTemporalPattern($b, $contact);

                    $datesA = collect($patternA['events_timeline'])
                        ->pluck('datetime')
                        ->map(fn ($d) => Carbon::parse($d));
                    $datesB = collect($patternB['events_timeline'])
                        ->pluck('datetime')
                        ->map(fn ($d) => Carbon::parse($d));

                    $minDiffHours = null;
                    $closestPair = null;
                    foreach ($datesA as $da) {
                        foreach ($datesB as $db) {
                            $diff = abs($da->diffInHours($db));
                            if ($minDiffHours === null || $diff < $minDiffHours) {
                                $minDiffHours = round($diff, 1);
                                $closestPair = [
                                    'date_a' => $da->format('Y-m-d H:i'),
                                    'date_b' => $db->format('Y-m-d H:i'),
                                ];
                            }
                        }
                    }

                    $crosses[] = [
                        'contact_number'     => $contact,
                        'batch_a'            => $a,
                        'batch_b'            => $b,
                        'main_a'             => $batchMains[$a],
                        'main_b'             => $batchMains[$b],
                        'events_with_a'      => $patternA['total_events'],
                        'events_with_b'      => $patternB['total_events'],
                        'date_range_a'       => $patternA['date_range'],
                        'date_range_b'       => $patternB['date_range'],
                        'closest_pair_hours' => $minDiffHours,
                        'closest_pair_dates' => $closestPair,
                        'relevance_score'    => $this->calculateRelevance(
                            $patternA['total_events'],
                            $patternB['total_events'],
                            $minDiffHours
                        ),
                    ];
                }
            }
        }

        usort($crosses, fn ($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return $crosses;
    }

    /**
     * Score de relevancia (máx. teórico ~280): prioriza coordinación real entre
     * objetivos —presencia equilibrada en ambas sábanas y cercanía temporal—
     * sobre el simple volumen de llamadas (que se normaliza con logaritmo).
     */
    private function calculateRelevance(int $eventsA, int $eventsB, ?float $closestGapHours): float
    {
        // 1. BALANCE entre sábanas (0-100): premia presencia equilibrada.
        $minEvents = min($eventsA, $eventsB);
        $maxEvents = max($eventsA, $eventsB);
        $balanceScore = $maxEvents > 0 ? ($minEvents / $maxEvents) * 100 : 0;

        // 2. PRESENCIA MÍNIMA EN AMBAS SÁBANAS (0 o 50): cruce genuino.
        $minPresenceBonus = $minEvents >= 2 ? 50 : 0;

        // 3. PROXIMIDAD TEMPORAL (0-100): cercanía sugiere coordinación.
        if ($closestGapHours === null) {
            $proximityScore = 0;
        } elseif ($closestGapHours <= 24) {
            $proximityScore = 100;
        } elseif ($closestGapHours <= 72) {
            $proximityScore = 70;
        } elseif ($closestGapHours <= 168) {
            $proximityScore = 40;
        } elseif ($closestGapHours <= 720) {
            $proximityScore = 15;
        } else {
            $proximityScore = 5;
        }

        // 4. VOLUMEN TOTAL (0-30): importa, pero normalizado para no dominar.
        $totalEvents = $eventsA + $eventsB;
        $volumeScore = min(30, log($totalEvents + 1, 2) * 5);

        return round($balanceScore + $minPresenceBonus + $proximityScore + $volumeScore, 2);
    }
}
