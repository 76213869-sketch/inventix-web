<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Crear Planes SaaS
        $planBasico = Plan::create([
            'nombre' => 'Básico',
            'descripcion' => 'Ideal para bodegas pequeñas que inician su digitalización.',
            'precio' => 19.90,
            'limite_usuarios' => 2,
            'limite_productos' => 100,
            'limite_sucursales' => 1,
            'caracteristicas' => ['kardex_basico', 'ventas_pos', 'alertas_stock'],
        ]);

        $planPro = Plan::create([
            'nombre' => 'Pro',
            'descripcion' => 'Para negocios en crecimiento con múltiples vendedores.',
            'precio' => 49.90,
            'limite_usuarios' => 5,
            'limite_productos' => 500,
            'limite_sucursales' => 3,
            'caracteristicas' => ['kardex_avanzado', 'ventas_pos', 'alertas_stock', 'roles_personalizados', 'soporte_email'],
        ]);

        $planPremium = Plan::create([
            'nombre' => 'Premium',
            'descripcion' => 'Sin límites para negocios consolidados y cadenas locales.',
            'precio' => 99.90,
            'limite_usuarios' => 15,
            'limite_productos' => 5000,
            'limite_sucursales' => 10,
            'caracteristicas' => ['kardex_completo', 'ventas_pos', 'alertas_stock', 'roles_personalizados', 'soporte_prioritario', 'reportes_exportables'],
        ]);

        // 2. Crear Super Administrador (Global, sin empresa_id)
        Usuario::create([
            'empresa_id' => null,
            'nombre' => 'Super Admin Inventex',
            'correo' => 'superadmin@inventex.com',
            'password' => Hash::make('password'),
            'rol' => 'super_admin',
            'estado' => 'activo',
        ]);

        // 3. Crear Empresa Demo
        $empresaDemo = Empresa::create([
            'nombre' => 'Bodega Don Pepe',
            'ruc' => '20123456789',
            'direccion' => 'Av. Larco 123, Miraflores, Lima',
            'telefono' => '987654321',
            'correo' => 'contacto@donpepe.com',
            'tipo_negocio' => 'bodega',
        ]);

        // Asociar Suscripción Demo (Plan Básico, activa por 1 año)
        $empresaDemo->suscripciones()->create([
            'plan_id' => $planBasico->id,
            'estado' => 'activo',
            'fecha_inicio' => now(),
            'fecha_fin' => now()->addYear(),
            'metodo_pago' => 'yape_plin',
        ]);

        // 4. Crear Usuario Administrador de Empresa Demo
        Usuario::create([
            'empresa_id' => $empresaDemo->id,
            'nombre' => 'Don Pepe',
            'correo' => 'donpepe@inventex.com',
            'password' => Hash::make('password'),
            'rol' => 'administrador',
            'estado' => 'activo',
        ]);

        // 5. Crear otros usuarios de prueba para la empresa demo
        Usuario::create([
            'empresa_id' => $empresaDemo->id,
            'nombre' => 'Cajero Demo',
            'correo' => 'cajero@donpepe.com',
            'password' => Hash::make('password'),
            'rol' => 'cajero',
            'estado' => 'activo',
        ]);
    }
}

