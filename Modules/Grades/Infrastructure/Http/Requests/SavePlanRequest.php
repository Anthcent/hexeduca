<?php

namespace Modules\Grades\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The plan's shape (sums, counts) is validated by PlanStructure; this only
 * checks types. Whether the teacher may edit it is checked by the use case.
 */
class SavePlanRequest extends FormRequest
{
    /**
     * Route-level `auth` + `permission:grades.manage` already gates access.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'offer_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'moment_id' => ['required', 'integer'],
            'referents' => ['required', 'array', 'min:1', 'max:10'],
            'referents.*.topic' => ['nullable', 'string', 'max:200'],
            'referents.*.technique' => ['nullable', 'string', 'max:200'],
            'referents.*.indicators' => ['required', 'array', 'min:1', 'max:10'],
            'referents.*.indicators.*.description' => ['nullable', 'string', 'max:300'],
            'referents.*.indicators.*.maxPoints' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'referents.required' => 'El plan necesita al menos un referente.',
            'referents.min' => 'El plan necesita al menos un referente.',
            'referents.max' => 'El plan puede tener hasta 10 referentes.',
            'referents.*.indicators.required' => 'Cada referente necesita al menos un indicador.',
            'referents.*.indicators.min' => 'Cada referente necesita al menos un indicador.',
            'referents.*.indicators.max' => 'Cada referente puede tener hasta 10 indicadores.',
            'referents.*.topic.max' => 'El tema puede tener hasta 200 caracteres.',
            'referents.*.technique.max' => 'La técnica puede tener hasta 200 caracteres.',
            'referents.*.indicators.*.description.max' => 'La descripción puede tener hasta 300 caracteres.',
            'referents.*.indicators.*.maxPoints.required' => 'Cada indicador debe valer entre 1 y 20 puntos.',
            'referents.*.indicators.*.maxPoints.integer' => 'Cada indicador debe valer entre 1 y 20 puntos.',
            'referents.*.indicators.*.maxPoints.min' => 'Cada indicador debe valer entre 1 y 20 puntos.',
            'referents.*.indicators.*.maxPoints.max' => 'Cada indicador debe valer entre 1 y 20 puntos.',
        ];
    }

    /**
     * @return list<array{topic: string, technique: ?string, indicators: list<array{description: string, maxPoints: int}>}>
     */
    public function referents(): array
    {
        return array_values(array_map(fn (array $referent): array => [
            'topic' => (string) ($referent['topic'] ?? ''),
            'technique' => $referent['technique'] ?? null,
            'indicators' => array_values(array_map(fn (array $indicator): array => [
                'description' => (string) ($indicator['description'] ?? ''),
                'maxPoints' => (int) $indicator['maxPoints'],
            ], $referent['indicators'])),
        ], $this->validated('referents')));
    }
}
