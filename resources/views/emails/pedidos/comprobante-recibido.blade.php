@extends('emails.layout')

@section('titulo', 'Comprobante recibido')
@section('preheader', 'Estamos revisando tu pago del pedido '.$pedido['folio'].'.')
@section('encabezado', 'Recibimos tu comprobante')
@section('subtitulo', 'Lo revisaremos y te confirmaremos tu pago.')

@section('contenido')
    @include('emails.partials.folio')
    <p style="margin:0;">Gracias, {{ $pedido['nombre_cliente'] }}. Nuestro equipo validará tu transferencia y te avisaremos por correo en cuanto quede confirmada.</p>
    @include('emails.partials.boton', ['url' => $urlPedido, 'etiqueta' => 'Ver mi pedido'])
@endsection
