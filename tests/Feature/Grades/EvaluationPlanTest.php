<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Grades\GradesFixtures as G;

uses(RefreshDatabase::class);

beforeEach(function () {
    G::scenario($this);
});

function evaluationPlanSave(object $test, ?array $referents = null)
{
    return $test->actingAs($test->teacher)
        ->put(G::url($test->school, '/plan'), G::planPayload($test->offer, $test->math, $test->moment, $referents));
}

test('saving a plan creates it with its referentes and lettered indicators, then opens the sheet', function () {
    evaluationPlanSave($this)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('grades.sheet', DB::table('grade_plans')->value('id')));

    $plan = DB::table('grade_plans')->sole();
    expect($plan->school_id)->toBe($this->school->id)
        ->and($plan->academic_period_id)->toBe($this->period)
        ->and([$plan->academic_offer_id, $plan->study_plan_subject_id, $plan->academic_moment_id])->toBe([$this->offer, $this->math, $this->moment])
        ->and(DB::table('grade_plan_referents')->orderBy('position')->pluck('topic')->all())->toBe(['Fracciones', 'Geometría'])
        ->and(DB::table('grade_plan_indicators')->orderBy('id')->get(['letter', 'max_points'])->map(fn ($i) => $i->letter.$i->max_points)->all())
        ->toBe(['A12', 'B8', 'A10', 'B10']);

    $this->actingAs($this->teacher)->get(G::url($this->school, "/plan?offer={$this->offer}&subject={$this->math}&moment={$this->moment}"))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Grades::Plan')
            ->where('plan.id', $plan->id)
            ->has('plan.referents', 2)
            ->where('plan.referents.0.indicators.1.maxPoints', 8)
            ->where('locked', false)
            ->where('context.subjectName', 'Matemática'));
});

test('the plan page of a slot without a plan shows an empty plan', function () {
    $this->actingAs($this->teacher)->get(G::url($this->school, "/plan?offer={$this->offer}&subject={$this->math}&moment={$this->moment}"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('plan', null)->where('locked', false)->where('periodOpen', true));
});

test('a referente that does not sum 20 is refused on the referents field', function () {
    $referents = G::referents();
    $referents[0]['indicators'][1]['maxPoints'] = 7;

    evaluationPlanSave($this, $referents)
        ->assertSessionHasErrors(['referents' => 'Los indicadores del referente 1 suman 19 puntos; deben sumar exactamente 20.']);

    expect(DB::table('grade_plans')->count())->toBe(0);
});

test('without scores, the plan can be reshaped', function () {
    evaluationPlanSave($this);

    evaluationPlanSave($this, [['topic' => 'Todo', 'indicators' => [['description' => 'Única', 'maxPoints' => 20]]]])
        ->assertSessionHasNoErrors();

    expect(DB::table('grade_plans')->count())->toBe(1)
        ->and(DB::table('grade_plan_referents')->pluck('topic')->all())->toBe(['Todo'])
        ->and(DB::table('grade_plan_indicators')->pluck('max_points')->all())->toBe([20]);
});

test('once a plan has scores only its texts may change', function () {
    evaluationPlanSave($this);
    $plan = (int) DB::table('grade_plans')->value('id');
    $indicators = G::indicatorIds($plan);

    $this->actingAs($this->teacher)->putJson(G::url($this->school, "/sheets/{$plan}/cells"), [
        'student_id' => $this->s1->id, 'indicator_id' => $indicators[0], 'points' => 10,
    ])->assertOk();

    // Same points in another split: refused, nothing changes.
    $reshaped = G::referents();
    $reshaped[0]['indicators'][0]['maxPoints'] = 8;
    $reshaped[0]['indicators'][1]['maxPoints'] = 12;
    evaluationPlanSave($this, $reshaped)
        ->assertSessionHasErrors(['referents' => 'El plan ya tiene notas cargadas: solo se pueden cambiar textos, no referentes, indicadores ni puntos.']);

    $oneMore = G::referents();
    $oneMore[] = ['topic' => 'Extra', 'indicators' => [['description' => 'X', 'maxPoints' => 20]]];
    evaluationPlanSave($this, $oneMore)->assertSessionHasErrors('referents');

    // Texts only: accepted, same ids, the score is kept.
    $texts = G::referents();
    $texts[0]['topic'] = 'Fracciones y decimales';
    $texts[0]['technique'] = 'Taller';
    $texts[1]['indicators'][0]['description'] = 'Calcula perímetros';
    evaluationPlanSave($this, $texts)->assertSessionHasNoErrors();

    expect(G::indicatorIds($plan))->toBe($indicators)
        ->and(DB::table('grade_plan_referents')->orderBy('position')->pluck('topic')->all())->toBe(['Fracciones y decimales', 'Geometría'])
        ->and(DB::table('grade_plan_referents')->orderBy('position')->value('technique'))->toBe('Taller')
        ->and(DB::table('grade_plan_indicators')->where('id', $indicators[2])->value('description'))->toBe('Calcula perímetros')
        ->and(DB::table('grade_plan_indicators')->orderBy('id')->pluck('max_points')->all())->toBe([12, 8, 10, 10])
        ->and(DB::table('grade_scores')->sole()->points)->toBe(10);

    $this->actingAs($this->teacher)->get(G::url($this->school, "/plan?offer={$this->offer}&subject={$this->math}&moment={$this->moment}"))
        ->assertInertia(fn (Assert $page) => $page->where('locked', true));
});

test('an extra alone also locks the plan', function () {
    evaluationPlanSave($this);
    $plan = (int) DB::table('grade_plans')->value('id');

    $this->actingAs($this->teacher)->putJson(G::url($this->school, "/sheets/{$plan}/cells"), [
        'student_id' => $this->s1->id, 'indicator_id' => null, 'points' => 2,
    ])->assertOk();

    evaluationPlanSave($this, [['topic' => 'Todo', 'indicators' => [['description' => 'Única', 'maxPoints' => 20]]]])
        ->assertSessionHasErrors('referents');

    expect(DB::table('grade_plan_indicators')->count())->toBe(4);
});
