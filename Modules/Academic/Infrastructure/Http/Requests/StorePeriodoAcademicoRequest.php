<?php

namespace Modules\Academic\Infrastructure\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePeriodoAcademicoRequest extends FormRequest
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
        $periodoId = $this->route('periodo')?->id;

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('periodos_academicos', 'name')
                    ->where('school_id', $schoolId)
                    ->ignore($periodoId),
            ],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after:starts_on'],
        ];
    }
}
