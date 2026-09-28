<?php

use Modules\Subjects\Domain\Entities\Subject;
use Modules\Subjects\Domain\Exceptions\InvalidStatusChange;
use Modules\Subjects\Domain\Exceptions\InvalidSubject;
use Modules\Subjects\Domain\Exceptions\RecordArchived;

test('a subject normalizes its name and optional code', function () {
    $subject = Subject::create(1, 2, 3, ' Matemática ', '  ', null);

    expect($subject->name())->toBe('Matemática')
        ->and($subject->code())->toBeNull()
        ->and($subject->weeklyHours())->toBeNull();
});

test('weekly hours must be a positive integer when given', function (int $hours) {
    Subject::create(1, 2, 3, 'Matemática', null, $hours);
})->with([0, -1])->throws(InvalidSubject::class);

test('a blank name is rejected', function () {
    Subject::create(1, 2, 3, '   ', null, null);
})->throws(InvalidSubject::class);

test('an archived subject cannot be edited and state changes are one-way each', function () {
    $active = new Subject(9, 1, 2, 3, 'Arte', null, 2);
    $archived = $active->archive();

    expect(fn () => $archived->withDetails(3, 'Arte II', null, 2))->toThrow(RecordArchived::class)
        ->and(fn () => $archived->archive())->toThrow(InvalidStatusChange::class)
        ->and(fn () => $active->reactivate())->toThrow(InvalidStatusChange::class)
        ->and($archived->reactivate()->isArchived())->toBeFalse();
});

test('a subject can be deleted only when no open period activates or excludes it', function (bool $activatedInOpenPeriod, bool $excludedInOpenPeriod, bool $expected) {
    expect(Subject::canBeDeleted($activatedInOpenPeriod, $excludedInOpenPeriod))->toBe($expected);
})->with([
    'unused, or used only in closed periods' => [false, false, true],
    'active in an open period' => [true, false, false],
    'excluded in an open period' => [false, true, false],
    'both' => [true, true, false],
]);
