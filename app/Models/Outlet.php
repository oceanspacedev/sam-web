<?php

namespace App\Models;

use App\Traits\CleansUpMedia;
use App\Traits\HasOrganizationalScope;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Outlet extends Model
{
    use CleansUpMedia;
    use HasFactory;
    use HasOrganizationalScope;
    use SoftDeletes;

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'last_reset_at' => 'datetime',
        'reset_count_yearly' => 'integer',
        'limit' => 'integer',
        'radius' => 'integer',
    ];

    protected array $mediaCleanupFields = [
        'poto_shop_sign',
        'poto_depan',
        'poto_kiri',
        'poto_kanan',
        'poto_ktp',
        'video',
    ];

    public const CHANGE_ARCHIVE_FIELDS = [
        'kode_outlet',
        'nama_outlet',
        'alamat_outlet',
        'nama_pemilik_outlet',
        'nomer_tlp_outlet',
        'badanusaha_id',
        'divisi_id',
        'region_id',
        'cluster_id',
        'distric',
        'poto_shop_sign',
        'poto_depan',
        'poto_kiri',
        'poto_kanan',
        'poto_ktp',
        'video',
        'limit',
        'radius',
        'latlong',
        'status_outlet',
        'register_id',
        'last_reset_at',
        'reset_count_yearly',
    ];

    private const NON_RESTORABLE_ARCHIVE_FIELDS = [
        'last_reset_at',
        'reset_count_yearly',
    ];

    public function changeArchives(): HasMany
    {
        return $this->hasMany(OutletChangeArchive::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function changeArchiveSnapshot(): array
    {
        $snapshot = [];

        foreach (self::CHANGE_ARCHIVE_FIELDS as $field) {
            $snapshot[$field] = $this->normalizeArchiveValue($this->getAttribute($field));
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, string>
     */
    public static function changedArchiveFields(array $before, array $after): array
    {
        $changed = [];

        foreach (self::CHANGE_ARCHIVE_FIELDS as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function recordChangeArchive(
        string $action,
        ?User $actor,
        array $before,
        array $after,
        ?array $requestMeta = null,
        ?OutletChangeArchive $restoredFrom = null
    ): ?OutletChangeArchive {
        $changedFields = self::changedArchiveFields($before, $after);

        if ($changedFields === []) {
            return null;
        }

        return OutletChangeArchive::query()->create([
            'outlet_id' => $this->id,
            'kode_outlet' => $before['kode_outlet'] ?? $this->kode_outlet,
            'action' => $action,
            'actor_user_id' => $actor?->id,
            'actor_name' => $actor?->nama_lengkap,
            'old_values' => $before,
            'new_values' => $after,
            'changed_fields' => $changedFields,
            'request_meta' => $requestMeta,
            'restored_from_id' => $restoredFrom?->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function restorableValues(array $values): array
    {
        $restorable = [];

        foreach (array_diff(self::CHANGE_ARCHIVE_FIELDS, self::NON_RESTORABLE_ARCHIVE_FIELDS) as $field) {
            if (array_key_exists($field, $values)) {
                $restorable[$field] = $values[$field];
            }
        }

        return $restorable;
    }

    protected function normalizeArchiveValue(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return $value;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeFilter(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('kode_outlet', 'like', "%{$search}%")
                ->orWhere('nama_outlet', 'like', "%{$search}%")
                ->orWhere('alamat_outlet', 'like', "%{$search}%")
                ->orWhere('nama_pemilik_outlet', 'like', "%{$search}%");
        });
    }

    /**
     * Scope query to outlets near a given location using Haversine formula
     *
     * @param  float  $latitude  User's current latitude
     * @param  float  $longitude  User's current longitude
     * @param  int  $limit  Maximum number of results (default: 10)
     */
    public function scopeNearbyLocation(Builder $query, float $latitude, float $longitude, int $limit = 10): Builder
    {
        // Haversine formula for calculating distance in kilometers
        $distanceFormula = "
            (
                6371 * acos(
                    cos(radians(?)) 
                    * cos(radians(CAST(SUBSTRING_INDEX(latlong, ',', 1) AS DECIMAL(10, 8))))
                    * cos(radians(CAST(SUBSTRING_INDEX(latlong, ',', -1) AS DECIMAL(11, 8))) - radians(?))
                    + sin(radians(?))
                    * sin(radians(CAST(SUBSTRING_INDEX(latlong, ',', 1) AS DECIMAL(10, 8))))
                )
            ) AS distance
        ";

        // Check if columns are already selected, if so just add distance, otherwise select all
        $existingColumns = $query->getQuery()->columns;
        if (empty($existingColumns)) {
            $query->selectRaw("*, {$distanceFormula}", [$latitude, $longitude, $latitude]);
        } else {
            $query->selectRaw($distanceFormula, [$latitude, $longitude, $latitude]);
        }

        return $query
            ->whereNotNull('latlong')
            ->where('latlong', '!=', '')
            ->where('latlong', '!=', '-')
            ->orderBy('distance', 'asc')
            ->limit($limit);
    }

    /**
     * Scope query to outlets accessible by the given user based on RBAC.
     * Uses cached organizational IDs from the user to avoid N+1 queries.
     */
    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        $ids = $user->getOrganizationalIds();
        $scopeLevel = $ids['scope_level'];

        // If full access, no filtering needed
        if ($scopeLevel === 'all') {
            return $query;
        }

        // Apply hierarchical filtering based on scope level
        if (! empty($ids['badanusaha'])) {
            $query->whereIn('badanusaha_id', $ids['badanusaha']);
        }

        if (! empty($ids['divisi'])) {
            $query->whereIn('divisi_id', $ids['divisi']);
        }

        // Apply region and cluster filters for cluster-level scope
        if ($scopeLevel === 'cluster') {
            if (! empty($ids['region'])) {
                $query->whereIn('region_id', $ids['region']);
            }
            if (! empty($ids['cluster'])) {
                $query->whereIn('cluster_id', $ids['cluster']);
            }
        }

        return $query;
    }

    /**
     * @return array<string, string>
     */
    protected static function visitRequirementLabels(): array
    {
        return [
            'alamat_outlet' => 'Alamat Outlet',
            'nama_pemilik_outlet' => 'Nama Pemilik',
            'nomer_tlp_outlet' => 'Nomor Telepon',
            'latlong' => 'Lokasi GPS',
            'poto_shop_sign' => 'Foto Shop Sign',
            'poto_depan' => 'Foto Depan',
            'poto_kiri' => 'Foto Kiri',
            'poto_kanan' => 'Foto Kanan',
            'video' => 'Video',
            'poto_ktp' => 'Foto KTP',
        ];
    }

    protected static function isMissingValue(?string $value): bool
    {
        if ($value === null) {
            return true;
        }

        $normalized = trim($value);

        return $normalized === '' || $normalized === '-' || $normalized === '0';
    }

    protected static function isValidLatlong(?string $value): bool
    {
        if (self::isMissingValue($value)) {
            return false;
        }

        $parts = explode(',', (string) $value);
        if (count($parts) < 2) {
            return false;
        }

        $lat = trim($parts[0]);
        $lng = trim($parts[1]);

        if ($lat === '' || $lng === '') {
            return false;
        }

        return is_numeric($lat) && is_numeric($lng);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class)->withTrashed();
    }

    public function planvisit(): MorphMany
    {
        return $this->morphMany(PlanVisit::class, 'visitable');
    }

    public function visit(): MorphMany
    {
        return $this->morphMany(Visit::class, 'visitable');
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class)->withTrashed();
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class)->withTrashed();
    }

    public function user(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function badanusaha(): BelongsTo
    {
        return $this->belongsTo(BadanUsaha::class)->withTrashed();
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Division::class)->withTrashed();
    }
}
