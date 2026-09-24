<?php

namespace Modules\Enrollments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEnrollmentRequest extends FormRequest
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
            'academic_offer_id' => ['required', 'integer', 'exists:ofertas_academicas,id'],
            // Deferred to Fase 6: still validated against the shared
            // `users` table directly.
            'student_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
