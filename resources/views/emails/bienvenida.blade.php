@extends('emails.layout')

@section('titulo', 'Bienvenido')
@section('preheader', 'Tu cuenta ya está lista para comprar.')
@section('encabezado', '¡Hola, '.$nombre.'!')
@section('subtitulo', 'Tu cuenta en '.$tienda['nombre'].' ya está lista.')

@section('contenido')
    <p style="margin:0 0 14px;">Gracias por registrarte. Desde tu cuenta puedes:</p>
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">
        <tr><td style="padding:4px 10px 4px 0; color:#3F8A1C; font-weight:800;">&#10003;</td><td style="padding:4px 0;">Comprar con transferencia bancaria o efectivo.</td></tr>
        <tr><td style="padding:4px 10px 4px 0; color:#3F8A1C; font-weight:800;">&#10003;</td><td style="padding:4px 0;">Guardar tus direcciones de entrega.</td></tr>
        <tr><td style="padding:4px 10px 4px 0; color:#3F8A1C; font-weight:800;">&#10003;</td><td style="padding:4px 0;">Seguir el estado de tus pedidos y descargar su resumen.</td></tr>
    </table>
    @include('emails.partials.boton', ['url' => $urlTienda, 'etiqueta' => 'Ir a la tienda'])
    <p style="margin:14px 0 0; font-size:13px; color:#6B7280;">
        Consulta tus pedidos cuando quieras en <a href="{{ $urlPedidos }}" style="color:#3F8A1C;">Mis pedidos</a>.
    </p>
@endsection
