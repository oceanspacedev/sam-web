<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cluster extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [
        'id',
    ];

    protected $hidden = [
        'created_at', 'updated_at', 'deleted_at',
    ];

    public function user(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function outlet(): HasMany
    {
        return $this->hasMany(Outlet::class);
    }

    // Alias untuk compatibility dengan Filament
    public function outlets(): HasMany
    {
        return $this->outlet();
    }

    public function registers(): HasMany
    {
        return $this->hasMany(Register::class);
    }

    /**
     * @deprecated Use registers() instead.
     */
    public function noo(): HasMany
    {
        return $this->registers();
    }

    public function badanusaha(): BelongsTo
    {
        return $this->belongsTo(BadanUsaha::class)->withTrashed();
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Division::class)->withTrashed();
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class)->withTrashed();
    }
}
