<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class PlanVisit extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Unique period key: (user_id, visitable_type, visitable_id, schedule_scope, period_start).
     * Soft-deleted rows still occupy that key — restore/reuse instead of inserting a duplicate.
     */
    protected $guarded = [
        'id',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'realized_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function visitable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function isOutletVisit(): bool
    {
        return $this->visitable_type === Outlet::class;
    }

    public function isRegisterVisit(): bool
    {
        return $this->visitable_type === Register::class;
    }

    /**
     * Backward-compat: load outlet relation via visitable when it's an Outlet.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class, 'visitable_id')
            ->withTrashed();
    }

    public function realizedVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'realized_visit_id')->withTrashed();
    }

    public function scopeUnrealized(Builder $query): Builder
    {
        return $query->whereNull('realized_at');
    }

    public function markAsRealized(?Visit $visit = null, ?Carbon $timestamp = null): void
    {
        $this->forceFill([
            'realized_at' => $timestamp ?? now(),
            'realized_visit_id' => $visit?->id,
        ])->save();
    }

    public function clearRealization(): void
    {
        $this->forceFill([
            'realized_at' => null,
            'realized_visit_id' => null,
        ])->save();
    }

    public static function periodKeyExists(
        int $userId,
        string $visitableType,
        int $visitableId,
        string $scheduleScope,
        Carbon|string $periodStart,
        ?int $exceptId = null,
    ): bool {
        return static::queryForPeriodKey($userId, $visitableType, $visitableId, $scheduleScope, $periodStart)
            ->when($exceptId, fn (Builder $query) => $query->where('id', '!=', $exceptId))
            ->exists();
    }

    /**
     * @return Builder<static>
     */
    public static function queryForPeriodKey(
        int $userId,
        string $visitableType,
        int $visitableId,
        string $scheduleScope,
        Carbon|string $periodStart,
    ): Builder {
        $periodStart = $periodStart instanceof Carbon
            ? $periodStart->toDateString()
            : Carbon::parse($periodStart)->toDateString();

        return static::withTrashed()
            ->where('user_id', $userId)
            ->where('visitable_type', $visitableType)
            ->where('visitable_id', $visitableId)
            ->where('schedule_scope', $scheduleScope)
            ->whereDate('period_start', $periodStart);
    }

    /**
     * Restore a soft-deleted plan that occupies the unique period key, clearing prior realization.
     */
    public function restoreForReuse(array $attributes = []): static
    {
        if ($this->trashed()) {
            $this->restore();
        }

        $this->forceFill(array_merge($attributes, [
            'realized_at' => null,
            'realized_visit_id' => null,
        ]))->save();

        return $this;
    }

    /**
     * Create a plan for the period key, or restore the soft-deleted row that still occupies it.
     *
     * @throws ValidationException when an active plan already occupies the key
     */
    public static function createOrRestoreForPeriod(array $attributes): static
    {
        $existing = static::findForPeriodAttributes($attributes);

        if ($existing && ! $existing->trashed()) {
            throw ValidationException::withMessages([
                'period_start' => 'Plan visit untuk target, scope, dan periode ini sudah ada.',
            ]);
        }

        if ($existing) {
            return $existing->restoreForReuse($attributes);
        }

        try {
            return static::create($attributes);
        } catch (ValidationException|UniqueConstraintViolationException $exception) {
            $existing = static::findForPeriodAttributes($attributes);

            if ($existing?->trashed()) {
                return $existing->restoreForReuse($attributes);
            }

            if ($existing) {
                throw ValidationException::withMessages([
                    'period_start' => 'Plan visit untuk target, scope, dan periode ini sudah ada.',
                ]);
            }

            throw $exception;
        }
    }

    /**
     * Update an existing plan for the period key (including soft-deleted), or create one.
     *
     * @return array{0: static, 1: bool} Plan and whether it was newly created
     */
    public static function updateOrCreateForPeriod(array $attributes): array
    {
        $existing = static::findForPeriodAttributes($attributes);

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restoreForReuse($attributes);
            } else {
                $existing->update($attributes);
            }

            return [$existing->fresh() ?? $existing, false];
        }

        try {
            return [static::create($attributes), true];
        } catch (ValidationException|UniqueConstraintViolationException $exception) {
            $existing = static::findForPeriodAttributes($attributes);

            if (! $existing) {
                throw $exception;
            }

            if ($existing->trashed()) {
                $existing->restoreForReuse($attributes);
            } else {
                $existing->update($attributes);
            }

            return [$existing->fresh() ?? $existing, false];
        }
    }

    public static function findForPeriodAttributes(array $attributes): ?static
    {
        $userId = (int) ($attributes['user_id'] ?? 0);
        $visitableType = (string) ($attributes['visitable_type'] ?? '');
        $visitableId = (int) ($attributes['visitable_id'] ?? 0);
        $scheduleScope = (string) ($attributes['schedule_scope'] ?? '');
        $periodStart = $attributes['period_start'] ?? null;

        if (! $userId || $visitableType === '' || ! $visitableId || $scheduleScope === '' || $periodStart === null) {
            return null;
        }

        return static::queryForPeriodKey($userId, $visitableType, $visitableId, $scheduleScope, $periodStart)
            ->orderByRaw('deleted_at is not null')
            ->orderBy('id')
            ->first();
    }

    public static function schedulePayload(Carbon|string $startDate, string $scope = 'daily', Carbon|string|null $endDate = null): array
    {
        $start = $startDate instanceof Carbon ? $startDate->copy() : Carbon::parse($startDate);
        $start->startOfDay();

        if ($scope === 'weekly') {
            $start->startOfWeek(Carbon::MONDAY);
            $end = $start->copy()->addDays(6);
        } else {
            $end = $endDate ? ($endDate instanceof Carbon ? $endDate->copy() : Carbon::parse($endDate)) : $start->copy();
            $end->startOfDay();
        }

        return [
            'schedule_scope' => $scope,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'schedule_week' => $start->weekOfYear,
            'schedule_year' => $start->year,
            'tanggal_visit' => $start,
        ];
    }

    public function isWeekly(): bool
    {
        return $this->schedule_scope === 'weekly';
    }

    public function visitCoversDate(Carbon|string $date): bool
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date);

        if ($this->isWeekly() && $this->period_start && $this->period_end) {
            return $date->between($this->period_start, $this->period_end);
        }

        return $this->tanggal_visit ? $date->isSameDay(Carbon::parse($this->tanggal_visit)) : false;
    }
}
