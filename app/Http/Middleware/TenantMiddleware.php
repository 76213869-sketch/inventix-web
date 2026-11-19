<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            // El Super Admin NO pertenece a ninguna empresa.
            // Solo puede ingresar a módulos de empresa si está suplantando explícitamente a una para soporte.
            if ($user->isSuperAdmin()) {
                if (!$request->session()->has('impersonated_empresa_id')) {
                    return redirect()->route('saas.index');
                }
                return $next($request);
            }

            // Un usuario operativo debe pertenecer a una empresa
            if (!$user->empresa_id) {
                abort(403, 'Su cuenta de usuario no está asociada a ninguna empresa.');
            }

            // Verificar que la empresa asociada exista
            $empresa = $user->empresa;
            if (!$empresa) {
                abort(403, 'La empresa asociada a su cuenta no existe o ha sido dada de baja.');
            }
        }

        return $next($request);
    }
}
