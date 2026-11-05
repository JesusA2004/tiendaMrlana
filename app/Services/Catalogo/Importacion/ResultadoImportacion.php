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

    /**
     * Fase opcional (--imagenes / --publicar / --forzar-precios), un registro por modelo.
     *
     * @var list<array{sku: string, nombre: string, cambios: list<string>, avisos: list<string>, publicado: bool}>
     */
    public array $venta = [];

    public function __construct(public readonly bool $simulacion = false) {}

    /**
     * @param  list<string>  $campos
     */
    public function registrar(string $clave, string $sku, string $nombre, string $accion, array $campos = []): void
    {
        $this->productos[] = compact('clave', 'sku', 'nombre', 'accion', 'campos');
    }

    /**
     * @param  list<string>  $cambios
     * @param  list<string>  $avisos  motivos por los que no se publicó (o advertencias)
     */
    public function registrarVenta(string $sku, string $nombre, array $cambios, array $avisos = [], bool $publicado = false): void
    {
        $this->venta[] = compact('sku', 'nombre', 'cambios', 'avisos', 'publicado');
    }

    /**
     * @return list<array{sku: string, nombre: string, cambios: list<string>, avisos: list<string>, publicado: bool}>
     */
    public function noPublicados(): array
    {
        return array_values(array_filter($this->venta, fn (array $v) => $v['avisos'] !== []));
    }

    public function resumenVenta(): string
    {
        $prefijo = $this->simulacion ? '[Simulación] ' : '';
        $conCambios = count(array_filter($this->venta, fn (array $v) => $v['cambios'] !== []));
        $imagenes = count(array_filter($this->venta, fn (array $v) => preg_grep('/imagen/', $v['cambios']) !== []));
        $precios = count(array_filter($this->venta, fn (array $v) => preg_grep('/^precio /', $v['cambios']) !== []));

        return sprintf(
            '%sPreparación para venta: %d con cambios, %d sin cambios; %d precios asignados, %d con fotos nuevas, %d publicados ahora, %d con pendientes.',
            $prefijo,
            $conCambios,
            count($this->venta) - $conCambios,
            $precios,
            $imagenes,
            count(array_filter($this->venta, fn (array $v) => $v['publicado'])),
            count($this->noPublicados()),
        );
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
