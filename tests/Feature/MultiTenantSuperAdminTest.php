<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenantSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected $empresa;
    protected $tenantAdmin;
    protected $superAdmin;
    protected $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::create([
            'nombre' => 'Plan Estandar',
            'descripcion' => 'Plan de prueba',
            'precio' => 29.90,
            'limite_usuarios' => 5,
            'limite_productos' => 100,
            'limite_sucursales' => 1,
            'caracteristicas' => json_encode([]),
        ]);

        $this->empresa = Empresa::create([
            'nombre' => 'Bodega Don Pepe',
            'ruc' => '20123456789',
            'direccion' => 'Av. Principal 123',
            'telefono' => '999888777',
            'correo' => 'donpepe@inventex.com',
            'tipo_negocio' => 'bodega',
        ]);

        Suscripcion::create([
            'empresa_id' => $this->empresa->id,
            'plan_id' => $this->plan->id,
            'estado' => 'active',
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addDays(30),
            'metodo_pago' => 'efectivo',
        ]);

        $this->tenantAdmin = Usuario::create([
            'empresa_id' => $this->empresa->id,
            'nombre' => 'Don Pepe Admin',
            'correo' => 'donpepe@inventex.com',
            'password' => \Hash::make('password'),
            'rol' => 'administrador',
            'estado' => 'activo',
        ]);

        $this->superAdmin = Usuario::create([
            'empresa_id' => null,
            'nombre' => 'Super Admin INVENTEX',
            'correo' => 'superadmin@inventex.com',
            'password' => \Hash::make('password'),
            'rol' => 'super_admin',
            'estado' => 'activo',
        ]);
    }

    public function test_super_admin_login_redirects_directly_to_saas_console(): void
    {
        $response = $this->post('/login', [
            'correo' => 'superadmin@inventex.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($this->superAdmin);
        $response->assertRedirect(route('saas.index'));
    }

    public function test_tenant_admin_login_redirects_to_pos(): void
    {
        $response = $this->post('/login', [
            'correo' => 'donpepe@inventex.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($this->tenantAdmin);
        $response->assertRedirect(route('pos'));
    }

    public function test_tenant_user_cannot_access_saas_console(): void
    {
        $this->actingAs($this->tenantAdmin);

        $response = $this->get(route('saas.index'));
        $response->assertStatus(403);
    }

    public function test_super_admin_cannot_access_retail_routes_without_impersonation(): void
    {
        $this->actingAs($this->superAdmin);

        // Accessing /pos directly without impersonating should redirect to /saas
        $responsePos = $this->get(route('pos'));
        $responsePos->assertRedirect(route('saas.index'));

        // Accessing /dashboard directly without impersonating should redirect to /saas
        $responseDash = $this->get(route('dashboard'));
        $responseDash->assertRedirect(route('saas.index'));
    }

    public function test_super_admin_can_impersonate_tenant_and_access_retail_routes(): void
    {
        $this->actingAs($this->superAdmin);

        // Start impersonating Don Pepe
        $response = $this->post(route('saas.impersonate', $this->empresa->id));
        $response->assertRedirect(route('pos'));
        $this->assertEquals($this->empresa->id, session('impersonated_empresa_id'));

        // Now super admin CAN access /pos with the impersonation session
        $responsePos = $this->get(route('pos'));
        $responsePos->assertStatus(200);
        $responsePos->assertInertia(fn ($page) => $page
            ->component('Ventas/POS')
            ->where('impersonated_empresa.id', $this->empresa->id)
            ->where('impersonated_empresa.nombre', 'Bodega Don Pepe')
        );
    }

    public function test_super_admin_stop_impersonating_redirects_to_saas_console(): void
    {
        $this->actingAs($this->superAdmin);
        session(['impersonated_empresa_id' => $this->empresa->id]);

        // Stop impersonating returning to general SaaS
        $response = $this->post(route('saas.stopImpersonating'));
        $response->assertRedirect(route('saas.index'));
        $this->assertFalse(session()->has('impersonated_empresa_id'));

        // Stop impersonating with redirect_to empresas
        session(['impersonated_empresa_id' => $this->empresa->id]);
        $responseTab = $this->post(route('saas.stopImpersonating'), ['redirect_to' => 'empresas']);
        $responseTab->assertRedirect(route('saas.index', ['tab' => 'empresas']));
        $this->assertFalse(session()->has('impersonated_empresa_id'));
    }
}