<?php

namespace Modules\Grades\Infrastructure\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * One cell of the grade sheet. indicator_id null means the extra; points
 * null clears the cell. Ranges are checked by the use case against the plan.
 */
class RecordGradeRequest extends FormRequest
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
            'student_id' => ['required', 'integer'],
            'indicator_id' => ['nullable', 'integer'],
            'points' => ['nullable', 'integer', 'min:0', 'max:20'],
        ];
    }

    /**
     * The sheet saves cell by cell over JSON: answer JSON, not a redirect.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'La nota debe ser un número entero entre 0 y 20.',
        ], 422));
    }
}
