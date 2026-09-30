<?php

namespace Modules\Grades\Domain\Exceptions;

use DomainException;

/**
 * A grade that cannot be recorded. The message is shown to the user as is,
 * so it is in Spanish. `status` is the HTTP status to answer with.
 */
final class GradingRefused extends DomainException
{
    private function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function notYourSubject(): self
    {
        return new self('No tienes asignada esta asignatura en esta sección.', 403);
    }

    public static function windowClosed(): self
    {
        return new self('La carga de notas de este momento está cerrada.');
    }

    public static function periodClosed(): self
    {
        return new self('El periodo está cerrado; sus notas son de solo lectura.');
    }

    public static function notEnrolled(): self
    {
        return new self('El estudiante no está inscrito en esta sección.');
    }

    public static function unknownIndicator(): self
    {
        return new self('El indicador no pertenece a este plan.');
    }

    public static function outOfRange(int $max): self
    {
        return new self("La nota debe ser un número entero entre 0 y {$max}.");
    }

    public static function extraOverCap(int $max): self
    {
        return $max === 0
            ? new self('El estudiante ya llega a 20: no hay lugar para puntos extra.')
            : new self("Con el promedio actual, la nota extra puede ser como máximo {$max} para no pasar de 20.");
    }

    /**
     * No such plan in the school: 404, like any other school's record.
     */
    public static function planNotFound(): self
    {
        return new self('El plan de evaluación no existe.', 404);
    }

    public static function staffOnly(): self
    {
        return new self('Solo el personal administrativo puede abrir o cerrar una corrección.', 403);
    }

    public static function correctionReasonRequired(): self
    {
        return new self('Indica el motivo de la corrección.');
    }

    public static function correctionExpiry(int $maxDays): self
    {
        return new self("La corrección debe vencer entre hoy y dentro de {$maxDays} días.");
    }

    public static function unknownSlot(): self
    {
        return new self('La sección, la asignatura o el momento no son válidos para este periodo.');
    }
}
