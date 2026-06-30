<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class FilamentTabBadgeCounts
{
    /**
     * @return array{
     *     pending_noo: int,
     *     confirmed_noo: int,
     *     approved_noo: int,
     *     rejected_noo: int,
     *     pending_lead: int
     * }
     */
    public static function registerStatusCounts(Builder $query): array
    {
        $rows = (clone $query)
            ->selectRaw('status, type, COUNT(*) as aggregate')
            ->groupBy('status', 'type')
            ->get();

        $counts = [
            'pending_noo' => 0,
            'confirmed_noo' => 0,
            'approved_noo' => 0,
            'rejected_noo' => 0,
            'pending_lead' => 0,
        ];

        foreach ($rows as $row) {
            $key = strtolower((string) $row->status).'_'.strtolower((string) $row->type);

            if ($key === 'pending_noo') {
                $counts['pending_noo'] = (int) $row->aggregate;
            } elseif ($key === 'confirmed_noo') {
                $counts['confirmed_noo'] = (int) $row->aggregate;
            } elseif ($key === 'approved_noo') {
                $counts['approved_noo'] = (int) $row->aggregate;
            } elseif ($key === 'rejected_noo') {
                $counts['rejected_noo'] = (int) $row->aggregate;
            } elseif ($key === 'pending_lead') {
                $counts['pending_lead'] = (int) $row->aggregate;
            }
        }

        return $counts;
    }

    /**
     * @return array{planned: int, extracall: int}
     */
    public static function visitTypeCounts(Builder $query): array
    {
        $rows = (clone $query)
            ->selectRaw('tipe_visit, COUNT(*) as aggregate')
            ->groupBy('tipe_visit')
            ->pluck('aggregate', 'tipe_visit');

        return [
            'planned' => (int) ($rows['PLANNED'] ?? 0),
            'extracall' => (int) ($rows['EXTRACALL'] ?? 0),
        ];
    }
}
