<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

class WhatsAppQueueDelay
{
    public const LOCK_KEY = 'fonnte_whatsapp_queue_delay_lock';

    public const NEXT_AVAILABLE_AT_KEY = 'fonnte_whatsapp_next_available_at';

    public function nextDelay(): int
    {
        $delaySeconds = $this->delaySeconds();

        if ($delaySeconds <= 0) {
            return 0;
        }

        try {
            return (int) Cache::lock(self::LOCK_KEY, 5)->block(3, function () use ($delaySeconds): int {
                $now = now()->timestamp;
                $nextAvailableAt = max($now, (int) Cache::get(self::NEXT_AVAILABLE_AT_KEY, $now));

                Cache::put(
                    self::NEXT_AVAILABLE_AT_KEY,
                    $nextAvailableAt + $delaySeconds,
                    now()->addDay()
                );

                return max(0, $nextAvailableAt - $now);
            });
        } catch (Throwable) {
            return $delaySeconds;
        }
    }

    public function delaySeconds(): int
    {
        return max(0, (int) config('services.fonnte.queue_delay_seconds', 10));
    }
}
