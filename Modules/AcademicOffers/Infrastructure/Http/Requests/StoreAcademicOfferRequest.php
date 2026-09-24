<?php

namespace Modules\AcademicOffers\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAcademicOfferRequest extends FormRequest
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
            // Table-level FK validation only — Fase 4 does not import
            // Modules\GradeLevels/Sections code, only reads via their
            // Public\Contracts readers for display (see controller).
            'grade_level_id' => ['required', 'integer', 'exists:grados,id'],
            'section_id' => ['required', 'integer', 'exists:secciones,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            // Deferred to Fase 6: still validated against the shared
            // `users` table directly.
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
