<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutletChangeArchive extends Model
{
    use HasFactory;

    public const ACTION_UPDATE = 'update';

    public const ACTION_RESET_DATA = 'reset_data';

    public const ACTION_RESET_LOCATION = 'reset_location';

    public const ACTION_RESTORE = 'restore';

    public const ACTION_APPROVAL_OVERRIDE = 'approval_override';

    protected $guarded = [
        'id',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'changed_fields' => 'array',
            'request_meta' => 'array',
            'restored_at' => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function restoredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'restored_by_user_id');
    }

    public function restoredFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'restored_from_id');
    }
}
