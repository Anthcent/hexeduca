<?php

namespace Modules\TeachingAssignments\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CoordinatorRequest extends FormRequest
{
    /**
     * Route-level `auth` + `permission:teachingassignments.manage` already gates access.
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
            'period_id' => ['required', 'integer'],
            'teacher_id' => ['nullable', 'integer'],
        ];
    }
}
