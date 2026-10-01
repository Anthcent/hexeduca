<?php

namespace Modules\Users\Infrastructure\Http\Requests;

use App\ModulePlatform\Services\RoleTemplates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    /**
     * Route-level `permission:users.manage` middleware
     * already restricts who can reach this endpoint. The finer-grained
     * tenant-ownership check is enforced by
     * `UserPolicy::assignRole` in the controller, not here.
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
            'role' => ['required', Rule::in(array_keys(RoleTemplates::TEMPLATES))],
        ];
    }
}
