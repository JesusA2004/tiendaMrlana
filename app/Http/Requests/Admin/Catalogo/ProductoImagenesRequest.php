<?php

namespace App\Http\Requests\Admin\Catalogo;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Subida de imágenes de producto: jpg/png/webp reales (mime verificado), máx. 5 MB c/u,
 * y confirmación obligatoria de derechos de uso.
 */
class ProductoImagenesRequest extends FormRequest
{
    public const MAX_KB = 5120;

    public const MAX_ARCHIVOS = 10;

    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-catalogo') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'imagenes' => ['required', 'array', 'min:1', 'max:'.self::MAX_ARCHIVOS],
            'imagenes.*' => [
                'required', 'file', 'image',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.self::MAX_KB,
                'dimensions:max_width=8000,max_height=8000',
            ],
            'derechos' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'derechos.accepted' => 'Confirma que Mr Lana tiene derechos para usar estas imágenes.',
            'imagenes.required' => 'Selecciona al menos una imagen.',
            'imagenes.max' => 'Puedes subir hasta '.self::MAX_ARCHIVOS.' imágenes a la vez.',
            'imagenes.*.max' => 'Cada imagen debe pesar máximo 5 MB.',
            'imagenes.*.image' => 'Sólo se permiten imágenes JPG, PNG o WebP.',
            'imagenes.*.mimes' => 'Sólo se permiten imágenes JPG, PNG o WebP.',
            'imagenes.*.mimetypes' => 'Sólo se permiten imágenes JPG, PNG o WebP.',
            'imagenes.*.dimensions' => 'Cada imagen debe medir máximo 8000 px por lado.',
        ];
    }
}
