@extends('emails.layout')

@section('titulo', 'Nuevo pedido')
@section('preheader', 'Nuevo pedido '.$pedido['folio'].' por $'.number_format($pedido['total'], 2).'.')
@section('encabezado', 'Nuevo pedido recibido')
@section('subtitulo', $pedido['nombre_cliente'].' · '.$pedido['metodo_pago_label'])

@section('contenido')
    @include('emails.partials.folio')
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 6px; font-size:14px;">
        <tr><td style="padding:3px 0; color:#6B7280; width:40%;">Cliente</td><td style="padding:3px 0; font-weight:700;">{{ $pedido['nombre_cliente'] }}</td></tr>
        <tr><td style="padding:3px 0; color:#6B7280;">Entrega</td><td style="padding:3px 0; font-weight:700;">{{ $pedido['modalidad_entrega_label'] }}{{ $pedido['direccion'] ? ' · '.$pedido['direccion']['municipio'].', '.$pedido['direccion']['estado'] : '' }}</td></tr>
        <tr><td style="padding:3px 0; color:#6B7280;">Estado</td><td style="padding:3px 0; font-weight:700;">{{ $pedido['estatus']['label'] }}</td></tr>
    </table>
    @if ($pedido['envio_por_cotizar'])
        @include('emails.partials.aviso', ['tono' => 'warning', 'titulo' => 'Envío por cotizar', 'mensaje' => 'Este pedido necesita que se cotice el envío para que el cliente pueda pagar.'])
    @endif
    @include('emails.partials.resumen')
    @include('emails.partials.boton', ['url' => $urlAdmin, 'etiqueta' => 'Abrir en el panel'])
@endsection
