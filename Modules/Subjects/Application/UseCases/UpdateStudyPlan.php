<?php

namespace Modules\Subjects\Application\UseCases;

use Modules\Subjects\Application\DTOs\StudyPlanData;
use Modules\Subjects\Domain\Entities\StudyPlan;
use Modules\Subjects\Domain\Exceptions\ObservationRequired;
use Modules\Subjects\Domain\Exceptions\RecordArchived;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;

final class UpdateStudyPlan
{
    public function __construct(private readonly StudyPlanRepositoryInterface $plans) {}

    /**
     * @throws StudyPlanNotFound
     * @throws RecordArchived
     * @throws ObservationRequired
     */
    public function handle(int $planId, StudyPlanData $data): StudyPlan
    {
        $plan = $this->plans->findInSchool($planId, $data->schoolId) ?? throw StudyPlanNotFound::withId($planId);

        return $this->plans->save($plan->withDetails(
            $data->code,
            $data->name,
            $data->observation,
            $this->plans->codeTaken($data->schoolId, $data->code, $planId),
        ));
    }
}
