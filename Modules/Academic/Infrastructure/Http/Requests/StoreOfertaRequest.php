<?php

namespace Modules\Academic\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOfertaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
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
            'grado_id' => ['required', 'integer', 'exists:grados,id'],
            'seccion_id' => ['required', 'integer', 'exists:secciones,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
