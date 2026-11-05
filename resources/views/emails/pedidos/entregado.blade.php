@extends('emails.layout')

@section('titulo', 'Pedido entregado')
@section('preheader', 'Tu pedido '.$pedido['folio'].' fue entregado.')
@section('encabezado', '¡Tu pedido fue entregado!')
@section('subtitulo', 'Gracias por comprar con nosotros.')

@section('contenido')
    @include('emails.partials.folio')
    <p style="margin:0;">Esperamos que disfrutes tus productos. Si algo no llegó como esperabas, contáctanos y te ayudamos.</p>
    @include('emails.partials.boton', ['url' => $urlPedido, 'etiqueta' => 'Ver mi pedido'])
@endsection
