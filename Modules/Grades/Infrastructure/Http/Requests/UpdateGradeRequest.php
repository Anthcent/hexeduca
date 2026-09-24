<?php

namespace Modules\Grades\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGradeRequest extends FormRequest
{
    /**
     * GradeController@update authorizes via GradePolicy before calling
     * this — see plan §16/§11.
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
            'value' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
