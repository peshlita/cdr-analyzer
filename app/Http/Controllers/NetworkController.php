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
        // ── Números objetivo: uno por sábana cargada (número_a más frecuente) ─
        $targetNumbers = \DB::table('cdr_records')
            ->selectRaw('source_file, number_a, COUNT(*) as cnt')
            ->whereNotNull('number_a')
            ->whereNotNull('source_file')
            ->groupBy('source_file', 'number_a')
            ->get()
            ->groupBy('source_file')
            ->map(fn($g) => $g->sortByDesc('cnt')->first()->number_a)
            ->values()
            ->unique()
            ->filter()
            ->toArray();

        if (empty($targetNumbers)) {
            $single = CdrRecord::getTargetNumber();
            $targetNumbers = $single ? [$single] : [];
        }

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

        // ── Number list: TODOS los teléfonos que aparecen en registros de voz ──
        // Se cuenta el número de veces que aparece en cualquier columna
        // (number_a O number_b) y se ordena por total combinado desc.
        $dateBindings = [];
        $dateWhere    = '';
        if ($request->filled('date_from')) {
            $dateWhere    .= ' AND date >= ?';
            $dateBindings[] = $request->date_from;
        }
        if ($request->filled('date_to')) {
            $dateWhere    .= ' AND date <= ?';
            $dateBindings[] = $request->date_to;
        }

        // Los bindings se repiten para las dos mitades del UNION ALL
        $numFreqs = collect(\DB::select("
            SELECT phone, SUM(cnt) AS cnt
            FROM (
                SELECT number_a AS phone, COUNT(*) AS cnt
                  FROM cdr_records
                 WHERE type = 'voice'
                   AND number_a IS NOT NULL
                   AND number_b IS NOT NULL
                   {$dateWhere}
                 GROUP BY number_a

                UNION ALL

                SELECT number_b AS phone, COUNT(*) AS cnt
                  FROM cdr_records
                 WHERE type = 'voice'
                   AND number_a IS NOT NULL
                   AND number_b IS NOT NULL
                   {$dateWhere}
                 GROUP BY number_b
            ) t
            GROUP BY phone
            ORDER BY cnt DESC
        ", array_merge($dateBindings, $dateBindings)));

        $nlPhones   = $numFreqs->pluck('phone');
        $nlContacts = PhoneContact::whereIn('phone_number', $nlPhones)->get()->keyBy('phone_number');

        // Teléfonos que aparecen en más de una sábana (cruce entre sábanas)
        $crossPhones = collect(\DB::select("
            SELECT phone FROM (
                SELECT number_a AS phone, source_file FROM cdr_records WHERE number_a IS NOT NULL AND source_file IS NOT NULL
                UNION
                SELECT number_b AS phone, source_file FROM cdr_records WHERE number_b IS NOT NULL AND source_file IS NOT NULL
            ) t
            GROUP BY phone HAVING COUNT(DISTINCT source_file) > 1
        "))->pluck('phone')->flip();

        $numberList = $numFreqs->map(fn($r) => [
            'phone'    => $r->phone,
            'label'    => $nlContacts->get($r->phone)?->name
                       ?? $nlContacts->get($r->phone)?->alias
                       ?? $r->phone,
            'calls'    => (int) $r->cnt,
            'isTarget' => in_array($r->phone, $targetNumbers),
            'crossRef' => isset($crossPhones[$r->phone]),
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

        // ── Consolidar pares con clave canónica min|max ───────────────────────
        //
        // Con múltiples sábanas el mismo par A↔B puede aparecer en la BD con
        // roles invertidos: (number_a=A, number_b=B) y (number_a=B, number_b=A).
        // Usando siempre la clave canónica garantizamos máximo 2 aristas por par,
        // sin importar cuántas sábanas estén cargadas.
        //
        // Dirección canónica (relativa al nodo "source" = número menor):
        //   outgoing : source llamó a target
        //   incoming : target llamó a source
        //
        // Cómo se determina la dirección canónica según el registro:
        //   number_a=lo, direction=Outgoing  → lo llamó a hi  → outgoing
        //   number_a=lo, direction=Incoming  → hi llamó a lo  → incoming
        //   number_a=hi, direction=Outgoing  → hi llamó a lo  → incoming
        //   number_a=hi, direction=Incoming  → lo llamó a hi  → outgoing
        $pairs = [];
        foreach ($records as $r) {
            if (!$r->number_a || !$r->number_b) continue;

            // Determinar lo/hi (orden canónico)
            [$lo, $hi] = strcmp($r->number_a, $r->number_b) <= 0
                ? [$r->number_a, $r->number_b]
                : [$r->number_b, $r->number_a];

            $key = $lo . '|' . $hi;
            if (!isset($pairs[$key])) {
                $pairs[$key] = [
                    'source'   => $lo,
                    'target'   => $hi,
                    'outgoing' => 0,
                    'incoming' => 0,
                    'duration' => 0,
                ];
            }

            // Traducir dirección del registro a dirección canónica.
            //
            // Hay dos convenciones de dirección según el formato:
            //
            // Formato B: number_a es SIEMPRE el abonado analizado (target).
            //   Outgoing → number_a llamó → caller = number_a
            //   Incoming → number_b llamó → caller = number_b
            //
            // Formato A: number_a ALTERNA de rol:
            //   Outgoing → number_a = target (caller), number_b = otro
            //   Incoming → number_a = otro (caller), number_b = target  ← CLAVE
            //
            // En registros Incoming de Formato A, number_a es el que LLAMA (el otro),
            // por lo que la dirección canónica se determina igual que para Outgoing:
            // "caller = number_a, ¿es number_a el lo?" → isCanonicalOutgoing = aIsLo.
            //
            // Regla: si number_a ES un target → Formato B → Incoming invierte caller
            //        si number_a NO es target → Formato A Incoming → caller = number_a
            $aIsLo    = ($r->number_a === $lo);
            $isTargetA = in_array($r->number_a, $targetNumbers);
            $isCanonicalOutgoing = match($r->direction) {
                'Outgoing' => $aIsLo,
                'Incoming' => $isTargetA ? !$aIsLo : $aIsLo,
                default    => $isTargetA ? !$aIsLo : $aIsLo,
            };

            if ($isCanonicalOutgoing) {
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

        // ── Cross-reference: which source files each number appears in ─────────
        $sourcesMap = \DB::table('cdr_records')
            ->select('number_a as phone', 'source_file')
            ->whereNotNull('source_file')->whereNotNull('number_a')
            ->union(
                \DB::table('cdr_records')
                    ->select('number_b as phone', 'source_file')
                    ->whereNotNull('number_b')->whereNotNull('source_file')
            )
            ->distinct()->get()
            ->groupBy('phone')
            ->map(fn($g) => $g->pluck('source_file')->unique()->values()->toArray());

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
            $isTarget = in_array($num, $targetNumbers);
            $f        = $freq[$num] ?? 1;
            $size     = 20 + round(($f / $maxFreq) * 40);

            $sources  = $sourcesMap->get($num, []);
            $crossRef = count($sources) > 1;

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
                    'sources'  => $sources,
                    'crossRef' => $crossRef,
                ],
            ];
        }

        // ── Edges ─────────────────────────────────────────────────────────────
        // outgoing: source=lo → target=hi  (lo llamó a hi)
        // incoming: source=hi → target=lo  (hi llamó a lo)
        //
        // Al invertir source/target en la arista entrante, ambas aristas van en
        // sentidos físicamente opuestos dentro de Cytoscape. El motor de grafo
        // las separa automáticamente con curvas en arco visibles, eliminando el
        // colapso visual que ocurría cuando las dos compartían el mismo source y target.
        $edges = [];
        $ei    = 0;
        foreach ($pairs as $p) {
            if ($p['outgoing'] > 0) {
                $edges[] = [
                    'data' => [
                        'id'      => 'e' . $ei++,
                        'source'  => $p['source'],   // lo
                        'target'  => $p['target'],   // hi
                        'dirType' => 'outgoing',
                        'calls'   => $p['outgoing'],
                        'duration'=> $p['duration'],
                        'label'   => (string) $p['outgoing'],
                        'color'   => '#10b981',
                        'width'   => max(1, min(8, round($p['outgoing'] / 2))),
                    ],
                ];
            }
            if ($p['incoming'] > 0) {
                $edges[] = [
                    'data' => [
                        'id'      => 'e' . $ei++,
                        'source'  => $p['target'],   // hi → invertido
                        'target'  => $p['source'],   // lo → invertido
                        'dirType' => 'incoming',
                        'calls'   => $p['incoming'],
                        'duration'=> $p['duration'],
                        'label'   => (string) $p['incoming'],
                        'color'   => '#ef4444',
                        'width'   => max(1, min(8, round($p['incoming'] / 2))),
                    ],
                ];
            }
        }

        return response()->json([
            'nodes'         => $nodes,
            'edges'         => $edges,
            'targetNumbers' => $targetNumbers,
            'numberList'    => $numberList,
        ]);
    }
}
