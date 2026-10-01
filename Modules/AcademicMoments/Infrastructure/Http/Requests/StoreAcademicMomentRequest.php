<?php

namespace Modules\AcademicMoments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcademicMomentRequest extends FormRequest
{
    /**
     * Route-level `auth` + `permission:` middleware already gates
     * access — no additional per-request authorization is needed here.
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
            'name' => ['required', 'string', 'max:255'],
            'order' => ['required', 'integer', 'min:1'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            // The grade-entry window: both dates or none.
            'grading_opens_on' => ['nullable', 'date', 'required_with:grading_closes_on'],
            'grading_closes_on' => ['nullable', 'date', 'required_with:grading_opens_on', 'after_or_equal:grading_opens_on'],
            // No academic_period_id: the controller always uses the school's
            // active period, never a period taken from the request.
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es obligatorio.',
            'order.required' => 'El orden es obligatorio.',
            'order.min' => 'El orden debe ser 1 o mayor.',
            'starts_on.required' => 'La fecha de inicio es obligatoria.',
            'ends_on.required' => 'La fecha de fin es obligatoria.',
            'ends_on.after_or_equal' => 'El fin debe ser igual o posterior al inicio.',
            'grading_opens_on.required_with' => 'Indica desde cuándo se cargan notas.',
            'grading_closes_on.required_with' => 'Indica hasta cuándo se cargan notas.',
            'grading_closes_on.after_or_equal' => 'El cierre de carga debe ser igual o posterior a la apertura.',
            '*.date' => 'La fecha no es válida.',
        ];
    }
}
