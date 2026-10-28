<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Listado paginado con filtros: search (nombre o correo), role y status.
     */
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'role' => ['nullable', Rule::in(['superadmin', 'admin', 'usuario'])],
            'status' => ['nullable', Rule::in(['activo', 'inactivo'])],
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $actor = $request->user();

        $usuarios = User::query()
            ->with('roles')
            ->when($request->filled('search'), function ($q) use ($request) {
                $texto = '%'.mb_strtolower($request->search).'%';
                $q->where(function ($q) use ($texto) {
                    $q->whereRaw('LOWER(name) LIKE ?', [$texto])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$texto]);
                });
            })
            ->when($request->filled('role'), fn ($q) => $q->role($request->role))
            ->when($request->status === 'activo', fn ($q) => $q->where('active', true))
            ->when($request->status === 'inactivo', fn ($q) => $q->where('active', false))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) $request->input('per_page', 10))
            ->through(fn (User $u) => $this->transformar($u, $actor));

        return response()->json($usuarios);
    }

    /**
     * Crea un usuario con una contraseña temporal: deberá cambiarla al iniciar sesión.
     */
    public function store(Request $request)
    {
        $actor = $request->user();

        // El superadmin crea administradores y usuarios; el admin, solo usuarios
        $rolesPermitidos = $actor->hasRole('superadmin') ? ['admin', 'usuario'] : ['usuario'];

        $datos = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => ['required', Rule::in($rolesPermitidos)],
            'password' => 'required|string|min:6|max:100',
        ]);

        $user = DB::transaction(function () use ($datos) {
            $nuevo = new User();
            $nuevo->forceFill([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => Hash::make($datos['password']),
                'active' => true,
                'must_change_password' => true,
            ])->save();

            $nuevo->assignRole($datos['role']);

            return $nuevo;
        });

        return response()->json(['user' => $this->transformar($user->load('roles'), $actor)], 201);
    }

    /**
     * Activa o desactiva una cuenta. Al desactivarla se cierran sus sesiones.
     */
    public function status(Request $request, User $user)
    {
        $request->validate(['active' => 'required|boolean']);
        $this->autorizar($request->user(), $user);

        $user->forceFill(['active' => $request->boolean('active')])->save();

        if (! $user->active) {
            $user->tokens()->delete();
        }

        return response()->json(['user' => $this->transformar($user->load('roles'), $request->user())]);
    }

    /**
     * Resetea el 2FA (QR) de un usuario que perdió su dispositivo.
     * Se cierran sus sesiones: el dispositivo perdido pudo quedar con una abierta.
     */
    public function resetTwoFactor(Request $request, User $user)
    {
        $this->autorizar($request->user(), $user);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestamp' => null,
        ])->save();

        $user->tokens()->delete();

        return response()->json(['user' => $this->transformar($user->load('roles'), $request->user())]);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Nadie se administra a sí mismo desde aquí. El superadmin puede con todos;
     * el admin solo con usuarios normales (no con otros admins ni superadmins).
     */
    private function puedeGestionar(User $actor, User $objetivo): bool
    {
        if ($objetivo->is($actor)) {
            return false;
        }

        if ($actor->hasRole('superadmin')) {
            return true;
        }

        return ! $objetivo->hasAnyRole(['admin', 'superadmin']);
    }

    private function autorizar(User $actor, User $objetivo): void
    {
        abort_unless($this->puedeGestionar($actor, $objetivo), 403, 'No puedes administrar a este usuario.');
    }

    private function transformar(User $u, User $actor): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->roles->first()?->name,
            'active' => (bool) $u->active,
            'two_factor_enabled' => $u->two_factor_confirmed_at !== null,
            'must_change_password' => (bool) $u->must_change_password,
            'puede_gestionar' => $this->puedeGestionar($actor, $u),
        ];
    }
}
