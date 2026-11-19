<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed plans since tenant/subscription middleware checks active subscriptions
        \DB::table('planes')->insert([
            'id' => 1,
            'nombre' => 'Plan Demo',
            'descripcion' => 'Demo Plan',
            'precio' => 0.00,
            'limite_usuarios' => 10,
            'limite_productos' => 100,
            'limite_sucursales' => 1,
            'caracteristicas' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_tenant_isolation_for_catalogs(): void
    {
        // 1. Create Empresa A
        $empresaA = Empresa::create([
            'nombre' => 'Empresa A',
            'ruc' => '20123456789',
            'direccion' => 'Calle A',
            'telefono' => '123',
            'correo' => 'a@test.com',
            'tipo_negocio' => 'bodega',
        ]);

        // Subscribe Empresa A
        \DB::table('suscripciones')->insert([
            'empresa_id' => $empresaA->id,
            'plan_id' => 1,
            'estado' => 'activo',
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->addDays(30),
            'metodo_pago' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create User for Empresa A
        $userA = Usuario::create([
            'empresa_id' => $empresaA->id,
            'nombre' => 'Admin A',
            'correo' => 'admin_a@test.com',
            'password' => \Hash::make('password'),
            'rol' => 'administrador',
            'estado' => 'activo',
        ]);

        // 2. Create Empresa B
        $empresaB = Empresa::create([
            'nombre' => 'Empresa B',
            'ruc' => '20987654321',
            'direccion' => 'Calle B',
            'telefono' => '987',
            'correo' => 'b@test.com',
            'tipo_negocio' => 'bodega',
        ]);

        // Subscribe Empresa B
        \DB::table('suscripciones')->insert([
            'empresa_id' => $empresaB->id,
            'plan_id' => 1,
            'estado' => 'activo',
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->addDays(30),
            'metodo_pago' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create User for Empresa B
        $userB = Usuario::create([
            'empresa_id' => $empresaB->id,
            'nombre' => 'Admin B',
            'correo' => 'admin_b@test.com',
            'password' => \Hash::make('password'),
            'rol' => 'administrador',
            'estado' => 'activo',
        ]);

        // 3. Create items for Empresa A under User A's session context
        $this->actingAs($userA);

        $catA = Categoria::create([
            'nombre' => 'Categoria Empresa A',
            'descripcion' => 'Desc A',
            'estado' => 'activo',
        ]);
        $this->assertEquals($empresaA->id, $catA->empresa_id);

        $provA = Proveedor::create([
            'nombre' => 'Proveedor A',
            'ruc' => '10123456789',
            'direccion' => 'Dir A',
            'telefono' => '123',
            'correo' => 'prov_a@test.com',
            'estado' => 'activo',
        ]);

        $cliA = Cliente::create([
            'nombre' => 'Cliente A',
            'documento_tipo' => 'DNI',
            'documento_numero' => '12345678',
            'direccion' => 'Dir Cli A',
            'telefono' => '123',
            'correo' => 'cli_a@test.com',
        ]);

        $prodA = Producto::create([
            'nombre' => 'Producto A',
            'categoria_id' => $catA->id,
            'tipo_producto' => 'estandar',
            'unidad_medida' => 'unidad',
            'precio_compra' => 10.00,
            'precio_venta' => 15.00,
            'stock_minimo' => 2,
            'stock_actual' => 10,
            'estado' => 'activo',
        ]);

        // 4. Create items for Empresa B under User B's session context
        $this->actingAs($userB);

        $catB = Categoria::create([
            'nombre' => 'Categoria Empresa B',
            'descripcion' => 'Desc B',
            'estado' => 'activo',
        ]);
        $this->assertEquals($empresaB->id, $catB->empresa_id);

        $provB = Proveedor::create([
            'nombre' => 'Proveedor B',
            'ruc' => '10987654321',
            'direccion' => 'Dir B',
            'telefono' => '987',
            'correo' => 'prov_b@test.com',
            'estado' => 'activo',
        ]);

        $cliB = Cliente::create([
            'nombre' => 'Cliente B',
            'documento_tipo' => 'DNI',
            'documento_numero' => '87654321',
            'direccion' => 'Dir Cli B',
            'telefono' => '987',
            'correo' => 'cli_b@test.com',
        ]);

        $prodB = Producto::create([
            'nombre' => 'Producto B',
            'categoria_id' => $catB->id,
            'tipo_producto' => 'estandar',
            'unidad_medida' => 'unidad',
            'precio_compra' => 20.00,
            'precio_venta' => 30.00,
            'stock_minimo' => 5,
            'stock_actual' => 20,
            'estado' => 'activo',
        ]);

        // 5. Test Isolation: Query catalogs in B
        $this->actingAs($userB);

        // Check Categoria
        $categoriasB = Categoria::all();
        $this->assertCount(1, $categoriasB);
        $this->assertEquals('Categoria Empresa B', $categoriasB->first()->nombre);

        // Check Proveedor
        $proveedoresB = Proveedor::all();
        $this->assertCount(1, $proveedoresB);
        $this->assertEquals('Proveedor B', $proveedoresB->first()->nombre);

        // Check Cliente
        $clientesB = Cliente::all();
        $this->assertCount(1, $clientesB);
        $this->assertEquals('Cliente B', $clientesB->first()->nombre);

        // Check Producto
        $productosB = Producto::all();
        $this->assertCount(1, $productosB);
        $this->assertEquals('Producto B', $productosB->first()->nombre);

        // 6. Test Isolation: Query catalogs in A
        $this->actingAs($userA);

        // Check Categoria
        $categoriasA = Categoria::all();
        $this->assertCount(1, $categoriasA);
        $this->assertEquals('Categoria Empresa A', $categoriasA->first()->nombre);

        // Check Proveedor
        $proveedoresA = Proveedor::all();
        $this->assertCount(1, $proveedoresA);
        $this->assertEquals('Proveedor A', $proveedoresA->first()->nombre);

        // Check Cliente
        $clientesA = Cliente::all();
        $this->assertCount(1, $clientesA);
        $this->assertEquals('Cliente A', $clientesA->first()->nombre);

        // Check Producto
        $productosA = Producto::all();
        $this->assertCount(1, $productosA);
        $this->assertEquals('Producto A', $productosA->first()->nombre);

        // 7. Test Controller Index Responses via Inertia
        $responseCat = $this->get(route('categorias.index'));
        $responseCat->assertStatus(200);
        $responseCat->assertInertia(fn ($page) => $page
            ->component('Categorias/Index')
            ->has('categorias', 1)
            ->where('categorias.0.nombre', 'Categoria Empresa A')
        );

        $responseProd = $this->get(route('productos.index'));
        $responseProd->assertStatus(200);
        $responseProd->assertInertia(fn ($page) => $page
            ->component('Productos/Index')
            ->has('productos', 1)
            ->where('productos.0.nombre', 'Producto A')
        );
    }
}
