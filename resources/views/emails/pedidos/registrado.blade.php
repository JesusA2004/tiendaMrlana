@extends('emails.layout')

@section('titulo', 'Pedido registrado')
@section('preheader', 'Recibimos tu pedido '.$pedido['folio'].'.')
@section('encabezado', '¡Gracias por tu pedido!')
@section('subtitulo', 'Hola '.$pedido['nombre_cliente'].', ya registramos tu compra.')

@section('contenido')
    @include('emails.partials.folio')
    <p style="margin:0 0 6px;">
        Método de pago: <strong>{{ $pedido['metodo_pago_label'] }}</strong> ·
        Entrega: <strong>{{ $pedido['modalidad_entrega_label'] }}</strong>
    </p>
    @include('emails.partials.instrucciones')
    @include('emails.partials.resumen')
    @include('emails.partials.boton', ['url' => $urlPedido, 'etiqueta' => 'Ver mi pedido'])
@endsection
