@extends('emails.layout')

@section('titulo', 'Pedido cancelado')
@section('preheader', 'Tu pedido '.$pedido['folio'].' fue cancelado.')
@section('encabezado', 'Tu pedido fue cancelado')

@section('contenido')
    @include('emails.partials.folio')
    @if (filled($motivo))
        @include('emails.partials.aviso', ['tono' => 'warning', 'titulo' => 'Motivo', 'mensaje' => $motivo])
    @endif
    @if ($requiereReembolso)
        <p style="margin:0 0 10px;">Como tu pago ya estaba confirmado, nos pondremos en contacto contigo para gestionar tu reembolso.</p>
    @endif
    <p style="margin:0;">Si crees que se trata de un error, contáctanos y con gusto lo revisamos.</p>
    @include('emails.partials.boton', ['url' => $urlPedido, 'etiqueta' => 'Ver mi pedido'])
@endsection
