@extends('emails.layout')

@section('titulo', 'Listo para recoger')
@section('preheader', 'Tu pedido '.$pedido['folio'].' te espera en tienda.')
@section('encabezado', 'Tu pedido está listo para recoger')
@section('subtitulo', 'Presenta tu folio al llegar.')

@section('contenido')
    @include('emails.partials.folio')
    @php
        $recoger = $instrucciones['recoger'] ?? null;
    @endphp
    @if ($recoger && (! empty($recoger['direccion']) || ! empty($recoger['horario'])))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 12px; font-size:14px;">
            @if (! empty($recoger['direccion']))
                <tr><td style="padding:4px 0; color:#6B7280; width:30%;">Dirección</td><td style="padding:4px 0; font-weight:700;">{{ $recoger['direccion'] }}</td></tr>
            @endif
            @if (! empty($recoger['horario']))
                <tr><td style="padding:4px 0; color:#6B7280;">Horario</td><td style="padding:4px 0; font-weight:700;">{{ $recoger['horario'] }}</td></tr>
            @endif
        </table>
    @else
        <p style="margin:0 0 12px;">Te contactaremos para coordinar la entrega en tienda.</p>
    @endif
    @if ($pedido['saldo_pendiente'] > 0 && in_array($pedido['estado_pago']['value'], ['pendiente', 'rechazado'], true))
        @include('emails.partials.aviso', ['tono' => 'info', 'titulo' => 'Saldo por pagar', 'mensaje' => 'Pagarás $'.number_format($pedido['saldo_pendiente'], 2).' MXN al recoger.'])
    @endif
    @include('emails.partials.boton', ['url' => $urlPedido, 'etiqueta' => 'Ver mi pedido'])
@endsection
