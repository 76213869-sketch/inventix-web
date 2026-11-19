<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\Empresa;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Receta;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected $empresa;
    protected $usuario;
    protected $planId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed plans since tenant/subscription middleware checks active subscriptions
        \DB::table('planes')->insert([
            'id' => $this->planId,
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

        // Create Empresa
        $this->empresa = Empresa::create([
            'nombre' => 'Bodega Test',
            'ruc' => '20123456789',
            'direccion' => 'Calle Falsa 123',
            'telefono' => '999999999',
            'correo' => 'bodega@test.com',
            'tipo_negocio' => 'bodega',
        ]);

        // Subscribe Empresa
        \DB::table('suscripciones')->insert([
            'empresa_id' => $this->empresa->id,
            'plan_id' => $this->planId,
            'estado' => 'activo',
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->addDays(30),
            'metodo_pago' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create Usuario
        $this->usuario = Usuario::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Cajero Test',
            'correo' => 'cajero@test.com',
            'password' => \Hash::make('password'),
            'rol' => 'administrador',
            'estado' => 'activo',
        ]);
    }

    public function test_compras_replenishes_stock(): void
    {
        $this->actingAs($this->usuario);

        $proveedor = Proveedor::create([
            'nombre' => 'Proveedor Mayorista',
            'ruc' => '10123456789',
            'direccion' => 'Av. Central 456',
            'telefono' => '123456',
            'correo' => 'mayorista@test.com',
            'estado' => 'activo',
        ]);

        $producto = Producto::create([
            'nombre' => 'Leche Gloria',
            'tipo_producto' => 'estandar',
            'unidad_medida' => 'unidad',
            'precio_compra' => 3.20,
            'precio_venta' => 4.50,
            'stock_minimo' => 5,
            'stock_actual' => 10,
            'estado' => 'activo',
        ]);

        $response = $this->post(route('compras.store'), [
            'proveedor_id' => $proveedor->id,
            'fecha_compra' => now()->toDateString(),
            'notas' => 'Compra inicial',
            'items' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 15,
                    'precio_unitario' => 3.00,
                ]
            ]
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('compras.index'));

        // Assert stock updated
        $this->assertEquals(25, $producto->fresh()->stock_actual);

        // Assert movement stock created
        $this->assertDatabaseHas('movimientos_stock', [
            'empresa_id' => $this->empresa->id,
            'producto_id' => $producto->id,
            'tipo' => 'entrada',
            'cantidad' => 15.00,
            'motivo' => 'compra',
        ]);

        // Assert Compra and detail exists
        $this->assertDatabaseHas('compras', [
            'empresa_id' => $this->empresa->id,
            'proveedor_id' => $proveedor->id,
            'total' => 45.00,
            'estado' => 'completada',
        ]);
    }

    public function test_pos_sale_deducts_stock(): void
    {
        $this->actingAs($this->usuario);

        $producto = Producto::create([
            'nombre' => 'Gaseosa Inka Cola 1L',
            'tipo_producto' => 'estandar',
            'unidad_medida' => 'unidad',
            'precio_compra' => 2.50,
            'precio_venta' => 3.50,
            'stock_minimo' => 5,
            'stock_actual' => 10,
            'estado' => 'activo',
        ]);

        $response = $this->post(route('ventas.store'), [
            'cliente_id' => null,
            'fecha_venta' => now()->toDateString(),
            'descuento' => 0.00,
            'metodo_pago' => 'efectivo',
            'items' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 4,
                    'precio_unitario' => 3.50,
                    'descuento' => 0.00,
                ]
            ]
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('pos'));

        // Assert stock decremented
        $this->assertEquals(6, $producto->fresh()->stock_actual);

        // Assert stock movement created
        $this->assertDatabaseHas('movimientos_stock', [
            'empresa_id' => $this->empresa->id,
            'producto_id' => $producto->id,
            'tipo' => 'salida',
            'cantidad' => 4.00,
            'motivo' => 'venta',
        ]);

        // Assert Venta and detail exists
        $this->assertDatabaseHas('ventas', [
            'empresa_id' => $this->empresa->id,
            'total' => 14.00,
            'metodo_pago' => 'efectivo',
            'estado' => 'completada',
        ]);
    }

    public function test_pos_sale_deducts_recipe_ingredients(): void
    {
        $this->actingAs($this->usuario);

        // Create 2 raw materials
        $insumoCarne = Producto::create([
            'nombre' => 'Carne de Res Hamburguesa',
            'tipo_producto' => 'insumo',
            'unidad_medida' => 'kilogramo',
            'precio_compra' => 15.00,
            'precio_venta' => 0.00,
            'stock_minimo' => 1.00,
            'stock_actual' => 5.00, // 5kg
            'estado' => 'activo',
        ]);

        $insumoPan = Producto::create([
            'nombre' => 'Pan de Hamburguesa',
            'tipo_producto' => 'insumo',
            'unidad_medida' => 'unidad',
            'precio_compra' => 0.30,
            'precio_venta' => 0.00,
            'stock_minimo' => 10,
            'stock_actual' => 20, // 20 units
            'estado' => 'activo',
        ]);

        // Create composite recipe product
        $platoReceta = Producto::create([
            'nombre' => 'Hamburguesa Clásica',
            'tipo_producto' => 'receta',
            'unidad_medida' => 'unidad',
            'precio_compra' => 2.50,
            'precio_venta' => 8.50,
            'stock_minimo' => 0,
            'stock_actual' => 0,
            'estado' => 'activo',
        ]);

        // Associate ingredients in recipe composition table
        Receta::create([
            'producto_padre_id' => $platoReceta->id,
            'producto_insumo_id' => $insumoCarne->id,
            'cantidad' => 0.150, // 150 grams per Burger
        ]);

        Receta::create([
            'producto_padre_id' => $platoReceta->id,
            'producto_insumo_id' => $insumoPan->id,
            'cantidad' => 1.00, // 1 Pan per Burger
        ]);

        // Sell 3 Hamburguesas
        $response = $this->post(route('ventas.store'), [
            'cliente_id' => null,
            'fecha_venta' => now()->toDateString(),
            'descuento' => 0.00,
            'metodo_pago' => 'yape_plin',
            'items' => [
                [
                    'producto_id' => $platoReceta->id,
                    'cantidad' => 3,
                    'precio_unitario' => 8.50,
                    'descuento' => 0.00,
                ]
            ]
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('pos'));

        // Assert raw material stocks decremented
        // Carne stock = 5 - (3 * 0.15) = 4.55
        $this->assertEquals(4.55, $insumoCarne->fresh()->stock_actual);
        // Pan stock = 20 - (3 * 1) = 17
        $this->assertEquals(17, $insumoPan->fresh()->stock_actual);

        // Assert stock movements generated for each ingredient
        $this->assertDatabaseHas('movimientos_stock', [
            'producto_id' => $insumoCarne->id,
            'tipo' => 'salida',
            'cantidad' => 0.45,
            'motivo' => 'venta',
        ]);

        $this->assertDatabaseHas('movimientos_stock', [
            'producto_id' => $insumoPan->id,
            'tipo' => 'salida',
            'cantidad' => 3.00,
            'motivo' => 'venta',
        ]);
    }

    public function test_pos_does_not_allow_sale_without_stock(): void
    {
        $this->actingAs($this->usuario);

        $producto = Producto::create([
            'nombre' => 'Chocolate Sublimo',
            'tipo_producto' => 'estandar',
            'unidad_medida' => 'unidad',
            'precio_compra' => 1.00,
            'precio_venta' => 1.50,
            'stock_minimo' => 2,
            'stock_actual' => 2,
            'estado' => 'activo',
        ]);

        // Sell 3 (which exceeds stock 2)
        $response = $this->post(route('ventas.store'), [
            'cliente_id' => null,
            'fecha_venta' => now()->toDateString(),
            'descuento' => 0.00,
            'metodo_pago' => 'efectivo',
            'items' => [
                [
                    'producto_id' => $producto->id,
                    'cantidad' => 3,
                    'precio_unitario' => 1.50,
                    'descuento' => 0.00,
                ]
            ]
        ]);

        // Assert validation exception (redirect back with errors)
        $response->assertSessionHasErrors('items');
        
        // Assert stock unchanged
        $this->assertEquals(2, $producto->fresh()->stock_actual);
    }
}
