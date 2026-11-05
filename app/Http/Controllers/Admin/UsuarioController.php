<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\User;
use App\Support\TextoPlano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Usuarios y roles. Nunca se envían contraseñas: al crear una cuenta se manda un enlace
 * de restablecimiento (Password::sendResetLink). Siempre debe quedar al menos un admin.
 */
class UsuarioController extends Controller
{
    private const ROLES = [User::ROL_ADMIN, User::ROL_VENDEDOR, User::ROL_CLIENTE];

    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $rol = in_array($request->query('rol'), self::ROLES, true) ? (string) $request->query('rol') : '';

        $usuarios = User::query()
            ->select('users.*')
            ->addSelect(['pedidos_count' => Pedido::query()->selectRaw('count(*)')->whereColumn('pedidos.user_id', 'users.id')])
            ->when($buscar !== '', function (Builder $q) use ($buscar) {
                $termino = '%'.str_replace(['%', '_'], ['\%', '\_'], $buscar).'%';
                $q->where(fn (Builder $s) => $s->where('name', 'like', $termino)->orWhere('email', 'like', $termino));
            })
            ->when($rol !== '', fn (Builder $q) => $q->where('rol', $rol))
            ->orderByRaw("case rol when 'admin' then 0 when 'vendedor' then 1 else 2 end")
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'rol' => $u->rol,
                'verificado' => $u->email_verified_at !== null,
                'pedidos_count' => (int) $u->pedidos_count,
                'created_at' => $u->created_at?->toIso8601String(),
                'es_yo' => $u->is($request->user()),
            ]);

        $conteos = User::query()->selectRaw('rol, count(*) as total')->groupBy('rol')->pluck('total', 'rol');

        return Inertia::render('Admin/Usuarios/Index', [
            'usuarios' => $usuarios,
            'filtros' => ['buscar' => $buscar, 'rol' => $rol],
            'conteos' => [
                'admin' => (int) ($conteos[User::ROL_ADMIN] ?? 0),
                'vendedor' => (int) ($conteos[User::ROL_VENDEDOR] ?? 0),
                'cliente' => (int) ($conteos[User::ROL_CLIENTE] ?? 0),
            ],
            'roles' => [
                ['value' => User::ROL_ADMIN, 'label' => 'Administrador', 'descripcion' => 'Acceso total: pagos, catálogo, configuración y usuarios.'],
                ['value' => User::ROL_VENDEDOR, 'label' => 'Vendedor', 'descripcion' => 'Opera pedidos y envíos. No aprueba pagos ni cancela.'],
                ['value' => User::ROL_CLIENTE, 'label' => 'Cliente', 'descripcion' => 'Compra en la tienda y consulta sus pedidos.'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'rol' => ['required', Rule::in(self::ROLES)],
        ]);

        $user = User::create([
            'name' => TextoPlano::linea($data['name']),
            'email' => $data['email'],
            'rol' => $data['rol'],
            // Contraseña aleatoria que nadie conoce: el usuario define la suya con el enlace.
            'password' => Str::password(40),
        ]);

        $estado = Password::sendResetLink(['email' => $user->email]);

        return back()->with(
            $estado === Password::RESET_LINK_SENT ? 'success' : 'error',
            $estado === Password::RESET_LINK_SENT
                ? 'Usuario creado. Enviamos a '.$user->email.' un enlace para que defina su contraseña.'
                : 'Usuario creado, pero no se pudo enviar el enlace de acceso: '.__($estado),
        );
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'rol' => ['required', Rule::in(self::ROLES)],
        ]);

        DB::transaction(function () use ($request, $user, $data) {
            if ($user->rol === User::ROL_ADMIN && $data['rol'] !== User::ROL_ADMIN) {
                if ($user->is($request->user())) {
                    throw ValidationException::withMessages(['rol' => 'No puedes quitarte tu propio rol de administrador.']);
                }

                if ($this->adminsRestantes($user) === 0) {
                    throw ValidationException::withMessages(['rol' => 'Debe quedar al menos un administrador.']);
                }
            }

            $cambioCorreo = $data['email'] !== $user->email;

            $user->fill([
                'name' => TextoPlano::linea($data['name']),
                'email' => $data['email'],
                'rol' => $data['rol'],
            ]);

            if ($cambioCorreo) {
                $user->email_verified_at = null;
            }

            $user->save();
        });

        return back()->with('success', 'Usuario actualizado.');
    }

    public function enviarEnlace(User $user): RedirectResponse
    {
        $estado = Password::sendResetLink(['email' => $user->email]);

        return $estado === Password::RESET_LINK_SENT
            ? back()->with('success', 'Enlace de acceso enviado a '.$user->email.'.')
            : back()->with('error', __($estado));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta desde aquí.');
        }

        if ($user->rol === User::ROL_ADMIN && $this->adminsRestantes($user) === 0) {
            return back()->with('error', 'No puedes eliminar al último administrador.');
        }

        if (Pedido::query()->where('user_id', $user->id)->exists()) {
            return back()->with('error', 'Este usuario tiene pedidos registrados; cambia su rol a cliente en lugar de eliminarlo.');
        }

        $user->delete();

        return back()->with('success', 'Usuario eliminado.');
    }

    /** Administradores que quedarían si $user dejara de serlo (con bloqueo para evitar carreras). */
    private function adminsRestantes(User $user): int
    {
        return User::query()
            ->where('rol', User::ROL_ADMIN)
            ->whereKeyNot($user->getKey())
            ->lockForUpdate()
            ->count();
    }
}
