<?php

use Modules\Subjects\Domain\Entities\PlanAssignment;
use Modules\Subjects\Domain\Services\EffectivePlanResolver;
use Modules\Subjects\Domain\ValueObjects\AssignmentScope;

function storedAssignment(int $id, int $planId, AssignmentScope $scope, ?int $grade = null, ?int $offer = null, bool $replaced = false): PlanAssignment
{
    return PlanAssignment::fromStorage($id, 1, 10, $planId, $scope, $grade, $offer, $replaced);
}

test('the most specific scope wins: offer over grade level over school', function () {
    $school = storedAssignment(1, 100, AssignmentScope::School);
    $grade = storedAssignment(2, 200, AssignmentScope::GradeLevel, grade: 7);
    $offer = storedAssignment(3, 300, AssignmentScope::Offer, grade: 7, offer: 55);

    expect(EffectivePlanResolver::resolve([$offer, $school, $grade], 55, 7))->toBe($offer)
        ->and(EffectivePlanResolver::resolve([$school, $grade], 55, 7))->toBe($grade)
        ->and(EffectivePlanResolver::resolve([$school], 55, 7))->toBe($school);
});

test('assignments for other grade levels or offers do not apply', function () {
    $school = storedAssignment(1, 100, AssignmentScope::School);
    $otherGrade = storedAssignment(2, 200, AssignmentScope::GradeLevel, grade: 8);
    $otherOffer = storedAssignment(3, 300, AssignmentScope::Offer, grade: 7, offer: 56);

    expect(EffectivePlanResolver::resolve([$otherGrade, $otherOffer, $school], 55, 7))->toBe($school)
        ->and(EffectivePlanResolver::resolve([$otherGrade, $otherOffer], 55, 7))->toBeNull();
});

test('replaced assignments are history and never resolve', function () {
    $school = storedAssignment(1, 100, AssignmentScope::School);
    $replacedOffer = storedAssignment(2, 300, AssignmentScope::Offer, grade: 7, offer: 55, replaced: true);

    expect(EffectivePlanResolver::resolve([$replacedOffer, $school], 55, 7))->toBe($school);
});

test('slot targets are normalized so a unique index can compare them', function () {
    expect(PlanAssignment::forSchool(1, 10, 100)->targetId())->toBe(0)
        ->and(PlanAssignment::forGradeLevel(1, 10, 100, 7)->targetId())->toBe(7)
        ->and(PlanAssignment::forOffer(1, 10, 100, 55, 7)->targetId())->toBe(55)
        ->and(PlanAssignment::forOffer(1, 10, 100, 55, 7)->slotKey())->toBe('10:offer:55');
});

test('an assignment covers a grade level when it is school-wide or targets that grade', function () {
    expect(PlanAssignment::forSchool(1, 10, 100)->coversGradeLevel(7))->toBeTrue()
        ->and(PlanAssignment::forGradeLevel(1, 10, 100, 7)->coversGradeLevel(7))->toBeTrue()
        ->and(PlanAssignment::forGradeLevel(1, 10, 100, 7)->coversGradeLevel(8))->toBeFalse()
        ->and(PlanAssignment::forOffer(1, 10, 100, 55, 7)->coversGradeLevel(7))->toBeTrue()
        ->and(PlanAssignment::forOffer(1, 10, 100, 55, 7)->coversGradeLevel(8))->toBeFalse();
});
