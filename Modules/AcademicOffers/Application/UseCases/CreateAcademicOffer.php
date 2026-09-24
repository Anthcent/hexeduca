<?php

namespace Modules\AcademicOffers\Application\UseCases;

use App\IntegrationEvents\Outbox\OutboxEventRecorder;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\AcademicOffers\Application\DTOs\CreateAcademicOfferData;
use Modules\AcademicOffers\Domain\Entities\AcademicOffer;
use Modules\AcademicOffers\Domain\Events\AcademicOfferCreated as DomainAcademicOfferCreated;
use Modules\AcademicOffers\Domain\Repositories\AcademicOfferRepositoryInterface;
use Modules\AcademicOffers\Domain\ValueObjects\Capacity;
use Modules\AcademicOffers\Public\Events\AcademicOfferCreated as IntegrationAcademicOfferCreated;
use Modules\GradeLevels\Public\Contracts\GradeLevelReader;
use Modules\Sections\Public\Contracts\SectionReader;
use Modules\Users\Public\Contracts\TeacherReader;

final class CreateAcademicOffer
{
    public function __construct(
        private readonly AcademicOfferRepositoryInterface $academicOffers,
        private readonly OutboxEventRecorder $outbox,
        private readonly TeacherReader $teachers,
        private readonly GradeLevelReader $gradeLevels,
        private readonly SectionReader $sections,
    ) {}

    public function handle(CreateAcademicOfferData $data): AcademicOffer
    {
        if ($this->gradeLevels->findForSchool($data->gradeLevelId, $data->schoolId) === null) {
            throw new DomainException('The grade level must belong to this school.');
        }

        if ($this->sections->findForSchool($data->sectionId, $data->schoolId) === null) {
            throw new DomainException('The section must belong to this school.');
        }

        if ($data->teacherId !== null && $this->teachers->findForSchool($data->teacherId, $data->schoolId) === null) {
            throw new DomainException('The assigned teacher must be a teacher in this school.');
        }

        $existing = $this->academicOffers->findByPeriodGradeLevelSection(
            $data->academicPeriodId,
            $data->gradeLevelId,
            $data->sectionId,
        );

        if ($existing !== null) {
            throw new DomainException(
                'An AcademicOffer already exists for this period/gradeLevel/section combination.',
            );
        }

        $academicOffer = new AcademicOffer(
            id: null,
            schoolId: $data->schoolId,
            academicPeriodId: $data->academicPeriodId,
            gradeLevelId: $data->gradeLevelId,
            sectionId: $data->sectionId,
            teacherId: $data->teacherId,
            capacity: new Capacity($data->capacity),
        );

        $saved = DB::transaction(function () use ($academicOffer) {
            $saved = $this->academicOffers->save($academicOffer);

            // The integration event is recorded to the outbox INSIDE this
            // transaction (plan §9/§10) — a crash right after COMMIT can
            // never lose it, unlike a plain event() call after the save.
            $this->outbox->record(new IntegrationAcademicOfferCreated(
                academicOfferId: $saved->id(),
                schoolId: $saved->schoolId(),
                academicPeriodId: $saved->academicPeriodId(),
            ));

            return $saved;
        });

        event(new DomainAcademicOfferCreated($saved));

        return $saved;
    }
}
