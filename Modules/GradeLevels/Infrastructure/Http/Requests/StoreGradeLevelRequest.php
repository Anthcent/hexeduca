<?php

namespace Modules\GradeLevels\Infrastructure\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGradeLevelRequest extends FormRequest
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
        $schoolId = app(TenantContext::class)->current()->id;
        $gradeLevelId = $this->route('grade_level')?->id;

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('grados', 'name')
                    ->where('school_id', $schoolId)
                    ->where('nivel_academico_id', $this->input('academic_level_id'))
                    ->ignore($gradeLevelId),
            ],
            // Table-level FK validation only (checks the row exists in the
            // shared `niveles_academicos` table) — not a code import of
            // Modules\AcademicLevels, same treatment as `school_id`/
            // `academic_period_id` root dependencies (plan §3/§5).
            'academic_level_id' => ['required', 'integer', 'exists:niveles_academicos,id'],
            'order' => ['required', 'integer', 'min:1'],
        ];
    }
}
