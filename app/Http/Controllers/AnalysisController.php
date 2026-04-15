<?php

namespace App\Http\Controllers;

use App\Models\CdrRecord;
use App\Models\PhoneContact;
use Carbon\Carbon;

class AnalysisController extends Controller
{
    public function index()
    {
        $targetNumber = CdrRecord::getTargetNumber();
        $totalRecords = CdrRecord::count();

        if (!$totalRecords) {
            return view('analysis.index', ['empty' => true, 'targetNumber' => null]);
        }

        // Load all voice records with number_b
        $voiceRecords = CdrRecord::where('type', 'voice')
            ->whereNotNull('number_b')
            ->get(['number_a', 'number_b', 'direction', 'duration', 'hour', 'date']);

        // Build interaction map: other_number => {out, in, duration}
        $map = [];
        foreach ($voiceRecords as $r) {
            $other = ($r->direction === 'Outgoing') ? $r->number_b : $r->number_a;
            if (!$other) continue;
            if (!isset($map[$other])) $map[$other] = ['out' => 0, 'in' => 0, 'duration' => 0];
            if ($r->direction === 'Outgoing') $map[$other]['out']++;
            else $map[$other]['in']++;
            $map[$other]['duration'] += (int) ($r->duration ?? 0);
        }

        // Enrich with contacts
        $contacts = PhoneContact::whereIn('phone_number', array_keys($map))->get()->keyBy('phone_number');

        $list = [];
        foreach ($map as $num => $s) {
            $c = $contacts->get($num);
            $list[] = [
                'number'   => $num,
                'name'     => $c?->name ?? $c?->alias,
                'out'      => $s['out'],
                'in'       => $s['in'],
                'total'    => $s['out'] + $s['in'],
                'duration' => $s['duration'],
            ];
        }

        // Section 1: by incidence
        usort($list, fn($a, $b) => $b['total'] - $a['total']);
        $incidenceList = $list;

        // Section 2: by duration
        $byDuration = $list;
        usort($byDuration, fn($a, $b) => $b['duration'] - $a['duration']);
        $maxDuration = max(1, $byDuration[0]['duration'] ?? 1);

        // Section 3: nocturnal (23:00-07:00)
        $nocturnalMap = [];
        foreach ($voiceRecords as $r) {
            if (!$r->hour) continue;
            $h = (int) substr($r->hour, 0, 2);
            if ($h >= 7 && $h < 23) continue; // daytime, skip
            $other = ($r->direction === 'Outgoing') ? $r->number_b : $r->number_a;
            if (!$other) continue;
            if (!isset($nocturnalMap[$other])) $nocturnalMap[$other] = ['count' => 0, 'duration' => 0];
            $nocturnalMap[$other]['count']++;
            $nocturnalMap[$other]['duration'] += (int) ($r->duration ?? 0);
        }
        $nocturnalList = [];
        foreach ($nocturnalMap as $num => $s) {
            $c = $contacts->get($num);
            $totalForNum = ($map[$num]['out'] ?? 0) + ($map[$num]['in'] ?? 0);
            $nocturnalList[] = [
                'number'         => $num,
                'name'           => $c?->name ?? $c?->alias,
                'count'          => $s['count'],
                'duration'       => $s['duration'],
                'only_nocturnal' => ($totalForNum > 0 && $s['count'] >= $totalForNum),
            ];
        }
        usort($nocturnalList, fn($a, $b) => $b['count'] - $a['count']);

        // Section 4: patterns
        $hourCounts = [];
        $dayCounts = [];
        foreach ($voiceRecords as $r) {
            if ($r->hour) {
                $h = (int) substr($r->hour, 0, 2);
                $hourCounts[$h] = ($hourCounts[$h] ?? 0) + 1;
            }
            if ($r->date) {
                $d = (string) $r->date;
                $dayCounts[$d] = ($dayCounts[$d] ?? 0) + 1;
            }
        }
        arsort($hourCounts);
        arsort($dayCounts);
        $peakHour = !empty($hourCounts) ? sprintf('%02d:00 h', array_key_first($hourCounts)) : 'N/D';
        $peakDayDate = !empty($dayCounts) ? array_key_first($dayCounts) : null;
        $peakDay = $peakDayDate
            ? Carbon::parse($peakDayDate)->format('d/m/Y') . ' (' . $dayCounts[$peakDayDate] . ' llamadas)'
            : 'N/D';
        $avgPerDay = count($dayCounts) > 0 ? round(count($voiceRecords) / count($dayCounts), 1) : 0;

        $patterns = [
            'peakHour'    => $peakHour,
            'peakDay'     => $peakDay,
            'avgPerDay'   => $avgPerDay,
            'topDuration' => $byDuration[0] ?? null,
        ];

        return view('analysis.index', [
            'empty'         => false,
            'targetNumber'  => $targetNumber,
            'incidenceList' => $incidenceList,
            'byDuration'    => $byDuration,
            'maxDuration'   => $maxDuration,
            'nocturnalList' => $nocturnalList,
            'patterns'      => $patterns,
        ]);
    }
}
