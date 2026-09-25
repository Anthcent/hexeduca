<?php

namespace Modules\Subjects\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Include or exclude one subject, either on an assignment or ("only this
 * section") on one offer of a period.
 */
class SubjectExclusionRequest extends FormRequest
{
    /**
     * Route-level `auth` + `permission:subjects.manage` already gates access.
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
        $forOffer = $this->routeIs('subjects.assignments.offer-override');

        return [
            'subject_id' => ['required', 'integer'],
            'excluded' => ['required', 'boolean'],
            'period_id' => [$forOffer ? 'required' : 'prohibited', 'integer'],
            'offer_id' => [$forOffer ? 'required' : 'prohibited', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.required' => 'Selecciona una asignatura.',
            'excluded.required' => 'Indica si la asignatura se incluye o se excluye.',
            'period_id.required' => 'Selecciona un periodo.',
            'offer_id.required' => 'Selecciona una sección.',
            '*.integer' => 'El valor seleccionado no es válido.',
            '*.boolean' => 'El valor seleccionado no es válido.',
        ];
    }
}
