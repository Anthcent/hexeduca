<?php

namespace Modules\Users\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRoleRequest extends FormRequest
{
    /**
     * Route-level `['auth', 'role:staff/admin|super-admin']` middleware
     * already restricts who can reach this endpoint. The finer-grained
     * tenant-ownership and super-admin-escalation checks are enforced by
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
            'role' => ['required', Rule::in(['student', 'teacher', 'staff/admin', 'super-admin'])],
        ];
    }
}
