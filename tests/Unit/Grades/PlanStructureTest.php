<?php

use Modules\Grades\Domain\Exceptions\InvalidPlan;
use Modules\Grades\Domain\ValueObjects\PlanStructure;

function planStructureReferent(array $points, string $topic = 'Tema'): array
{
    return [
        'topic' => $topic,
        'technique' => null,
        'indicators' => array_map(fn (int $p): array => ['description' => "Vale {$p}", 'maxPoints' => $p], $points),
    ];
}

test('a valid plan keeps its referentes and trims texts; an empty technique is null', function () {
    $plan = PlanStructure::fromArray([
        ['topic' => '  Fracciones ', 'technique' => '  ', 'indicators' => [
            ['description' => ' Suma ', 'maxPoints' => 12],
            ['description' => 'Resta', 'maxPoints' => 8],
        ]],
        planStructureReferent([20]),
    ]);

    expect($plan->referents)->toHaveCount(2)
        ->and($plan->referents[0])->toBe([
            'topic' => 'Fracciones',
            'technique' => null,
            'indicators' => [['description' => 'Suma', 'maxPoints' => 12], ['description' => 'Resta', 'maxPoints' => 8]],
        ]);
});

test('an invalid plan is refused with a message the user can act on', function (array $referents, string $message) {
    expect(fn () => PlanStructure::fromArray($referents))->toThrow(InvalidPlan::class, $message);
})->with([
    'a sum under 20' => [[planStructureReferent([12, 7])], 'Los indicadores del referente 1 suman 19 puntos; deben sumar exactamente 20.'],
    'a sum over 20 in the second referente' => [[planStructureReferent([20]), planStructureReferent([15, 6])], 'Los indicadores del referente 2 suman 21 puntos; deben sumar exactamente 20.'],
    'no referentes' => [[], 'El plan debe tener entre 1 y 10 referentes.'],
    'eleven referentes' => [array_fill(0, 11, planStructureReferent([20])), 'El plan debe tener entre 1 y 10 referentes.'],
    'no indicators' => [[planStructureReferent([])], 'El referente 1 debe tener entre 1 y 10 indicadores.'],
    'eleven indicators' => [[planStructureReferent([2, 2, 2, 2, 2, 2, 2, 2, 2, 1, 1])], 'El referente 1 debe tener entre 1 y 10 indicadores.'],
    'an indicator worth 0' => [[planStructureReferent([20, 0])], 'El indicador B del referente 1 debe valer entre 1 y 20 puntos.'],
    'an indicator worth 21' => [[planStructureReferent([21])], 'El indicador A del referente 1 debe valer entre 1 y 20 puntos.'],
    'a missing topic' => [[planStructureReferent([20], '   ')], 'El referente 1 necesita un tema.'],
    'a missing description' => [[['topic' => 'T', 'indicators' => [['description' => '', 'maxPoints' => 20]]]], 'El indicador A del referente 1 necesita una descripción.'],
]);

test('ten referentes of ten indicators each are allowed', function () {
    $plan = PlanStructure::fromArray(array_fill(0, 10, planStructureReferent([2, 2, 2, 2, 2, 2, 2, 2, 2, 2])));

    expect($plan->referents)->toHaveCount(10)
        ->and(PlanStructure::letter(9))->toBe('J');
});

test('sameShapeAs compares referentes and points, not texts', function () {
    $base = PlanStructure::fromArray([planStructureReferent([12, 8], 'Fracciones'), planStructureReferent([20], 'Geometría')]);

    $textsOnly = PlanStructure::fromArray([
        ['topic' => 'Otro tema', 'technique' => 'Exposición', 'indicators' => [
            ['description' => 'Nueva A', 'maxPoints' => 12],
            ['description' => 'Nueva B', 'maxPoints' => 8],
        ]],
        planStructureReferent([20], 'Figuras'),
    ]);

    expect($base->sameShapeAs($textsOnly))->toBeTrue()
        ->and($base->sameShapeAs(PlanStructure::fromArray([planStructureReferent([8, 12]), planStructureReferent([20])])))->toBeFalse()
        ->and($base->sameShapeAs(PlanStructure::fromArray([planStructureReferent([12, 8]), planStructureReferent([10, 10])])))->toBeFalse()
        ->and($base->sameShapeAs(PlanStructure::fromArray([planStructureReferent([12, 8])])))->toBeFalse()
        ->and($base->sameShapeAs(PlanStructure::fromArray([planStructureReferent([12, 8]), planStructureReferent([20]), planStructureReferent([20])])))->toBeFalse();
});
