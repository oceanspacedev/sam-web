<?php

use App\Support\WhatsAppQueueDelay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

afterEach(function (): void {
    Carbon::setTestNow();
});

it('staggered whatsapp queue dispatches by the configured delay', function (): void {
    config(['services.whatsapp.queue_delay_seconds' => 12]);

    Cache::forget(WhatsAppQueueDelay::LOCK_KEY);
    Cache::forget(WhatsAppQueueDelay::NEXT_AVAILABLE_AT_KEY);
    Carbon::setTestNow(Carbon::parse('2026-04-29 10:00:00'));

    $delayer = app(WhatsAppQueueDelay::class);

    expect($delayer->nextDelay())->toBe(0)
        ->and($delayer->nextDelay())->toBe(12)
        ->and($delayer->nextDelay())->toBe(24);
});

it('can disable whatsapp queue delay', function (): void {
    config(['services.whatsapp.queue_delay_seconds' => 0]);

    expect(app(WhatsAppQueueDelay::class)->nextDelay())->toBe(0);
});
