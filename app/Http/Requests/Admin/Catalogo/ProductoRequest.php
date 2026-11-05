<?php

namespace App\Http\Requests\Admin\Catalogo;

use App\Enums\ModalidadVenta;
use App\Models\Producto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Alta/edición de producto. Las existencias NO se editan aquí (InventarioService),
 * salvo `stock_inicial` al crear, que se registra como movimiento de entrada.
 */
class ProductoRequest extends FormRequest
{
    use LimpiaTexto;

    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-catalogo') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->limpiarTextos([
            'nombre', 'sku', 'slug', 'descripcion', 'descripcion_larga', 'modelo', 'acabado', 'dimensiones',
            'observaciones', 'meta_titulo', 'meta_descripcion',
        ]);

        if (is_string($this->input('sku'))) {
            $this->merge(['sku' => Str::upper($this->input('sku'))]);
        }

        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => Str::slug($this->input('slug')) ?: null]);
        }

        if (is_array($this->input('atributos'))) {
            $this->merge([
                'atributos' => collect($this->input('atributos'))
                    ->filter(fn ($a) => is_array($a))
                    ->map(fn (array $a) => [
                        'nombre' => trim(strip_tags((string) ($a['nombre'] ?? ''))),
                        'valor' => trim(strip_tags((string) ($a['valor'] ?? ''))),
                    ])
                    ->reject(fn (array $a) => $a['nombre'] === '' && $a['valor'] === '')
                    ->values()
                    ->all(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Producto|null $producto */
        $producto = $this->route('producto');
        $id = $producto?->id;

        return [
            'nombre' => ['required', 'string', 'min:3', 'max:180'],
            'sku' => ['nullable', 'string', 'max:100', 'regex:/^[A-Z0-9][A-Z0-9._\-]*$/', Rule::unique('productos', 'sku')->ignore($id)],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('productos', 'slug')->ignore($id)],
            'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')],
            'marca_id' => ['nullable', 'integer', Rule::exists('marcas', 'id')],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'descripcion_larga' => ['nullable', 'string', 'max:20000'],
            'precio' => ['nullable', 'numeric', 'min:0.01', 'max:9999999.99', 'decimal:0,2'],
            'precio_comparacion' => ['nullable', 'numeric', 'min:0.01', 'max:9999999.99', 'decimal:0,2'],
            'costo' => ['nullable', 'numeric', 'min:0', 'max:9999999.99', 'decimal:0,2'],
            'modalidad_venta' => ['required', Rule::in(ModalidadVenta::values())],
            'tiempo_entrega_dias' => [
                Rule::requiredIf(fn () => $this->input('modalidad_venta') === ModalidadVenta::BajoPedido->value),
                'nullable', 'integer', 'min:1', 'max:365',
            ],
            'stock_minimo' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'stock_inicial' => $producto ? ['prohibited'] : ['nullable', 'integer', 'min:0', 'max:100000'],
            'peso' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'alto' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'ancho' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'largo' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'modelo' => ['nullable', 'string', 'max:120'],
            'acabado' => ['nullable', 'string', 'max:180'],
            'dimensiones' => ['nullable', 'string', 'max:180'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'atributos' => ['nullable', 'array', 'max:30'],
            'atributos.*.nombre' => ['required', 'string', 'max:60'],
            'atributos.*.valor' => ['required', 'string', 'max:255'],
            'meta_titulo' => ['nullable', 'string', 'max:180'],
            'meta_descripcion' => ['nullable', 'string', 'max:300'],
            'destacado' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $comparacion = $this->input('precio_comparacion');

                if ($comparacion === null || $comparacion === '' || $validator->errors()->has('precio_comparacion')) {
                    return;
                }

                $precio = $this->input('precio');

                if ($precio === null || $precio === '') {
                    $validator->errors()->add('precio_comparacion', 'Define primero el precio de venta para usar un precio de comparación.');
                } elseif ((float) $comparacion <= (float) $precio) {
                    $validator->errors()->add('precio_comparacion', 'El precio de comparación debe ser mayor que el precio de venta.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'precio_comparacion' => 'precio de comparación',
            'tiempo_entrega_dias' => 'tiempo de entrega',
            'stock_minimo' => 'existencia mínima',
            'stock_inicial' => 'existencia inicial',
            'categoria_id' => 'categoría',
            'marca_id' => 'marca',
            'modalidad_venta' => 'modalidad de venta',
            'meta_titulo' => 'título SEO',
            'meta_descripcion' => 'descripción SEO',
            'atributos.*.nombre' => 'nombre del atributo',
            'atributos.*.valor' => 'valor del atributo',
        ];
    }
}
