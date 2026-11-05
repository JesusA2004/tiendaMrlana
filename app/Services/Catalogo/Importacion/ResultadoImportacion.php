<?php

namespace App\Services\Catalogo\Importacion;

/**
 * Resumen de una corrida de importación del catálogo.
 */
final class ResultadoImportacion
{
    public const CREADO = 'creado';

    public const ACTUALIZADO = 'actualizado';

    public const SIN_CAMBIOS = 'sin_cambios';

    /** @var list<array{clave: string, sku: string, nombre: string, accion: string, campos: list<string>}> */
    public array $productos = [];

    /** @var list<string> */
    public array $categoriasCreadas = [];

    public function __construct(public readonly bool $simulacion = false) {}

    /**
     * @param  list<string>  $campos
     */
    public function registrar(string $clave, string $sku, string $nombre, string $accion, array $campos = []): void
    {
        $this->productos[] = compact('clave', 'sku', 'nombre', 'accion', 'campos');
    }

    public function total(string $accion): int
    {
        return count(array_filter($this->productos, fn (array $p) => $p['accion'] === $accion));
    }

    public function creados(): int
    {
        return $this->total(self::CREADO);
    }

    public function actualizados(): int
    {
        return $this->total(self::ACTUALIZADO);
    }

    public function sinCambios(): int
    {
        return $this->total(self::SIN_CAMBIOS);
    }

    public function resumen(): string
    {
        $prefijo = $this->simulacion ? '[Simulación] ' : '';

        return sprintf(
            '%s%d creados, %d actualizados, %d sin cambios (%d modelos). Categorías nuevas: %d.',
            $prefijo,
            $this->creados(),
            $this->actualizados(),
            $this->sinCambios(),
            count($this->productos),
            count($this->categoriasCreadas),
        );
    }
}
