<?php

namespace App\Support;

class StorageDisk
{
    public static function default(): string
    {
        return (string) config('filesystems.default', 'public');
    }
}
