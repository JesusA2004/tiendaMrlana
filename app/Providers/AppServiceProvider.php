<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Catalogo\PrecioService;
use App\Services\Configuracion\ConfiguracionTienda;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Gates del contrato §1: gate => roles permitidos.
     */
    public const GATES = [
        'gestionar-catalogo' => [User::ROL_ADMIN],
        'gestionar-configuracion' => [User::ROL_ADMIN],
        'gestionar-usuarios' => [User::ROL_ADMIN],
        'revisar-pagos' => [User::ROL_ADMIN],
        'operar-pedidos' => [User::ROL_ADMIN, User::ROL_VENDEDOR],
        'cotizar-envio' => [User::ROL_ADMIN, User::ROL_VENDEDOR],
        'cancelar-pedidos' => [User::ROL_ADMIN],
    ];

    public function register(): void
    {
        $this->app->scoped(ConfiguracionTienda::class);
        $this->app->scoped(PrecioService::class);
    }

    public function boot(): void
    {
        $this->configureDefaults();
        Schema::defaultStringLength(191);
        $this->registrarGates();
    }

    protected function registrarGates(): void
    {
        foreach (self::GATES as $gate => $roles) {
            Gate::define($gate, fn (User $user): bool => in_array($user->rol, $roles, true));
        }
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);
        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );
        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
