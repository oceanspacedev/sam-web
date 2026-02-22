<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegisterFieldValue extends Model
{
    protected $guarded = ['id'];

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(DivisionRegisterField::class, 'field_id');
    }
}
