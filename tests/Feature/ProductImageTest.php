<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageTest extends TestCase
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

    public function test_can_create_product_with_image_url(): void
    {
        $empresa = Empresa::create([
            'nombre' => 'Empresa Test',
            'ruc' => '20123456789',
            'tipo_negocio' => 'bodega',
        ]);

        \DB::table('suscripciones')->insert([
            'empresa_id' => $empresa->id,
            'plan_id' => 1,
            'estado' => 'activo',
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->addDays(30),
            'metodo_pago' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Admin Test',
            'correo' => 'admin@test.com',
            'password' => \Hash::make('password'),
            'rol' => 'administrador',
            'estado' => 'activo',
        ]);

        $this->actingAs($user);

        $cat = Categoria::create([
            'nombre' => 'Categoria A',
            'descripcion' => 'Desc A',
            'estado' => 'activo',
        ]);

        $externalUrl = 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e.jpg';

        $response = $this->post(route('productos.store'), [
            'nombre' => 'Audífonos Inalámbricos',
            'categoria_id' => $cat->id,
            'tipo_producto' => 'estandar',
            'tipo_venta' => 'unidad',
            'unidad_medida' => 'unidad',
            'precio_compra' => 50.00,
            'precio_venta' => 80.00,
            'stock_minimo' => 2,
            'stock_actual' => 15,
            'estado' => 'activo',
            'imagen_tipo' => 'url',
            'imagen_url' => $externalUrl,
        ]);

        $response->assertRedirect(route('productos.index'));

        $product = Producto::first();
        $this->assertNotNull($product);
        $this->assertEquals('url', $product->metadatos['imagen_tipo']);
        $this->assertEquals($externalUrl, $product->metadatos['imagen']);
        $this->assertEquals($externalUrl, $product->imagen_url);
    }

    public function test_can_update_product_image_from_file_to_url(): void
    {
        Storage::fake('public');

        $empresa = Empresa::create([
            'nombre' => 'Empresa Test',
            'ruc' => '20123456789',
            'tipo_negocio' => 'bodega',
        ]);

        \DB::table('suscripciones')->insert([
            'empresa_id' => $empresa->id,
            'plan_id' => 1,
            'estado' => 'activo',
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->addDays(30),
            'metodo_pago' => 'manual',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Admin Test',
            'correo' => 'admin@test.com',
            'password' => \Hash::make('password'),
            'rol' => 'administrador',
            'estado' => 'activo',
        ]);

        $this->actingAs($user);

        $cat = Categoria::create([
            'nombre' => 'Categoria A',
            'descripcion' => 'Desc A',
            'estado' => 'activo',
        ]);

        // 1. Create with uploaded file first (using pre-generated 1x1 PNG bytes to bypass GD dependency)
        $tempFile = tempnam(sys_get_temp_dir(), 'test_img');
        file_put_contents($tempFile, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
        $file = new UploadedFile($tempFile, 'producto.png', 'image/png', null, true);
        
        $response1 = $this->post(route('productos.store'), [
            'nombre' => 'Audífonos Inalámbricos',
            'categoria_id' => $cat->id,
            'tipo_producto' => 'estandar',
            'tipo_venta' => 'unidad',
            'unidad_medida' => 'unidad',
            'precio_compra' => 50.00,
            'precio_venta' => 80.00,
            'stock_minimo' => 2,
            'stock_actual' => 15,
            'estado' => 'activo',
            'imagen_tipo' => 'archivo',
            'imagen' => $file,
        ]);

        $product = Producto::first();
        $this->assertEquals('archivo', $product->metadatos['imagen_tipo']);
        $filePath = $product->metadatos['imagen'];
        Storage::disk('public')->assertExists($filePath);

        // 2. Update to external URL
        $externalUrl = 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e.jpg';
        
        $response2 = $this->put(route('productos.update', $product->id), [
            'nombre' => 'Audífonos Inalámbricos Pro',
            'categoria_id' => $cat->id,
            'tipo_producto' => 'estandar',
            'tipo_venta' => 'unidad',
            'unidad_medida' => 'unidad',
            'precio_compra' => 50.00,
            'precio_venta' => 90.00,
            'stock_minimo' => 2,
            'stock_actual' => 15,
            'estado' => 'activo',
            'imagen_tipo' => 'url',
            'imagen_url' => $externalUrl,
        ]);

        $product = $product->fresh();
        $this->assertEquals('url', $product->metadatos['imagen_tipo']);
        $this->assertEquals($externalUrl, $product->metadatos['imagen']);
        $this->assertEquals($externalUrl, $product->imagen_url);
        
        // Assert old physical file was deleted
        Storage::disk('public')->assertMissing($filePath);
    }
}
