<?php

namespace Modules\Academic\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMatriculaRequest extends FormRequest
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
            'oferta_academica_id' => ['required', 'integer', 'exists:ofertas_academicas,id'],
            'student_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
