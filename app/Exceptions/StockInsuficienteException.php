<?php

namespace App\Exceptions;

class StockInsuficienteException extends DominioException
{
    public function __construct(
        public readonly int $productoId,
        public readonly string $productoNombre,
        public readonly int $disponible,
        public readonly int $solicitado,
    ) {
        parent::__construct($disponible > 0
            ? "Sólo hay {$disponible} pieza(s) disponibles de «{$productoNombre}»."
            : "«{$productoNombre}» está agotado.");
    }
}
