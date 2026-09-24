<?php

namespace Modules\Academic\Domain\Repositories;

use Modules\Academic\Domain\Entities\OfertaAcademica;

interface OfertaAcademicaRepositoryInterface
{
    public function findById(int $id): ?OfertaAcademica;

    public function findByIdForEnrollment(int $id): ?OfertaAcademica;

    /**
     * Finds an existing offering for the same (periodo, grado, seccion)
     * combination — used to enforce the unique-per-cycle constraint before
     * insert, since the catalog pair may legitimately repeat across periods.
     */
    public function findByPeriodoGradoSeccion(int $periodoAcademicoId, int $gradoId, int $seccionId): ?OfertaAcademica;

    public function save(OfertaAcademica $ofertaAcademica): OfertaAcademica;
}
