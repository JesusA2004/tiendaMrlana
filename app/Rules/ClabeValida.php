<?php

namespace App\Rules;

use App\Services\Configuracion\ConfiguracionTienda;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CLABE interbancaria: 18 dígitos con dígito verificador válido.
 */
class ClabeValida implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! ConfiguracionTienda::clabeValida($value)) {
            $fail('La CLABE debe tener 18 dígitos y un dígito verificador válido.');
        }
    }
}
