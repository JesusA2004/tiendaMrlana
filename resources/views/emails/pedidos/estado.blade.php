<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $contenido['titulo'] }}</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6; padding:32px 12px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:680px; background:#ffffff; border:1px solid #e5e7eb; border-radius:24px; overflow:hidden;">
                <tr>
                    <td style="padding:30px 34px; background:#111827;">
                        <p style="margin:0; font-size:12px; letter-spacing:3px; text-transform:uppercase; color:#9ca3af; font-weight:800;">
                            Mr Lana
                        </p>

                        <h1 style="margin:12px 0 0; font-size:30px; line-height:1.12; color:#ffffff; font-weight:900;">
                            {{ $contenido['titulo'] }}
                        </h1>

                        <p style="margin:14px 0 0; font-size:15px; line-height:1.7; color:#d1d5db;">
                            Hola {{ $pedido->nombre_cliente }}, {{ $contenido['mensaje'] }}
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:30px 34px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:18px;">
                            <tr>
                                <td style="padding:20px;">
                                    <p style="margin:0; color:#6b7280; font-size:12px; letter-spacing:2px; text-transform:uppercase; font-weight:800;">
                                        Pedido
                                    </p>

                                    <p style="margin:8px 0 0; color:#111827; font-size:24px; font-weight:900;">
                                        {{ $pedido->folio }}
                                    </p>

                                    <p style="margin:8px 0 0; display:inline-block; background:#ecfdf5; color:#047857; padding:7px 12px; border-radius:999px; font-size:12px; font-weight:900; text-transform:uppercase;">
                                        {{ $contenido['badge'] }}
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;">
                            <tr>
                                <td width="50%" style="padding-right:8px;">
                                    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:16px;">
                                        <tr>
                                            <td style="padding:18px;">
                                                <p style="margin:0; color:#6b7280; font-size:12px; text-transform:uppercase; font-weight:800;">
                                                    Total
                                                </p>
                                                <p style="margin:8px 0 0; color:#111827; font-size:20px; font-weight:900;">
                                                    ${{ number_format((float) $pedido->total, 2) }}
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>

                                <td width="50%" style="padding-left:8px;">
                                    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:16px;">
                                        <tr>
                                            <td style="padding:18px;">
                                                <p style="margin:0; color:#6b7280; font-size:12px; text-transform:uppercase; font-weight:800;">
                                                    Fecha
                                                </p>
                                                <p style="margin:8px 0 0; color:#111827; font-size:15px; font-weight:900;">
                                                    {{ now()->format('d/m/Y H:i') }}
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        @if($tipo === 'enviado')
                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:18px;">
                                <tr>
                                    <td style="padding:20px;">
                                        <p style="margin:0; color:#111827; font-size:17px; font-weight:900;">
                                            Datos de envío
                                        </p>

                                        <p style="margin:12px 0 0; color:#374151; font-size:14px; line-height:1.7;">
                                            <strong>Paquetería:</strong> {{ $pedido->paqueteria ?: 'Por confirmar' }}<br>
                                            <strong>Guía:</strong> {{ $pedido->numero_guia ?: 'Por confirmar' }}
                                        </p>

                                        @if($urlRastreo)
                                            <p style="margin:20px 0 0;">
                                                <a href="{{ $urlRastreo }}" style="display:inline-block; background:#111827; color:#ffffff; text-decoration:none; padding:13px 22px; border-radius:999px; font-size:14px; font-weight:900;">
                                                    Rastrear pedido
                                                </a>
                                            </p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        @endif

                        <div style="margin-top:24px;">
                            <p style="margin:0; color:#111827; font-size:17px; font-weight:900;">
                                Productos
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:10px; border-collapse:collapse;">
                                @foreach($pedido->items as $item)
                                    <tr>
                                        <td style="padding:14px 0; border-bottom:1px solid #e5e7eb;">
                                            <p style="margin:0; color:#111827; font-size:14px; font-weight:800;">
                                                {{ $item->nombre }}
                                            </p>
                                            <p style="margin:4px 0 0; color:#6b7280; font-size:13px;">
                                                Cantidad: {{ $item->cantidad }}
                                            </p>
                                        </td>
                                        <td align="right" style="padding:14px 0; border-bottom:1px solid #e5e7eb;">
                                            <p style="margin:0; color:#111827; font-size:14px; font-weight:900;">
                                                ${{ number_format((float) $item->subtotal, 2) }}
                                            </p>
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>

                        @php($dirEntrega = $pedido->direccionEntrega())
                        @if($dirEntrega)
                            <div style="margin-top:24px; padding:20px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:18px;">
                                <p style="margin:0; color:#111827; font-size:17px; font-weight:900;">
                                    Dirección
                                </p>

                                <p style="margin:10px 0 0; color:#374151; font-size:14px; line-height:1.8;">
                                    <strong>{{ $dirEntrega['nombre_receptor'] ?? '' }}</strong><br>
                                    {{ $dirEntrega['calle'] ?? '' }} {{ $dirEntrega['numero_exterior'] ?? '' }}
                                    @if(! empty($dirEntrega['numero_interior']))
                                        Int. {{ $dirEntrega['numero_interior'] }}
                                    @endif
                                    <br>
                                    {{ $dirEntrega['colonia'] ?? '' }},
                                    {{ $dirEntrega['municipio'] ?? '' }},
                                    {{ $dirEntrega['estado'] ?? '' }}<br>
                                    CP {{ $dirEntrega['codigo_postal'] ?? '' }}
                                </p>
                            </div>
                        @endif

                        <p style="margin:26px 0 0; color:#6b7280; font-size:14px; line-height:1.7;">
                            Gracias por comprar en Mr Lana.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:20px 34px; background:#f9fafb; border-top:1px solid #e5e7eb;">
                        <p style="margin:0; color:#9ca3af; font-size:12px; line-height:1.6;">
                            Este correo fue generado automáticamente para notificar el avance de tu pedido.
                        </p>
                    </td>
                </tr>
            </table>

            <p style="margin:18px 0 0; color:#9ca3af; font-size:12px;">
                © {{ date('Y') }} Mr Lana
            </p>
        </td>
    </tr>
</table>
</body>
</html>
