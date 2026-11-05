<?php

namespace App\Http\Requests\Admin\Catalogo;

use App\Enums\ModalidadVenta;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edición rápida desde la cola de aprobación: precio, categoría y modalidad.
 */
class ProductoRapidoRequest extends FormRequest
{
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
            'precio' => ['nullable', 'numeric', 'min:0.01', 'max:9999999.99', 'decimal:0,2'],
            'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')],
            'modalidad_venta' => ['required', Rule::in(ModalidadVenta::values())],
            'tiempo_entrega_dias' => [
                Rule::requiredIf(fn () => $this->input('modalidad_venta') === ModalidadVenta::BajoPedido->value),
                'nullable', 'integer', 'min:1', 'max:365',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tiempo_entrega_dias' => 'tiempo de entrega',
            'categoria_id' => 'categoría',
            'modalidad_venta' => 'modalidad de venta',
        ];
    }
}
