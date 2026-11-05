<?php

namespace App\Data;

use App\Models\Direccion;

/**
 * Datos ya validados del checkout. Nunca contiene precios: el workflow recalcula todo.
 */
final readonly class DatosCheckout
{
    /**
     * @param  array<int|string, array{producto_id: int, cantidad: int}>  $lineas
     */
    public function __construct(
        public array $lineas,
        public string $metodoPago,
        public string $modalidadEntrega,
        public ?Direccion $direccion,
        public string $nombreCliente,
        public string $correoCliente,
        public ?string $telefonoCliente = null,
        public ?string $notasCliente = null,
        public ?string $cuponCodigo = null,
    ) {}
}
