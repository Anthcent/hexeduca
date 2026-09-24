<?php

namespace Modules\AcademicPeriods\Application\UseCases;

use App\IntegrationEvents\Outbox\OutboxEventRecorder;
use Illuminate\Support\Facades\DB;
use Modules\AcademicPeriods\Domain\Entities\AcademicPeriod;
use Modules\AcademicPeriods\Domain\Events\AcademicPeriodActivated;
use Modules\AcademicPeriods\Domain\ValueObjects\DateRange;
use Modules\AcademicPeriods\Infrastructure\Models\AcademicPeriod as AcademicPeriodModel;
use Modules\AcademicPeriods\Public\Events\AcademicPeriodActivated as IntegrationAcademicPeriodActivated;

/**
 * Authoritative activation transaction used by both AcademicPeriods routes
 * and the backward-compatible Academic period route. Deactivates any other
 * active period for the school, then activates the target.
 *
 * Uses the Eloquent model directly for the bulk deactivate (a
 * cross-cutting update, not a single aggregate load) rather than the
 * repository.
 */
final class ActivateAcademicPeriod
{
    public function __construct(
        private readonly OutboxEventRecorder $outbox,
    ) {}

    public function handle(AcademicPeriodModel $period): AcademicPeriod
    {
        $activated = DB::transaction(function () use ($period): AcademicPeriod {
            // The school row is a stable mutex even when no period is active.
            // PostgreSQL row locks serialize all activations for one school;
            // SQLite remains supported and the partial unique index provides
            // the final invariant backstop on both databases.
            DB::table('schools')
                ->where('id', $period->school_id)
                ->lockForUpdate()
                ->firstOrFail();

            AcademicPeriodModel::where('school_id', $period->school_id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            $period->update(['is_active' => true]);

            $activated = new AcademicPeriod(
                id: $period->id,
                schoolId: $period->school_id,
                name: $period->name,
                dateRange: new DateRange(
                    new \DateTimeImmutable($period->starts_on->toDateString()),
                    new \DateTimeImmutable($period->ends_on->toDateString()),
                ),
                isActive: true,
            );

            $this->outbox->record(new IntegrationAcademicPeriodActivated(
                academicPeriodId: $activated->id(),
                schoolId: $activated->schoolId(),
                name: $activated->name(),
            ));

            return $activated;
        });

        event(new AcademicPeriodActivated($activated));

        return $activated;
    }
}
