<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Usuario;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'empresa_nombre' => 'required|string|max:255',
            'empresa_ruc' => 'required|string|size:11|unique:empresas,ruc',
            'nombre' => 'required|string|max:255',
            'correo' => 'required|string|lowercase|email|max:255|unique:usuarios,correo',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        // Procesar en una transacción
        $user = DB::transaction(function () use ($request) {
            // 1. Crear Empresa
            $empresa = Empresa::create([
                'nombre' => $request->empresa_nombre,
                'ruc' => $request->empresa_ruc,
                'tipo_negocio' => 'bodega', // default MVP
            ]);

            // 2. Crear Usuario Administrador asociado a la empresa
            $usuario = Usuario::create([
                'empresa_id' => $empresa->id,
                'nombre' => $request->nombre,
                'correo' => $request->correo,
                'password' => Hash::make($request->password),
                'rol' => 'administrador',
                'estado' => 'activo',
            ]);

            return $usuario;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('pos', absolute: false));
    }
}

