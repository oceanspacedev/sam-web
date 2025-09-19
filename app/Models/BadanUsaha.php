<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BadanUsaha extends Model
{
    use HasFactory;

    protected $guarded = [
        'id',
    ];

    protected $hidden = [
        'created_at', 'updated_at',
    ];

    protected $table = 'badan_usahas';

    public function user(): HasMany
    {
        return $this->hasMany(User::class, 'badanusaha_id');
    }

    public function outlet(): HasMany
    {
        return $this->hasMany(Outlet::class, 'badanusaha_id');
    }

    public function noo(): HasMany
    {
        return $this->hasMany(Noo::class, 'badanusaha_id');
    }

    public function divisi(): HasMany
    {
        return $this->hasMany(Division::class, 'badanusaha_id');
    }

    // Alias untuk compatibility dengan Filament
    public function divisions(): HasMany
    {
        return $this->divisi();
    }

    public function region(): HasMany
    {
        return $this->hasMany(Region::class, 'badanusaha_id');
    }

    // Alias untuk compatibility dengan Filament
    public function regions(): HasMany
    {
        return $this->region();
    }

    public function cluster(): HasMany
    {
        return $this->hasMany(Cluster::class, 'badanusaha_id');
    }

    // Alias untuk compatibility dengan Filament
    public function clusters(): HasMany
    {
        return $this->cluster();
    }
}
