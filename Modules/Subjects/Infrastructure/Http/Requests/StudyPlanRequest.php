<?php

namespace Modules\Subjects\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create or edit a plan. The "observation required when the code repeats"
 * rule is a domain rule, checked by the use case.
 */
class StudyPlanRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:150'],
            'observation' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => 'El código del plan es obligatorio.',
            'code.max' => 'El código no puede superar los 50 caracteres.',
            'name.required' => 'El nombre del plan es obligatorio.',
            'name.max' => 'El nombre no puede superar los 150 caracteres.',
            'observation.max' => 'La observación no puede superar los 500 caracteres.',
            '*.string' => 'El valor debe ser un texto.',
        ];
    }
}
