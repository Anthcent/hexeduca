<?php

namespace Modules\Grades\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGradeRequest extends FormRequest
{
    /**
     * Route-level `auth` + `role:teacher|staff/admin` middleware gates
     * coarse access; GradeController enforces who may record a grade for
     * whom. RecordGrade independently verifies actor school, active period,
     * offer assignment and any delegated teacher — see plan §16/§11.
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
            'academic_offer_id' => ['required', 'integer'],
            'student_id' => ['required', 'integer'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'value' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
