<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Suscripcion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SaaSController extends Controller
{
    /**
     * Display a listing of plans and subscriptions (Super Admin view).
     */
    public function index()
    {
        Gate::authorize('superadmin-access');

        $planes = Plan::orderBy('precio')->get();
        
        $suscripciones = Suscripcion::with(['empresa', 'plan'])
            ->orderBy('created_at', 'desc')
            ->get();

        $empresas = Empresa::with(['suscripciones.plan', 'usuarios'])
            ->withCount('usuarios')
            ->orderBy('nombre')
            ->get()
            ->map(function ($empresa) {
                $sub = $empresa->suscripciones->whereIn('estado', ['active', 'trialing', 'activo', 'demo'])->first()
                    ?? $empresa->suscripciones->first();
                $latestUser = $empresa->usuarios->sortByDesc('updated_at')->first();

                return [
                    'id' => $empresa->id,
                    'nombre' => $empresa->nombre,
                    'ruc' => $empresa->ruc,
                    'direccion' => $empresa->direccion,
                    'telefono' => $empresa->telefono,
                    'correo' => $empresa->correo,
                    'logo_url' => $empresa->logo_url,
                    'usuarios_count' => $empresa->usuarios_count,
                    'created_at' => $empresa->created_at,
                    'ultimo_acceso' => $latestUser ? $latestUser->updated_at : $empresa->updated_at,
                    'plan_nombre' => $sub && $sub->plan ? $sub->plan->nombre : 'Plan Estándar',
                    'estado_suscripcion' => $sub ? $sub->estado : 'activo',
                ];
            });

        $usuarios = \App\Models\Usuario::with('empresa')->orderBy('created_at', 'desc')->get();

        $stats = [
            'total_empresas' => $empresas->count(),
            'empresas_activas' => $suscripciones->whereIn('estado', ['active', 'activo'])->count() ?: $empresas->count(),
            'empresas_suspendidas' => $suscripciones->whereIn('estado', ['suspended', 'suspendido'])->count(),
            'total_usuarios' => $usuarios->count(),
            'licencias_activas' => $suscripciones->whereIn('estado', ['active', 'activo'])->count() ?: $empresas->count(),
            'licencias_vencidas' => $suscripciones->whereIn('estado', ['expired', 'vencido'])->count(),
            'ingresos_mensuales' => (float) $suscripciones->whereIn('estado', ['active', 'activo'])->sum(function($s) {
                return $s->plan ? (float) $s->plan->precio : 0.0;
            }),
        ];

        return Inertia('SaaS/Index', [
            'planes' => $planes,
            'suscripciones' => $suscripciones,
            'empresas' => $empresas,
            'usuarios' => $usuarios,
            'stats' => $stats,
        ]);
    }

    /**
     * Store a new subscription plan.
     */
    public function storePlan(Request $request)
    {
        Gate::authorize('superadmin-access');

        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:planes,nombre',
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'limite_usuarios' => 'required|integer|min:1',
            'limite_productos' => 'required|integer|min:1',
            'limite_sucursales' => 'required|integer|min:1',
            'caracteristicas' => 'nullable|array',
        ]);

        Plan::create($validated);

        return redirect()->route('saas.index')->with('success', 'Plan creado con éxito.');
    }

    /**
     * Update an existing subscription plan.
     */
    public function updatePlan(Request $request, Plan $plan)
    {
        Gate::authorize('superadmin-access');

        $validated = $request->validate([
            'nombre' => 'required|string|max:255|unique:planes,nombre,' . $plan->id,
            'descripcion' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'limite_usuarios' => 'required|integer|min:1',
            'limite_productos' => 'required|integer|min:1',
            'limite_sucursales' => 'required|integer|min:1',
            'caracteristicas' => 'nullable|array',
        ]);

        $plan->update($validated);

        return redirect()->route('saas.index')->with('success', 'Plan actualizado con éxito.');
    }

    /**
     * Store a new subscription for a company.
     */
    public function storeSuscripcion(Request $request)
    {
        Gate::authorize('superadmin-access');

        $validated = $request->validate([
            'empresa_id' => 'required|exists:empresas,id',
            'plan_id' => 'required|exists:planes,id',
            'estado' => 'required|in:trialing,active,suspended,expired',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'metodo_pago' => 'required|string|max:255',
        ]);

        Suscripcion::create($validated);

        return redirect()->route('saas.index')->with('success', 'Suscripción asignada con éxito.');
    }

    /**
     * Update a company's subscription.
     */
    public function updateSuscripcion(Request $request, Suscripcion $suscripcion)
    {
        Gate::authorize('superadmin-access');

        $validated = $request->validate([
            'plan_id' => 'required|exists:planes,id',
            'estado' => 'required|in:trialing,active,suspended,expired',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'metodo_pago' => 'required|string|max:255',
        ]);

        $suscripcion->update($validated);

        return redirect()->route('saas.index')->with('success', 'Suscripción actualizada con éxito.');
    }

    /**
     * Start impersonating a company as a Super Admin.
     */
    public function impersonate(Request $request, Empresa $empresa)
    {
        Gate::authorize('superadmin-access');

        $request->session()->put('impersonated_empresa_id', $empresa->id);

        return redirect()->route('pos')->with('success', 'Administrando empresa: ' . $empresa->nombre);
    }

    /**
     * Stop impersonating and return to Super Admin global console.
     */
    public function stopImpersonating(Request $request)
    {
        Gate::authorize('superadmin-access');

        $request->session()->forget('impersonated_empresa_id');

        if ($request->input('redirect_to') === 'empresas') {
            return redirect()->route('saas.index', ['tab' => 'empresas'])->with('success', 'Seleccione la empresa que desea administrar.');
        }

        return redirect()->route('saas.index')->with('success', 'Regresó al Panel Global de INVENTEX.');
    }
}
