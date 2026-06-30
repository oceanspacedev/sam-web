<?php

namespace App\Support;

class FilamentTableEagerLoad
{
    private const ORG_COLUMNS = 'id,name,code';

    /**
     * @return array<int, string>
     */
    public static function hierarchy(string ...$relations): array
    {
        $map = [
            'badanusaha' => 'badanusaha:'.self::ORG_COLUMNS,
            'divisi' => 'divisi:'.self::ORG_COLUMNS,
            'region' => 'region:'.self::ORG_COLUMNS,
            'cluster' => 'cluster:'.self::ORG_COLUMNS,
        ];

        return array_values(array_intersect_key($map, array_flip($relations)));
    }

    /**
     * @return array<int, string>
     */
    public static function fullHierarchy(): array
    {
        return self::hierarchy('badanusaha', 'divisi', 'region', 'cluster');
    }

    /**
     * @return array<int, string>
     */
    public static function userAssignments(): array
    {
        return [
            'role:id,name,organizational_scope_level',
            'tm:id,nama_lengkap',
            'badanUsahas:'.self::ORG_COLUMNS,
            'divisis:'.self::ORG_COLUMNS,
            'regions:'.self::ORG_COLUMNS,
            'clusters:'.self::ORG_COLUMNS,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function registerHierarchy(): array
    {
        return [
            'createdBy:id,nama_lengkap',
            'badanusaha:'.self::ORG_COLUMNS,
            'divisi:'.self::ORG_COLUMNS,
            'region:'.self::ORG_COLUMNS,
            'cluster:'.self::ORG_COLUMNS,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function visitableTarget(): array
    {
        return [
            'user:id,nama_lengkap',
            'visitable',
        ];
    }
}
