@extends('emails.layout')

@section('titulo', 'Pedido en preparación')
@section('preheader', 'Estamos preparando tu pedido '.$pedido['folio'].'.')
@section('encabezado', 'Estamos preparando tu pedido')
@section('subtitulo', 'Te avisaremos cuando esté listo.')

@section('contenido')
    @include('emails.partials.folio')
    <p style="margin:0;">
        @if ($pedido['modalidad_entrega'] === 'recoger_en_tienda')
            Estamos alistando tus productos. Te escribiremos en cuanto puedas pasar a recogerlos.
        @else
            Estamos empacando tus productos con cuidado. Te enviaremos la paquetería y el número de guía en cuanto salga.
        @endif
    </p>
    @include('emails.partials.boton', ['url' => $urlPedido, 'etiqueta' => 'Ver mi pedido'])
@endsection
