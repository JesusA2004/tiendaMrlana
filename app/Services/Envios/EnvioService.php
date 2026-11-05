<?php

namespace App\Services\Envios;

use App\Enums\EstadoEnvio;
use App\Enums\MetodoPagoClave;
use App\Enums\ModalidadEntrega;
use App\Enums\TipoReglaEnvio;
use App\Exceptions\DominioException;
use App\Models\Direccion;
use App\Models\MetodoPago;
use App\Models\ReglaEnvio;
use App\Services\Configuracion\ConfiguracionTienda;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Cálculo del envío a partir de reglas administrables. Nunca inventa costos:
 * sin regla aplicable → estado `por_cotizar` (monto null).
 */
class EnvioService
{
    /** Texto del checkout cuando el envío se cotiza por separado (decisión del dueño). */
    public const MENSAJE_COTIZACION = 'El costo de envío se cotiza por separado. Te enviaremos opciones de paquetería con precio y tiempo para que elijas.';

    public function __construct(private ConfiguracionTienda $config) {}

    /**
     * @param  Direccion|array{estado?: ?string, codigo_postal?: ?string}|null  $destino
     * @param  Collection<int, mixed>|null  $items  reservado para reglas por peso/volumen
     * @return array{modalidad: string, regla_id: int|null, estado: string, monto: float|null, permite_contra_entrega: bool, dias_estimados: string|null, nombre: string|null, mensaje: string|null}
     */
    public function cotizar(Direccion|array|null $destino, string $modalidad, float $subtotal, ?Collection $items = null): array
    {
        if ($modalidad === ModalidadEntrega::RecogerEnTienda->value) {
            return $this->cotizarRecoger();
        }

        if ($modalidad !== ModalidadEntrega::EnvioDomicilio->value) {
            throw new DominioException('Modalidad de entrega no válida.');
        }

        $estado = $destino instanceof Direccion ? $destino->estado : ($destino['estado'] ?? null);
        $cp = $destino instanceof Direccion ? $destino->codigo_postal : ($destino['codigo_postal'] ?? null);

        if ($destino === null || (blank($estado) && blank($cp))) {
            return $this->porCotizar(null, 'El costo de envío se calculará al elegir tu dirección.');
        }

        $regla = ReglaEnvio::query()
            ->activas()
            ->where('tipo', '!=', TipoReglaEnvio::RecogerEnTienda->value)
            ->get()
            ->first(fn (ReglaEnvio $r) => $r->aplicaA($estado, $cp));

        if (! $regla) {
            return $this->porCotizar(null, self::MENSAJE_COTIZACION);
        }

        if ($regla->tipo === TipoReglaEnvio::CotizacionManual->value || $regla->monto === null) {
            return $this->porCotizar($regla, self::MENSAJE_COTIZACION);
        }

        $monto = round((float) $regla->monto, 2);

        if ($regla->gratis_desde !== null && round($subtotal, 2) >= (float) $regla->gratis_desde) {
            $monto = 0.0;
        }

        return [
            'modalidad' => ModalidadEntrega::EnvioDomicilio->value,
            'regla_id' => $regla->id,
            'estado' => EstadoEnvio::Calculado->value,
            'monto' => $monto,
            'permite_contra_entrega' => (bool) $regla->permite_contra_entrega,
            'dias_estimados' => $regla->dias_estimados,
            'nombre' => $regla->nombre,
            'mensaje' => $monto == 0.0 ? 'Envío sin costo para este pedido.' : null,
        ];
    }

    /** ¿Está habilitado recoger en tienda (regla activa o dirección de recolección configurada)? */
    public function recogerDisponible(): bool
    {
        return $this->reglaRecoger() !== null || filled($this->config->recoger()['direccion']);
    }

    /** @return list<array{value: string, label: string, disponible: bool}> */
    public function modalidadesDisponibles(): array
    {
        return [
            ['value' => ModalidadEntrega::EnvioDomicilio->value, 'label' => ModalidadEntrega::EnvioDomicilio->label(), 'disponible' => true],
            ['value' => ModalidadEntrega::RecogerEnTienda->value, 'label' => ModalidadEntrega::RecogerEnTienda->label(), 'disponible' => $this->recogerDisponible()],
        ];
    }

    /**
     * ¿Se permite pago contra entrega para esta cotización/total/destino?
     * Requiere: método activo, regla con permite_contra_entrega, estados permitidos y monto máximo del método.
     */
    public function permiteContraEntrega(array $cotizacion, float $total, ?string $estadoDestino): bool
    {
        if (($cotizacion['modalidad'] ?? null) !== ModalidadEntrega::EnvioDomicilio->value
            || empty($cotizacion['permite_contra_entrega'])
            || ($cotizacion['estado'] ?? null) === EstadoEnvio::PorCotizar->value) {
            return false;
        }

        $metodo = MetodoPago::query()->where('clave', MetodoPagoClave::EfectivoContraEntrega->value)->first();

        if (! $metodo || ! $metodo->activo) {
            return false;
        }

        $conf = (array) ($metodo->configuracion ?? []);
        $estados = array_filter((array) ($conf['estados'] ?? []));

        if ($estados !== []) {
            $normal = fn (?string $s) => mb_strtolower(trim(Str::ascii((string) $s)));

            if (! in_array($normal($estadoDestino), array_map($normal, $estados), true)) {
                return false;
            }
        }

        if (isset($conf['monto_maximo']) && is_numeric($conf['monto_maximo']) && $total > (float) $conf['monto_maximo']) {
            return false;
        }

        return true;
    }

    private function cotizarRecoger(): array
    {
        $regla = $this->reglaRecoger();

        if (! $regla && blank($this->config->recoger()['direccion'])) {
            throw new DominioException('La opción de recoger en tienda no está disponible por ahora.');
        }

        return [
            'modalidad' => ModalidadEntrega::RecogerEnTienda->value,
            'regla_id' => $regla?->id,
            'estado' => EstadoEnvio::Calculado->value,
            'monto' => round((float) ($regla?->monto ?? 0), 2),
            'permite_contra_entrega' => false,
            'dias_estimados' => $regla?->dias_estimados,
            'nombre' => $regla?->nombre ?? ModalidadEntrega::RecogerEnTienda->label(),
            'mensaje' => null,
        ];
    }

    private function porCotizar(?ReglaEnvio $regla, string $mensaje): array
    {
        return [
            'modalidad' => ModalidadEntrega::EnvioDomicilio->value,
            'regla_id' => $regla?->id,
            'estado' => EstadoEnvio::PorCotizar->value,
            'monto' => null,
            'permite_contra_entrega' => (bool) ($regla?->permite_contra_entrega ?? false),
            'dias_estimados' => $regla?->dias_estimados,
            'nombre' => $regla?->nombre,
            'mensaje' => $mensaje,
        ];
    }

    private function reglaRecoger(): ?ReglaEnvio
    {
        return ReglaEnvio::query()->activas()->where('tipo', TipoReglaEnvio::RecogerEnTienda->value)->first();
    }
}
