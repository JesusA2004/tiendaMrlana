<?php

namespace App\Http\Requests\Admin\Catalogo;

use App\Models\Categoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CategoriaRequest extends FormRequest
{
    use LimpiaTexto;

    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-catalogo') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->limpiarTextos(['nombre', 'slug', 'descripcion']);

        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => Str::slug($this->input('slug')) ?: null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Categoria|null $categoria */
        $categoria = $this->route('categoria');
        $id = $categoria?->id;

        return [
            'nombre' => ['required', 'string', 'min:2', 'max:150', Rule::unique('categorias', 'nombre')->ignore($id)],
            'slug' => ['nullable', 'string', 'max:170', Rule::unique('categorias', 'slug')->ignore($id)],
            'categoria_padre_id' => ['nullable', 'integer', Rule::exists('categorias', 'id'), Rule::notIn(array_filter([$id]))],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'activa' => ['boolean'],
            'orden' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'imagen' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'quitar_imagen' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var Categoria|null $categoria */
                $categoria = $this->route('categoria');
                $padreId = $this->input('categoria_padre_id');

                if (! $categoria || ! $padreId) {
                    return;
                }

                // Evita ciclos: el padre no puede ser una subcategoría de esta categoría.
                $actual = Categoria::find($padreId);
                $vistos = [];

                while ($actual && ! in_array($actual->id, $vistos, true)) {
                    if ($actual->id === $categoria->id) {
                        $validator->errors()->add('categoria_padre_id', 'La categoría padre no puede ser una de sus subcategorías.');

                        return;
                    }

                    $vistos[] = $actual->id;
                    $actual = $actual->categoria_padre_id ? Categoria::find($actual->categoria_padre_id) : null;
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['categoria_padre_id' => 'categoría padre'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.unique' => 'Ya existe una categoría con ese nombre.',
            'slug.unique' => 'Ese identificador web ya está en uso.',
            'imagen.max' => 'La imagen debe pesar máximo 5 MB.',
        ];
    }
}
