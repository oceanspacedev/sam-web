<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DivisionSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'allow_register_visit' => 'boolean',
            'max_visit_per_day' => 'integer',
            'default_register_radius' => 'integer',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }
}
