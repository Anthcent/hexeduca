<?php

namespace Modules\Grades\Domain\Services;

/**
 * The definitive year grade of a subject: the mean of its moment grades,
 * round half up, between 01 and 20. It exists only once every moment of
 * the period has a complete grade; a partial year has no definitive grade.
 */
final class YearGrade
{
    /**
     * @param  list<int|null>  $momentFinals  one entry per moment of the period; null when that moment is not complete
     */
    public static function final(array $momentFinals): ?int
    {
        if ($momentFinals === [] || in_array(null, $momentFinals, true)) {
            return null;
        }

        $mean = round(array_sum($momentFinals) / count($momentFinals), 2);
        $rounded = (int) round($mean, 0, PHP_ROUND_HALF_UP);

        return max(MomentGrade::MIN, min(MomentGrade::MAX, $rounded));
    }
}
