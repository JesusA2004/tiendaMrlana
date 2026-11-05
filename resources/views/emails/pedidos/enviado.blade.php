@extends('emails.layout')

@section('titulo', 'Pedido enviado')
@section('preheader', 'Tu pedido '.$pedido['folio'].' va en camino.')
@section('encabezado', 'Tu pedido va en camino')
@section('subtitulo', 'Ya lo entregamos a la paquetería.')

@section('contenido')
    @include('emails.partials.folio')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 12px; font-size:14px;">
        @if ($pedido['paqueteria'])
            <tr><td style="padding:4px 0; color:#6B7280; width:40%;">Paquetería</td><td style="padding:4px 0; font-weight:700;">{{ $pedido['paqueteria'] }}</td></tr>
        @endif
        @if ($pedido['numero_guia'])
            <tr><td style="padding:4px 0; color:#6B7280;">Número de guía</td><td style="padding:4px 0; font-weight:700; font-family:'Courier New', Courier, monospace;">{{ $pedido['numero_guia'] }}</td></tr>
        @endif
        @if (! empty($fechaEnvio))
            <tr><td style="padding:4px 0; color:#6B7280;">Fecha de envío</td><td style="padding:4px 0; font-weight:700;">{{ $fechaEnvio }}</td></tr>
        @endif
    </table>
    @if ($pedido['saldo_pendiente'] > 0 && $pedido['metodo_pago'] === 'efectivo_contra_entrega')
        @include('emails.partials.aviso', ['tono' => 'info', 'titulo' => 'Pago contra entrega', 'mensaje' => 'Ten listo $'.number_format($pedido['saldo_pendiente'], 2).' MXN en efectivo para recibir tu pedido.'])
    @endif
    @if (! empty($urlRastreo))
        @include('emails.partials.boton', ['url' => $urlRastreo, 'etiqueta' => 'Rastrear envío'])
    @else
        @include('emails.partials.boton', ['url' => $urlPedido, 'etiqueta' => 'Ver mi pedido'])
    @endif
@endsection
