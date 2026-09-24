<?php

namespace Modules\Notifications\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Notifications\Domain\ValueObjects\Announcement;
use Modules\Notifications\Domain\ValueObjects\Audience;

class StoreAnnouncementRequest extends FormRequest
{
    /**
     * Route-level `auth` + `permission:notifications.send` middleware already
     * gates access.
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
            'title' => ['required', 'string', 'max:'.Announcement::TITLE_MAX_LENGTH],
            'body' => ['required', 'string', 'max:'.Announcement::BODY_MAX_LENGTH],
            'audience' => ['required', 'string', Rule::in(Audience::values())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'El título es obligatorio.',
            'title.max' => 'El título no puede superar los :max caracteres.',
            'body.required' => 'El mensaje es obligatorio.',
            'body.max' => 'El mensaje no puede superar los :max caracteres.',
            'audience.required' => 'Selecciona a quién va dirigido el anuncio.',
            'audience.in' => 'El destinatario elegido no es válido.',
        ];
    }
}
