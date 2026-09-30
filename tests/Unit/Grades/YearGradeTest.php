<?php

use Modules\Grades\Domain\Services\YearGrade;

test('the year grade is the rounded-half-up mean of the moment grades, between 01 and 20', function (array $moments, int $year) {
    expect(YearGrade::final($moments))->toBe($year);
})->with([
    'an exact mean' => [[15, 16, 17], 16],
    '9.5 rounds up to 10' => [[9, 10], 10],
    '9.33 rounds down to 9' => [[9, 9, 10], 9],
    '16.67 rounds up to 17' => [[16, 17, 17], 17],
    'all 01 stays 01' => [[1, 1, 1], 1],
    'all 20 stays 20' => [[20, 20, 20], 20],
]);

test('there is no year grade while a moment has no complete grade', function (array $moments) {
    expect(YearGrade::final($moments))->toBeNull();
})->with([
    'a missing moment' => [[15, null, 17]],
    'no moments at all' => [[]],
]);
