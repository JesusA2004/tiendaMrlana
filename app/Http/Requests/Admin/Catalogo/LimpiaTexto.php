<?php

namespace App\Http\Requests\Admin\Catalogo;

/**
 * Normaliza entradas de texto: quita etiquetas HTML y espacios sobrantes; vacío → null.
 */
trait LimpiaTexto
{
    /**
     * @param  list<string>  $campos
     */
    protected function limpiarTextos(array $campos): void
    {
        $limpios = [];

        foreach ($campos as $campo) {
            if (! $this->has($campo)) {
                continue;
            }

            $valor = $this->input($campo);

            if (is_string($valor)) {
                $valor = trim(strip_tags($valor));
                $limpios[$campo] = $valor === '' ? null : $valor;
            }
        }

        $this->merge($limpios);
    }
}
