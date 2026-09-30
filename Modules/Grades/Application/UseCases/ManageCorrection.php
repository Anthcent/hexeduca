<?php

namespace Modules\Grades\Application\UseCases;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Grades\Application\DTOs\Actor;
use Modules\Grades\Domain\Exceptions\GradingRefused;
use Modules\Grades\Domain\Repositories\GradeBookRepositoryInterface;

/**
 * Correction mode: staff reopens one plan so its grades can change outside
 * the grading window, even in a closed period. A reason is required and it
 * expires at the end of the chosen day; every change made under it is
 * logged with it.
 */
final class ManageCorrection
{
    public const MAX_DAYS = 30;

    public function __construct(private readonly GradeBookRepositoryInterface $book) {}

    /**
     * @param  string  $expiresOn  ISO date (Y-m-d); the correction ends at the end of that day
     *
     * @throws GradingRefused
     */
    public function open(Actor $actor, int $planId, string $reason, string $expiresOn): int
    {
        $plan = $this->book->findPlan($planId, $actor->schoolId) ?? throw GradingRefused::planNotFound();

        if (! $actor->isStaff) {
            throw GradingRefused::staffOnly();
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw GradingRefused::correctionReasonRequired();
        }

        $today = CarbonImmutable::today();
        $expires = CarbonImmutable::parse($expiresOn)->endOfDay();

        if ($expires->lt($today) || $expires->gt($today->addDays(self::MAX_DAYS)->endOfDay())) {
            throw GradingRefused::correctionExpiry(self::MAX_DAYS);
        }

        // Close-then-open in one transaction, so two concurrent opens never
        // leave two open corrections.
        return DB::transaction(fn (): int => $this->book->openCorrection($plan, $actor->id, $reason, $expires->toDateTimeString()));
    }

    /**
     * @throws GradingRefused
     */
    public function close(Actor $actor, int $planId): bool
    {
        $this->book->findPlan($planId, $actor->schoolId) ?? throw GradingRefused::planNotFound();

        if (! $actor->isStaff) {
            throw GradingRefused::staffOnly();
        }

        return $this->book->closeCorrection($planId, $actor->schoolId, $actor->id);
    }
}
