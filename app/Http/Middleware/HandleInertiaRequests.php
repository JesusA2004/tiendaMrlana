<?php

namespace App\Http\Middleware;

use App\Providers\AppServiceProvider;
use App\Services\Carrito\CarritoService;
use App\Services\Configuracion\ConfiguracionTienda;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'name' => config('app.name'),

            'auth' => [
                'user' => $user,
                // Permisos por gate (contrato §1): { revisar_pagos: bool, operar_pedidos: bool, ... }
                'can' => collect(array_keys(AppServiceProvider::GATES))
                    ->mapWithKeys(fn (string $gate) => [
                        Str::replace('-', '_', $gate) => $user ? Gate::forUser($user)->allows($gate) : false,
                    ])
                    ->all(),
            ],

            // Metadatos SEO/Open Graph por defecto (noindex fuera de las rutas públicas). Las páginas
            // públicas envían su propia prop `seo`, que reemplaza a ésta. Ver App\Support\Seo.
            'seo' => fn () => Seo::predeterminado($request->route()?->getName())->toArray(),

            // Datos públicos mínimos de la tienda (sin datos bancarios) + enlaces del pie de página.
            'tienda' => fn () => app(ConfiguracionTienda::class)->publico(),

            'sidebarOpen' => ! $request->hasCookie('sidebar_state')
                || $request->cookie('sidebar_state') === 'true',

            // Sólo cantidad: los importes siempre se recalculan en el carrito/checkout.
            'carrito' => [
                'cantidad_items' => app(CarritoService::class)->cantidadItems(),
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
