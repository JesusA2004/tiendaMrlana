@extends('emails.layout')

@section('titulo', 'Pedido vencido')
@section('preheader', 'El plazo de pago del pedido '.$pedido['folio'].' terminó.')
@section('encabezado', 'Tu pedido venció')
@section('subtitulo', 'No recibimos el pago dentro del plazo.')

@section('contenido')
    @include('emails.partials.folio')
    <p style="margin:0;">Liberamos los productos reservados para tu pedido. Si aún los quieres, puedes hacer un nuevo pedido desde la tienda. Si ya habías pagado, contáctanos con tu comprobante.</p>
    @include('emails.partials.boton', ['url' => $urlPedido, 'etiqueta' => 'Ver mi pedido'])
@endsection
