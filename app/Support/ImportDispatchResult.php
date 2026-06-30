<?php

namespace App\Support;

class ImportDispatchResult
{
    public function __construct(
        public readonly string $mode,
        public readonly bool $queued,
    ) {}

    public function isSync(): bool
    {
        return $this->mode === 'sync';
    }
}
