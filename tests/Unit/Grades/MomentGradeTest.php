<?php

use Modules\Grades\Domain\Services\MomentGrade;

test('the moment grade is the rounded-half-up average of the referente totals plus the extra, between 01 and 20', function (array $totals, int $extra, int $final, bool $passes) {
    $grade = MomentGrade::final(MomentGrade::average($totals), $extra);

    expect($grade)->toBe($final)
        ->and(MomentGrade::passes($grade))->toBe($passes);
})->with([
    '16.5 rounds up to 17' => [[17, 16], 0, 17, true],
    '9.5 rounds up to 10 and passes' => [[10, 9], 0, 10, true],
    '9.33 rounds down to 9 and fails' => [[10, 9, 9], 0, 9, false],
    'all zeros is 01' => [[0, 0, 0], 0, 1, false],
    'a single referente' => [[14], 0, 14, true],
    'the extra is added before rounding' => [[17, 16], 2, 19, true],
    'the extra lifts a failing average to a pass' => [[9, 8], 1, 10, true],
    'repeating decimals (19.67) round to 20' => [[20, 20, 19], 0, 20, true],
    'repeating decimals (1.67) round to 2' => [[1, 2, 2], 0, 2, false],
    'clamped at 20' => [[20, 19], 3, 20, true],
    'a perfect score stays 20' => [[20, 20], 0, 20, true],
]);

test('the average of no referentes is 0 and rounds to 2 decimals', function () {
    expect(MomentGrade::average([]))->toBe(0.0)
        ->and(MomentGrade::average([20, 20, 19]))->toBe(19.67)
        ->and(MomentGrade::average([17, 16]))->toBe(16.5);
});

test('the extra may only fill the room left up to 20', function (float $average, int $max) {
    expect(MomentGrade::maxExtra($average))->toBe($max);
})->with([
    [16.5, 3],
    [16.0, 4],
    [0.0, 20],
    [19.5, 0],
    [20.0, 0],
    [9.67, 10],
]);

test('10 passes and 9 fails', function () {
    expect(MomentGrade::passes(10))->toBeTrue()
        ->and(MomentGrade::passes(9))->toBeFalse()
        ->and(MomentGrade::passes(1))->toBeFalse()
        ->and(MomentGrade::passes(20))->toBeTrue();
});
