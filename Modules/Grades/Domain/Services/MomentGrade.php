<?php

namespace Modules\Grades\Domain\Services;

/**
 * The institution's moment (lapso) grade, the single formula every screen
 * and report uses:
 *
 *   referente = sum of its indicator points (0–20)
 *   average   = mean of the referente totals
 *   final     = round half up (average + extra), between 01 and 20
 *
 * The extra (extracurricular participation) may only fill the room left
 * up to 20. 01–09 fails, 10–20 passes.
 */
final class MomentGrade
{
    public const MIN = 1;

    public const MAX = 20;

    public const PASS = 10;

    /**
     * @param  list<int>  $referentTotals
     */
    public static function average(array $referentTotals): float
    {
        return $referentTotals === [] ? 0.0 : round(array_sum($referentTotals) / count($referentTotals), 2);
    }

    /**
     * The largest extra that still fits under 20 after rounding.
     */
    public static function maxExtra(float $average): int
    {
        return max(0, (int) floor(self::MAX - $average));
    }

    public static function final(float $average, int $extra): int
    {
        // round() on 2 decimals first, so 16.4999… from float noise never
        // rounds down.
        $rounded = (int) round(round($average + $extra, 2), 0, PHP_ROUND_HALF_UP);

        return max(self::MIN, min(self::MAX, $rounded));
    }

    public static function passes(int $final): bool
    {
        return $final >= self::PASS;
    }
}
