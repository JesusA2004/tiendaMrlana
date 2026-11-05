@extends('emails.layout')

@section('titulo', 'Pago confirmado')
@section('preheader', 'Tu pago del pedido '.$pedido['folio'].' fue confirmado.')
@section('encabezado', '¡Pago confirmado!')
@section('subtitulo', 'Comenzaremos a preparar tu pedido.')

@section('contenido')
    @include('emails.partials.folio')
    @include('emails.partials.aviso', ['tono' => 'success', 'titulo' => 'Todo listo', 'mensaje' => 'Confirmamos tu pago. Te avisaremos cuando tu pedido esté en preparación y cuando salga a entrega.'])
    @include('emails.partials.resumen')
    @include('emails.partials.boton', ['url' => $urlPedido, 'etiqueta' => 'Seguir mi pedido'])
@endsection
