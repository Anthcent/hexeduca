<?php

namespace Modules\AcademicPeriods\Application\UseCases;

use App\IntegrationEvents\Outbox\OutboxEventRecorder;
use Illuminate\Support\Facades\DB;
use Modules\AcademicPeriods\Application\DTOs\CreateAcademicPeriodData;
use Modules\AcademicPeriods\Domain\Entities\AcademicPeriod;
use Modules\AcademicPeriods\Domain\Events\AcademicPeriodCreated;
use Modules\AcademicPeriods\Domain\Repositories\AcademicPeriodRepositoryInterface;
use Modules\AcademicPeriods\Domain\ValueObjects\DateRange;
use Modules\AcademicPeriods\Public\Events\AcademicPeriodCreated as IntegrationAcademicPeriodCreated;

final class CreateAcademicPeriod
{
    public function __construct(
        private readonly AcademicPeriodRepositoryInterface $academicPeriods,
        private readonly OutboxEventRecorder $outbox,
    ) {}

    public function handle(CreateAcademicPeriodData $data): AcademicPeriod
    {
        $academicPeriod = new AcademicPeriod(
            id: null,
            schoolId: $data->schoolId,
            name: $data->name,
            dateRange: new DateRange($data->startsOn, $data->endsOn),
            isActive: false,
        );

        $saved = DB::transaction(function () use ($academicPeriod) {
            $saved = $this->academicPeriods->save($academicPeriod);

            $this->outbox->record(new IntegrationAcademicPeriodCreated(
                academicPeriodId: $saved->id(),
                schoolId: $saved->schoolId(),
                name: $saved->name(),
                startsOn: $saved->dateRange()->startsOn(),
                endsOn: $saved->dateRange()->endsOn(),
            ));

            return $saved;
        });

        event(new AcademicPeriodCreated($saved));

        return $saved;
    }
}
