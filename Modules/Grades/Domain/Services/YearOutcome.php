<?php

namespace Modules\Grades\Domain\Services;

/**
 * A student's result for the year, from the definitive grades of every
 * subject of their offer: up to two failed subjects promote them with
 * those subjects pending; three or more make them repeat the year. While
 * any subject lacks its definitive grade, the year has no result yet.
 */
final class YearOutcome
{
    public const MAX_PENDING = 2;

    public const INCOMPLETE = 'incomplete';

    public const PASSED = 'passed';

    public const PENDING = 'pending';

    public const REPEATS = 'repeats';

    /**
     * @param  list<int|null>  $subjectFinals  one definitive grade per subject; null when it has none yet
     */
    public static function for(array $subjectFinals): string
    {
        if ($subjectFinals === [] || in_array(null, $subjectFinals, true)) {
            return self::INCOMPLETE;
        }

        $failed = count(array_filter($subjectFinals, fn (int $final): bool => ! MomentGrade::passes($final)));

        return match (true) {
            $failed === 0 => self::PASSED,
            $failed <= self::MAX_PENDING => self::PENDING,
            default => self::REPEATS,
        };
    }
}
