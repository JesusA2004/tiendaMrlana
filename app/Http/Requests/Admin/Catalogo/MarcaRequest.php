<?php

namespace App\Http\Requests\Admin\Catalogo;

use App\Models\Marca;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MarcaRequest extends FormRequest
{
    use LimpiaTexto;

    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-catalogo') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->limpiarTextos(['nombre', 'slug']);

        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => Str::slug($this->input('slug')) ?: null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Marca|null $marca */
        $marca = $this->route('marca');
        $id = $marca?->id;

        return [
            'nombre' => ['required', 'string', 'min:2', 'max:150', Rule::unique('marcas', 'nombre')->ignore($id)],
            'slug' => ['nullable', 'string', 'max:170', Rule::unique('marcas', 'slug')->ignore($id)],
            'activa' => ['boolean'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'quitar_logo' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe una marca con ese nombre.',
            'slug.unique' => 'Ese identificador web ya está en uso.',
            'logo.max' => 'El logo debe pesar máximo 5 MB.',
        ];
    }
}
