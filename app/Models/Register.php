<?php

namespace App\Models;

use App\Traits\CleansUpMedia;
use App\Traits\HasOrganizationalScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Register extends Model
{
    use CleansUpMedia;
    use HasFactory;
    use HasOrganizationalScope;
    use SoftDeletes;

    protected $table = 'registers';

    protected $guarded = [
        'id',
    ];

    protected array $mediaCleanupFields = [
        'poto_shop_sign',
        'poto_depan',
        'poto_kiri',
        'poto_kanan',
        'poto_ktp',
        'video',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Override visibleTo scope for Register model.
     * Filter by created_by_id and tm_id instead of organizational hierarchy.
     */
    public function scopeVisibleTo(Builder $query, \App\Models\User $user): Builder
    {
        // Check if user has a role
        if (! $user->role) {
            return $query->whereRaw('1 = 0'); // Return empty result
        }

        // If role has full access, no filtering needed
        if ($user->role->hasFullAccess()) {
            return $query;
        }

        // Filter by created_by_id OR tm_id
        return $query->where(function ($q) use ($user) {
            $q->where('created_by_id', $user->id)
                ->orWhere('tm_id', $user->id);
        });
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(Cluster::class)->withTrashed();
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class)->withTrashed();
    }

    public function badanusaha(): BelongsTo
    {
        return $this->belongsTo(BadanUsaha::class)->withTrashed();
    }

    public function divisi(): BelongsTo
    {
        return $this->belongsTo(Division::class)->withTrashed();
    }

    public function tm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tm_id')->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id')->withTrashed();
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id')->withTrashed();
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by_id')->withTrashed();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id')->withTrashed();
    }

    public function outlet(): HasOne
    {
        return $this->hasOne(Outlet::class)->withTrashed();
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(RegisterFieldValue::class);
    }

    public function visits(): MorphMany
    {
        return $this->morphMany(Visit::class, 'visitable');
    }

    public function planVisits(): MorphMany
    {
        return $this->morphMany(PlanVisit::class, 'visitable');
    }
}
