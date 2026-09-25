<?php

namespace Modules\Subjects\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create or edit a subject. Whether the grade level belongs to the school
 * is checked by the use case through GradeLevelReader.
 */
class SubjectRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:50'],
            'grade_level_id' => ['required', 'integer'],
            'weekly_hours' => ['nullable', 'integer', 'min:1', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la asignatura es obligatorio.',
            'name.max' => 'El nombre no puede superar los 150 caracteres.',
            'code.max' => 'El código no puede superar los 50 caracteres.',
            'grade_level_id.required' => 'Selecciona el año de la asignatura.',
            'grade_level_id.integer' => 'Selecciona un año válido.',
            'weekly_hours.integer' => 'Las horas semanales deben ser un número entero.',
            'weekly_hours.min' => 'Las horas semanales deben ser mayores que cero.',
            'weekly_hours.max' => 'Las horas semanales no pueden superar 60.',
            '*.string' => 'El valor debe ser un texto.',
        ];
    }
}
