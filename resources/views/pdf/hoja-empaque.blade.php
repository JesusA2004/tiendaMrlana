@php
    $dir = $pedido['direccion'];
    $totalPiezas = collect($pedido['items'])->sum('cantidad');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Hoja de empaque {{ $pedido['folio'] }}</title>
    @include('pdf._estilos')
    <style>
        .check { width: 16px; height: 16px; border: 2px solid #14332B; border-radius: 4px; display: inline-block; }
        .destino { font-size: 14px; line-height: 1.45; }
        .grande { font-size: 18px; font-weight: 800; }
        .firmas { display: flex; gap: 24px; margin-top: 34px; }
        .firmas div { flex: 1; border-top: 1px solid #0A0A0A; padding-top: 4px; text-align: center; font-size: 10px; color: #6B7280; }
    </style>
</head>
<body>
    @include('pdf._encabezado', ['tipoDocumento' => 'Hoja de empaque'])

    <div class="fila">
        <div class="caja" style="flex:1.6;">
            <h3>{{ $pedido['modalidad_entrega'] === 'recoger_en_tienda' ? 'Entrega en tienda' : 'Enviar a' }}</h3>
            @if ($dir)
                <div class="destino">
                    <div class="grande">{{ $dir['nombre_receptor'] }}</div>
                    <div>{{ $dir['calle'] }} {{ $dir['numero_exterior'] }}@if ($dir['numero_interior']) int. {{ $dir['numero_interior'] }}@endif</div>
                    <div>Col. {{ $dir['colonia'] }}</div>
                    <div>{{ $dir['municipio'] }}, {{ $dir['estado'] }}</div>
                    <div><strong>C.P. {{ $dir['codigo_postal'] }}</strong></div>
                    <div>Tel. {{ $dir['telefono'] }}</div>
                    @if ($dir['referencias'])
                        <div class="muted" style="font-size:11px; margin-top:4px;">Referencias: {{ $dir['referencias'] }}</div>
                    @endif
                </div>
            @else
                <div class="destino">
                    <div class="grande">{{ $pedido['nombre_cliente'] }}</div>
                    <div>Tel. {{ $pedido['telefono_cliente'] }}</div>
                </div>
            @endif
        </div>
        <div class="caja">
            <h3>Pedido</h3>
            <p><span class="muted">Piezas:</span> <strong>{{ $totalPiezas }}</strong></p>
            <p><span class="muted">Pago:</span> {{ $pedido['metodo_pago_label'] }}</p>
            <p><span class="muted">Estado de pago:</span> {{ $pedido['estado_pago']['label'] }}</p>
            @if ($pedido['metodo_pago'] === 'efectivo_contra_entrega' && $pedido['saldo_pendiente'] > 0)
                <p style="margin-top:6px;"><span class="chip warning">Cobrar ${{ number_format($pedido['saldo_pendiente'], 2) }} al entregar</span></p>
            @endif
            @if ($pedido['paqueteria'] || $pedido['numero_guia'])
                <p style="margin-top:6px;">{{ $pedido['paqueteria'] }}<br><span class="mono">{{ $pedido['numero_guia'] }}</span></p>
            @endif
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:34px;"></th>
                <th>Producto</th>
                <th>SKU</th>
                <th class="num">Cantidad</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($pedido['items'] as $item)
                <tr>
                    <td><span class="check"></span></td>
                    <td><strong>{{ $item['nombre'] }}</strong></td>
                    <td class="mono">{{ $item['sku'] ?: '—' }}</td>
                    <td class="num grande">{{ $item['cantidad'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($pedido['notas_cliente'])
        <div class="aviso warning">
            <strong>Notas del cliente</strong>
            {{ $pedido['notas_cliente'] }}
        </div>
    @endif

    <div class="firmas">
        <div>Empacó</div>
        <div>Revisó</div>
        <div>Recibió</div>
    </div>

    <div class="pie">
        <span>{{ $tienda['nombre'] }} · Uso interno</span>
        <span>Generado {{ $generado_en->copy()->timezone('America/Mexico_City')->format('d/m/Y H:i') }}</span>
    </div>
</body>
</html>
