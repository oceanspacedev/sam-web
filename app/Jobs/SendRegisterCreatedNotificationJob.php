<?php

namespace App\Jobs;

use App\Helpers\SendNotif;
use App\Models\Register;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendRegisterCreatedNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param  array{badanusaha_id:int|null,divisi_id:int|null,region_id:int|null,cluster_id:int|null,tm_id:int|null}  $hierarchy
     */
    public function __construct(
        public int $registerId,
        public int $actorId,
        public array $hierarchy,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $register = Register::query()
            ->select(['id', 'nama_outlet'])
            ->find($this->registerId);
        $actor = User::query()
            ->select(['id', 'nama_lengkap', 'tm_id', 'id_notif'])
            ->with(['tm:id,id_notif'])
            ->find($this->actorId);

        if (! $register || ! $actor) {
            return;
        }

        $recipientIds = $this->buildNotificationRecipients($actor, $this->hierarchy);
        if ($recipientIds === []) {
            return;
        }

        SendNotif::sendMessage(
            'Register baru '.$register->nama_outlet.' ditambahkan oleh '.$actor->nama_lengkap,
            $recipientIds
        );
    }

    /**
     * @param  array{region_id:int|null,cluster_id:int|null}  $hierarchy
     * @return array<int, string>
     */
    protected function buildNotificationRecipients(User $actor, array $hierarchy): array
    {
        $recipients = [];

        if ($actor->tm?->id_notif) {
            $recipients[] = $actor->tm->id_notif;
        }

        if ($hierarchy['region_id'] ?? null) {
            $recipients = array_merge($recipients, User::query()
                ->whereNotNull('id_notif')
                ->whereHas('role', fn ($query) => $query->where('organizational_scope_level', 'region'))
                ->whereHas('regions', fn ($query) => $query->where('regions.id', $hierarchy['region_id']))
                ->pluck('id_notif')
                ->toArray());
        }

        if ($hierarchy['cluster_id'] ?? null) {
            $recipients = array_merge($recipients, User::query()
                ->whereNotNull('id_notif')
                ->whereHas('role', fn ($query) => $query->where('organizational_scope_level', 'cluster'))
                ->whereHas('clusters', fn ($query) => $query->where('clusters.id', $hierarchy['cluster_id']))
                ->pluck('id_notif')
                ->toArray());
        }

        return array_values(array_unique(array_filter($recipients)));
    }
}
