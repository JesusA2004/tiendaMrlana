{{-- Datos para transferencia. Sólo se incluye en correos al dueño del pedido. --}}
@if (! empty($banco) && ($banco['configurado'] ?? false))
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0; border:2px solid #64CBD4; border-radius:16px;">
        <tr>
            <td style="padding:18px 18px 6px; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#0E5960;">Datos para tu transferencia</td>
        </tr>
        <tr>
            <td style="padding:0 18px 18px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px; color:#1F2937;">
                    <tr><td style="padding:4px 0; color:#6B7280; width:42%;">Banco</td><td style="padding:4px 0; font-weight:700;">{{ $banco['nombre'] }}</td></tr>
                    <tr><td style="padding:4px 0; color:#6B7280;">Beneficiario</td><td style="padding:4px 0; font-weight:700;">{{ $banco['beneficiario'] }}</td></tr>
                    <tr><td style="padding:4px 0; color:#6B7280;">CLABE</td><td style="padding:4px 0; font-weight:700; font-family:'Courier New', Courier, monospace; letter-spacing:1px;">{{ trim(chunk_split($banco['clabe'], 4, ' ')) }}</td></tr>
                    <tr><td style="padding:4px 0; color:#6B7280;">Referencia / concepto</td><td style="padding:4px 0; font-weight:700;">{{ $banco['referencia'] }}</td></tr>
                    <tr><td style="padding:4px 0; color:#6B7280;">Monto exacto</td><td style="padding:4px 0; font-weight:700;">${{ number_format($pedido['total'], 2) }} MXN</td></tr>
                    @if (! empty($venceEn))
                        <tr><td style="padding:4px 0; color:#6B7280;">Fecha límite</td><td style="padding:4px 0; font-weight:700;">{{ $venceEn }}</td></tr>
                    @endif
                </table>
                @if (! empty($banco['indicaciones']))
                    <p style="margin:10px 0 0; font-size:13px; color:#374151;">{{ $banco['indicaciones'] }}</p>
                @endif
            </td>
        </tr>
    </table>
@elseif (! empty($banco))
    @include('emails.partials.aviso', ['tono' => 'info', 'titulo' => 'Datos bancarios', 'mensaje' => 'Te contactaremos con los datos bancarios para completar tu pago.'])
@endif
