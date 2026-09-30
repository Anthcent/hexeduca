<?php

use App\Tenancy\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Subjects\Public\Contracts\OfferSubjectsReader;
use Tests\Feature\Subjects\SubjectsFixtures as F;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->grade1 = F::gradeLevel($this->school, 'Primer año', 1);
    $this->grade2 = F::gradeLevel($this->school, 'Segundo año', 2);
    $this->period = F::period($this->school, '2026', true);
    $this->offer1A = F::offer($this->school, $this->period, $this->grade1, F::section($this->school, 'A'));
    $this->offer1B = F::offer($this->school, $this->period, $this->grade1, F::section($this->school, 'B'));
    $this->offer2A = F::offer($this->school, $this->period, $this->grade2, F::section($this->school, 'A'));

    $this->general = F::plan($this->school, '31060');
    $this->math1 = F::subject($this->school, $this->general, $this->grade1, 'Matemática', ['code' => 'MAT1', 'weekly_hours' => 4]);
    $this->art1 = F::subject($this->school, $this->general, $this->grade1, 'Arte');
    $this->chem2 = F::subject($this->school, $this->general, $this->grade2, 'Química');

    $this->technical = F::plan($this->school, '40000');
    $this->workshop1 = F::subject($this->school, $this->technical, $this->grade1, 'Taller');
    $this->drawing2 = F::subject($this->school, $this->technical, $this->grade2, 'Dibujo');
});

function offerSubjectNames(int $schoolId, int $periodId, int $offerId): array
{
    return array_map(fn ($s) => $s->name, app(OfferSubjectsReader::class)->forOffer($schoolId, $periodId, $offerId));
}

test('the offer takes the most specific plan: offer beats grade level beats school', function () {
    F::assignment($this->school, $this->period, $this->general, 'school');
    F::assignment($this->school, $this->period, $this->technical, 'grade_level', $this->grade2);
    F::assignment($this->school, $this->period, $this->technical, 'offer', $this->grade1, $this->offer1B);

    $byOffer = app(OfferSubjectsReader::class)->forPeriod($this->school->id, $this->period);

    expect(array_keys($byOffer))->toEqualCanonicalizing([$this->offer1A, $this->offer1B, $this->offer2A])
        ->and(array_map(fn ($s) => $s->name, $byOffer[$this->offer1A]))->toBe(['Arte', 'Matemática'])
        ->and(array_map(fn ($s) => $s->name, $byOffer[$this->offer1B]))->toBe(['Taller'])
        ->and(array_map(fn ($s) => $s->name, $byOffer[$this->offer2A]))->toBe(['Dibujo']);

    $math = $byOffer[$this->offer1A][1];
    expect([$math->id, $math->planId, $math->gradeLevelId, $math->code, $math->weeklyHours])
        ->toBe([$this->math1, $this->general, $this->grade1, 'MAT1', 4]);
});

test('only active subjects of the offer\'s grade level, minus the assignment\'s exclusions', function () {
    $school = F::assignment($this->school, $this->period, $this->general, 'school');
    F::subject($this->school, $this->general, $this->grade1, 'Latín', ['status' => 'archived']);
    F::exclude($this->school, $school, $this->art1);

    expect(offerSubjectNames($this->school->id, $this->period, $this->offer1A))->toBe(['Matemática'])
        ->and(offerSubjectNames($this->school->id, $this->period, $this->offer2A))->toBe(['Química']);
});

test('the exclusions of a broader assignment do not leak into a more specific one', function () {
    $school = F::assignment($this->school, $this->period, $this->general, 'school');
    F::exclude($this->school, $school, $this->art1);
    F::assignment($this->school, $this->period, $this->general, 'offer', $this->grade1, $this->offer1B);

    expect(offerSubjectNames($this->school->id, $this->period, $this->offer1A))->toBe(['Matemática'])
        ->and(offerSubjectNames($this->school->id, $this->period, $this->offer1B))->toBe(['Arte', 'Matemática']);
});

test('offers without a plan, replaced assignments and other periods give no subjects', function () {
    F::assignment($this->school, $this->period, $this->general, 'school', replaced: true);
    $next = F::period($this->school, '2027', false, '2027-01-01', '2027-12-31');
    F::assignment($this->school, $next, $this->general, 'school');

    expect(app(OfferSubjectsReader::class)->forPeriod($this->school->id, $this->period))
        ->toBe([$this->offer1A => [], $this->offer1B => [], $this->offer2A => []])
        ->and(offerSubjectNames($this->school->id, $this->period, 999999))->toBe([]);
});

test('another school reads nothing of this school', function () {
    F::assignment($this->school, $this->period, $this->general, 'school');
    $other = School::factory()->create();

    expect(app(OfferSubjectsReader::class)->forPeriod($other->id, $this->period))->toBe([])
        ->and(offerSubjectNames($other->id, $this->period, $this->offer1A))->toBe([]);
});
