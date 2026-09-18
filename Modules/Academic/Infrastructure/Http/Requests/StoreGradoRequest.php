<?php

namespace Modules\Academic\Infrastructure\Http\Requests;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGradoRequest extends FormRequest
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
        $gradoId = $this->route('grado')?->id;

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('grados', 'name')
                    ->where('school_id', $schoolId)
                    ->where('nivel_academico_id', $this->input('nivel_academico_id'))
                    ->ignore($gradoId),
            ],
            'nivel_academico_id' => ['required', 'integer', 'exists:niveles_academicos,id'],
            'order' => ['required', 'integer', 'min:1'],
        ];
    }
}
