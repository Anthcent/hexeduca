<?php

namespace Modules\Academic\Application\UseCases;

use DateTimeImmutable;
use DomainException;
use Modules\Academic\Application\DTOs\MatricularEstudianteData;
use Modules\Academic\Domain\Entities\Matricula;
use Modules\Academic\Domain\Events\EstudianteMatriculado;
use Modules\Academic\Domain\Repositories\MatriculaRepositoryInterface;
use Modules\Academic\Domain\Repositories\OfertaAcademicaRepositoryInterface;

final class MatricularEstudiante
{
    public function __construct(
        private readonly OfertaAcademicaRepositoryInterface $ofertasAcademicas,
        private readonly MatriculaRepositoryInterface $matriculas,
    ) {}

    public function handle(MatricularEstudianteData $data): Matricula
    {
        $ofertaAcademica = $this->ofertasAcademicas->findById($data->ofertaAcademicaId);

        if ($ofertaAcademica === null) {
            throw new DomainException('The OfertaAcademica to enroll into does not exist.');
        }

        if ($this->matriculas->existsForOfertaAcademicaAndStudent($data->ofertaAcademicaId, $data->studentId)) {
            throw new DomainException('The student is already enrolled in this OfertaAcademica.');
        }

        $enrolledCount = $this->matriculas->countByOfertaAcademica($data->ofertaAcademicaId);

        if ($ofertaAcademica->capacity()->isFullAt($enrolledCount)) {
            throw new DomainException('The OfertaAcademica has reached its enrollment capacity.');
        }

        // school_id and periodo_academico_id are derived from the offering
        // itself, not from request-scoped context — a Matricula must always
        // inherit the tenant+period of the OfertaAcademica it targets.
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

        event(new EstudianteMatriculado($saved));

        return $saved;
    }
}
