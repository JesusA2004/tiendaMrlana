@extends('emails.layout')

@section('titulo', 'Datos para tu transferencia')
@section('preheader', 'Paga tu pedido '.$pedido['folio'].' por transferencia.')
@section('encabezado', 'Datos para tu transferencia')
@section('subtitulo', 'Usa tu folio como referencia para identificar tu pago.')

@section('contenido')
    @include('emails.partials.folio')
    @if (! empty($instrucciones['banco']))
        @include('emails.partials.banco', ['banco' => $instrucciones['banco'], 'venceEn' => $venceEn ?? null])
    @else
        @include('emails.partials.aviso', ['tono' => 'info', 'titulo' => 'Datos bancarios', 'mensaje' => 'Te contactaremos con los datos bancarios para completar tu pago.'])
    @endif
    <p style="margin:0;">Después de pagar, sube tu comprobante (foto o PDF) desde tu pedido. Lo revisaremos y te confirmaremos por correo.</p>
    @include('emails.partials.boton', ['url' => $urlPedido, 'etiqueta' => 'Subir comprobante'])
@endsection
