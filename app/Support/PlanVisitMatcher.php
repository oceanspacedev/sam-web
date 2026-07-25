<?php

namespace App\Support;

use App\Models\PlanVisit;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PlanVisitMatcher
{
    /**
     * Unrealized plans that cover the visit date (daily ±1 day / weekly ±1 day window).
     *
     * @return Collection<int, PlanVisit>
     */
    public static function unrealizedPlansForVisit(
        int $userId,
        string $visitableType,
        int $visitableId,
        Carbon|string $visitDate,
    ): Collection {
        $visitDate = Carbon::parse($visitDate)->startOfDay();
        $yesterday = $visitDate->copy()->subDay();
        $tomorrow = $visitDate->copy()->addDay();

        return PlanVisit::query()
            ->where('user_id', $userId)
            ->where('visitable_type', $visitableType)
            ->where('visitable_id', $visitableId)
            ->unrealized()
            ->where(function (Builder $query) use ($visitDate, $yesterday, $tomorrow): void {
                $query->where(function (Builder $weekly) use ($yesterday, $tomorrow): void {
                    $weekly
                        ->where('schedule_scope', 'weekly')
                        ->whereDate('period_start', '<=', $tomorrow->toDateString())
                        ->whereDate('period_end', '>=', $yesterday->toDateString());
                })->orWhere(function (Builder $daily) use ($visitDate, $yesterday, $tomorrow): void {
                    $daily
                        ->where('schedule_scope', 'daily')
                        ->where(function (Builder $q) use ($visitDate, $yesterday, $tomorrow): void {
                            $q->whereDate('period_start', $visitDate->toDateString())
                                ->orWhereDate('period_start', $yesterday->toDateString())
                                ->orWhereDate('period_start', $tomorrow->toDateString());
                        });
                });
            })
            ->orderByDesc('schedule_scope')
            ->orderBy('period_start')
            ->orderBy('id')
            ->get();
    }

    /**
     * Whether check-in should be typed as PLANNED for this target/date.
     *
     * Rejects when the weekly/daily period is already consumed by a realized sibling
     * plan or an existing PLANNED visit in that period.
     */
    public static function shouldMarkPlanned(
        int $userId,
        string $visitableType,
        int $visitableId,
        Carbon|string $visitDate,
    ): bool {
        $candidates = static::unrealizedPlansForVisit($userId, $visitableType, $visitableId, $visitDate);

        if ($candidates->isEmpty()) {
            return false;
        }

        $visitDate = Carbon::parse($visitDate)->startOfDay();

        foreach ($candidates as $candidate) {
            if (static::periodAlreadyConsumed($candidate, $visitDate)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * Realize the matched plan and any duplicate rows for the same period key.
     *
     * @return Collection<int, PlanVisit>
     */
    public static function realizeMatchingPlans(Visit $visit, ?Carbon $realizedAt = null): Collection
    {
        if (! $visit->user_id || ! $visit->visitable_id || ! $visit->tanggal_visit) {
            return collect();
        }

        $candidates = static::unrealizedPlansForVisit(
            (int) $visit->user_id,
            (string) $visit->visitable_type,
            (int) $visit->visitable_id,
            $visit->tanggal_visit,
        )->filter(fn (PlanVisit $plan) => ! static::periodAlreadyConsumed($plan, Carbon::parse($visit->tanggal_visit)));

        if ($candidates->isEmpty()) {
            return collect();
        }

        $primary = $candidates->first();
        $realizedAt ??= $visit->check_out_time
            ? Carbon::parse($visit->check_out_time)
            : ($visit->check_in_time ? Carbon::parse($visit->check_in_time) : now());

        $periodKeyPlans = PlanVisit::query()
            ->where('user_id', $primary->user_id)
            ->where('visitable_type', $primary->visitable_type)
            ->where('visitable_id', $primary->visitable_id)
            ->where('schedule_scope', $primary->schedule_scope)
            ->whereDate('period_start', Carbon::parse($primary->period_start)->toDateString())
            ->unrealized()
            ->orderBy('id')
            ->get();

        foreach ($periodKeyPlans as $plan) {
            $plan->markAsRealized($visit, $realizedAt);
        }

        return $periodKeyPlans;
    }

    public static function periodAlreadyConsumed(PlanVisit $plan, Carbon $visitDate): bool
    {
        $periodStart = Carbon::parse($plan->period_start)->toDateString();
        $periodEnd = Carbon::parse($plan->period_end ?? $plan->period_start)->toDateString();

        $siblingRealized = PlanVisit::query()
            ->where('user_id', $plan->user_id)
            ->where('visitable_type', $plan->visitable_type)
            ->where('visitable_id', $plan->visitable_id)
            ->where('schedule_scope', $plan->schedule_scope)
            ->whereDate('period_start', $periodStart)
            ->whereNotNull('realized_at')
            ->exists();

        if ($siblingRealized) {
            return true;
        }

        return Visit::query()
            ->where('user_id', $plan->user_id)
            ->where('visitable_type', $plan->visitable_type)
            ->where('visitable_id', $plan->visitable_id)
            ->where('tipe_visit', 'PLANNED')
            ->whereDate('tanggal_visit', '>=', $periodStart)
            ->whereDate('tanggal_visit', '<=', $periodEnd)
            ->whereDate('tanggal_visit', '<>', $visitDate->toDateString())
            ->exists();
    }
}
