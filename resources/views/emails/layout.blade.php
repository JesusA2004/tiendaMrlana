{{--
    Plantilla base de correos Mr Lana.
    HTML de tablas con estilos en línea (compatible con Gmail/Outlook/Apple Mail).
    Encabezado con degradado verde→turquesa (con color sólido de respaldo para Outlook).
--}}
@php
    $nombreTienda = $tienda['nombre'] ?? config('app.name', 'Mr Lana');
    $fuente = "Montserrat, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    $logoSrc = null;
    if (! empty($logoPath) && isset($message) && is_file($logoPath)) {
        $logoSrc = $message->embed($logoPath);
    }
@endphp
<!DOCTYPE html>
<html lang="es" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light">
    <title>@yield('titulo', $nombreTienda)</title>
</head>
<body style="margin:0; padding:0; background:#F3F2EF; font-family:{{ $fuente }}; color:#0A0A0A; -webkit-text-size-adjust:100%;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">@yield('preheader')</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F3F2EF;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; background:#FFFFFF; border-radius:20px; overflow:hidden; border:1px solid #E3E1DE;">
                    {{-- Encabezado de marca --}}
                    <tr>
                        <td bgcolor="#73C83A" style="background-color:#73C83A; background-image:linear-gradient(135deg,#73C83A 0%,#64CBD4 100%); padding:28px 28px 26px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td>
                                        @if ($logoSrc)
                                            <img src="{{ $logoSrc }}" alt="{{ $nombreTienda }}" width="132" style="display:block; width:132px; max-width:132px; height:auto; border:0;">
                                        @else
                                            <p style="margin:0; font-size:24px; font-weight:800; color:#0A0A0A; font-family:{{ $fuente }};">{{ $nombreTienda }}</p>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding-top:18px;">
                                        <h1 style="margin:0; font-size:26px; line-height:1.2; color:#0A0A0A; font-weight:800; font-family:{{ $fuente }};">@yield('encabezado')</h1>
                                        @hasSection('subtitulo')
                                            <p style="margin:8px 0 0; font-size:15px; line-height:1.5; color:#14332B; font-family:{{ $fuente }};">@yield('subtitulo')</p>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Contenido --}}
                    <tr>
                        <td style="padding:28px; font-size:15px; line-height:1.6; color:#1F2937; font-family:{{ $fuente }};">
                            @yield('contenido')
                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td bgcolor="#14332B" style="background-color:#14332B; padding:22px 28px; font-family:{{ $fuente }};">
                            <p style="margin:0; font-size:14px; font-weight:700; color:#FFFFFF;">{{ $nombreTienda }}</p>
                            @php($contacto = $tienda['contacto'] ?? [])
                            @if (! empty($contacto['correo']) || ! empty($contacto['telefono']) || ! empty($contacto['whatsapp']))
                                <p style="margin:6px 0 0; font-size:13px; line-height:1.6; color:#CDE8D4;">
                                    ¿Dudas? Escríbenos
                                    @if (! empty($contacto['correo']))
                                        a <a href="mailto:{{ $contacto['correo'] }}" style="color:#9BE07A; text-decoration:underline;">{{ $contacto['correo'] }}</a>
                                    @endif
                                    @if (! empty($contacto['whatsapp']))
                                        · WhatsApp {{ $contacto['whatsapp'] }}
                                    @elseif (! empty($contacto['telefono']))
                                        · Tel. {{ $contacto['telefono'] }}
                                    @endif
                                </p>
                            @endif
                            <p style="margin:10px 0 0; font-size:12px; line-height:1.5; color:#9DB8A8;">
                                Recibiste este correo porque tienes una cuenta o un pedido en {{ $nombreTienda }}.
                                Nunca te pediremos datos de tarjeta ni contraseñas por correo.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
