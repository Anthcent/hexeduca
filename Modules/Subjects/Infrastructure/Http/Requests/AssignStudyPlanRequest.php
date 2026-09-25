<?php

namespace Modules\Subjects\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Subjects\Domain\ValueObjects\AssignmentScope;

/**
 * Whether the period, grade level or offer belongs to the school is
 * checked by the use case through the sibling readers.
 */
class AssignStudyPlanRequest extends FormRequest
{
    /**
     * Route-level `auth` + `permission:subjects.manage` already gates access.
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
            'period_id' => ['required', 'integer'],
            'plan_id' => ['required', 'integer'],
            'scope' => ['required', Rule::enum(AssignmentScope::class)],
            'grade_level_id' => ['required_if:scope,grade_level', 'nullable', 'integer'],
            'offer_id' => ['required_if:scope,offer', 'nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_id.required' => 'Selecciona un periodo.',
            'plan_id.required' => 'Selecciona un plan de estudio.',
            'scope.required' => 'Selecciona dónde se aplica el plan.',
            'scope.enum' => 'El alcance seleccionado no es válido.',
            'grade_level_id.required_if' => 'Selecciona un año.',
            'offer_id.required_if' => 'Selecciona una sección.',
            '*.integer' => 'El valor seleccionado no es válido.',
        ];
    }
}
