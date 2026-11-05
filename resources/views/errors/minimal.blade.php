<!DOCTYPE html>
<html lang="es-MX">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <meta name="theme-color" content="#73C83A">
        <title>@yield('title') · Mr Lana</title>
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <style>
            *, *::before, *::after { box-sizing: border-box; }
            html, body { margin: 0; }
            body {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px 16px;
                font-family: 'Montserrat', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
                color: #0a0a0a;
                background:
                    radial-gradient(600px 400px at 0% 0%, rgba(115, 200, 58, 0.22), transparent 70%),
                    radial-gradient(600px 400px at 100% 100%, rgba(100, 203, 212, 0.22), transparent 70%),
                    linear-gradient(160deg, #1f4d3a 0%, #14332b 100%);
            }
            main {
                width: 100%;
                max-width: 440px;
                padding: 36px 28px;
                border-radius: 28px;
                background: #ffffff;
                text-align: center;
                box-shadow: 0 40px 80px -40px rgba(0, 0, 0, 0.6);
            }
            .logo { width: 72px; height: 72px; border-radius: 20px; }
            .codigo {
                margin: 20px 0 0;
                font-size: 13px;
                font-weight: 700;
                letter-spacing: 0.2em;
                color: #3f8a1c;
            }
            h1 { margin: 6px 0 0; font-size: 26px; line-height: 1.2; font-weight: 800; }
            p.mensaje { margin: 12px 0 0; font-size: 15px; line-height: 1.6; color: #4b5563; }
            .acciones { margin-top: 28px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
            a.boton {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 44px;
                padding: 0 22px;
                border-radius: 999px;
                font-size: 15px;
                font-weight: 700;
                text-decoration: none;
            }
            a.primario { background: #73c83a; color: #0a0a0a; }
            a.secundario { border: 1px solid #e3e1de; color: #0a0a0a; }
            a.boton:focus-visible { outline: 3px solid #1b7880; outline-offset: 2px; }
        </style>
    </head>
    <body>
        <main>
            <img class="logo" src="/icons/icon-192.png" alt="Mr Lana" width="72" height="72">
            <p class="codigo">ERROR @yield('code')</p>
            <h1>@yield('title')</h1>
            <p class="mensaje">@yield('message')</p>
            <div class="acciones">
                <a class="boton primario" href="{{ url('/') }}">Ir a la tienda</a>
                <a class="boton secundario" href="javascript:history.back()">Regresar</a>
            </div>
        </main>
    </body>
</html>
