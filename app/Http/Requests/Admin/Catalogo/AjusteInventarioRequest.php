<?php

namespace App\Http\Requests\Admin\Catalogo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Movimiento manual de inventario: entrada, salida, ajuste (+/-) o conteo físico (establecer).
 */
class AjusteInventarioRequest extends FormRequest
{
    use LimpiaTexto;

    public const OPERACIONES = ['entrada', 'salida', 'ajuste', 'establecer'];

    public function authorize(): bool
    {
        return $this->user()?->can('gestionar-catalogo') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->limpiarTextos(['motivo']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cantidad = match ($this->input('operacion')) {
            'ajuste' => ['required', 'integer', 'between:-100000,100000', 'not_in:0'],
            'establecer' => ['required', 'integer', 'min:0', 'max:100000'],
            default => ['required', 'integer', 'min:1', 'max:100000'],
        };

        return [
            'producto_id' => ['required', 'integer', Rule::exists('productos', 'id')],
            'operacion' => ['required', Rule::in(self::OPERACIONES)],
            'cantidad' => $cantidad,
            'motivo' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['producto_id' => 'producto', 'operacion' => 'tipo de movimiento'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['cantidad.not_in' => 'El ajuste no puede ser cero.'];
    }
}
