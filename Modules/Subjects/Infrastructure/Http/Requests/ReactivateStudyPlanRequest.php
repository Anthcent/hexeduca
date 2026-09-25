<?php

namespace Modules\Subjects\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `approved_assignment_ids` lists the current assignments the user
 * approved replacing; it may be empty when there are no conflicts.
 */
class ReactivateStudyPlanRequest extends FormRequest
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
            'approved_assignment_ids' => ['present', 'array'],
            'approved_assignment_ids.*' => ['integer'],
        ];
    }

    /**
     * @return list<int>
     */
    public function approvedAssignmentIds(): array
    {
        return array_map('intval', $this->input('approved_assignment_ids', []));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'approved_assignment_ids.present' => 'Falta la confirmación de la reactivación.',
            'approved_assignment_ids.array' => 'La confirmación no es válida.',
            'approved_assignment_ids.*.integer' => 'La confirmación no es válida.',
        ];
    }
}
