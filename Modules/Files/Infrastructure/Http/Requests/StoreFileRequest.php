<?php

namespace Modules\Files\Infrastructure\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Files\Domain\ValueObjects\UploadRules;

class StoreFileRequest extends FormRequest
{
    /**
     * Route-level `auth` + `permission:files.upload` middleware already
     * gates access.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `mimes` checks the type detected from the file content; `extensions`
     * additionally rejects a client filename with an unexpected extension.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowed = implode(',', UploadRules::ALLOWED_EXTENSIONS);

        return [
            'file' => [
                'required',
                'file',
                'max:'.UploadRules::MAX_SIZE_KILOBYTES,
                'mimes:'.$allowed,
                'extensions:'.$allowed,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $types = 'Formatos permitidos: PDF, JPG, PNG, Word, Excel y PowerPoint.';

        return [
            'file.required' => 'Selecciona un archivo para subir.',
            'file.file' => 'No se pudo subir el archivo. Inténtalo de nuevo.',
            'file.uploaded' => 'No se pudo subir el archivo. Verifica que no supere los 10 MB e inténtalo de nuevo.',
            'file.max' => 'El archivo no puede superar los 10 MB.',
            'file.mimes' => "El tipo de archivo no está permitido. {$types}",
            'file.extensions' => "El tipo de archivo no está permitido. {$types}",
        ];
    }
}
