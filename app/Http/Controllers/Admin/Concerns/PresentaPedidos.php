<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoLogistico;
use App\Enums\EstadoPago;
use App\Enums\EstadoPagoRegistro;
use App\Enums\EstatusPedido;
use App\Enums\MetodoPagoClave;
use App\Enums\ModalidadEntrega;
use App\Models\Pago;
use App\Models\PagoComprobante;
use App\Models\Paqueteria;
use App\Models\Pedido;
use App\Models\PedidoOpcionEnvio;

/**
 * Serialización compartida de pedidos/pagos para el panel (sin exponer rutas de disco).
 */
trait PresentaPedidos
{
    /** @return array{value: string, label: string, color: string}|null */
    protected function etiqueta(string $enum, ?string $valor): ?array
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        /** @var class-string<EstatusPedido|EstadoPago|EstadoLogistico|EstadoEnvio|EstadoPagoRegistro|MetodoPagoClave|ModalidadEntrega> $enum */
        $caso = $enum::tryFrom($valor);

        return $caso ? $caso->toArray() : ['value' => $valor, 'label' => ucfirst(str_replace('_', ' ', $valor)), 'color' => 'neutral'];
    }

    /** @return array<string, mixed> */
    protected function pedidoFila(Pedido $pedido): array
    {
        return [
            'id' => $pedido->id,
            'folio' => $pedido->folio,
            'nombre_cliente' => $pedido->nombre_cliente,
            'correo_cliente' => $pedido->correo_cliente,
            'telefono_cliente' => $pedido->telefono_cliente,
            'total' => (float) $pedido->total,
            'envio' => (float) $pedido->envio,
            'subtotal' => (float) $pedido->subtotal,
            'saldo_pendiente' => (float) $pedido->saldo_pendiente,
            'estatus' => $this->etiqueta(EstatusPedido::class, $pedido->estatus),
            'estado_pago' => $this->etiqueta(EstadoPago::class, $pedido->estado_pago),
            'estado_logistico' => $this->etiqueta(EstadoLogistico::class, $pedido->estado_logistico),
            'envio_estado' => $this->etiqueta(EstadoEnvio::class, $pedido->envio_estado),
            'metodo_pago' => $this->etiqueta(MetodoPagoClave::class, $pedido->metodo_pago),
            'modalidad_entrega' => $this->etiqueta(ModalidadEntrega::class, $pedido->modalidad_entrega),
            'items_count' => $pedido->items_count ?? null,
            'paqueteria' => $pedido->paqueteria,
            'numero_guia' => $pedido->numero_guia,
            'fecha_envio' => $pedido->fecha_envio?->toDateString(),
            'vence_en' => $pedido->vence_en?->toIso8601String(),
            'enviado_en' => $pedido->enviado_en?->toIso8601String(),
            'created_at' => $pedido->created_at?->toIso8601String(),
            'updated_at' => $pedido->updated_at?->toIso8601String(),
            'destino' => $this->destino($pedido),
        ];
    }

    /** "Municipio, Estado · CP" desde la copia del pedido (o la dirección cargada). */
    protected function destino(Pedido $pedido): ?string
    {
        $d = is_array($pedido->direccion_envio) && $pedido->direccion_envio !== []
            ? $pedido->direccion_envio
            : ($pedido->relationLoaded('direccion') ? $pedido->direccion?->copiaParaPedido() : null);

        if (! $d) {
            return null;
        }

        return trim(($d['municipio'] ?? '').', '.($d['estado'] ?? '').' · CP '.($d['codigo_postal'] ?? ''), ' ,·') ?: null;
    }

    /**
     * Opciones de envío del pedido para el panel (incluye quién cotizó).
     *
     * @return list<array<string, mixed>>
     */
    protected function opcionesEnvioDatos(Pedido $pedido): array
    {
        return PedidoOpcionEnvio::presentarLista($pedido->opcionesEnvio);
    }

    /**
     * Paqueterías activas para los selectores del panel.
     *
     * @return list<array{id: int, nombre: string, rastreo: bool}>
     */
    protected function paqueteriasActivas(): array
    {
        return Paqueteria::query()->activas()->get(['id', 'nombre', 'url_rastreo'])
            ->map(fn (Paqueteria $p) => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'rastreo' => $p->url_rastreo !== null && str_contains($p->url_rastreo, Paqueteria::MARCADOR_GUIA),
            ])->values()->all();
    }

    /** @return array<string, mixed> */
    protected function pagoDatos(Pago $pago): array
    {
        return [
            'id' => $pago->id,
            'pedido_id' => $pago->pedido_id,
            'tipo' => $this->etiqueta(MetodoPagoClave::class, $pago->tipo ?? $pago->metodoPago?->clave),
            'estatus' => $this->etiqueta(EstadoPagoRegistro::class, $pago->estatus),
            'monto' => (float) $pago->monto,
            'monto_recibido' => $pago->monto_recibido !== null ? (float) $pago->monto_recibido : null,
            'referencia_cliente' => $pago->referencia_cliente,
            'referencia_externa' => $pago->referencia_externa,
            'motivo_rechazo' => $pago->motivo_rechazo,
            'notas_internas' => $pago->notas_internas,
            'pagado_en' => $pago->pagado_en?->toIso8601String(),
            'revisado_en' => $pago->revisado_en?->toIso8601String(),
            'revisado_por' => $pago->relationLoaded('revisadoPor') ? $pago->revisadoPor?->name : null,
            'created_at' => $pago->created_at?->toIso8601String(),
            'es_historico' => $pago->tipo === MetodoPagoClave::Openpay->value
                || ($pago->relationLoaded('metodoPago') && $pago->metodoPago?->clave === MetodoPagoClave::Openpay->value),
            'comprobantes' => $pago->relationLoaded('comprobantes')
                ? $pago->comprobantes->sortByDesc('id')->values()->map(fn (PagoComprobante $c) => $this->comprobanteDatos($c))->all()
                : [],
        ];
    }

    /** @return array<string, mixed> */
    protected function comprobanteDatos(PagoComprobante $c): array
    {
        return [
            'id' => $c->id,
            'nombre_original' => $c->nombre_original,
            'mime' => $c->mime,
            'es_pdf' => $c->mime === 'application/pdf',
            'tamano' => (int) $c->tamano,
            'referencia' => $c->referencia,
            'fecha_transferencia' => $c->fecha_transferencia?->toDateString(),
            'monto_declarado' => $c->monto_declarado !== null ? (float) $c->monto_declarado : null,
            'estado' => $c->estado,
            'created_at' => $c->created_at?->toIso8601String(),
            'url' => route('admin.comprobantes.show', $c),
            'descargar_url' => route('admin.comprobantes.descargar', $c),
        ];
    }

    /** Opciones de filtros para el listado. */
    protected function opcionesFiltros(): array
    {
        return [
            'estatus' => EstatusPedido::options(),
            'estado_pago' => EstadoPago::options(),
            'estado_logistico' => EstadoLogistico::options(),
            'metodo_pago' => MetodoPagoClave::options(),
        ];
    }
}
