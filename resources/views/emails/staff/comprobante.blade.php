@extends('emails.layout')

@section('titulo', 'Comprobante por revisar')
@section('preheader', 'El pedido '.$pedido['folio'].' tiene un comprobante por revisar.')
@section('encabezado', 'Comprobante por revisar')
@section('subtitulo', $pedido['nombre_cliente'].' subió su comprobante de transferencia.')

@section('contenido')
    @include('emails.partials.folio')
    <p style="margin:0;">Revisa el comprobante en el panel y aprueba o rechaza el pago (con motivo) para avisar al cliente.</p>
    @include('emails.partials.boton', ['url' => $urlAdmin, 'etiqueta' => 'Revisar pago'])
@endsection
