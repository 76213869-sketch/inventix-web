<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SubscriptionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Validar suscripción solo para empresas (Super Admin está exento)
        if ($user && !$user->isSuperAdmin() && $user->empresa_id) {
            $empresa = $user->empresa;

            // Consultar si tiene una suscripción vigente (activo o demo o active o trialing)
            $suscripcionActiva = $empresa->suscripciones()
                ->whereIn('estado', ['activo', 'demo', 'active', 'trialing'])
                ->where('fecha_inicio', '<=', now())
                ->where('fecha_fin', '>=', now())
                ->exists();

            if (!$suscripcionActiva) {
                // Si la ruta solicitada es de suscripción expirada o de logout, permitir pasar
                if ($request->routeIs('suscripcion.expirada') || $request->routeIs('logout')) {
                    return $next($request);
                }

                abort(402, 'Suscripción inactiva o vencida. Por favor, renueve su plan en INVENTEX.');
            }
        }

        return $next($request);
    }
}
