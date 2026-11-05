<?php

namespace App\Services\Carrito;

use App\Enums\EstadoEnvio;
use App\Enums\ModalidadVenta;
use App\Exceptions\CuponInvalidoException;
use App\Exceptions\DominioException;
use App\Models\Direccion;
use App\Models\Producto;
use App\Models\User;
use App\Services\Catalogo\PrecioService;
use App\Services\Cupones\CuponService;
use App\Services\Envios\EnvioService;
use Illuminate\Contracts\Session\Session;

/**
 * Carrito en sesión. La sesión guarda SÓLO producto_id + cantidad (y el código de cupón);
 * todo importe se recalcula desde BD en cada resumen.
 */
class CarritoService
{
    public const SESION = 'carrito';

    public const SESION_CUPON = 'carrito_cupon';

    public const MAX_POR_LINEA = 99;

    public function __construct(
        private Session $session,
        private PrecioService $precios,
        private CuponService $cupones,
        private EnvioService $envios,
    ) {}

    /**
     * Líneas normalizadas de la sesión (tolera el formato viejo con subtotales).
     *
     * @return array<int, array{producto_id: int, cantidad: int}> indexado por producto_id
     */
    public function lineas(): array
    {
        $crudo = $this->session->get(self::SESION, []);
        $lineas = [];

        foreach ((array) $crudo as $clave => $linea) {
            $id = (int) (is_array($linea) ? ($linea['producto_id'] ?? $clave) : $clave);
            $cantidad = (int) (is_array($linea) ? ($linea['cantidad'] ?? 1) : $linea);

            if ($id > 0 && $cantidad > 0) {
                $lineas[$id] = ['producto_id' => $id, 'cantidad' => min(self::MAX_POR_LINEA, $cantidad)];
            }
        }

        return $lineas;
    }

    public function cantidadItems(): int
    {
        return (int) array_sum(array_column($this->lineas(), 'cantidad'));
    }

    public function estaVacio(): bool
    {
        return $this->lineas() === [];
    }

    /**
     * @throws DominioException si el producto no está disponible o excede existencias
     */
    public function agregar(int $productoId, int $cantidad = 1): Producto
    {
        $lineas = $this->lineas();
        $nueva = ($lineas[$productoId]['cantidad'] ?? 0) + max(1, $cantidad);

        $producto = $this->validarDisponible($productoId, $nueva);

        $lineas[$productoId] = ['producto_id' => $productoId, 'cantidad' => $nueva];
        $this->guardar($lineas);

        return $producto;
    }

    /**
     * Cantidad 0 elimina la línea.
     */
    public function actualizar(int $productoId, int $cantidad): ?Producto
    {
        $lineas = $this->lineas();

        if (! isset($lineas[$productoId])) {
            throw new DominioException('El producto no está en el carrito.');
        }

        if ($cantidad < 1) {
            $this->eliminar($productoId);

            return null;
        }

        $producto = $this->validarDisponible($productoId, $cantidad);
        $lineas[$productoId]['cantidad'] = $cantidad;
        $this->guardar($lineas);

        return $producto;
    }

    public function eliminar(int $productoId): void
    {
        $lineas = $this->lineas();
        unset($lineas[$productoId]);
        $this->guardar($lineas);
    }

    public function vaciar(): void
    {
        $this->session->forget([self::SESION, self::SESION_CUPON]);
    }

    public function codigoCupon(): ?string
    {
        $valor = $this->session->get(self::SESION_CUPON);

        if (is_array($valor)) {
            $valor = $valor['codigo'] ?? null;
        }

        $valor = CuponService::normalizarCodigo(is_string($valor) ? $valor : null);

        return $valor !== '' ? $valor : null;
    }

    /**
     * Valida y guarda el cupón contra el subtotal actual.
     *
     * @throws CuponInvalidoException
     */
    public function aplicarCupon(string $codigo, ?User $user = null): array
    {
        $resumen = $this->cotizar($this->lineas(), null, $user);

        if ($resumen['resumen']['subtotal'] <= 0) {
            throw new CuponInvalidoException('Tu carrito está vacío.');
        }

        $resultado = $this->cupones->validar($codigo, $resumen['resumen']['subtotal'], $user);
        $this->session->put(self::SESION_CUPON, $resultado['codigo']);

        return $resultado;
    }

    public function quitarCupon(): void
    {
        $this->session->forget(self::SESION_CUPON);
    }

    /**
     * Resumen del carrito de la sesión (recalculado desde BD).
     */
    public function resumen(?User $user = null, string $modalidad = 'envio_domicilio', ?Direccion $direccion = null): array
    {
        $resumen = $this->cotizar($this->lineas(), $this->codigoCupon(), $user, $modalidad, $direccion);

        // Un cupón que dejó de ser válido se retira de la sesión.
        if ($resumen['cupon_error'] !== null) {
            $this->quitarCupon();
        }

        return $resumen;
    }

    /**
     * Cálculo completo para cualquier conjunto de líneas (usado también por el checkout).
     *
     * @param  array<int|string, array{producto_id: int, cantidad: int}>  $lineas
     * @return array{
     *     items: list<array<string, mixed>>,
     *     cupon: array<string, mixed>|null,
     *     cupon_error: string|null,
     *     envio: array<string, mixed>,
     *     resumen: array<string, mixed>,
     *     errores: list<string>,
     *     puede_comprar: bool
     * }
     */
    public function cotizar(array $lineas, ?string $codigoCupon = null, ?User $user = null, string $modalidad = 'envio_domicilio', ?Direccion $direccion = null): array
    {
        $lineas = collect($lineas)
            ->map(fn ($l) => ['producto_id' => (int) $l['producto_id'], 'cantidad' => (int) $l['cantidad']])
            ->filter(fn ($l) => $l['producto_id'] > 0 && $l['cantidad'] > 0)
            ->keyBy('producto_id');

        $productos = Producto::query()
            ->with(['imagenes:id,producto_id,ruta,orden,principal', 'ofertas'])
            ->whereIn('id', $lineas->keys())
            ->get()
            ->keyBy('id');

        $precios = $this->precios->calcularMuchos($productos->values());
        $items = [];
        $errores = [];

        foreach ($lineas as $productoId => $linea) {
            /** @var Producto|null $producto */
            $producto = $productos->get($productoId);
            $cantidad = $linea['cantidad'];

            if (! $producto) {
                $errores[] = 'Un producto de tu carrito ya no existe.';

                continue;
            }

            $precio = $precios->get($producto->id);
            $error = $this->errorDeLinea($producto, $cantidad);
            $stock = max(0, (int) $producto->stock);
            $final = (float) ($precio['precio_final'] ?? 0);
            $original = (float) ($precio['precio_original'] ?? 0);

            if ($error) {
                $errores[] = $error;
            }

            $items[] = [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'slug' => $producto->slug,
                'sku' => $producto->sku,
                'imagen' => $producto->imagenPrincipalUrl(),
                'precio' => round($final, 2),
                'precio_original' => round($original, 2),
                'precio_comparacion' => $producto->precio_comparacion !== null ? round((float) $producto->precio_comparacion, 2) : null,
                'cantidad' => $cantidad,
                'stock' => $stock,
                'cantidad_maxima' => $producto->esBajoPedido() ? self::MAX_POR_LINEA : min(self::MAX_POR_LINEA, $stock),
                'modalidad_venta' => $producto->modalidad_venta ?? ModalidadVenta::Inventario->value,
                'tiempo_entrega_dias' => $producto->tiempo_entrega_dias,
                'subtotal_original' => round($original * $cantidad, 2),
                'subtotal' => round($final * $cantidad, 2),
                'descuento_oferta' => round(($original - $final) * $cantidad, 2),
                'tiene_oferta' => (bool) ($precio['tiene_oferta'] ?? false),
                'oferta' => $precio['oferta'] ?? null,
                'disponible' => $error === null,
                'error' => $error,
            ];
        }

        $validos = collect($items)->where('disponible', true);
        $subtotal = round((float) $validos->sum('subtotal'), 2);
        $subtotalOriginal = round((float) $validos->sum('subtotal_original'), 2);
        $descuentoOfertas = round((float) $validos->sum('descuento_oferta'), 2);

        $cupon = null;
        $cuponError = null;
        $descuentoCupon = 0.0;

        if ($codigoCupon && $subtotal > 0) {
            try {
                $resultado = $this->cupones->validar($codigoCupon, $subtotal, $user);
                $descuentoCupon = $resultado['descuento_aplicado'];
                $cupon = [
                    'id' => $resultado['cupon']->id,
                    'codigo' => $resultado['codigo'],
                    'nombre' => $resultado['nombre'],
                    'tipo' => $resultado['tipo'],
                    'valor' => $resultado['valor'],
                    'descuento_aplicado' => $descuentoCupon,
                ];
            } catch (CuponInvalidoException $e) {
                $cuponError = $e->getMessage();
            }
        }

        try {
            $envio = $this->envios->cotizar($direccion, $modalidad, round($subtotal - $descuentoCupon, 2), $validos);
        } catch (DominioException $e) {
            $errores[] = $e->getMessage();
            $envio = [
                'modalidad' => $modalidad, 'regla_id' => null, 'estado' => EstadoEnvio::PorCotizar->value, 'monto' => null,
                'permite_contra_entrega' => false, 'dias_estimados' => null, 'nombre' => null, 'mensaje' => $e->getMessage(),
            ];
        }

        $montoEnvio = round((float) ($envio['monto'] ?? 0), 2);
        $total = round(max(0, $subtotal - $descuentoCupon) + $montoEnvio, 2);

        return [
            'items' => $items,
            'cupon' => $cupon,
            'cupon_error' => $cuponError,
            'envio' => $envio,
            'resumen' => [
                'subtotal' => $subtotal,
                'subtotal_original' => $subtotalOriginal,
                'envio' => $montoEnvio,
                'envio_estado' => $envio['estado'],
                'envio_por_cotizar' => $envio['estado'] === EstadoEnvio::PorCotizar->value,
                'descuento' => round($descuentoOfertas + $descuentoCupon, 2),
                'descuento_ofertas' => $descuentoOfertas,
                'descuento_cupon' => $descuentoCupon,
                'total' => $total,
                'total_productos' => (int) $validos->sum('cantidad'),
            ],
            'errores' => array_values(array_unique($errores)),
            'puede_comprar' => $items !== [] && $errores === [] && $cuponError === null,
        ];
    }

    private function errorDeLinea(Producto $producto, int $cantidad): ?string
    {
        if (! $producto->estaPublicado()) {
            return "«{$producto->nombre}» ya no está disponible.";
        }

        if ($cantidad > self::MAX_POR_LINEA) {
            return 'Máximo '.self::MAX_POR_LINEA." piezas por producto («{$producto->nombre}»).";
        }

        if (! $producto->esBajoPedido() && (int) $producto->stock < $cantidad) {
            return (int) $producto->stock > 0
                ? "Sólo hay {$producto->stock} pieza(s) disponibles de «{$producto->nombre}»."
                : "«{$producto->nombre}» está agotado.";
        }

        return null;
    }

    private function validarDisponible(int $productoId, int $cantidad): Producto
    {
        $producto = Producto::query()->find($productoId);

        if (! $producto) {
            throw new DominioException('El producto no existe.');
        }

        $error = $this->errorDeLinea($producto, $cantidad);

        if ($error !== null) {
            throw new DominioException($error);
        }

        return $producto;
    }

    /** @param  array<int, array{producto_id: int, cantidad: int}>  $lineas */
    private function guardar(array $lineas): void
    {
        if ($lineas === []) {
            $this->vaciar();

            return;
        }

        $this->session->put(self::SESION, $lineas);
    }
}
