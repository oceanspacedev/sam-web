<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cluster extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected $hidden = [
        'created_at', 'updated_at',
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

    public function noo(): HasMany
    {
        return $this->hasMany(Noo::class);
    }

    public function badanusaha(): BelongsTo
    {
        return $this->belongsTo(BadanUsaha::class);
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }
}
