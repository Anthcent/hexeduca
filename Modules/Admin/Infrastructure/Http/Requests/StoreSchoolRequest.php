<?php

namespace Modules\Admin\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolRequest extends FormRequest
{
    /**
     * Route-level `auth` + `role:super-admin` + `RequireLandlordHost`
     * middleware already gate access — no additional per-request
     * authorization is needed here.
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
        $schoolId = $this->route('school')?->id;
        $baseDomain = strtolower((string) config('tenancy.base_domain'));
        $reservedLabels = collect((array) config('tenancy.landlord_hosts', []))
            ->map(fn (string $host) => strtolower(str($host)->before('.'.$baseDomain)))
            ->push('www')
            ->all();

        return [
            'name' => ['required', 'string', 'max:255'],
            'subdomain' => [
                'required', 'string', 'max:63',
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                Rule::unique('schools', 'subdomain')->ignore($schoolId),
                Rule::notIn($reservedLabels),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subdomain.regex' => 'El subdominio solo puede tener minúsculas, números y guiones, sin empezar ni terminar en guión.',
            'subdomain.not_in' => 'Ese subdominio está reservado para el panel de plataforma.',
        ];
    }
}
