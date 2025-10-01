<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Division extends Model
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

    /**
     * @deprecated Use registers() instead.
     */
    public function noo(): HasMany
    {
        return $this->registers();
    }

    public function badanusaha(): BelongsTo
    {
        return $this->belongsTo(BadanUsaha::class);
    }

    public function region(): HasMany
    {
        return $this->hasMany(Region::class, 'divisi_id');
    }

    // Alias untuk compatibility dengan Filament
    public function regions(): HasMany
    {
        return $this->region();
    }

    public function cluster(): HasMany
    {
        return $this->hasMany(Cluster::class, 'divisi_id');
    }

    // Alias untuk compatibility dengan Filament
    public function clusters(): HasMany
    {
        return $this->cluster();
    }
}
