<?php

namespace App\Models;

use App\Services\SystemSettingResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Division extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [
        'id',
    ];

    protected $hidden = [
        'created_at', 'updated_at', 'deleted_at',
    ];

    protected static function booted(): void
    {
        static::updated(function (Division $division): void {
            if (! $division->wasChanged('badanusaha_id')) {
                return;
            }

            Region::query()
                ->where('divisi_id', $division->id)
                ->update([
                    'badanusaha_id' => $division->badanusaha_id,
                    'updated_at' => now(),
                ]);

            Cluster::query()
                ->where('divisi_id', $division->id)
                ->update([
                    'badanusaha_id' => $division->badanusaha_id,
                    'updated_at' => now(),
                ]);
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function user(): HasMany
    {
        return $this->hasMany(User::class, 'divisi_id');
    }

    public function outlet(): HasMany
    {
        return $this->hasMany(Outlet::class, 'divisi_id');
    }

    public function registers(): HasMany
    {
        return $this->hasMany(Register::class, 'divisi_id');
    }

    public function badanusaha(): BelongsTo
    {
        return $this->belongsTo(BadanUsaha::class)->withTrashed();
    }

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class, 'divisi_id');
    }

    public function clusters(): HasMany
    {
        return $this->hasMany(Cluster::class, 'divisi_id');
    }

    public function setting(): HasOne
    {
        return $this->hasOne(SystemSetting::class, 'division_id')
            ->where('scope_level', SystemSetting::SCOPE_DIVISION);
    }

    /**
     * Check if this division allows visits to Register (LEAD/NOO)
     */
    public function allowsRegisterVisit(): bool
    {
        return app(SystemSettingResolver::class)->allowsRegisterVisitForIds(
            $this->badanusaha_id ? (int) $this->badanusaha_id : null,
            $this->id ? (int) $this->id : null,
            null,
            null,
        );
    }
}
