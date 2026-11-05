<?php

namespace App\Support;

/**
 * Sanitiza texto capturado en el panel: sin HTML ni caracteres de control.
 * Los saltos de línea se conservan (el front los muestra como texto con whitespace-pre-line).
 */
final class TextoPlano
{
    public static function limpiar(?string $texto, bool $multilinea = true): ?string
    {
        if ($texto === null) {
            return null;
        }

        $texto = strip_tags($texto);
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        // Quita caracteres de control salvo salto de línea y tabulador.
        $texto = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $texto);

        if (! $multilinea) {
            $texto = (string) preg_replace('/\s+/u', ' ', $texto);
        } else {
            $texto = (string) preg_replace("/\n{3,}/", "\n\n", $texto);
        }

        $texto = trim($texto);

        return $texto === '' ? null : $texto;
    }

    public static function linea(?string $texto): string
    {
        return (string) self::limpiar($texto, false);
    }
}
