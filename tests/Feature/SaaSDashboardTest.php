<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Suscripcion;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\ActividadSistema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaaSDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected $empresa;
    protected $adminUser;
    protected $superAdminUser;
    protected $planDemo;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed initial demo plan
        $this->planDemo = Plan::create([
            'nombre' => 'Plan Inicial Demo',
            'descripcion' => 'Demo Plan for testing',
            'precio' => 19.99,
            'limite_usuarios' => 5,
            'limite_productos' => 50,
            'limite_sucursales' => 1,
            'caracteristicas' => json_encode(['soporte_email']),
        ]);

        // 2. Create Empresa
        $this->empresa = Empresa::create([
            'nombre' => 'Mi Tienda Test',
            'ruc' => '20777777777',
            'direccion' => 'Av. Larco 123',
            'telefono' => '987654321',
            'correo' => 'mitienda@test.com',
            'tipo_negocio' => 'ferreteria',
        ]);

        // 3. Subscribe Empresa to Demo Plan
        \DB::table('suscripciones')->insert([
            'empresa_id' => $this->empresa->id,
            'plan_id' => $this->planDemo->id,
            'estado' => 'active',
            'fecha_inicio' => now()->subDays(2),
            'fecha_fin' => now()->addDays(28),
            'metodo_pago' => 'transferencia',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Create Standard Tenant Admin User
        $this->adminUser = Usuario::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Admin Pedro',
            'correo' => 'pedro@mitienda.com',
            'password' => \Hash::make('password'),
            'rol' => 'administrador',
            'estado' => 'activo',
        ]);

        // 5. Create Super Admin User (not bound to a specific tenant check, but gets bypassing)
        $this->superAdminUser = Usuario::create([
            'empresa_id' => null, // Super Admins don't belong to a tenant
            'nombre' => 'Super Admin Boss',
            'correo' => 'superadmin@inventex.com',
            'password' => \Hash::make('password'),
            'rol' => 'super_admin',
            'estado' => 'activo',
        ]);
    }

    /**
     * Test auditing logs automatically on Categoria creation, update, and deletion.
     */
    public function test_audit_trail_logging(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Create a Categoria and assert creation is audited
        $categoria = Categoria::create([
            'nombre' => 'Bebidas Alcoholicas',
            'descripcion' => 'Categoria de licores',
            'estado' => 'activo',
        ]);

        $this->assertDatabaseHas('actividad_sistema', [
            'empresa_id' => $this->empresa->id,
            'usuario_id' => $this->adminUser->id,
            'evento' => 'creacion',
            'tabla' => 'categorias',
            'registro_id' => $categoria->id,
        ]);

        // 2. Update Categoria and assert update is audited with delta values
        $categoria->update([
            'nombre' => 'Bebidas Gasificadas',
            'descripcion' => 'Gaseosas y aguas',
        ]);

        $this->assertDatabaseHas('actividad_sistema', [
            'empresa_id' => $this->empresa->id,
            'usuario_id' => $this->adminUser->id,
            'evento' => 'actualizacion',
            'tabla' => 'categorias',
            'registro_id' => $categoria->id,
        ]);

        $auditLog = ActividadSistema::where('evento', 'actualizacion')
            ->where('tabla', 'categorias')
            ->where('registro_id', $categoria->id)
            ->first();

        $this->assertNotNull($auditLog);
        $valoresAnteriores = $auditLog->valores_anteriores;
        $valoresNuevos = $auditLog->valores_nuevos;

        $this->assertEquals('Bebidas Alcoholicas', $valoresAnteriores['nombre']);
        $this->assertEquals('Categoria de licores', $valoresAnteriores['descripcion']);

        $this->assertEquals('Bebidas Gasificadas', $valoresNuevos['nombre']);
        $this->assertEquals('Gaseosas y aguas', $valoresNuevos['descripcion']);

        // 3. Delete Categoria and assert deletion is audited
        $categoria->delete();

        $this->assertDatabaseHas('actividad_sistema', [
            'empresa_id' => $this->empresa->id,
            'usuario_id' => $this->adminUser->id,
            'evento' => 'eliminacion',
            'tabla' => 'categorias',
            'registro_id' => $categoria->id,
        ]);
    }

    /**
     * Test SaaS console routes authentication and authorization rules.
     */
    public function test_saas_console_access_permissions(): void
    {
        // 1. Non-super admin gets 403 Forbidden
        $this->actingAs($this->adminUser);
        $response = $this->get(route('saas.index'));
        $response->assertStatus(403);

        // 2. Super admin gets 200 OK
        $this->actingAs($this->superAdminUser);
        $response = $this->get(route('saas.index'));
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SaaS/Index')
            ->has('planes')
            ->has('suscripciones')
            ->has('empresas')
        );
    }

    /**
     * Test Super Admin plan management CRUD (Store & Update).
     */
    public function test_saas_plan_creation_and_update(): void
    {
        $this->actingAs($this->superAdminUser);

        // 1. Create a Plan
        $response = $this->post(route('saas.storePlan'), [
            'nombre' => 'Plan Pro Plus',
            'descripcion' => 'Plan para medianas empresas',
            'precio' => 49.90,
            'limite_usuarios' => 15,
            'limite_productos' => 500,
            'limite_sucursales' => 3,
            'caracteristicas' => ['api_access', 'export_excel'],
        ]);

        $response->assertRedirect(route('saas.index'));
        $this->assertDatabaseHas('planes', [
            'nombre' => 'Plan Pro Plus',
            'precio' => 49.90,
            'limite_usuarios' => 15,
            'limite_productos' => 500,
            'limite_sucursales' => 3,
        ]);

        $plan = Plan::where('nombre', 'Plan Pro Plus')->first();

        // 2. Update the Plan
        $response = $this->put(route('saas.updatePlan', $plan->id), [
            'nombre' => 'Plan Pro Ultra',
            'descripcion' => 'Plan premium ilimitado',
            'precio' => 99.90,
            'limite_usuarios' => 100,
            'limite_productos' => 5000,
            'limite_sucursales' => 10,
            'caracteristicas' => ['api_access', 'export_excel', 'premium_support'],
        ]);

        $response->assertRedirect(route('saas.index'));
        $this->assertDatabaseHas('planes', [
            'id' => $plan->id,
            'nombre' => 'Plan Pro Ultra',
            'precio' => 99.90,
            'limite_usuarios' => 100,
            'limite_productos' => 5000,
            'limite_sucursales' => 10,
        ]);
    }

    /**
     * Test Super Admin subscription assignment and dates/states update.
     */
    public function test_saas_subscription_assignment_and_update(): void
    {
        $this->actingAs($this->superAdminUser);

        // 1. Create new Empresa for a new subscription
        $nuevaEmpresa = Empresa::create([
            'nombre' => 'Ferreteria Trujillo',
            'ruc' => '20600000001',
            'direccion' => 'Calle Real 456',
            'telefono' => '944333222',
            'correo' => 'trujillo@ferreteria.com',
            'tipo_negocio' => 'ferreteria',
        ]);

        // Assign a subscription
        $response = $this->post(route('saas.storeSuscripcion'), [
            'empresa_id' => $nuevaEmpresa->id,
            'plan_id' => $this->planDemo->id,
            'estado' => 'trialing',
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addDays(15)->toDateString(),
            'metodo_pago' => 'manual',
        ]);

        $response->assertRedirect(route('saas.index'));
        $this->assertDatabaseHas('suscripciones', [
            'empresa_id' => $nuevaEmpresa->id,
            'plan_id' => $this->planDemo->id,
            'estado' => 'trialing',
            'metodo_pago' => 'manual',
        ]);

        $suscripcion = Suscripcion::where('empresa_id', $nuevaEmpresa->id)->first();

        // 2. Update subscription dates and state
        $response = $this->put(route('saas.updateSuscripcion', $suscripcion->id), [
            'plan_id' => $this->planDemo->id,
            'estado' => 'active',
            'fecha_inicio' => now()->subDays(1)->toDateString(),
            'fecha_fin' => now()->addDays(45)->toDateString(),
            'metodo_pago' => 'tarjeta',
        ]);

        $response->assertRedirect(route('saas.index'));
        $this->assertDatabaseHas('suscripciones', [
            'id' => $suscripcion->id,
            'estado' => 'active',
            'metodo_pago' => 'tarjeta',
        ]);
    }

    /**
     * Test Dashboard KPIs math, low stock alert table, and recent system activities lists.
     */
    public function test_dashboard_operational_kpis_and_warnings(): void
    {
        $this->actingAs($this->adminUser);

        // 1. Create a Categoria
        $categoria = Categoria::create([
            'nombre' => 'Materiales de Construcción',
            'estado' => 'activo',
        ]);

        // 2. Create Products: 2 active, 1 inactive. One active product has low stock.
        $productoNormal = Producto::create([
            'nombre' => 'Martillo Truper 16oz',
            'categoria_id' => $categoria->id,
            'tipo_producto' => 'estandar',
            'unidad_medida' => 'unidad',
            'precio_compra' => 12.00,
            'precio_venta' => 18.00,
            'stock_minimo' => 5,
            'stock_actual' => 10,
            'estado' => 'activo',
        ]);

        $productoBajoStock = Producto::create([
            'nombre' => 'Bolsa Cemento Sol 42.5kg',
            'categoria_id' => $categoria->id,
            'tipo_producto' => 'estandar',
            'unidad_medida' => 'unidad',
            'precio_compra' => 20.00,
            'precio_venta' => 28.50,
            'stock_minimo' => 20,
            'stock_actual' => 5, // 5 <= 20 (LOW STOCK ALERT!)
            'estado' => 'activo',
        ]);

        $productoInactivo = Producto::create([
            'nombre' => 'Clavos de 2 pulgadas',
            'categoria_id' => $categoria->id,
            'tipo_producto' => 'estandar',
            'unidad_medida' => 'kilogramo',
            'precio_compra' => 2.00,
            'precio_venta' => 3.50,
            'stock_minimo' => 100,
            'stock_actual' => 2, // Low stock, but product is INACTIVE
            'estado' => 'inactivo',
        ]);

        // 3. Create transactions for today
        // Complete a Sale
        $venta = Venta::create([
            'usuario_id' => $this->adminUser->id,
            'cliente_id' => null,
            'numero_venta' => 'V-001',
            'fecha_venta' => now(),
            'subtotal' => 28.50,
            'impuesto' => 0.00,
            'descuento' => 0.00,
            'total' => 28.50,
            'metodo_pago' => 'efectivo',
            'estado' => 'completada',
        ]);

        // Complete an annulled Sale (should not sum up)
        $ventaAnulada = Venta::create([
            'usuario_id' => $this->adminUser->id,
            'cliente_id' => null,
            'numero_venta' => 'V-002',
            'fecha_venta' => now(),
            'subtotal' => 50.00,
            'impuesto' => 0.00,
            'descuento' => 0.00,
            'total' => 50.00,
            'metodo_pago' => 'efectivo',
            'estado' => 'anulada',
        ]);

        // Complete a Purchase
        $proveedor = Proveedor::create([
            'nombre' => 'Distribuidora Ferretera',
            'ruc' => '20111222333',
            'estado' => 'activo',
        ]);

        $compra = Compra::create([
            'usuario_id' => $this->adminUser->id,
            'proveedor_id' => $proveedor->id,
            'numero_compra' => 'C-001',
            'fecha_compra' => now(),
            'total' => 120.00,
            'estado' => 'completada',
        ]);

        // Complete a pending Purchase (should not sum up)
        $compraPendiente = Compra::create([
            'usuario_id' => $this->adminUser->id,
            'proveedor_id' => $proveedor->id,
            'numero_compra' => 'C-002',
            'fecha_compra' => now(),
            'total' => 450.00,
            'estado' => 'pendiente',
        ]);

        // 4. Access dashboard route
        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            // Check KPIs
            ->where('kpis.ventas_hoy', '28.50')
            ->where('kpis.compras_hoy', '120.00')
            ->where('kpis.total_productos', 2) // Martillo + Cemento (only active ones)
            ->where('kpis.bajo_stock_count', 1) // Only cemento (active & stock <= stock_minimo)
            
            // Check low-stock list
            ->has('productosBajoStock', 1)
            ->where('productosBajoStock.0.nombre', 'Bolsa Cemento Sol 42.5kg')
            
            // Check recent activities includes category & product creation events
            ->has('actividadesRecientes')
        );
    }
}
