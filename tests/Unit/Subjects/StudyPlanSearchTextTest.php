<?php

use Modules\Subjects\Infrastructure\Models\StudyPlanModel;

test('plan search text folds accents and case, so both sides of the search match', function (string $text, string $expected) {
    expect(StudyPlanModel::searchText($text))->toBe($expected);
})->with([
    'accented' => ['Matemática', 'matematica'],
    'upper case with accents' => ['MATEMÁTICA', 'matematica'],
    'plain' => ['matematica', 'matematica'],
    'eñe and diaeresis' => ['Añó Pingüino', 'ano pinguino'],
]);
