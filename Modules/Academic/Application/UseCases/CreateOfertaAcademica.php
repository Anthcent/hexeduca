<?php

namespace Modules\Academic\Application\UseCases;

use DomainException;
use Modules\Academic\Application\DTOs\CreateOfertaAcademicaData;
use Modules\Academic\Domain\Entities\OfertaAcademica;
use Modules\Academic\Domain\Events\OfertaAcademicaCreated;
use Modules\Academic\Domain\Repositories\OfertaAcademicaRepositoryInterface;
use Modules\Academic\Domain\ValueObjects\Capacity;
use Modules\GradeLevels\Public\Contracts\GradeLevelReader;
use Modules\Sections\Public\Contracts\SectionReader;
use Modules\Users\Public\Contracts\TeacherReader;

final class CreateOfertaAcademica
{
    public function __construct(
        private readonly OfertaAcademicaRepositoryInterface $ofertasAcademicas,
        private readonly TeacherReader $teachers,
        private readonly GradeLevelReader $gradeLevels,
        private readonly SectionReader $sections,
    ) {}

    public function handle(CreateOfertaAcademicaData $data): OfertaAcademica
    {
        if ($this->gradeLevels->findForSchool($data->gradoId, $data->schoolId) === null) {
            throw new DomainException('The grade level must belong to this school.');
        }

        if ($this->sections->findForSchool($data->seccionId, $data->schoolId) === null) {
            throw new DomainException('The section must belong to this school.');
        }

        if ($data->teacherId !== null && $this->teachers->findForSchool($data->teacherId, $data->schoolId) === null) {
            throw new DomainException('The assigned teacher must be a teacher in this school.');
        }

        $existing = $this->ofertasAcademicas->findByPeriodoGradoSeccion(
            $data->periodoAcademicoId,
            $data->gradoId,
            $data->seccionId,
        );

        if ($existing !== null) {
            throw new DomainException(
                'An OfertaAcademica already exists for this periodo/grado/seccion combination.',
            );
        }

        $ofertaAcademica = new OfertaAcademica(
            id: null,
            schoolId: $data->schoolId,
            periodoAcademicoId: $data->periodoAcademicoId,
            gradoId: $data->gradoId,
            seccionId: $data->seccionId,
            teacherId: $data->teacherId,
            capacity: new Capacity($data->capacity),
        );

        $saved = $this->ofertasAcademicas->save($ofertaAcademica);

        event(new OfertaAcademicaCreated($saved));

        return $saved;
    }
}
