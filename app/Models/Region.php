<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Region extends Model
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
        static::saving(function (Region $region): void {
            if (! $region->divisi_id) {
                return;
            }

            $division = Division::withTrashed()
                ->select('id', 'badanusaha_id')
                ->find($region->divisi_id);

            if (! $division) {
                return;
            }

            $region->badanusaha_id = $division->badanusaha_id;
        });

        static::updated(function (Region $region): void {
            if (! $region->wasChanged(['divisi_id', 'badanusaha_id'])) {
                return;
            }

            Cluster::query()
                ->where('region_id', $region->id)
                ->update([
                    'divisi_id' => $region->divisi_id,
                    'badanusaha_id' => $region->badanusaha_id,
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
        return $this->hasMany(User::class);
    }

    public function outlet(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    public function registers(): HasMany
    {
        return $this->hasMany(Register::class);
    }

    public function badanusaha(): BelongsTo
    {
        return $this->belongsTo(BadanUsaha::class)->withTrashed();
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Division::class)->withTrashed();
    }

    public function clusters(): HasMany
    {
        return $this->hasMany(Cluster::class);
    }
}
