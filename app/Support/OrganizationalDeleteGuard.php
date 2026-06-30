<?php

namespace App\Support;

use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\SystemSetting;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrganizationalDeleteGuard
{
    /**
     * @return array<string, int>
     */
    public static function dependencies(Model $record): array
    {
        $dependencies = [];

        foreach (self::dependencyCounters($record) as $label => $counter) {
            $count = $counter($record);

            if ($count > 0) {
                $dependencies[$label] = $count;
            }
        }

        return $dependencies;
    }

    public static function hasDependencies(Model $record): bool
    {
        return self::dependencies($record) !== [];
    }

    public static function recordLabel(Model $record): string
    {
        $code = $record->getAttribute('code');
        $name = $record->getAttribute('name');

        if ($code && $name) {
            return "{$code} — {$name}";
        }

        return (string) ($name ?: $code ?: $record->getKey());
    }

    public static function dependencySummary(Model $record): string
    {
        $parts = [];

        foreach (self::dependencies($record) as $label => $count) {
            $parts[] = "{$count} {$label}";
        }

        return implode(', ', $parts);
    }

    public static function blockMessage(Model $record): string
    {
        return 'Tidak bisa menghapus '.self::recordLabel($record).' karena masih digunakan. Pindahkan atau bersihkan relasi berikut terlebih dahulu: '
            .self::dependencySummary($record)
            .'.';
    }

    public static function bulkBlockMessage(Collection $records): string
    {
        $blocked = $records
            ->filter(fn (Model $record): bool => self::hasDependencies($record))
            ->map(fn (Model $record): string => self::recordLabel($record).': '.self::dependencySummary($record))
            ->values();

        if ($blocked->isEmpty()) {
            return '';
        }

        if ($blocked->count() === 1) {
            return self::blockMessage($records->first(fn (Model $record): bool => self::hasDependencies($record)));
        }

        return 'Tidak bisa menghapus '.$blocked->count().' record karena masih digunakan:'."\n"
            .$blocked->map(fn (string $line): string => '• '.$line)->implode("\n");
    }

    public static function notifyBlocked(Model $record): void
    {
        Notification::make()
            ->title('Tidak Bisa Menghapus')
            ->body(self::blockMessage($record))
            ->danger()
            ->send();
    }

    public static function notifyBulkBlocked(Collection $records): void
    {
        Notification::make()
            ->title('Tidak Bisa Menghapus')
            ->body(self::bulkBlockMessage($records))
            ->danger()
            ->send();
    }

    public static function blockIfHasDependencies(Model $record): bool
    {
        if (! self::hasDependencies($record)) {
            return false;
        }

        self::notifyBlocked($record);

        return true;
    }

    public static function blockBulkIfHasDependencies(Collection $records): bool
    {
        $blockedRecords = $records->filter(fn (Model $record): bool => self::hasDependencies($record));

        if ($blockedRecords->isEmpty()) {
            return false;
        }

        self::notifyBulkBlocked($blockedRecords);

        return true;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Model&(\App\Models\BadanUsaha|\App\Models\Division|\App\Models\Region|\App\Models\Cluster)  $record
     */
    public static function deleteRecord(Model $record): void
    {
        if (self::blockIfHasDependencies($record)) {
            return;
        }

        $record->delete();
    }

    public static function deleteRecords(Collection $records): void
    {
        if (self::blockBulkIfHasDependencies($records)) {
            return;
        }

        $records->each->delete();
    }

    public static function forceDeleteRecords(Collection $records): void
    {
        if (self::blockBulkIfHasDependencies($records)) {
            return;
        }

        $records->each->forceDelete();
    }

    /**
     * @return array<string, callable(Model): int>
     */
    private static function dependencyCounters(Model $record): array
    {
        return match ($record::class) {
            BadanUsaha::class => [
                'Division' => fn (BadanUsaha $badanUsaha): int => $badanUsaha->divisions()->count(),
                'Region' => fn (BadanUsaha $badanUsaha): int => $badanUsaha->regions()->count(),
                'Cluster' => fn (BadanUsaha $badanUsaha): int => $badanUsaha->clusters()->count(),
                'Outlet' => fn (BadanUsaha $badanUsaha): int => $badanUsaha->outlet()->count(),
                'Register' => fn (BadanUsaha $badanUsaha): int => $badanUsaha->registers()->count(),
                'User' => fn (BadanUsaha $badanUsaha): int => (int) DB::table('user_badan_usaha')
                    ->where('badanusaha_id', $badanUsaha->getKey())
                    ->count(),
                'System Setting' => fn (BadanUsaha $badanUsaha): int => SystemSetting::query()
                    ->where('scope_level', SystemSetting::SCOPE_BADANUSAHA)
                    ->where('badanusaha_id', $badanUsaha->getKey())
                    ->count(),
            ],
            Division::class => [
                'Region' => fn (Division $division): int => $division->regions()->count(),
                'Cluster' => fn (Division $division): int => $division->clusters()->count(),
                'Outlet' => fn (Division $division): int => $division->outlet()->count(),
                'Register' => fn (Division $division): int => $division->registers()->count(),
                'User' => fn (Division $division): int => (int) DB::table('user_divisi')
                    ->where('divisi_id', $division->getKey())
                    ->count(),
                'System Setting' => fn (Division $division): int => SystemSetting::query()
                    ->where('scope_level', SystemSetting::SCOPE_DIVISION)
                    ->where('division_id', $division->getKey())
                    ->count(),
            ],
            Region::class => [
                'Cluster' => fn (Region $region): int => $region->clusters()->count(),
                'Outlet' => fn (Region $region): int => $region->outlet()->count(),
                'Register' => fn (Region $region): int => $region->registers()->count(),
                'User' => fn (Region $region): int => (int) DB::table('user_regions')
                    ->where('region_id', $region->getKey())
                    ->count(),
                'System Setting' => fn (Region $region): int => SystemSetting::query()
                    ->where('scope_level', SystemSetting::SCOPE_REGION)
                    ->where('region_id', $region->getKey())
                    ->count(),
            ],
            Cluster::class => [
                'Outlet' => fn (Cluster $cluster): int => $cluster->outlets()->count(),
                'Register' => fn (Cluster $cluster): int => $cluster->registers()->count(),
                'User' => fn (Cluster $cluster): int => (int) DB::table('user_clusters')
                    ->where('cluster_id', $cluster->getKey())
                    ->count(),
                'System Setting' => fn (Cluster $cluster): int => SystemSetting::query()
                    ->where('scope_level', SystemSetting::SCOPE_CLUSTER)
                    ->where('cluster_id', $cluster->getKey())
                    ->count(),
            ],
            default => [],
        };
    }
}
