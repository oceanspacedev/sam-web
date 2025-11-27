<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BadanUsaha extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $guarded = [
        'id',
    ];

    protected $hidden = [
        'created_at', 'updated_at', 'deleted_at',
    ];

    protected $table = 'badan_usahas';

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function user(): HasMany
    {
        return $this->hasMany(User::class, 'badanusaha_id');
    }

    public function outlet(): HasMany
    {
        return $this->hasMany(Outlet::class, 'badanusaha_id');
    }

    public function registers(): HasMany
    {
        return $this->hasMany(Register::class, 'badanusaha_id');
    }

    public function divisions(): HasMany
    {
        return $this->hasMany(Division::class, 'badanusaha_id');
    }

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class, 'badanusaha_id');
    }

    public function clusters(): HasMany
    {
        return $this->hasMany(Cluster::class, 'badanusaha_id');
    }
}
