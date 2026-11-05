@extends('emails.layout')

@section('titulo', 'Activa tu cuenta')
@section('preheader', 'Define tu contraseña para entrar a tu cuenta.')
@section('encabezado', 'Tu cuenta está lista')
@section('subtitulo', 'Sólo falta que definas tu contraseña.')

@section('contenido')
    <p style="margin:0 0 14px;">Hola <strong>{{ $userName }}</strong>,</p>
    <p style="margin:0 0 14px;">
        Un administrador creó tu cuenta con el rol <strong>{{ $rolLabel }}</strong>.
        Tu usuario es <strong>{{ $email }}</strong>.
    </p>
    <p style="margin:0;">Por seguridad no enviamos contraseñas por correo. Usa este botón para crear la tuya:</p>

    @if ($resetUrl)
        @include('emails.partials.boton', ['url' => $resetUrl, 'etiqueta' => 'Definir mi contraseña'])
        <p style="margin:8px 0 0; font-size:13px; color:#6B7280;">El enlace caduca por seguridad. Si expira, usa "¿Olvidaste tu contraseña?" en la pantalla de acceso.</p>
    @endif

    <p style="margin:18px 0 0; font-size:13px; color:#6B7280;">Si no esperabas este correo, ignóralo o contacta a la tienda.</p>
@endsection
