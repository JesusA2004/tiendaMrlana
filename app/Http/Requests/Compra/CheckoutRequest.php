<?php

namespace App\Http\Requests\Compra;

use App\Enums\MetodoPagoClave;
use App\Enums\ModalidadEntrega;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Datos del checkout. Nunca incluye precios: el servidor recalcula todo.
 * Sólo se aceptan los métodos sin tarjeta; si el método está inactivo lo rechaza el workflow.
 */
class CheckoutRequest extends FormRequest
{
    public const METODOS = [
        MetodoPagoClave::Transferencia->value,
        MetodoPagoClave::EfectivoPresencial->value,
        MetodoPagoClave::EfectivoContraEntrega->value,
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'correo_cliente' => is_string($this->correo_cliente) ? trim(mb_strtolower($this->correo_cliente)) : $this->correo_cliente,
            'usar_direccion_existente' => $this->boolean('usar_direccion_existente'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $nueva = fn () => $this->input('modalidad_entrega') === ModalidadEntrega::EnvioDomicilio->value
            && ! $this->boolean('usar_direccion_existente');

        return [
            'checkout_token' => ['required', 'string', 'max:64'],

            'nombre_cliente' => ['required', 'string', 'max:180'],
            'correo_cliente' => ['required', 'email', 'max:180'],
            'telefono_cliente' => ['required', 'string', 'regex:/^[0-9 +()\-]{10,20}$/'],
            'notas_cliente' => ['nullable', 'string', 'max:1000'],

            'metodo_pago' => ['required', 'string', Rule::in(self::METODOS)],
            'modalidad_entrega' => ['required', 'string', Rule::in(ModalidadEntrega::values())],

            'usar_direccion_existente' => ['boolean'],
            'direccion_id' => [
                Rule::requiredIf(fn () => $this->input('modalidad_entrega') === ModalidadEntrega::EnvioDomicilio->value && $this->boolean('usar_direccion_existente')),
                'nullable',
                'integer',
                Rule::exists('direcciones', 'id')->where('user_id', $this->user()?->id),
            ],

            'alias' => ['nullable', 'string', 'max:80'],
            'nombre_receptor' => [Rule::requiredIf($nueva), 'nullable', 'string', 'max:180'],
            'telefono' => [Rule::requiredIf($nueva), 'nullable', 'string', 'regex:/^[0-9 +()\-]{10,20}$/'],
            'calle' => [Rule::requiredIf($nueva), 'nullable', 'string', 'max:180'],
            'numero_exterior' => [Rule::requiredIf($nueva), 'nullable', 'string', 'max:50'],
            'numero_interior' => ['nullable', 'string', 'max:50'],
            'colonia' => [Rule::requiredIf($nueva), 'nullable', 'string', 'max:120'],
            'municipio' => [Rule::requiredIf($nueva), 'nullable', 'string', 'max:120'],
            'estado' => [Rule::requiredIf($nueva), 'nullable', 'string', 'max:120'],
            'codigo_postal' => [Rule::requiredIf($nueva), 'nullable', 'string', 'regex:/^\d{5}$/'],
            'referencias' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'checkout_token.required' => 'Tu sesión de compra expiró. Revisa tu pedido y vuelve a confirmar.',
            'metodo_pago.required' => 'Elige cómo quieres pagar.',
            'metodo_pago.in' => 'Ese método de pago no está disponible. Por ahora aceptamos transferencia y efectivo.',
            'modalidad_entrega.required' => 'Elige cómo quieres recibir tu pedido.',
            'direccion_id.required' => 'Elige una de tus direcciones guardadas.',
            'direccion_id.exists' => 'Elige una de tus direcciones guardadas.',
            'telefono_cliente.regex' => 'Escribe un teléfono de 10 dígitos.',
            'telefono.regex' => 'Escribe un teléfono de 10 dígitos.',
            'codigo_postal.regex' => 'El código postal debe tener 5 dígitos.',
            'required' => 'Este campo es obligatorio.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nombre_cliente' => 'nombre',
            'correo_cliente' => 'correo',
            'telefono_cliente' => 'teléfono',
            'nombre_receptor' => 'quién recibe',
            'numero_exterior' => 'número exterior',
            'codigo_postal' => 'código postal',
        ];
    }
}
