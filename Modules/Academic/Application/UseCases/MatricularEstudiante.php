<?php

namespace Modules\Academic\Application\UseCases;

use App\IntegrationEvents\Outbox\OutboxEventRecorder;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Application\DTOs\MatricularEstudianteData;
use Modules\Academic\Domain\Entities\Matricula;
use Modules\Academic\Domain\Events\EstudianteMatriculado;
use Modules\Academic\Domain\Repositories\MatriculaRepositoryInterface;
use Modules\Academic\Domain\Repositories\OfertaAcademicaRepositoryInterface;
use Modules\Enrollments\Public\Events\EnrollmentCreated as IntegrationEnrollmentCreated;
use Modules\Users\Public\Contracts\StudentReader;

final class MatricularEstudiante
{
    public function __construct(
        private readonly OfertaAcademicaRepositoryInterface $ofertasAcademicas,
        private readonly MatriculaRepositoryInterface $matriculas,
        private readonly StudentReader $students,
        private readonly OutboxEventRecorder $outbox,
    ) {}

    public function handle(MatricularEstudianteData $data): Matricula
    {
        $saved = DB::transaction(function () use ($data) {
            // Shared physical lock target with CreateEnrollment: both paths
            // serialize on the same ofertas_academicas row in PostgreSQL.
            $ofertaAcademica = $this->ofertasAcademicas->findByIdForEnrollment($data->ofertaAcademicaId);

            if ($ofertaAcademica === null) {
                throw new DomainException('The OfertaAcademica to enroll into does not exist.');
            }

            if ($this->students->findForSchool($data->studentId, $ofertaAcademica->schoolId()) === null) {
                throw new DomainException('The enrolled user must be a student in the OfertaAcademica school.');
            }

            if ($this->matriculas->existsForOfertaAcademicaAndStudent($data->ofertaAcademicaId, $data->studentId)) {
                throw new DomainException('The student is already enrolled in this OfertaAcademica.');
            }

            if ($ofertaAcademica->capacity()->isFullAt($this->matriculas->countByOfertaAcademica($data->ofertaAcademicaId))) {
                throw new DomainException('The OfertaAcademica has reached its enrollment capacity.');
            }

            $matricula = new Matricula(
                id: null,
                schoolId: $ofertaAcademica->schoolId(),
                periodoAcademicoId: $ofertaAcademica->periodoAcademicoId(),
                ofertaAcademicaId: $data->ofertaAcademicaId,
                studentId: $data->studentId,
                status: $data->status,
                enrolledAt: $data->enrolledAt ?? new DateTimeImmutable,
            );

            $saved = $this->matriculas->save($matricula);

            $this->outbox->record(new IntegrationEnrollmentCreated(
                enrollmentId: $saved->id(),
                schoolId: $saved->schoolId(),
                academicPeriodId: $saved->periodoAcademicoId(),
                academicOfferId: $saved->ofertaAcademicaId(),
                studentId: $saved->studentId(),
                status: $saved->status(),
                version: $saved->version(),
            ));

            return $saved;
        });

        event(new EstudianteMatriculado($saved));

        return $saved;
    }
}
