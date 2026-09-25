<?php

use Modules\Subjects\Domain\Entities\PlanAssignment;
use Modules\Subjects\Domain\Exceptions\ReactivationNotApproved;
use Modules\Subjects\Domain\Services\OpenPeriodPolicy;
use Modules\Subjects\Domain\Services\PlanReactivationPlanner;
use Modules\Subjects\Domain\ValueObjects\AssignmentScope;

const SUBJECTS_TEST_PLAN = 100;

function replacedAssignment(int $id, AssignmentScope $scope, ?int $grade = null, ?int $offer = null, int $period = 10, int $plan = SUBJECTS_TEST_PLAN): PlanAssignment
{
    return PlanAssignment::fromStorage($id, 1, $period, $plan, $scope, $grade, $offer, true);
}

function currentHolder(int $id, int $plan, AssignmentScope $scope, ?int $grade = null, ?int $offer = null, int $period = 10): PlanAssignment
{
    return PlanAssignment::fromStorage($id, 1, $period, $plan, $scope, $grade, $offer, false);
}

test('a lost slot now held by another plan is a conflict; a free one is a plain restoration', function () {
    $lostSchool = replacedAssignment(1, AssignmentScope::School);
    $lostGrade = replacedAssignment(2, AssignmentScope::GradeLevel, grade: 7);
    $holder = currentHolder(9, 200, AssignmentScope::School);

    $impact = PlanReactivationPlanner::plan(SUBJECTS_TEST_PLAN, [$lostSchool, $lostGrade], [$holder->slotKey() => $holder]);

    expect($impact->restorations)->toHaveCount(2)
        ->and($impact->restorations[0]->restored)->toBe($lostSchool)
        ->and($impact->restorations[0]->holder)->toBe($holder)
        ->and($impact->restorations[1]->holder)->toBeNull()
        ->and($impact->hasConflicts())->toBeTrue()
        ->and($impact->replacedHolderIds())->toBe([9]);
});

test('only the latest replaced assignment of a slot is restored', function () {
    $older = replacedAssignment(1, AssignmentScope::Offer, grade: 7, offer: 55);
    $newer = replacedAssignment(4, AssignmentScope::Offer, grade: 7, offer: 55);

    $impact = PlanReactivationPlanner::plan(SUBJECTS_TEST_PLAN, [$newer, $older], []);

    expect($impact->restorations)->toHaveCount(1)
        ->and($impact->restorations[0]->restored)->toBe($newer)
        ->and($impact->hasConflicts())->toBeFalse();
});

test('other plans\' rows, current rows and slots the plan already holds are ignored', function () {
    $foreign = replacedAssignment(1, AssignmentScope::School, plan: 999);
    $current = currentHolder(2, SUBJECTS_TEST_PLAN, AssignmentScope::GradeLevel, grade: 7);
    $lost = replacedAssignment(3, AssignmentScope::GradeLevel, grade: 8);
    $sameHolder = currentHolder(5, SUBJECTS_TEST_PLAN, AssignmentScope::GradeLevel, grade: 8);

    $impact = PlanReactivationPlanner::plan(SUBJECTS_TEST_PLAN, [$foreign, $current, $lost], [$sameHolder->slotKey() => $sameHolder]);

    expect($impact->restorations)->toBe([]);
});

test('slots in different periods are different slots', function () {
    $lost = replacedAssignment(1, AssignmentScope::School, period: 10);
    $holderOtherPeriod = currentHolder(9, 200, AssignmentScope::School, period: 11);

    $impact = PlanReactivationPlanner::plan(SUBJECTS_TEST_PLAN, [$lost], [$holderOtherPeriod->slotKey() => $holderOtherPeriod]);

    expect($impact->hasConflicts())->toBeFalse();
});

test('every conflict must be approved by the id of the assignment it replaces', function () {
    $a = currentHolder(8, 200, AssignmentScope::School);
    $b = currentHolder(9, 200, AssignmentScope::GradeLevel, grade: 7);
    $impact = PlanReactivationPlanner::plan(SUBJECTS_TEST_PLAN, [replacedAssignment(1, AssignmentScope::School), replacedAssignment(2, AssignmentScope::GradeLevel, grade: 7)], [
        $a->slotKey() => $a,
        $b->slotKey() => $b,
    ]);

    expect(fn () => $impact->assertApproved([]))->toThrow(ReactivationNotApproved::class)
        ->and(fn () => $impact->assertApproved([8]))->toThrow(ReactivationNotApproved::class)
        ->and(fn () => $impact->assertApproved([8, 9]))->not->toThrow(ReactivationNotApproved::class);
});

test('without conflicts no approval is needed', function () {
    $impact = PlanReactivationPlanner::plan(SUBJECTS_TEST_PLAN, [replacedAssignment(1, AssignmentScope::School)], []);

    expect(fn () => $impact->assertApproved([]))->not->toThrow(ReactivationNotApproved::class);
});

test('a period is open when it is active or has not ended yet', function (bool $active, string $endsOn, bool $expected) {
    expect(OpenPeriodPolicy::isOpen($active, $endsOn, '2026-09-25'))->toBe($expected);
})->with([
    'active and past' => [true, '2025-11-30', true],
    'ends today' => [false, '2026-09-25', true],
    'future' => [false, '2027-11-30', true],
    'ended' => [false, '2026-09-24', false],
]);
