<?php

use App\Tenancy\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\AcademicOffers\Public\Contracts\AcademicOfferReader;
use Modules\AcademicPeriods\Public\Contracts\AcademicPeriodReader;
use Tests\Feature\Subjects\SubjectsFixtures as F;

uses(RefreshDatabase::class);

/*
| The Public contracts Subjects added to its siblings (additively):
| AcademicPeriods' AcademicPeriodReader and AcademicOffers' allForPeriod()
| plus the gradeLevelId/sectionId fields of AcademicOfferSummary.
*/

test('AcademicPeriodReader reads only the given school, newest first, and finds the active period', function () {
    $school = School::factory()->create();
    $other = School::factory()->create();
    $old = F::period($school, '2025', false, '2025-01-01', '2025-11-30');
    $active = F::period($school, '2026', true, '2026-01-01', '2026-11-30');
    $foreign = F::period($other, '2026', true);

    $reader = app(AcademicPeriodReader::class);
    $all = $reader->allForSchool($school->id);

    expect(array_map(fn ($p) => $p->id, $all))->toBe([$active, $old])
        ->and($all[0]->isActive)->toBeTrue()
        ->and($all[0]->startsOn)->toBe('2026-01-01')
        ->and($all[0]->endsOn)->toBe('2026-11-30')
        ->and($all[1]->isActive)->toBeFalse()
        ->and($reader->activeForSchool($school->id)?->id)->toBe($active)
        ->and($reader->findForSchool($old, $school->id)?->name)->toBe('2025')
        ->and($reader->findForSchool($foreign, $school->id))->toBeNull();
});

test('AcademicOfferReader::allForPeriod returns the period\'s offers with grade level and section ids', function () {
    $school = School::factory()->create();
    $grade = F::gradeLevel($school, 'Primer año', 1);
    $section = F::section($school, 'A');
    $period = F::period($school, '2026', true);
    $otherPeriod = F::period($school, '2027');
    $offer = F::offer($school, $period, $grade, $section);
    F::offer($school, $otherPeriod, $grade, $section);
    $foreignSchool = School::factory()->create();
    F::offer($foreignSchool, F::period($foreignSchool, '2026', true), F::gradeLevel($foreignSchool, 'Primero', 1), F::section($foreignSchool, 'A'));

    $offers = app(AcademicOfferReader::class)->allForPeriod($school->id, $period);

    expect($offers)->toHaveCount(1)
        ->and($offers[0]->id)->toBe($offer)
        ->and($offers[0]->gradeLevelId)->toBe($grade)
        ->and($offers[0]->sectionId)->toBe($section)
        ->and($offers[0]->gradeLevelName)->toBe('Primer año')
        ->and($offers[0]->sectionName)->toBe('A');
});

test('allActiveForSchool also carries the new grade level and section ids', function () {
    $school = School::factory()->create();
    $grade = F::gradeLevel($school, 'Primer año', 1);
    $section = F::section($school, 'B');
    $offer = F::offer($school, F::period($school, '2026', true), $grade, $section);

    $offers = app(AcademicOfferReader::class)->allActiveForSchool($school->id);

    expect($offers)->toHaveCount(1)
        ->and($offers[0]->id)->toBe($offer)
        ->and($offers[0]->gradeLevelId)->toBe($grade)
        ->and($offers[0]->sectionId)->toBe($section);
});
