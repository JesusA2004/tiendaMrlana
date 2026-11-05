@php
    $moneda = fn ($v) => '$'.number_format((float) $v, 2);
    $dir = $pedido['direccion'];
    $venceEn = $pedido['vence_en'] ? \Illuminate\Support\Carbon::parse($pedido['vence_en'])->timezone('America/Mexico_City')->locale('es')->isoFormat('D [de] MMMM [de] YYYY, h:mm a') : null;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Resumen de pedido {{ $pedido['folio'] }}</title>
    @include('pdf._estilos')
</head>
<body>
    @include('pdf._encabezado', ['tipoDocumento' => 'Resumen de pedido'])

    <div class="fila">
        <div class="caja">
            <h3>Cliente</h3>
            <p><strong>{{ $pedido['nombre_cliente'] }}</strong></p>
            <p class="muted">{{ $pedido['correo_cliente'] }}</p>
            @if ($pedido['telefono_cliente'])
                <p class="muted">Tel. {{ $pedido['telefono_cliente'] }}</p>
            @endif
        </div>
        <div class="caja">
            <h3>Entrega</h3>
            <p><strong>{{ $pedido['modalidad_entrega_label'] }}</strong></p>
            @if ($dir)
                <p>{{ $dir['nombre_receptor'] }}</p>
                <p class="muted">
                    {{ $dir['calle'] }} {{ $dir['numero_exterior'] }}@if ($dir['numero_interior']) int. {{ $dir['numero_interior'] }}@endif,
                    {{ $dir['colonia'] }}, {{ $dir['municipio'] }}, {{ $dir['estado'] }}, C.P. {{ $dir['codigo_postal'] }}
                </p>
            @elseif (! empty($instrucciones['recoger']['direccion']))
                <p class="muted">{{ $instrucciones['recoger']['direccion'] }}</p>
            @endif
            @if ($pedido['paqueteria'] || $pedido['numero_guia'])
                <p style="margin-top:4px;">{{ $pedido['paqueteria'] }}@if ($pedido['numero_guia']) · Guía <span class="mono">{{ $pedido['numero_guia'] }}</span>@endif</p>
                @if (! empty($pedido['url_rastreo']))
                    <p class="muted mono" style="margin-top:2px;">{{ $pedido['url_rastreo'] }}</p>
                @endif
            @endif
        </div>
        <div class="caja">
            <h3>Estado</h3>
            <p style="margin-bottom:4px;"><span class="chip {{ $pedido['estatus']['color'] }}">{{ $pedido['estatus']['label'] }}</span></p>
            <p><span class="muted">Pago:</span> {{ $pedido['metodo_pago_label'] }} · {{ $pedido['estado_pago']['label'] }}</p>
            <p><span class="muted">Logística:</span> {{ $pedido['estado_logistico']['label'] }}</p>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Producto</th>
                <th>SKU</th>
                <th class="num">Cant.</th>
                <th class="num">Precio</th>
                <th class="num">Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pedido['items'] as $item)
                <tr>
                    <td><strong>{{ $item['nombre'] }}</strong></td>
                    <td class="muted">{{ $item['sku'] ?: '—' }}</td>
                    <td class="num">{{ $item['cantidad'] }}</td>
                    <td class="num">{{ $moneda($item['precio_unitario']) }}</td>
                    <td class="num">{{ $moneda($item['subtotal']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totales">
        <div><span class="muted">Subtotal</span><span>{{ $moneda($pedido['subtotal']) }}</span></div>
        @if ($pedido['descuento_cupon'] > 0)
            <div><span class="muted">Descuento{{ $pedido['cupon_codigo'] ? ' ('.$pedido['cupon_codigo'].')' : '' }}</span><span>−{{ $moneda($pedido['descuento_cupon']) }}</span></div>
        @endif
        <div>
            <span class="muted">Envío</span>
            <span>
                @if (! empty($pedido['envio_esperando_eleccion']))
                    Por elegir
                @elseif ($pedido['envio_por_cotizar'])
                    Por cotizar
                @elseif ($pedido['modalidad_entrega'] === 'recoger_en_tienda')
                    Recoges en tienda
                @else
                    {{ $moneda($pedido['envio']) }}@if (! empty($pedido['paqueteria'])) ({{ $pedido['paqueteria'] }})@endif
                @endif
            </span>
        </div>
        <div class="total"><span>Total</span><span>{{ $moneda($pedido['total']) }} MXN</span></div>
        @if ($pedido['saldo_pendiente'] > 0 && $pedido['saldo_pendiente'] < $pedido['total'])
            <div><span class="muted">Saldo pendiente</span><span>{{ $moneda($pedido['saldo_pendiente']) }}</span></div>
        @endif
    </div>

    @if (! empty($pedido['envio_esperando_eleccion']) && ! $pedido['es_terminal'])
        <div class="aviso warning">
            <strong>Elige tu envío</strong>
            Ya cotizamos tu envío con varias paqueterías. Entra a tu pedido, elige la opción que prefieras y te mostraremos el total final. No realices ningún pago todavía.
        </div>
    @elseif ($pedido['envio_por_cotizar'] && ! $pedido['es_terminal'])
        <div class="aviso warning">
            <strong>Envío por cotizar</strong>
            Te enviaremos el costo de envío antes de que pagues. No realices ningún pago todavía.
        </div>
    @elseif (! empty($instrucciones['banco']))
        <div class="aviso">
            <strong>Datos para tu transferencia</strong>
            @if ($instrucciones['banco']['configurado'])
                <div class="banco">
                    <span>Banco</span><strong>{{ $instrucciones['banco']['nombre'] }}</strong>
                    <span>Beneficiario</span><strong>{{ $instrucciones['banco']['beneficiario'] }}</strong>
                    <span>CLABE</span><strong class="mono">{{ trim(chunk_split($instrucciones['banco']['clabe'], 4, ' ')) }}</strong>
                    <span>Referencia</span><strong>{{ $instrucciones['banco']['referencia'] }}</strong>
                    @if ($venceEn)
                        <span>Fecha límite</span><strong>{{ $venceEn }}</strong>
                    @endif
                </div>
            @else
                Te contactaremos con los datos bancarios para completar tu pago.
            @endif
        </div>
    @elseif (! empty($instrucciones['efectivo_presencial']))
        <div class="aviso">
            <strong>Pago en efectivo en tienda</strong>
            Menciona tu folio {{ $pedido['folio'] }} al pagar.
            @if (! empty($instrucciones['recoger']['direccion'])) Dirección: {{ $instrucciones['recoger']['direccion'] }}. @endif
            @if (! empty($instrucciones['recoger']['horario'])) Horario: {{ $instrucciones['recoger']['horario'] }}. @endif
        </div>
    @elseif (! empty($instrucciones['contra_entrega']))
        <div class="aviso">
            <strong>Pago contra entrega</strong>
            Pagarás {{ $moneda($pedido['saldo_pendiente']) }} MXN en efectivo al recibir tu pedido.
        </div>
    @endif

    @if ($pedido['notas_cliente'])
        <div class="caja" style="margin-top:16px;">
            <h3>Notas del pedido</h3>
            <p>{{ $pedido['notas_cliente'] }}</p>
        </div>
    @endif

    <div class="pie">
        <span>
            {{ $tienda['nombre'] }}
            @if (! empty($tienda['contacto']['correo'])) · {{ $tienda['contacto']['correo'] }} @endif
            @if (! empty($tienda['contacto']['whatsapp'])) · WhatsApp {{ $tienda['contacto']['whatsapp'] }} @elseif (! empty($tienda['contacto']['telefono'])) · Tel. {{ $tienda['contacto']['telefono'] }} @endif
        </span>
        <span>Documento informativo, no es comprobante fiscal · Generado {{ $generado_en->copy()->timezone('America/Mexico_City')->format('d/m/Y H:i') }}</span>
    </div>
</body>
</html>
