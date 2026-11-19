<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DigitalReceiptTest extends TestCase
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

    public function test_can_quick_store_client(): void
    {
        $empresa = Empresa::create([
            'nombre' => 'Bodega Test',
            'ruc' => '20123456789',
            'tipo_negocio' => 'bodega',
        ]);

        \DB::table('suscripciones')->insert([
            'empresa_id' => $empresa->id,
            'plan_id' => 1,
            'estado' => 'activo',
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Cajero Test',
            'correo' => 'cajero@test.com',
            'password' => bcrypt('password'),
            'rol' => 'cajero',
            'estado' => 'activo',
        ]);

        $this->actingAs($user);

        // 1. Create a new client quickly
        $response = $this->post(route('clientes.quickStore'), [
            'nombre' => 'Juan WhatsApp',
            'telefono' => '987654321',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('clientes', [
            'nombre' => 'Juan WhatsApp',
            'telefono' => '987654321',
            'empresa_id' => $empresa->id,
        ]);

        $cliente = Cliente::where('telefono', '987654321')->first();

        // 2. Update existing client phone
        $responseUpdate = $this->post(route('clientes.quickStore'), [
            'cliente_id' => $cliente->id,
            'telefono' => '999888777',
        ]);

        $responseUpdate->assertStatus(200);
        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'telefono' => '999888777',
        ]);
    }

    public function test_can_serve_and_regenerate_receipt_pdf(): void
    {
        $empresa = Empresa::create([
            'nombre' => 'Bodega Test',
            'ruc' => '20123456789',
            'tipo_negocio' => 'bodega',
        ]);

        \DB::table('suscripciones')->insert([
            'empresa_id' => $empresa->id,
            'plan_id' => 1,
            'estado' => 'activo',
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Cajero Test',
            'correo' => 'cajero@test.com',
            'password' => bcrypt('password'),
            'rol' => 'cajero',
            'estado' => 'activo',
        ]);

        $this->actingAs($user);

        // Create a mock sale
        $venta = Venta::create([
            'empresa_id' => $empresa->id,
            'cliente_id' => null,
            'usuario_id' => $user->id,
            'numero_venta' => 'VEN-999999',
            'fecha_venta' => now()->format('Y-m-d'),
            'subtotal' => 10.00,
            'impuesto' => 0.00,
            'descuento' => 0.00,
            'total' => 10.00,
            'metodo_pago' => 'efectivo',
            'estado' => 'completada',
        ]);

        $filename = 'VENTA-999999.pdf';
        $filePath = public_path('comprobantes/' . $filename);

        // Delete if already exists
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $this->assertFalse(file_exists($filePath));

        // Get the PDF route, which should trigger dynamic generation
        $response = $this->get(route('comprobantes.show', $filename));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        // Confirm the file was written to disk
        $this->assertTrue(file_exists($filePath));

        // Cleanup
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function test_can_update_company_details_and_upload_logo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');

        $empresa = Empresa::create([
            'nombre' => 'Bodega Inicial',
            'ruc' => '20111111111',
            'tipo_negocio' => 'bodega',
        ]);

        \DB::table('suscripciones')->insert([
            'empresa_id' => $empresa->id,
            'plan_id' => 1,
            'estado' => 'activo',
            'fecha_inicio' => now()->subDays(5),
            'fecha_fin' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Admin Test',
            'correo' => 'admin@test.com',
            'password' => bcrypt('password'),
            'rol' => 'administrador',
            'estado' => 'activo',
        ]);

        $this->actingAs($user);

        // 1. Visit Profile Edit page and check Inertia props
        $response = $this->get(route('profile.edit'));
        $response->assertStatus(200);
        
        // 2. Perform POST to update company details and upload logo
        $fakeLogo = \Illuminate\Http\UploadedFile::fake()->create('mylogo.png', 100, 'image/png');

        $updateResponse = $this->post(route('profile.updateEmpresa'), [
            'nombre' => 'Bodega Don Pepe',
            'ruc' => '20999999999',
            'direccion' => 'Av. Larco 123',
            'telefono' => '999111222',
            'correo' => 'donpepe@gmail.com',
            'logo' => $fakeLogo,
        ]);

        $updateResponse->assertRedirect(route('profile.edit'));
        $updateResponse->assertSessionHas('success');

        // Confirm database has updated values
        $this->assertDatabaseHas('empresas', [
            'id' => $empresa->id,
            'nombre' => 'Bodega Don Pepe',
            'ruc' => '20999999999',
            'direccion' => 'Av. Larco 123',
            'telefono' => '999111222',
            'correo' => 'donpepe@gmail.com',
        ]);

        // Confirm logo file was saved to storage public disk
        \Illuminate\Support\Facades\Storage::disk('public')->assertExists("logos/empresa_{$empresa->id}.png");
    }
}
