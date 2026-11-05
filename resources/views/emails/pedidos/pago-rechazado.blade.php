@extends('emails.layout')

@section('titulo', 'Revisa tu pago')
@section('preheader', 'No pudimos validar tu pago del pedido '.$pedido['folio'].'.')
@section('encabezado', 'No pudimos validar tu pago')
@section('subtitulo', 'Puedes volver a enviar tu comprobante.')

@section('contenido')
    @include('emails.partials.folio')
    @include('emails.partials.aviso', ['tono' => 'danger', 'titulo' => 'Motivo', 'mensaje' => $motivo])
    <p style="margin:0;">Revisa los datos de tu transferencia y sube un nuevo comprobante desde tu pedido. Si tienes dudas, contáctanos.</p>
    @if (! empty($instrucciones['banco']))
        @include('emails.partials.banco', ['banco' => $instrucciones['banco'], 'venceEn' => $venceEn ?? null])
    @endif
    @include('emails.partials.boton', ['url' => $urlPedido, 'etiqueta' => 'Subir nuevo comprobante'])
@endsection
