<?php

namespace App\Http\Requests\Compra;

use App\Models\PagoComprobante;
use Illuminate\Foundation\Http\FormRequest;

class SubirComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('subirComprobante', $this->route('pedido')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'archivo' => [
                'required',
                'file',
                'mimes:'.implode(',', PagoComprobante::EXTENSIONES),
                'mimetypes:'.implode(',', PagoComprobante::MIMES),
                'max:'.PagoComprobante::MAX_KB,
            ],
            'referencia' => ['nullable', 'string', 'max:120'],
            'fecha_transferencia' => ['nullable', 'date', 'before_or_equal:today'],
            'monto_declarado' => ['nullable', 'numeric', 'min:0.01', 'max:99999999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'archivo.required' => 'Adjunta tu comprobante de pago.',
            'archivo.file' => 'No pudimos leer el archivo. Intenta de nuevo.',
            'archivo.mimes' => 'El comprobante debe ser una imagen JPG, PNG o WEBP, o un PDF.',
            'archivo.mimetypes' => 'El comprobante debe ser una imagen JPG, PNG o WEBP, o un PDF.',
            'archivo.max' => 'El comprobante no puede pesar más de 5 MB.',
            'archivo.uploaded' => 'No pudimos subir el archivo (máximo 5 MB).',
            'fecha_transferencia.before_or_equal' => 'La fecha de la transferencia no puede ser futura.',
            'monto_declarado.numeric' => 'Escribe el monto sólo con números.',
            'monto_declarado.min' => 'El monto debe ser mayor a cero.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'archivo' => 'comprobante',
            'referencia' => 'referencia',
            'fecha_transferencia' => 'fecha de la transferencia',
            'monto_declarado' => 'monto transferido',
        ];
    }
}
