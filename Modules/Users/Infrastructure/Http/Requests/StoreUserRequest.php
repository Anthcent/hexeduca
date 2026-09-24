<?php

namespace Modules\Users\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Users\Infrastructure\Models\User;

class StoreUserRequest extends FormRequest
{
    /**
     * Roles a user may be created with. `super-admin` is never assignable
     * from this screen, not even by another super-admin.
     *
     * @var list<string>
     */
    public const ASSIGNABLE_ROLES = ['student', 'teacher', 'staff/admin'];

    /**
     * Delegates to `UserPolicy::create`: a staff/admin may only create users
     * on their own school's subdomain; a super-admin may create anywhere.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', 'string', Rule::in(self::ASSIGNABLE_ROLES)],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            // Only a super-admin on the landlord host (no tenant bound) picks
            // the school; otherwise any submitted school_id is discarded.
            'school_id' => $this->mustChooseSchool()
                ? ['required', 'integer', Rule::exists('schools', 'id')->where('is_active', true)]
                : ['exclude'],
        ];
    }

    public function mustChooseSchool(): bool
    {
        return current_tenant() === null;
    }

    /**
     * The school the new user belongs to: always the bound tenant when there
     * is one, never a client-supplied value on a tenant host.
     */
    public function schoolId(): int
    {
        return current_tenant()?->id ?? (int) $this->validated('school_id');
    }
}
