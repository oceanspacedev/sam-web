<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DivisionRegisterField extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function values(): HasMany
    {
        return $this->hasMany(RegisterFieldValue::class, 'field_id');
    }

    public function appliesToLead(): bool
    {
        return in_array($this->applies_to, ['lead', 'both']);
    }

    public function appliesToNoo(): bool
    {
        return in_array($this->applies_to, ['noo', 'both']);
    }
}
