<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FonnteWhatsAppService;
use App\Support\WhatsAppNumber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendUserWhatsAppRegisteredNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $userId)
    {
        $this->onQueue('notifications');
    }

    public function handle(FonnteWhatsAppService $whatsApp): void
    {
        $user = User::query()
            ->select(['id', 'nama_lengkap', 'whatsapp_number', 'whatsapp_verified_at'])
            ->find($this->userId);

        if (! $user || blank($user->whatsapp_number) || ! $user->whatsapp_verified_at) {
            return;
        }

        $number = WhatsAppNumber::normalize((string) $user->whatsapp_number);

        if (! WhatsAppNumber::isValid($number)) {
            return;
        }

        try {
            $whatsApp->sendAccountRegistered($number, $user->nama_lengkap);
        } catch (Throwable $exception) {
            Log::warning('Gagal mengirim notifikasi WhatsApp akun SAM terdaftar', [
                'user_id' => $user->id,
                'masked_number' => WhatsAppNumber::mask($number),
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
