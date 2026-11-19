<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
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
        $avatarUrl = null;
        $empresaLogoUrl = null;
        $cajaAbierta = null;
        $impersonatedEmpresa = null;
        $empresaNombre = 'Inventex';

        if ($user) {
            $cajaAbierta = \App\Models\Caja::where('usuario_id', $user->id)
                ->where('estado', 'abierta')
                ->first();

            $extensions = ['jpg', 'jpeg', 'png', 'webp'];
            foreach ($extensions as $ext) {
                $path = "avatars/user_{$user->id}.{$ext}";
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                    $avatarUrl = \Illuminate\Support\Facades\Storage::url($path);
                    break;
                }
            }

            // Check if Super Admin is impersonating a tenant/empresa
            if ($user->rol === 'super_admin' && $request->session()->has('impersonated_empresa_id')) {
                $impersonatedEmpresa = \App\Models\Empresa::find($request->session()->get('impersonated_empresa_id'));
                if ($impersonatedEmpresa) {
                    $empresaNombre = $impersonatedEmpresa->nombre;
                    foreach ($extensions as $ext) {
                        $path = "logos/empresa_{$impersonatedEmpresa->id}.{$ext}";
                        if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                            $empresaLogoUrl = \Illuminate\Support\Facades\Storage::url($path);
                            break;
                        }
                    }
                }
            } elseif ($user->empresa) {
                $empresaNombre = $user->empresa->nombre;
                foreach ($extensions as $ext) {
                    $path = "logos/empresa_{$user->empresa->id}.{$ext}";
                    if (\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
                        $empresaLogoUrl = \Illuminate\Support\Facades\Storage::url($path);
                        break;
                    }
                }
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'nombre' => $user->nombre,
                    'correo' => $user->correo,
                    'rol' => $user->rol,
                    'empresa_id' => $impersonatedEmpresa ? $impersonatedEmpresa->id : $user->empresa_id,
                    'empresa_nombre' => $empresaNombre,
                    'avatar_url' => $avatarUrl,
                    'empresa_logo_url' => $empresaLogoUrl,
                    // Compatibilidad con Breeze:
                    'name' => $user->nombre,
                    'email' => $user->correo,
                ] : null,
            ],
            'impersonated_empresa' => $impersonatedEmpresa ? [
                'id' => $impersonatedEmpresa->id,
                'nombre' => $impersonatedEmpresa->nombre,
                'ruc' => $impersonatedEmpresa->ruc,
            ] : null,
            'caja_abierta' => $cajaAbierta,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'created_venta' => fn () => $request->session()->get('created_venta'),
            ],
        ];
    }
}
