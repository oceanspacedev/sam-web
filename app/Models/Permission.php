<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'guard_name', 'description'];

    protected $hidden = [
        'deleted_at',
    ];
}
