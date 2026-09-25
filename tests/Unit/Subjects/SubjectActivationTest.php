<?php

use Modules\Subjects\Domain\Entities\Subject;
use Modules\Subjects\Domain\Services\SubjectActivation;
use Modules\Subjects\Domain\ValueObjects\RecordStatus;

test('the plan subjects of the offer grade level are active unless excluded; archived ones are left out', function () {
    $math = new Subject(1, 1, 50, 7, 'Matemática', null, 5);
    $art = new Subject(2, 1, 50, 7, 'Arte', null, 2);
    $chemistry = new Subject(3, 1, 50, 8, 'Química', null, 4);
    $latin = new Subject(4, 1, 50, 7, 'Latín', null, null, RecordStatus::Archived);

    $rows = SubjectActivation::forGradeLevel([$math, $art, $chemistry, $latin], 7, [2]);

    expect(array_map(fn ($row) => [$row['subject']->name(), $row['active']], $rows))->toBe([
        ['Matemática', true],
        ['Arte', false],
    ]);
});

test('a grade level with no subjects in the plan has nothing to activate', function () {
    expect(SubjectActivation::forGradeLevel([new Subject(1, 1, 50, 7, 'Matemática', null, null)], 9, []))->toBe([]);
});
