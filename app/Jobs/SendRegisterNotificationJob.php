<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendRegisterNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        protected array $notificationIds,
        protected string $title,
        protected string $body,
        protected array $data = []
    ) {}

    public function handle(): void
    {
        if (empty($this->notificationIds)) {
            Log::info('[SendRegisterNotificationJob] No notification IDs provided, skipping');

            return;
        }

        $url = 'https://fcm.googleapis.com/fcm/send';
        $serverKey = env('FCM_SERVER_KEY');

        if (empty($serverKey)) {
            Log::error('[SendRegisterNotificationJob] FCM_SERVER_KEY not configured');

            return;
        }

        $payload = [
            'registration_ids' => array_filter($this->notificationIds), // Remove nulls
            'notification' => [
                'title' => $this->title,
                'body' => $this->body,
                'sound' => 'default',
            ],
            'data' => $this->data,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.$serverKey,
                'Content-Type' => 'application/json',
            ])->post($url, $payload);

            if ($response->successful()) {
                Log::info('[SendRegisterNotificationJob] Notification sent successfully', [
                    'ids_count' => count($this->notificationIds),
                    'response' => $response->json(),
                ]);
            } else {
                Log::error('[SendRegisterNotificationJob] FCM request failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                // Retry jika bukan client error (4xx)
                if ($response->status() >= 500) {
                    $this->release(60); // Retry after 1 minute
                }
            }
        } catch (\Exception $e) {
            Log::error('[SendRegisterNotificationJob] Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Retry on exception
            $this->release(60);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[SendRegisterNotificationJob] Job failed after retries', [
            'exception' => $exception->getMessage(),
            'notification_ids' => $this->notificationIds,
            'title' => $this->title,
        ]);
    }
}
