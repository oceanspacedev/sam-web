<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    protected $hidden = [
        'created_at', 'updated_at',
    ];

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'filter_data' => 'array',
    ];

    public function user(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }
}
