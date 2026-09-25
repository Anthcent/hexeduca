<?php

namespace Modules\Subjects\Application\UseCases;

use Modules\Subjects\Application\DTOs\StudyPlanData;
use Modules\Subjects\Domain\Entities\StudyPlan;
use Modules\Subjects\Domain\Exceptions\ObservationRequired;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;

final class CreateStudyPlan
{
    public function __construct(private readonly StudyPlanRepositoryInterface $plans) {}

    /**
     * @throws ObservationRequired when the code is taken and no observation is given
     */
    public function handle(StudyPlanData $data): StudyPlan
    {
        return $this->plans->save(StudyPlan::create(
            $data->schoolId,
            $data->code,
            $data->name,
            $data->observation,
            $this->plans->codeTaken($data->schoolId, $data->code),
        ));
    }
}
