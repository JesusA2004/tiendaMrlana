<?php

namespace App\Services\Configuracion;

use App\Http\Controllers\Publico\TiendaController;
use App\Models\Configuracion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Configuración administrable de la tienda (tabla `configuraciones`, clave → valor).
 *
 * Valores array se guardan como JSON. Todo se cachea en una sola llave y se invalida en set().
 * Nunca inventa datos comerciales: si una clave no existe devuelve null / [].
 */
class ConfiguracionTienda
{
    public const CACHE_KEY = 'configuracion_tienda.v1';

    /** Claves conocidas (sección 2 del contrato). Las de tipo json se decodifican. */
    public const CLAVES = [
        'tienda.nombre' => 'string',
        'tienda.eslogan' => 'string',
        'contacto.telefono' => 'string',
        'contacto.whatsapp' => 'string',
        'contacto.correo' => 'string',
        'contacto.direccion' => 'string',
        'contacto.horario' => 'string',
        'banco.nombre' => 'string',
        'banco.beneficiario' => 'string',
        'banco.clabe' => 'string',
        'banco.cuenta' => 'string',
        'banco.referencia_instrucciones' => 'string',
        'recoger.direccion' => 'string',
        'recoger.horario' => 'string',
        'politicas.envios' => 'string',
        'politicas.devoluciones' => 'string',
        'politicas.privacidad' => 'string',
        'politicas.terminos' => 'string',
        'home.faq' => 'json',
        'home.beneficios' => 'json',
        'redes.facebook' => 'string',
        'redes.instagram' => 'string',
        'redes.tiktok' => 'string',
        'seo.descripcion' => 'string',
    ];

    /** @var array<string, string|null>|null */
    private ?array $memoria = null;

    /** @return array<string, string|null> valores crudos */
    public function todos(): array
    {
        if ($this->memoria !== null) {
            return $this->memoria;
        }

        return $this->memoria = Cache::rememberForever(self::CACHE_KEY, function () {
            try {
                if (! Schema::hasTable('configuraciones')) {
                    return [];
                }

                return Configuracion::query()->pluck('valor', 'clave')->all();
            } catch (Throwable) {
                return [];
            }
        });
    }

    public function get(string $clave, mixed $default = null): mixed
    {
        $valor = $this->todos()[$clave] ?? null;

        if ($valor === null || $valor === '') {
            return $default;
        }

        if ((self::CLAVES[$clave] ?? 'string') === 'json') {
            $decodificado = json_decode($valor, true);

            return is_array($decodificado) ? $decodificado : $default;
        }

        return $valor;
    }

    public function string(string $clave, ?string $default = null): ?string
    {
        $valor = $this->get($clave);

        return is_scalar($valor) && trim((string) $valor) !== '' ? trim((string) $valor) : $default;
    }

    /** @return array<mixed> */
    public function array(string $clave): array
    {
        $valor = $this->get($clave);

        if (is_array($valor)) {
            return $valor;
        }

        if (is_string($valor)) {
            $decodificado = json_decode($valor, true);

            return is_array($decodificado) ? $decodificado : [];
        }

        return [];
    }

    public function bool(string $clave, bool $default = false): bool
    {
        $valor = $this->get($clave);

        return $valor === null ? $default : filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    public function int(string $clave, ?int $default = null): ?int
    {
        $valor = $this->get($clave);

        return is_numeric($valor) ? (int) $valor : $default;
    }

    public function set(string $clave, mixed $valor): void
    {
        if (is_array($valor)) {
            $valor = json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } elseif (is_bool($valor)) {
            $valor = $valor ? '1' : '0';
        } elseif ($valor !== null) {
            $valor = trim((string) $valor);
            $valor = $valor === '' ? null : $valor;
        }

        Configuracion::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        $this->olvidar();
    }

    /** @param  array<string, mixed>  $valores */
    public function setMuchos(array $valores): void
    {
        foreach ($valores as $clave => $valor) {
            $this->set($clave, $valor);
        }
    }

    public function olvidar(): void
    {
        $this->memoria = null;
        Cache::forget(self::CACHE_KEY);
    }

    // ---------------------------------------------------------------- getters tipados

    public function nombre(): string
    {
        return $this->string('tienda.nombre', config('app.name', 'Mr Lana'));
    }

    /** @return array{telefono: ?string, whatsapp: ?string, correo: ?string, direccion: ?string, horario: ?string} */
    public function contacto(): array
    {
        return [
            'telefono' => $this->string('contacto.telefono'),
            'whatsapp' => $this->string('contacto.whatsapp'),
            'correo' => $this->string('contacto.correo'),
            'direccion' => $this->string('contacto.direccion'),
            'horario' => $this->string('contacto.horario'),
        ];
    }

    /** @return array{facebook: ?string, instagram: ?string, tiktok: ?string} */
    public function redes(): array
    {
        return [
            'facebook' => $this->string('redes.facebook'),
            'instagram' => $this->string('redes.instagram'),
            'tiktok' => $this->string('redes.tiktok'),
        ];
    }

    /**
     * Datos bancarios. SÓLO para: instrucciones del pedido del dueño, correo de instrucciones y admin.
     *
     * @return array{nombre: ?string, beneficiario: ?string, clabe: ?string, cuenta: ?string, referencia_instrucciones: ?string}
     */
    public function banco(): array
    {
        return [
            'nombre' => $this->string('banco.nombre'),
            'beneficiario' => $this->string('banco.beneficiario'),
            'clabe' => $this->string('banco.clabe'),
            'cuenta' => $this->string('banco.cuenta'),
            'referencia_instrucciones' => $this->string('banco.referencia_instrucciones'),
        ];
    }

    /** Hay datos mínimos para cobrar por transferencia (banco, beneficiario y CLABE válida). */
    public function bancoConfigurado(): bool
    {
        $banco = $this->banco();

        return filled($banco['nombre']) && filled($banco['beneficiario'])
            && $banco['clabe'] !== null && self::clabeValida($banco['clabe']);
    }

    /** @return array{direccion: ?string, horario: ?string} */
    public function recoger(): array
    {
        return [
            'direccion' => $this->string('recoger.direccion'),
            'horario' => $this->string('recoger.horario'),
        ];
    }

    /** @return array{envios: ?string, devoluciones: ?string, privacidad: ?string, terminos: ?string} */
    public function politicas(): array
    {
        return [
            'envios' => $this->string('politicas.envios'),
            'devoluciones' => $this->string('politicas.devoluciones'),
            'privacidad' => $this->string('politicas.privacidad'),
            'terminos' => $this->string('politicas.terminos'),
        ];
    }

    /**
     * Datos públicos mínimos (prop compartida Inertia `tienda`). Sin datos bancarios.
     *
     * @return array<string, mixed>
     */
    public function publico(): array
    {
        $contacto = $this->contacto();

        return [
            'nombre' => $this->nombre(),
            'eslogan' => $this->string('tienda.eslogan'),
            'contacto' => [
                'whatsapp' => $contacto['whatsapp'],
                'telefono' => $contacto['telefono'],
                'correo' => $contacto['correo'],
                'horario' => $contacto['horario'],
                'direccion' => $contacto['direccion'],
            ],
            'redes' => $this->redes(),
            'enlaces' => $this->enlaces(),
        ];
    }

    /**
     * Enlaces del pie de página: páginas `politicas.*` con contenido + FAQ si `home.faq` tiene datos.
     *
     * @return list<array{titulo: string, url: string}>
     */
    public function enlaces(): array
    {
        $enlaces = [];

        foreach (TiendaController::PAGINAS as $slug => $pagina) {
            if ($this->string($pagina['clave']) !== null) {
                $enlaces[] = ['titulo' => $pagina['titulo'], 'url' => route('tienda.pagina', $slug)];
            }
        }

        $faq = collect($this->array('home.faq'))
            ->filter(fn ($f) => is_array($f) && filled($f['pregunta'] ?? null) && filled($f['respuesta'] ?? null));

        if ($faq->isNotEmpty()) {
            $enlaces[] = ['titulo' => 'Preguntas frecuentes', 'url' => route('tienda.preguntas')];
        }

        return $enlaces;
    }

    /**
     * Valida una CLABE interbancaria: 18 dígitos + dígito verificador (pesos 3,7,1).
     */
    public static function clabeValida(string $clabe): bool
    {
        $clabe = preg_replace('/\s+/', '', $clabe);

        if (! preg_match('/^\d{18}$/', $clabe)) {
            return false;
        }

        $pesos = [3, 7, 1];
        $suma = 0;

        for ($i = 0; $i < 17; $i++) {
            $suma += ((int) $clabe[$i] * $pesos[$i % 3]) % 10;
        }

        $verificador = (10 - ($suma % 10)) % 10;

        return $verificador === (int) $clabe[17];
    }
}
