<?php

use Modules\Subjects\Domain\Entities\StudyPlan;
use Modules\Subjects\Domain\Exceptions\InvalidStatusChange;
use Modules\Subjects\Domain\Exceptions\ObservationRequired;
use Modules\Subjects\Domain\Exceptions\RecordArchived;
use Modules\Subjects\Domain\ValueObjects\RecordStatus;

test('a unique code needs no observation and values are trimmed', function () {
    $plan = StudyPlan::create(1, ' 31060 ', ' Bachillerato ', '  ', codeTaken: false);

    expect($plan->code())->toBe('31060')
        ->and($plan->name())->toBe('Bachillerato')
        ->and($plan->observation())->toBeNull()
        ->and($plan->status())->toBe(RecordStatus::Active);
});

test('a repeated code requires an observation', function (?string $observation) {
    StudyPlan::create(1, '31060', 'Otro', $observation, codeTaken: true);
})->with([null, '', '   '])->throws(ObservationRequired::class);

test('a repeated code with an observation is accepted', function () {
    expect(StudyPlan::create(1, '31060', 'Otro', 'Nocturno', codeTaken: true)->observation())->toBe('Nocturno');
});

test('editing applies the same duplicate-code rule', function () {
    $plan = new StudyPlan(5, 1, '31060', 'Plan', null);

    expect($plan->withDetails('31060', 'Plan 2', null, codeTaken: false)->name())->toBe('Plan 2')
        ->and(fn () => $plan->withDetails('40000', 'Plan', null, codeTaken: true))->toThrow(ObservationRequired::class);
});

test('an archived plan cannot be edited', function () {
    $plan = (new StudyPlan(5, 1, '31060', 'Plan', null))->archive();

    expect(fn () => $plan->withDetails('31060', 'Nuevo', null, false))->toThrow(RecordArchived::class)
        ->and(fn () => $plan->assertEditable())->toThrow(RecordArchived::class);
});

test('archive and reactivate only move between the two states', function () {
    $active = new StudyPlan(5, 1, '31060', 'Plan', null);
    $archived = $active->archive();

    expect($archived->isArchived())->toBeTrue()
        ->and($archived->reactivate()->isArchived())->toBeFalse()
        ->and(fn () => $archived->archive())->toThrow(InvalidStatusChange::class)
        ->and(fn () => $active->reactivate())->toThrow(InvalidStatusChange::class);
});

test('only a plan with no subjects and no assignments can be deleted', function (int $subjects, int $assignments, bool $expected) {
    expect(StudyPlan::canBeDeleted($subjects, $assignments))->toBe($expected);
})->with([
    [0, 0, true],
    [1, 0, false],
    [0, 1, false],
    [2, 3, false],
]);
