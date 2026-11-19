<?php
//http://inventex.test
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\VentaController;
use App\Http\Controllers\KardexController;
use App\Http\Controllers\SaaSController;
use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();
        if ($user->rol === 'super_admin') {
            return redirect()->route('saas.index');
        }
        return redirect()->route('pos');
    }
    return redirect()->route('login');
});

// =========================================================================
// 1. RUTAS DEL SUPER ADMIN (Plataforma Global INVENTEX SaaS)
// =========================================================================
Route::middleware(['auth', 'can:superadmin-access'])->group(function () {
    Route::get('/saas', [SaaSController::class, 'index'])->name('saas.index');
    Route::post('/saas/planes', [SaaSController::class, 'storePlan'])->name('saas.storePlan');
    Route::put('/saas/planes/{plan}', [SaaSController::class, 'updatePlan'])->name('saas.updatePlan');
    Route::post('/saas/suscripciones', [SaaSController::class, 'storeSuscripcion'])->name('saas.storeSuscripcion');
    Route::put('/saas/suscripciones/{suscripcion}', [SaaSController::class, 'updateSuscripcion'])->name('saas.updateSuscripcion');
    Route::post('/saas/impersonate/{empresa}', [SaaSController::class, 'impersonate'])->name('saas.impersonate');
    Route::post('/saas/stop-impersonating', [SaaSController::class, 'stopImpersonating'])->name('saas.stopImpersonating');
});

// =========================================================================
// 2. RUTAS DE LA EMPRESA / BODEGA (Inquilinos / Multi-Tenant)
// =========================================================================
Route::middleware(['auth', 'tenant', 'subscription'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('categorias', CategoriaController::class)->except(['create', 'edit', 'show']);
    Route::resource('proveedores', ProveedorController::class)->except(['create', 'edit', 'show']);
    Route::resource('clientes', ClienteController::class)->except(['create', 'edit', 'show']);
    Route::resource('productos', ProductoController::class)->except(['create', 'edit', 'show']);
    Route::post('productos/{producto}/ajustar-stock', [ProductoController::class, 'ajustarStock'])->name('productos.ajustarStock');
    Route::post('productos/{producto}/duplicar', [ProductoController::class, 'duplicar'])->name('productos.duplicar');

    Route::resource('compras', CompraController::class)->only(['index', 'store', 'destroy']);
    Route::get('/pos', [VentaController::class, 'pos'])->name('pos');
    Route::get('/ventas/{venta}/edit-data', [VentaController::class, 'editData'])->name('ventas.edit-data');
    Route::resource('ventas', VentaController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/comprobantes/{filename}', [VentaController::class, 'showComprobante'])->name('comprobantes.show');
    Route::post('/clientes/quick-store', [ClienteController::class, 'quickStore'])->name('clientes.quickStore');
    Route::get('/kardex', [KardexController::class, 'index'])->name('kardex.index');

    // Rutas de Caja Diaria
    Route::get('/cajas', [\App\Http\Controllers\CajaController::class, 'index'])->name('cajas.index');
    Route::post('/cajas/apertura', [\App\Http\Controllers\CajaController::class, 'abrir'])->name('cajas.abrir');
    Route::post('/cajas/gasto', [\App\Http\Controllers\CajaController::class, 'registrarGasto'])->name('cajas.gasto');
    Route::post('/cajas/retiro', [\App\Http\Controllers\CajaController::class, 'registrarRetiro'])->name('cajas.retiro');
    Route::post('/cajas/ingreso', [\App\Http\Controllers\CajaController::class, 'registrarIngreso'])->name('cajas.ingreso');
    Route::post('/cajas/cierre', [\App\Http\Controllers\CajaController::class, 'cerrar'])->name('cajas.cerrar');
    Route::get('/cajas/historial', [\App\Http\Controllers\CajaController::class, 'historial'])->name('cajas.historial');
    Route::get('/cajas/reportes/exportar', [\App\Http\Controllers\CajaController::class, 'exportarFiltros'])->name('cajas.exportarFiltros');
    Route::get('/cajas/{caja}', [\App\Http\Controllers\CajaController::class, 'show'])->name('cajas.show');
    Route::get('/cajas/{caja}/reporte', [\App\Http\Controllers\CajaController::class, 'exportarReporte'])->name('cajas.exportarReporte');

    // Rutas del Asistente IA
    Route::post('/ai/chat', [\App\Http\Controllers\AIController::class, 'chat'])->name('ai.chat');
    Route::post('/ai/analyze', [\App\Http\Controllers\AIController::class, 'analyze'])->name('ai.analyze');

    // Perfil y Configuración de Empresa
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/empresa', [ProfileController::class, 'updateEmpresa'])->name('profile.updateEmpresa');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

