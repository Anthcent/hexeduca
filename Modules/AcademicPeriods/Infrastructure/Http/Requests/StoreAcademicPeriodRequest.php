<?php

namespace Modules\AcademicPeriods\Infrastructure\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAcademicPeriodRequest extends FormRequest
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
        $periodId = $this->route('academic_period')?->id;

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('periodos_academicos', 'name')
                    ->where('school_id', $schoolId)
                    ->ignore($periodId),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ];
    }
}
