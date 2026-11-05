<?php

namespace App\Http\Controllers\Cliente\Concerns;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoLogistico;
use App\Enums\EstadoPago;
use App\Enums\EstatusPedido;
use App\Enums\MetodoPagoClave;
use App\Enums\ModalidadEntrega;
use App\Models\Direccion;
use App\Models\MetodoPago;
use App\Models\Pago;
use App\Models\PagoComprobante;
use App\Models\Pedido;
use App\Models\PedidoEstatusHistorial;
use App\Models\PedidoItem;
use App\Models\PedidoOpcionEnvio;
use App\Services\Configuracion\ConfiguracionTienda;

/**
 * Serialización del pedido para el cliente (página de gracias, mis pedidos y PDF).
 * Nunca expone notas internas, rutas de archivo ni historial no visible al cliente.
 */
trait PresentaPedido
{
    /** @return array<int|string, mixed> Relaciones necesarias para presentarPedido(). */
    protected function relacionesPedido(): array
    {
        return [
            'items.producto',
            'direccion',
            'opcionesEnvio',
            'pagos.comprobantes',
            'historial' => fn ($q) => $q->where('visible_cliente', true)->oldest('id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentarPedido(Pedido $pedido, bool $incluirComprobantes = true): array
    {
        $pedido->loadMissing($this->relacionesPedido());

        /** @var Pago|null $pago */
        $pago = $pedido->pagos->sortByDesc('id')->first();

        return [
            'id' => $pedido->id,
            'folio' => $pedido->folio,
            'estatus' => $this->info(EstatusPedido::class, $pedido->estatus),
            'estado_pago' => $this->info(EstadoPago::class, $pedido->estado_pago),
            'estado_logistico' => $this->info(EstadoLogistico::class, $pedido->estado_logistico),
            'metodo_pago' => $pedido->metodo_pago,
            'metodo_pago_label' => MetodoPagoClave::labelDe($pedido->metodo_pago) ?? 'Sin definir',
            'modalidad_entrega' => $pedido->modalidad_entrega,
            'modalidad_entrega_label' => ModalidadEntrega::labelDe($pedido->modalidad_entrega),
            'envio_estado' => $pedido->envio_estado,
            // true mientras el total no sea final (por cotizar o esperando que el cliente elija).
            'envio_por_cotizar' => $pedido->envioPendiente(),
            'envio_esperando_eleccion' => $pedido->esperaEleccionEnvio(),
            'opciones_envio' => PedidoOpcionEnvio::presentarLista($pedido->opcionesEnvio),
            'url_rastreo' => $pedido->urlRastreo(),
            'es_terminal' => $pedido->esTerminal(),
            'moneda' => $pedido->moneda ?: 'MXN',
            'subtotal' => (float) $pedido->subtotal,
            'descuento' => (float) $pedido->descuento,
            // El subtotal ya trae las ofertas aplicadas; esto es lo que resta del total (cupón).
            'descuento_cupon' => $cupon = max(0, round((float) $pedido->subtotal + (float) $pedido->envio - (float) $pedido->total, 2)),
            'ahorro_ofertas' => max(0, round((float) $pedido->descuento - $cupon, 2)),
            'envio' => (float) $pedido->envio,
            'total' => (float) $pedido->total,
            'saldo_pendiente' => (float) $pedido->saldo_pendiente,
            'cupon_codigo' => $pedido->cupon_codigo,
            'nombre_cliente' => $pedido->nombre_cliente,
            'correo_cliente' => $pedido->correo_cliente,
            'telefono_cliente' => $pedido->telefono_cliente,
            'notas_cliente' => $pedido->notas_cliente,
            'paqueteria' => $pedido->paqueteria,
            'numero_guia' => $pedido->numero_guia,
            'fecha_envio' => $pedido->fecha_envio?->toDateString(),
            'motivo_cancelacion' => $pedido->motivo_cancelacion,
            'vence_en' => $pedido->vence_en?->toIso8601String(),
            'pagado_en' => $pedido->pagado_en?->toIso8601String(),
            'enviado_en' => $pedido->enviado_en?->toIso8601String(),
            'entregado_en' => $pedido->entregado_en?->toIso8601String(),
            'cancelado_en' => $pedido->cancelado_en?->toIso8601String(),
            'created_at' => $pedido->created_at?->toIso8601String(),
            'items' => $pedido->items->map(fn (PedidoItem $item) => [
                'id' => $item->id,
                'nombre' => $item->nombre,
                'sku' => $item->sku,
                'slug' => $item->producto?->slug,
                'imagen' => $item->producto?->imagenPrincipalUrl(),
                'cantidad' => (int) $item->cantidad,
                'precio_unitario' => (float) $item->precio_unitario,
                'subtotal' => (float) $item->subtotal,
            ])->values()->all(),
            'direccion' => ($copia = $pedido->direccionEntrega()) ? $this->presentarDireccion($copia) : null,
            'pago' => $pago ? [
                'id' => $pago->id,
                'estatus' => $pago->estatus,
                'monto' => (float) $pago->monto,
                'motivo_rechazo' => $pago->motivo_rechazo,
                'referencia_cliente' => $pago->referencia_cliente,
            ] : null,
            'comprobantes' => $incluirComprobantes ? $this->presentarComprobantes($pedido) : [],
            'historial' => $pedido->historial
                ->where('visible_cliente', true)
                ->sortByDesc('id')
                ->map(fn (PedidoEstatusHistorial $h) => [
                    'id' => $h->id,
                    'tipo' => $h->tipo,
                    'estatus' => EstatusPedido::labelDe($h->estatus),
                    'comentario' => $h->comentario,
                    'created_at' => $h->created_at?->toIso8601String(),
                ])->values()->all(),
            'linea_pago' => $this->lineaPago($pedido),
            'linea_logistica' => $this->lineaLogistica($pedido),
        ];
    }

    /** @return list<array<string, mixed>> */
    protected function presentarComprobantes(Pedido $pedido): array
    {
        return $pedido->pagos
            ->flatMap(fn (Pago $p) => $p->comprobantes->map(fn (PagoComprobante $c) => [$p, $c]))
            ->sortByDesc(fn (array $par) => $par[1]->id)
            ->map(fn (array $par) => [
                'id' => $par[1]->id,
                'nombre' => $par[1]->nombre_original,
                'mime' => $par[1]->mime,
                'tamano' => (int) $par[1]->tamano,
                'referencia' => $par[1]->referencia,
                'fecha_transferencia' => $par[1]->fecha_transferencia?->toDateString(),
                'monto_declarado' => $par[1]->monto_declarado !== null ? (float) $par[1]->monto_declarado : null,
                'estado' => $par[1]->estado,
                'motivo_rechazo' => $par[1]->estado === PagoComprobante::ESTADO_RECHAZADO ? $par[0]->motivo_rechazo : null,
                'created_at' => $par[1]->created_at?->toIso8601String(),
                'url' => route('cliente.pedidos.comprobantes.descargar', [$pedido, $par[1]]),
            ])->values()->all();
    }

    /**
     * Instrucciones de pago. Los datos bancarios sólo se entregan al DUEÑO del pedido
     * (y en su correo de instrucciones), nunca se inventan: si faltan, `configurado=false`.
     *
     * @return array<string, mixed>
     */
    protected function instruccionesPago(Pedido $pedido, bool $esDueno, ConfiguracionTienda $config): array
    {
        $metodo = $pedido->metodo_pago ? MetodoPago::query()->where('clave', $pedido->metodo_pago)->first() : null;
        $transferencia = $pedido->metodo_pago === MetodoPagoClave::Transferencia->value;
        // Sin total final (por cotizar o esperando elección de envío) no hay instrucciones ni comprobante.
        $porCotizar = $pedido->envioPendiente();
        $pagoAbierto = in_array($pedido->estado_pago, [EstadoPago::Pendiente->value, EstadoPago::Rechazado->value], true);
        $puedePagar = $transferencia && $pagoAbierto && ! $porCotizar && ! $pedido->esTerminal();

        $banco = null;

        if ($esDueno && $puedePagar) {
            $datos = $config->banco();
            $banco = $config->bancoConfigurado() ? [
                'configurado' => true,
                'nombre' => $datos['nombre'],
                'beneficiario' => $datos['beneficiario'],
                'clabe' => preg_replace('/\s+/', '', (string) $datos['clabe']),
                'cuenta' => $datos['cuenta'],
                'referencia' => $pedido->folio,
                'indicaciones' => $datos['referencia_instrucciones'],
            ] : ['configurado' => false];
        }

        $presencial = $pedido->metodo_pago === MetodoPagoClave::EfectivoPresencial->value;
        $recoger = $config->recoger();

        return [
            'texto' => $metodo?->config('instrucciones'),
            'banco' => $banco,
            'puede_subir_comprobante' => $esDueno && $puedePagar,
            'recoger' => ($pedido->esRecogerEnTienda() || $presencial) && (filled($recoger['direccion']) || filled($recoger['horario']))
                ? $recoger
                : null,
            'efectivo_presencial' => $presencial && $pedido->estado_pago === EstadoPago::Pendiente->value && ! $porCotizar && ! $pedido->esTerminal(),
            'contra_entrega' => $pedido->esContraEntrega() && (float) $pedido->saldo_pendiente > 0 && ! $pedido->esTerminal(),
        ];
    }

    /**
     * Dirección guardada o la copia del pedido (`direccion_envio`, mismas claves).
     *
     * @param  Direccion|array<string, mixed>  $d
     * @return array<string, mixed>
     */
    protected function presentarDireccion(Direccion|array $d): array
    {
        $d = $d instanceof Direccion ? [...$d->copiaParaPedido(), 'predeterminada' => $d->predeterminada] : $d;

        return [
            'id' => $d['id'] ?? null,
            'alias' => $d['alias'] ?? null,
            'nombre_receptor' => $d['nombre_receptor'] ?? null,
            'telefono' => $d['telefono'] ?? null,
            'calle' => $d['calle'] ?? null,
            'numero_exterior' => $d['numero_exterior'] ?? null,
            'numero_interior' => $d['numero_interior'] ?? null,
            'colonia' => $d['colonia'] ?? null,
            'municipio' => $d['municipio'] ?? null,
            'estado' => $d['estado'] ?? null,
            'pais' => $d['pais'] ?? null,
            'codigo_postal' => $d['codigo_postal'] ?? null,
            'referencias' => $d['referencias'] ?? null,
            'predeterminada' => (bool) ($d['predeterminada'] ?? false),
        ];
    }

    /**
     * @param  class-string<EstatusPedido|EstadoPago|EstadoLogistico>  $enum
     * @return array{value: ?string, label: string, color: string}
     */
    protected function info(string $enum, ?string $valor): array
    {
        $caso = $valor !== null ? $enum::tryFrom($valor) : null;

        return $caso ? $caso->toArray() : [
            'value' => $valor,
            'label' => $valor !== null ? ucfirst(str_replace('_', ' ', $valor)) : 'Sin definir',
            'color' => 'neutral',
        ];
    }

    /**
     * Pasos del pago (separados de la logística). estado: hecho|actual|pendiente|error.
     *
     * @return list<array{clave: string, titulo: string, estado: string}>
     */
    protected function lineaPago(Pedido $pedido): array
    {
        $ep = $pedido->estado_pago;
        $aprobado = in_array($ep, [EstadoPago::Aprobado->value, EstadoPago::Reembolsado->value], true);
        $terminal = $pedido->esTerminal();
        $porCotizar = $pedido->envioPendiente();

        $pasos = [['clave' => 'registrado', 'titulo' => 'Pedido registrado', 'estado' => 'hecho']];

        if ($porCotizar || $pedido->envio_estado === EstadoEnvio::Cotizado->value) {
            $pasos[] = [
                'clave' => 'envio',
                'titulo' => match (true) {
                    $pedido->esperaEleccionEnvio() => 'Elige tu envío',
                    $porCotizar => 'Cotizando tu envío',
                    $pedido->opcionesEnvio->whereNotNull('seleccionada_en')->isNotEmpty() => 'Envío elegido',
                    default => 'Envío cotizado',
                },
                'estado' => $porCotizar ? ($terminal ? 'pendiente' : 'actual') : 'hecho',
            ];
        }

        if ($pedido->metodo_pago === MetodoPagoClave::Transferencia->value) {
            $pasos[] = [
                'clave' => 'comprobante',
                'titulo' => match ($ep) {
                    EstadoPago::Rechazado->value => 'Comprobante rechazado',
                    EstadoPago::EnRevision->value => 'Comprobante en revisión',
                    default => $aprobado ? 'Comprobante recibido' : 'Envía tu comprobante',
                },
                'estado' => match (true) {
                    $aprobado, $ep === EstadoPago::EnRevision->value => 'hecho',
                    $ep === EstadoPago::Rechazado->value => 'error',
                    $porCotizar, $terminal => 'pendiente',
                    default => 'actual',
                },
            ];
            $pasos[] = [
                'clave' => 'aprobado',
                'titulo' => 'Pago confirmado',
                'estado' => $aprobado ? 'hecho' : ($ep === EstadoPago::EnRevision->value && ! $terminal ? 'actual' : 'pendiente'),
            ];
        } elseif ($pedido->esContraEntrega()) {
            $pagado = $aprobado || ((float) $pedido->saldo_pendiente <= 0 && $pedido->pagado_en !== null);
            $pasos[] = ['clave' => 'contra_entrega', 'titulo' => $pagado ? 'Pagado al recibir' : 'Pagas al recibir', 'estado' => $pagado ? 'hecho' : 'pendiente'];
        } else {
            $pasos[] = [
                'clave' => 'efectivo',
                'titulo' => $aprobado ? 'Pago recibido en tienda' : 'Paga en tienda',
                'estado' => $aprobado ? 'hecho' : ($porCotizar || $terminal ? 'pendiente' : 'actual'),
            ];
        }

        if (in_array($pedido->estatus, [EstatusPedido::Cancelado->value, EstatusPedido::Vencido->value], true)) {
            $pasos[] = ['clave' => 'cerrado', 'titulo' => (string) EstatusPedido::labelDe($pedido->estatus), 'estado' => 'error'];
        }

        return $pasos;
    }

    /** @return list<array{clave: string, titulo: string, estado: string}> */
    protected function lineaLogistica(Pedido $pedido): array
    {
        $orden = $pedido->esRecogerEnTienda()
            ? [
                EstadoLogistico::EnPreparacion->value => 'En preparación',
                EstadoLogistico::ListoRecoger->value => 'Listo para recoger',
                EstadoLogistico::Entregado->value => 'Entregado',
            ]
            : [
                EstadoLogistico::EnPreparacion->value => 'En preparación',
                EstadoLogistico::ListoEnvio->value => 'Listo para envío',
                EstadoLogistico::Enviado->value => 'Enviado',
                EstadoLogistico::Entregado->value => 'Entregado',
            ];

        $claves = array_keys($orden);
        $actual = (string) $pedido->estado_logistico;
        $indice = array_search($actual, $claves, true);

        if ($indice === false && $actual === EstadoLogistico::ListoEnvio->value && $pedido->esRecogerEnTienda()) {
            $indice = 1;
        }

        $pasos = [];

        foreach ($claves as $i => $clave) {
            $estado = match (true) {
                $indice === false => 'pendiente',
                $i < $indice => 'hecho',
                $i === $indice => $clave === EstadoLogistico::Entregado->value ? 'hecho' : 'actual',
                default => 'pendiente',
            };
            $pasos[] = ['clave' => $clave, 'titulo' => $orden[$clave], 'estado' => $estado];
        }

        if (in_array($actual, [EstadoLogistico::Incidencia->value, EstadoLogistico::Devuelto->value], true)) {
            $pasos[] = ['clave' => $actual, 'titulo' => (string) EstadoLogistico::labelDe($actual), 'estado' => 'error'];
        }

        return $pasos;
    }
}
