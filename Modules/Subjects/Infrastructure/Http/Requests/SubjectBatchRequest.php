<?php

namespace Modules\Subjects\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Quick entry of several subjects for one grade level. Whether the grade
 * level belongs to the school is checked by the use case.
 */
class SubjectBatchRequest extends FormRequest
{
    public const MAX_NAMES = 50;

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
            'grade_level_id' => ['required', 'integer'],
            'names' => ['required', 'array', 'min:1', 'max:'.self::MAX_NAMES],
            'names.*' => ['required', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grade_level_id.required' => 'Selecciona el año de las asignaturas.',
            'grade_level_id.integer' => 'Selecciona un año válido.',
            'names.required' => 'Escribe al menos una asignatura.',
            'names.array' => 'Escribe al menos una asignatura.',
            'names.min' => 'Escribe al menos una asignatura.',
            'names.max' => 'Puedes agregar hasta '.self::MAX_NAMES.' asignaturas a la vez.',
            'names.*.required' => 'Hay una línea vacía en la lista.',
            'names.*.string' => 'El nombre debe ser un texto.',
            'names.*.max' => 'Cada nombre puede tener hasta 150 caracteres.',
        ];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_map('strval', $this->validated('names')));
    }
}
