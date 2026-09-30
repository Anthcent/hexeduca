<?php

use Modules\Grades\Domain\Services\YearOutcome;

test('the year result depends on how many subjects were failed', function (array $finals, string $outcome) {
    expect(YearOutcome::for($finals))->toBe($outcome);
})->with([
    'every subject passed' => [[10, 15, 20], YearOutcome::PASSED],
    'one failed subject is pending' => [[9, 15, 20], YearOutcome::PENDING],
    'two failed subjects are pending' => [[9, 5, 20], YearOutcome::PENDING],
    'three failed subjects repeat the year' => [[9, 5, 1, 20], YearOutcome::REPEATS],
    'a subject without its definitive grade' => [[9, null, 20], YearOutcome::INCOMPLETE],
    'no subjects at all' => [[], YearOutcome::INCOMPLETE],
]);
