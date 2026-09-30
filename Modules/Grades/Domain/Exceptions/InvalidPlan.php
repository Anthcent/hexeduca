<?php

namespace Modules\Grades\Domain\Exceptions;

use DomainException;

/**
 * An evaluation plan that breaks the structure rules. The message is shown
 * to the user as is, so it is in Spanish.
 */
final class InvalidPlan extends DomainException
{
    public static function referentCount(int $max): self
    {
        return new self("El plan debe tener entre 1 y {$max} referentes.");
    }

    public static function missingTopic(int $position): self
    {
        return new self("El referente {$position} necesita un tema.");
    }

    public static function indicatorCount(int $position, int $max): self
    {
        return new self("El referente {$position} debe tener entre 1 y {$max} indicadores.");
    }

    public static function indicatorPoints(int $position, string $letter): self
    {
        return new self("El indicador {$letter} del referente {$position} debe valer entre 1 y 20 puntos.");
    }

    public static function missingDescription(int $position, string $letter): self
    {
        return new self("El indicador {$letter} del referente {$position} necesita una descripción.");
    }

    public static function referentSum(int $position, int $sum): self
    {
        return new self("Los indicadores del referente {$position} suman {$sum} puntos; deben sumar exactamente 20.");
    }

    public static function lockedByScores(): self
    {
        return new self('El plan ya tiene notas cargadas: solo se pueden cambiar textos, no referentes, indicadores ni puntos.');
    }
}
