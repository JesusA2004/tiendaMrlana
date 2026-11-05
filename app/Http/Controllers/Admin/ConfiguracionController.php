<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MetodoPagoClave;
use App\Http\Controllers\Controller;
use App\Models\MetodoPago;
use App\Rules\ClabeValida;
use App\Services\Configuracion\ConfiguracionTienda;
use App\Services\Envios\EnvioService;
use App\Support\EstadosMexico;
use App\Support\TextoPlano;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Configuración de la tienda en pestañas. Todo se escribe con ConfiguracionTienda::set()
 * (invalida caché). Texto plano: se elimina HTML; los saltos de línea se conservan.
 */
class ConfiguracionController extends Controller
{
    public const SECCIONES = ['negocio', 'identidad', 'banco', 'recoger', 'politicas', 'home'];

    public function __construct(private ConfiguracionTienda $config) {}

    public function index(Request $request, EnvioService $envios): Response
    {
        $c = $this->config;

        $metodos = MetodoPago::query()
            ->whereIn('clave', MetodoPagoClave::values())
            ->withCount('pagos')
            ->get()
            ->sortBy(fn (MetodoPago $m) => array_search($m->clave, MetodoPagoClave::values(), true))
            ->values()
            ->map(fn (MetodoPago $m) => [
                'id' => $m->id,
                'clave' => $m->clave,
                'nombre' => $m->nombre,
                'activo' => (bool) $m->activo,
                'bloqueado' => $m->clave === MetodoPagoClave::Openpay->value,
                'pagos_count' => (int) $m->pagos_count,
                'configuracion' => [
                    'instrucciones' => $m->config('instrucciones'),
                    'horas_vencimiento' => $m->config('horas_vencimiento'),
                    'estados' => array_values((array) $m->config('estados', [])),
                    'monto_maximo' => $m->config('monto_maximo'),
                ],
            ]);

        $tab = in_array($request->query('tab'), [...self::SECCIONES, 'metodos'], true) ? (string) $request->query('tab') : 'negocio';

        return Inertia::render('Admin/Configuraciones/Index', [
            'tab' => $tab,
            'valores' => [
                'negocio' => [
                    // Nombre efectivo (el de config si aún no se guarda) para que el primer guardado no falle.
                    'nombre' => $c->nombre(),
                    'telefono' => $c->string('contacto.telefono'),
                    'whatsapp' => $c->string('contacto.whatsapp'),
                    'correo' => $c->string('contacto.correo'),
                    'direccion' => $c->string('contacto.direccion'),
                    'horario' => $c->string('contacto.horario'),
                ],
                'identidad' => [
                    'eslogan' => $c->string('tienda.eslogan'),
                    'facebook' => $c->string('redes.facebook'),
                    'instagram' => $c->string('redes.instagram'),
                    'tiktok' => $c->string('redes.tiktok'),
                    'seo_descripcion' => $c->string('seo.descripcion'),
                ],
                'banco' => $c->banco(),
                'recoger' => $c->recoger(),
                'politicas' => $c->politicas(),
                'home' => [
                    'faq' => array_values($c->array('home.faq')),
                    'beneficios' => array_values($c->array('home.beneficios')),
                ],
            ],
            'estado' => [
                'banco_configurado' => $c->bancoConfigurado(),
                'recoger_disponible' => $envios->recogerDisponible(),
                'pagos_en_linea' => (bool) config('payments.enabled', false),
            ],
            'metodos' => $metodos,
            'estadosMexico' => EstadosMexico::LISTA,
            // Mismos nombres que resources/js/components/public/BeneficioIcono.vue.
            'iconosBeneficio' => ['truck', 'shield-check', 'badge-check', 'wallet', 'banknote', 'headset', 'heart-handshake',
                'store', 'home', 'map-pin', 'package-check', 'package-search', 'clock', 'sparkles'],
        ]);
    }

    public function update(Request $request, string $seccion): RedirectResponse
    {
        $valores = match ($seccion) {
            'negocio' => $this->negocio($request),
            'identidad' => $this->identidad($request),
            'banco' => $this->banco($request),
            'recoger' => $this->recoger($request),
            'politicas' => $this->politicas($request),
            'home' => $this->home($request),
        };

        $this->config->setMuchos($valores);

        $mensaje = match ($seccion) {
            'banco' => $this->config->bancoConfigurado()
                ? 'Datos bancarios guardados. Los clientes ya verán las instrucciones de transferencia.'
                : 'Datos bancarios guardados, pero aún faltan banco, beneficiario o CLABE.',
            default => 'Configuración guardada.',
        };

        return to_route('admin.configuraciones.index', ['tab' => $seccion])->with('success', $mensaje);
    }

    // ---------------------------------------------------------------- secciones

    /** @return array<string, mixed> */
    private function negocio(Request $request): array
    {
        $d = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s()-]{7,30}$/'],
            'whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^\+?\d{10,15}$/'],
            'correo' => ['nullable', 'email', 'max:160'],
            'direccion' => ['nullable', 'string', 'max:300'],
            'horario' => ['nullable', 'string', 'max:300'],
        ], [
            'whatsapp.regex' => 'Escribe el WhatsApp sólo con dígitos y lada (10 a 15 dígitos, p. ej. 5217771234567).',
            'telefono.regex' => 'El teléfono sólo admite dígitos, espacios, paréntesis, guiones y +.',
        ]);

        return [
            'tienda.nombre' => TextoPlano::linea($d['nombre']),
            'contacto.telefono' => TextoPlano::limpiar($d['telefono'] ?? null, false),
            'contacto.whatsapp' => TextoPlano::limpiar($d['whatsapp'] ?? null, false),
            'contacto.correo' => isset($d['correo']) ? mb_strtolower(trim($d['correo'])) : null,
            'contacto.direccion' => TextoPlano::limpiar($d['direccion'] ?? null),
            'contacto.horario' => TextoPlano::limpiar($d['horario'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    private function identidad(Request $request): array
    {
        $url = ['nullable', 'string', 'max:255', 'url:https'];

        $d = $request->validate([
            'eslogan' => ['nullable', 'string', 'max:160'],
            'facebook' => $url,
            'instagram' => $url,
            'tiktok' => $url,
            'seo_descripcion' => ['nullable', 'string', 'max:300'],
        ], [
            'facebook.url' => 'Usa la dirección completa con https://',
            'instagram.url' => 'Usa la dirección completa con https://',
            'tiktok.url' => 'Usa la dirección completa con https://',
        ]);

        return [
            'tienda.eslogan' => TextoPlano::limpiar($d['eslogan'] ?? null, false),
            'redes.facebook' => $d['facebook'] ?? null,
            'redes.instagram' => $d['instagram'] ?? null,
            'redes.tiktok' => $d['tiktok'] ?? null,
            'seo.descripcion' => TextoPlano::limpiar($d['seo_descripcion'] ?? null, false),
        ];
    }

    /** @return array<string, mixed> */
    private function banco(Request $request): array
    {
        $request->merge([
            'clabe' => preg_replace('/\s+/', '', (string) $request->input('clabe', '')) ?: null,
            'cuenta' => preg_replace('/\s+/', '', (string) $request->input('cuenta', '')) ?: null,
        ]);

        $d = $request->validate([
            'nombre' => ['nullable', 'string', 'max:80'],
            'beneficiario' => ['nullable', 'string', 'max:160'],
            'clabe' => ['nullable', 'string', new ClabeValida],
            'cuenta' => ['nullable', 'string', 'regex:/^\d{6,20}$/'],
            'referencia_instrucciones' => ['nullable', 'string', 'max:600'],
        ], [
            'cuenta.regex' => 'La cuenta sólo admite dígitos (6 a 20).',
        ]);

        return [
            'banco.nombre' => TextoPlano::limpiar($d['nombre'] ?? null, false),
            'banco.beneficiario' => TextoPlano::limpiar($d['beneficiario'] ?? null, false),
            'banco.clabe' => $d['clabe'] ?? null,
            'banco.cuenta' => $d['cuenta'] ?? null,
            'banco.referencia_instrucciones' => TextoPlano::limpiar($d['referencia_instrucciones'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    private function recoger(Request $request): array
    {
        $d = $request->validate([
            'direccion' => ['nullable', 'string', 'max:300'],
            'horario' => ['nullable', 'string', 'max:300'],
        ]);

        return [
            'recoger.direccion' => TextoPlano::limpiar($d['direccion'] ?? null),
            'recoger.horario' => TextoPlano::limpiar($d['horario'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    private function politicas(Request $request): array
    {
        $texto = ['nullable', 'string', 'max:20000'];
        $d = $request->validate([
            'envios' => $texto,
            'devoluciones' => $texto,
            'privacidad' => $texto,
            'terminos' => $texto,
        ]);

        return [
            'politicas.envios' => TextoPlano::limpiar($d['envios'] ?? null),
            'politicas.devoluciones' => TextoPlano::limpiar($d['devoluciones'] ?? null),
            'politicas.privacidad' => TextoPlano::limpiar($d['privacidad'] ?? null),
            'politicas.terminos' => TextoPlano::limpiar($d['terminos'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    private function home(Request $request): array
    {
        $d = $request->validate([
            'faq' => ['nullable', 'array', 'max:30'],
            'faq.*.pregunta' => ['required', 'string', 'max:200'],
            'faq.*.respuesta' => ['required', 'string', 'max:1500'],
            'beneficios' => ['nullable', 'array', 'max:12'],
            'beneficios.*.titulo' => ['required', 'string', 'max:80'],
            'beneficios.*.descripcion' => ['nullable', 'string', 'max:240'],
            'beneficios.*.icono' => ['nullable', 'string', 'max:30', 'regex:/^[a-z0-9_-]+$/'],
        ], [
            'faq.*.pregunta.required' => 'Cada pregunta necesita texto.',
            'faq.*.respuesta.required' => 'Cada pregunta necesita su respuesta.',
            'beneficios.*.titulo.required' => 'Cada beneficio necesita un título.',
        ]);

        $faq = collect($d['faq'] ?? [])
            ->map(fn (array $f) => [
                'pregunta' => TextoPlano::linea($f['pregunta']),
                'respuesta' => (string) TextoPlano::limpiar($f['respuesta']),
            ])
            ->filter(fn (array $f) => $f['pregunta'] !== '' && $f['respuesta'] !== '')
            ->values()
            ->all();

        $beneficios = collect($d['beneficios'] ?? [])
            ->map(fn (array $b) => [
                'titulo' => TextoPlano::linea($b['titulo']),
                'descripcion' => (string) TextoPlano::limpiar($b['descripcion'] ?? null, false),
                'icono' => $b['icono'] ?? null,
            ])
            ->filter(fn (array $b) => $b['titulo'] !== '')
            ->values()
            ->all();

        return [
            'home.faq' => $faq === [] ? null : $faq,
            'home.beneficios' => $beneficios === [] ? null : $beneficios,
        ];
    }
}
