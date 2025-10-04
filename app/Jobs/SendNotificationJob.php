<?php

namespace App\Jobs;

use App\Helpers\SendNotif;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    /**
     * @var array<int, string>
     */
    public array $recipientIds;

    public function __construct(public string $message, array $recipientIds)
    {
        $this->recipientIds = array_values(array_filter($recipientIds));
    }

    /**
     * Get the queue the job should be sent to.
     *
     * @return string
     */
    public function queue(): string
    {
        return 'notifications';
    }

    public function handle(): void
    {
        if ($this->recipientIds === []) {
            return;
        }

        SendNotif::sendMessage($this->message, $this->recipientIds);
    }
}
