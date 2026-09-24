<?php

namespace Modules\AcademicMoments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcademicMomentRequest extends FormRequest
{
    /**
     * Route-level `auth` + `role:staff/admin` middleware already gates
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
            // Table-level FK validation only — see plan §5 note on
            // school_id/academic_period_id as sanctioned root dependencies.
            'academic_period_id' => ['required', 'integer', 'exists:periodos_academicos,id'],
        ];
    }
}
