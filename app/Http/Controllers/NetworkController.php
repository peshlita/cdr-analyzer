<?php

namespace App\Http\Controllers;

use App\Models\CdrRecord;
use App\Models\PhoneContact;
use Illuminate\Http\Request;

class NetworkController extends Controller
{
    public function index()
    {
        return view('network.index');
    }

    public function saveSnapshot(\Illuminate\Http\Request $request)
    {
        $dataUri = $request->input('image', '');

        if (!str_starts_with($dataUri, 'data:image/png;base64,')) {
            return response()->json(['success' => false, 'error' => 'Invalid image'], 422);
        }

        $png = base64_decode(str_replace('data:image/png;base64,', '', $dataUri));

        \Storage::disk('public')->makeDirectory('snapshots');
        \Storage::disk('public')->put('snapshots/network.png', $png);

        return response()->json(['success' => true]);
    }

    public function data(Request $request)
    {
        $targetNumber = CdrRecord::getTargetNumber();

        // Base query: only voice records with both numbers
        $base = CdrRecord::where('type', 'voice')
            ->whereNotNull('number_a')
            ->whereNotNull('number_b');

        if ($request->filled('date_from')) {
            $base->whereDate('date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $base->whereDate('date', '<=', $request->date_to);
        }

        // ── Number list (always full, unfiltered by numbers[]) ───────────────
        $nlQuery = CdrRecord::where('type', 'voice')->whereNotNull('number_b');
        if ($request->filled('date_from')) $nlQuery->whereDate('date', '>=', $request->date_from);
        if ($request->filled('date_to'))   $nlQuery->whereDate('date', '<=', $request->date_to);

        $numFreqs   = $nlQuery
            ->selectRaw('number_b as phone, COUNT(*) as cnt')
            ->groupBy('number_b')
            ->orderByDesc('cnt')
            ->get();

        $nlPhones   = $numFreqs->pluck('phone');
        $nlContacts = PhoneContact::whereIn('phone_number', $nlPhones)->get()->keyBy('phone_number');

        $numberList = $numFreqs->map(fn($r) => [
            'phone' => $r->phone,
            'label' => $nlContacts->get($r->phone)?->name
                    ?? $nlContacts->get($r->phone)?->alias
                    ?? $r->phone,
            'calls' => (int) $r->cnt,
        ])->values()->toArray();

        // ── Apply numbers[] subgraph filter ──────────────────────────────────
        if ($request->filled('numbers')) {
            $nums = (array) $request->input('numbers');
            $base->whereIn('number_a', $nums)->whereIn('number_b', $nums);
        }

        // ── Aggregate by (number_a, number_b, direction) ─────────────────────
        $records = $base
            ->selectRaw('number_a, number_b, direction, COUNT(*) as calls, SUM(duration) as total_duration')
            ->groupBy('number_a', 'number_b', 'direction')
            ->get();

        // ── Normalize pairs ───────────────────────────────────────────────────
        // Outgoing  → target called other   (⟶)
        // Incoming  → other called target   (⟵)
        $pairs = [];
        foreach ($records as $r) {
            $key = $r->number_a . '|' . $r->number_b;
            if (!isset($pairs[$key])) {
                $pairs[$key] = [
                    'source'   => $r->number_a,
                    'target'   => $r->number_b,
                    'outgoing' => 0,
                    'incoming' => 0,
                    'duration' => 0,
                ];
            }
            if ($r->direction === 'Outgoing') {
                $pairs[$key]['outgoing'] += $r->calls;
            } else {
                $pairs[$key]['incoming'] += $r->calls;
            }
            $pairs[$key]['duration'] += $r->total_duration;
        }

        // ── Nodes ─────────────────────────────────────────────────────────────
        $allNumbers = collect();
        foreach ($pairs as $p) {
            $allNumbers->push($p['source']);
            $allNumbers->push($p['target']);
        }
        $allNumbers = $allNumbers->unique()->filter()->values();

        $contacts = PhoneContact::whereIn('phone_number', $allNumbers)->get()->keyBy('phone_number');

        $freq = [];
        foreach ($pairs as $p) {
            $total = $p['outgoing'] + $p['incoming'];
            $freq[$p['source']] = ($freq[$p['source']] ?? 0) + $total;
            $freq[$p['target']] = ($freq[$p['target']] ?? 0) + $total;
        }
        $maxFreq = max(array_values($freq) ?: [1]);

        $nodes = [];
        foreach ($allNumbers as $num) {
            $contact  = $contacts->get($num);
            $isTarget = $num === $targetNumber;
            $f        = $freq[$num] ?? 1;
            $size     = 20 + round(($f / $maxFreq) * 40);

            $nodes[] = [
                'data' => [
                    'id'       => $num,
                    'label'    => $contact?->name ?? $contact?->alias ?? $num,
                    'phone'    => $num,
                    'name'     => $contact?->name,
                    'alias'    => $contact?->alias,
                    'notes'    => $contact?->notes,
                    'image'    => $contact?->image_url,
                    'calls'    => $f,
                    'isTarget' => $isTarget,
                    'size'     => $size,
                ],
            ];
        }

        // ── Edges — one edge per direction so parallel arrows are separate ────
        $edges = [];
        $ei    = 0;
        foreach ($pairs as $p) {
            if ($p['outgoing'] > 0) {
                $edges[] = [
                    'data' => [
                        'id'      => 'e' . $ei++,
                        'source'  => $p['source'],
                        'target'  => $p['target'],
                        'dirType' => 'outgoing',
                        'calls'   => $p['outgoing'],
                        'duration'=> $p['duration'],
                        'label'   => '⟶ ' . $p['outgoing'],
                        'color'   => '#10b981',
                        'width'   => max(1, min(8, round($p['outgoing'] / 2))),
                    ],
                ];
            }
            if ($p['incoming'] > 0) {
                $edges[] = [
                    'data' => [
                        'id'      => 'e' . $ei++,
                        'source'  => $p['source'],
                        'target'  => $p['target'],
                        'dirType' => 'incoming',
                        'calls'   => $p['incoming'],
                        'duration'=> $p['duration'],
                        'label'   => '⟵ ' . $p['incoming'],
                        'color'   => '#ef4444',
                        'width'   => max(1, min(8, round($p['incoming'] / 2))),
                    ],
                ];
            }
        }

        return response()->json([
            'nodes'        => $nodes,
            'edges'        => $edges,
            'targetNumber' => $targetNumber,
            'numberList'   => $numberList,
        ]);
    }
}
