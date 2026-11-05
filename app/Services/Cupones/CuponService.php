<?php

namespace App\Services\Cupones;

use App\Exceptions\CuponInvalidoException;
use App\Models\Cupon;
use App\Models\CuponUso;
use App\Models\Pedido;
use App\Models\User;
use App\Services\Catalogo\PrecioService;
use Illuminate\Support\Facades\DB;

class CuponService
{
    public static function normalizarCodigo(?string $codigo): string
    {
        return mb_strtoupper(trim((string) $codigo));
    }

    /**
     * Valida el cupón contra el subtotal (ya con ofertas) y el usuario.
     *
     * @return array{cupon: Cupon, codigo: string, nombre: string, tipo: string, valor: float, descuento_aplicado: float}
     *
     * @throws CuponInvalidoException con mensaje legible
     */
    public function validar(string $codigo, float $subtotal, ?User $user = null): array
    {
        $codigo = self::normalizarCodigo($codigo);

        if ($codigo === '') {
            throw new CuponInvalidoException('Ingresa un código de cupón.');
        }

        $cupon = Cupon::query()->where('codigo', $codigo)->first();

        if (! $cupon || ! $cupon->activo) {
            throw new CuponInvalidoException('El cupón no existe o no está disponible.');
        }

        $ahora = now();

        if ($cupon->inicia_en && $cupon->inicia_en->gt($ahora)) {
            throw new CuponInvalidoException('El cupón todavía no está vigente.');
        }

        if ($cupon->termina_en && $cupon->termina_en->lt($ahora)) {
            throw new CuponInvalidoException('El cupón ya venció.');
        }

        if ($cupon->usos_maximos !== null && (int) $cupon->usos_actuales >= (int) $cupon->usos_maximos) {
            throw new CuponInvalidoException('El cupón ya alcanzó su límite de usos.');
        }

        if ($cupon->usos_por_usuario !== null) {
            if (! $user) {
                throw new CuponInvalidoException('Inicia sesión para usar este cupón.');
            }

            $usos = CuponUso::query()->where('cupon_id', $cupon->id)->where('user_id', $user->id)->count();

            if ($usos >= (int) $cupon->usos_por_usuario) {
                throw new CuponInvalidoException('Ya usaste este cupón el máximo de veces permitido.');
            }
        }

        if ($cupon->compra_minima !== null && round($subtotal, 2) < (float) $cupon->compra_minima) {
            throw new CuponInvalidoException('El cupón requiere una compra mínima de $'.number_format((float) $cupon->compra_minima, 2).' MXN.');
        }

        $descuento = $this->calcularDescuento($cupon, $subtotal);

        if ($descuento <= 0) {
            throw new CuponInvalidoException('El cupón no genera descuento para este carrito.');
        }

        return [
            'cupon' => $cupon,
            'codigo' => $cupon->codigo,
            'nombre' => $cupon->nombre,
            'tipo' => $cupon->tipo,
            'valor' => (float) $cupon->valor,
            'descuento_aplicado' => $descuento,
        ];
    }

    public function calcularDescuento(Cupon $cupon, float $subtotal): float
    {
        $valor = (float) $cupon->valor;

        $descuento = match (PrecioService::normalizarTipo((string) $cupon->tipo)) {
            'porcentaje' => $subtotal * (min(100, max(0, $valor)) / 100),
            'monto_fijo', 'montofijo' => $valor,
            default => 0.0,
        };

        return round(min(max(0, $subtotal), max(0, $descuento)), 2);
    }

    /**
     * Registra el uso al crear el pedido. Re-verifica el límite global con lock.
     *
     * @throws CuponInvalidoException
     */
    public function registrarUso(Cupon $cupon, Pedido $pedido, ?User $user = null): CuponUso
    {
        return DB::transaction(function () use ($cupon, $pedido, $user) {
            $bloqueado = Cupon::query()->whereKey($cupon->id)->lockForUpdate()->firstOrFail();

            if ($bloqueado->usos_maximos !== null && (int) $bloqueado->usos_actuales >= (int) $bloqueado->usos_maximos) {
                throw new CuponInvalidoException('El cupón ya alcanzó su límite de usos.');
            }

            $bloqueado->increment('usos_actuales');

            return CuponUso::create([
                'cupon_id' => $bloqueado->id,
                'pedido_id' => $pedido->id,
                'user_id' => $user?->id,
            ]);
        });
    }

    /**
     * Devuelve el uso del cupón cuando el pedido se cancela o vence.
     */
    public function liberarUso(Pedido $pedido): void
    {
        DB::transaction(function () use ($pedido) {
            $usos = CuponUso::query()->where('pedido_id', $pedido->id)->get();

            foreach ($usos as $uso) {
                $cupon = Cupon::query()->whereKey($uso->cupon_id)->lockForUpdate()->first();

                if ($cupon && (int) $cupon->usos_actuales > 0) {
                    $cupon->decrement('usos_actuales');
                }

                $uso->delete();
            }
        });
    }
}
