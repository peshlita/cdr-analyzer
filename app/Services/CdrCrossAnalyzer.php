<?php

namespace App\Services;

use App\Models\CdrRecord;

/**
 * Detecta cruces entre sábanas (batches): números de contacto en común y
 * comunicación directa entre los números principales. Las consultas usan el
 * modelo CdrRecord, por lo que respetan el aislamiento por usuario/tenant.
 */
class CdrCrossAnalyzer
{
    public function analyzeCross(array $batchIds): array
    {
        $batchIds = array_values(array_unique(array_filter($batchIds)));
        if (count($batchIds) < 2) {
            return ['common_contacts' => [], 'direct_communication' => []];
        }

        // Números de contacto por sábana (excluyendo DATOS).
        $batchContacts = [];
        $mainNumbers   = [];
        foreach ($batchIds as $bid) {
            $batchContacts[$bid] = CdrRecord::where('batch_id', $bid)
                ->whereNotNull('contact_number')
                ->where('record_type', '!=', 'DATOS')
                ->pluck('contact_number')
                ->map(fn ($n) => $this->clean($n))
                ->filter(fn ($n) => strlen($n) >= 7)
                ->unique()
                ->values()
                ->all();

            $mainNumbers[$bid] = $this->clean(
                (string) CdrRecord::where('batch_id', $bid)->value('phone_main')
            );
        }

        // Cruces de contactos comunes entre cada par de sábanas.
        $crosses = [];
        $list = array_keys($batchContacts);
        for ($i = 0; $i < count($list); $i++) {
            for ($j = $i + 1; $j < count($list); $j++) {
                $a = $list[$i];
                $b = $list[$j];
                $common = array_values(array_intersect($batchContacts[$a], $batchContacts[$b]));
                if (!empty($common)) {
                    $crosses[] = [
                        'batch_a'        => $a,
                        'batch_b'        => $b,
                        'common_numbers' => $common,
                        'count'          => count($common),
                    ];
                }
            }
        }

        // ¿Los números principales se comunicaron directamente?
        $direct = [];
        foreach ($mainNumbers as $bidA => $numA) {
            foreach ($mainNumbers as $bidB => $numB) {
                if ($bidA === $bidB || $numB === '') {
                    continue;
                }
                $hasContact = CdrRecord::where('batch_id', $bidA)
                    ->where('contact_number', $numB)
                    ->exists();
                if ($hasContact) {
                    $direct[] = [
                        'from_batch'  => $bidA,
                        'from_number' => $numA,
                        'to_number'   => $numB,
                    ];
                }
            }
        }

        return ['common_contacts' => $crosses, 'direct_communication' => $direct];
    }

    private function clean(?string $n): string
    {
        return preg_replace('/[^0-9]/', '', (string) $n) ?? '';
    }
}
