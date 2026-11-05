@extends('emails.layout')

@section('titulo', 'Tu cotización de envío está lista')
@section('preheader', 'Elige la paquetería para tu pedido '.$pedido['folio'].'.')
@section('encabezado', 'Tu cotización de envío está lista')
@section('subtitulo', 'Elige la opción que mejor te acomode según precio y tiempo de entrega.')

@section('contenido')
    @include('emails.partials.folio')
    <p style="margin:0 0 6px;">Cotizamos el envío de tu pedido con estas opciones. Entra a tu pedido y elige una: en cuanto lo hagas te mostraremos el total final y cómo pagar.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:16px 0; border-collapse:separate; border-spacing:0 10px;">
        @foreach ($opciones as $opcion)
            <tr>
                <td style="border:1px solid {{ $opcion['recomendada'] ? '#73C83A' : '#E3E1DE' }}; border-radius:14px; padding:14px 16px; background:{{ $opcion['recomendada'] ? '#F4FBEF' : '#FFFFFF' }};">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td style="font-size:15px; font-weight:800; color:#0A0A0A;">
                                {{ $opcion['paqueteria'] }}
                                <span style="font-weight:600; color:#6B7280;">· {{ $opcion['servicio'] }}</span>
                            </td>
                            <td align="right" style="font-size:17px; font-weight:800; color:#0A0A0A; white-space:nowrap;">${{ number_format($opcion['costo'], 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-top:4px; font-size:13px; color:#374151;">
                                @if ($opcion['tiempo'])
                                    Tiempo estimado: {{ $opcion['tiempo'] }}
                                @endif
                                @if ($opcion['recomendada'])
                                    <span style="display:inline-block; margin-left:6px; padding:2px 8px; border-radius:999px; background:#73C83A; color:#0A0A0A; font-size:11px; font-weight:700;">Recomendada</span>
                                @endif
                                @if ($opcion['mas_economica'])
                                    <span style="display:inline-block; margin-left:6px; padding:2px 8px; border-radius:999px; background:#EEF8E7; color:#2F6B12; font-size:11px; font-weight:700;">Más económica</span>
                                @endif
                                @if ($opcion['mas_rapida'])
                                    <span style="display:inline-block; margin-left:6px; padding:2px 8px; border-radius:999px; background:#E6F7F9; color:#0E5960; font-size:11px; font-weight:700;">Más rápida</span>
                                @endif
                            </td>
                        </tr>
                        @if (! empty($opcion['notas']))
                            <tr>
                                <td colspan="2" style="padding-top:4px; font-size:13px; color:#6B7280;">{{ $opcion['notas'] }}</td>
                            </tr>
                        @endif
                    </table>
                </td>
            </tr>
        @endforeach
    </table>

    @include('emails.partials.aviso', ['tono' => 'warning', 'titulo' => 'Aún no realices ningún pago', 'mensaje' => 'Primero elige tu envío; después te mostraremos el total final y las instrucciones de pago.'])
    @include('emails.partials.boton', ['url' => $urlPedido, 'etiqueta' => 'Elegir mi envío'])
@endsection
