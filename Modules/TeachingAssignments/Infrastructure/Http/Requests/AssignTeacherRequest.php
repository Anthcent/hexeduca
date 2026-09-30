<?php

namespace Modules\TeachingAssignments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\TeachingAssignments\Domain\ValueObjects\TeachingRole;

/**
 * Whether the period, offer, subject and teacher belong to the school is
 * checked by the use case through the sibling Public readers.
 */
class AssignTeacherRequest extends FormRequest
{
    /**
     * Route-level `auth` + `permission:teachingassignments.manage` already gates access.
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
            'offer_id' => ['required', 'integer'],
            'subject_id' => ['required', 'integer'],
            'teacher_id' => ['required', 'integer'],
            'role' => ['required', Rule::enum(TeachingRole::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'teacher_id.required' => 'Selecciona un docente.',
            'role.*' => 'Selecciona un rol válido.',
            '*.required' => 'Faltan datos de la asignación.',
            '*.integer' => 'Faltan datos de la asignación.',
        ];
    }
}
