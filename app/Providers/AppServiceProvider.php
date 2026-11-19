<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Definir Gates para el control de accesos basado en Roles (RBAC)
        \Illuminate\Support\Facades\Gate::before(function (\App\Models\Usuario $user, $ability) {
            if ($user->isSuperAdmin()) {
                return true; // El Super Admin tiene acceso a todo
            }
        });

        \Illuminate\Support\Facades\Gate::define('superadmin-access', function (\App\Models\Usuario $user) {
            return $user->isSuperAdmin();
        });

        \Illuminate\Support\Facades\Gate::define('admin-only', function (\App\Models\Usuario $user) {
            return $user->isAdmin();
        });

        \Illuminate\Support\Facades\Gate::define('cajero-access', function (\App\Models\Usuario $user) {
            return $user->isCajero() || $user->isAdmin() || $user->isGerente();
        });

        \Illuminate\Support\Facades\Gate::define('almacenero-access', function (\App\Models\Usuario $user) {
            return $user->isAlmacenero() || $user->isAdmin() || $user->isGerente();
        });

        \Illuminate\Support\Facades\Gate::define('vendedor-access', function (\App\Models\Usuario $user) {
            return $user->isVendedor() || $user->isAdmin() || $user->isGerente() || $user->isEmpleado();
        });

        \Illuminate\Support\Facades\Gate::define('gerente-access', function (\App\Models\Usuario $user) {
            return $user->isGerente() || $user->isAdmin();
        });

        \Illuminate\Support\Facades\Gate::define('empleado-access', function (\App\Models\Usuario $user) {
            return $user->isEmpleado() || $user->isCajero() || $user->isAlmacenero() || $user->isVendedor() || $user->isGerente() || $user->isAdmin();
        });
    }
}
