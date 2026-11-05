@extends('emails.layout')

@section('titulo', 'Envío cotizado')
@section('preheader', 'Ya puedes pagar tu pedido '.$pedido['folio'].'.')
@section('encabezado', 'Tu envío ya está cotizado')
@section('subtitulo', 'Costo de envío: $'.number_format($pedido['envio'], 2).' MXN')

@section('contenido')
    @include('emails.partials.folio')
    <p style="margin:0 0 6px;">Actualizamos el total de tu pedido con el costo de envío. Ya puedes realizar tu pago.</p>
    @include('emails.partials.instrucciones')
    @include('emails.partials.resumen')
    @include('emails.partials.boton', ['url' => $urlPedido, 'etiqueta' => 'Ver mi pedido'])
@endsection
