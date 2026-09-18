<?php

namespace Modules\Academic\Application\UseCases;

use DomainException;
use Modules\Academic\Application\DTOs\CreateOfertaAcademicaData;
use Modules\Academic\Domain\Entities\OfertaAcademica;
use Modules\Academic\Domain\Events\OfertaAcademicaCreated;
use Modules\Academic\Domain\Repositories\OfertaAcademicaRepositoryInterface;
use Modules\Academic\Domain\ValueObjects\Capacity;

final class CreateOfertaAcademica
{
    public function __construct(
        private readonly OfertaAcademicaRepositoryInterface $ofertasAcademicas,
    ) {}

    public function handle(CreateOfertaAcademicaData $data): OfertaAcademica
    {
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
