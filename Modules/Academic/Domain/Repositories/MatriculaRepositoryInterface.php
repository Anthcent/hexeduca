<?php

namespace Modules\Academic\Domain\Repositories;

use Modules\Academic\Domain\Entities\Matricula;

interface MatriculaRepositoryInterface
{
    public function findById(int $id): ?Matricula;

    /**
     * Counts enrollments with status = 'active' for an offering — used to
     * enforce Capacity before inserting a new Matricula. A withdrawn/inactive
     * matricula does not occupy a capacity seat.
     */
    public function countByOfertaAcademica(int $ofertaAcademicaId): int;

    /**
     * True when the student is already enrolled in the offering — enforces
     * the unique(oferta_academica_id, student_id) constraint before insert.
     */
    public function existsForOfertaAcademicaAndStudent(int $ofertaAcademicaId, int $studentId): bool;

    public function save(Matricula $matricula): Matricula;
}
