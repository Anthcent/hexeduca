<?php

namespace Modules\Academic\Infrastructure\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeccionRequest extends FormRequest
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
        $seccionId = $this->route('seccion')?->id;

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('secciones', 'name')
                    ->where('school_id', $schoolId)
                    ->ignore($seccionId),
            ],
        ];
    }
}
