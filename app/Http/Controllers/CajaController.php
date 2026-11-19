<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\MovimientoCaja;
use App\Models\GastoCaja;
use App\Models\RetiroCaja;
use App\Models\IngresoCaja;
use App\Models\Venta;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

class CajaController extends Controller
{
    /**
     * Display the current cash register panel (Index).
     */
    public function index()
    {
        // Buscar caja abierta para el usuario actual del tenant
        $caja = Caja::where('usuario_id', auth()->id())
            ->where('estado', 'abierta')
            ->first();

        if (!$caja) {
            return Inertia::render('Cajas/Index', [
                'caja' => null,
                'movimientos' => [],
                'resumen' => null,
            ]);
        }

        // Obtener resumen de ventas asociadas a la caja por método de pago
        $ventas = Venta::where('caja_id', $caja->id)
            ->where('estado', 'completada')
            ->get();

        $ventasEfectivo = $ventas->where('metodo_pago', 'efectivo')->sum('total');
        $ventasYapePlin = $ventas->where('metodo_pago', 'yape_plin')->sum('total');
        $ventasTarjeta = $ventas->where('metodo_pago', 'tarjeta')->sum('total');
        $ventasTransferencia = $ventas->where('metodo_pago', 'transferencia')->sum('total');
        $totalVendido = $ventas->sum('total');

        $gastosRegistrados = GastoCaja::where('caja_id', $caja->id)->sum('monto');
        $retirosCaja = RetiroCaja::where('caja_id', $caja->id)->sum('monto');
        $ingresosAdicionales = IngresoCaja::where('caja_id', $caja->id)->sum('monto');

        // Caja esperada en efectivo (inicial + ventas efectivo + ingresos adicionales - gastos - retiros)
        $cajaEsperada = $caja->monto_inicial + $ventasEfectivo + $ingresosAdicionales - $gastosRegistrados - $retirosCaja;

        // Actualizar el monto esperado en base de datos para consistencia
        if ($caja->monto_esperado != $cajaEsperada) {
            $caja->update(['monto_esperado' => $cajaEsperada]);
        }

        $movimientos = MovimientoCaja::where('caja_id', $caja->id)
            ->with('usuario')
            ->orderBy('id', 'desc')
            ->get();

        return Inertia::render('Cajas/Index', [
            'caja' => $caja,
            'movimientos' => $movimientos,
            'resumen' => [
                'caja_inicial' => (float)$caja->monto_inicial,
                'ventas_efectivo' => (float)$ventasEfectivo,
                'ventas_yape_plin' => (float)$ventasYapePlin,
                'ventas_tarjeta' => (float)($ventasTarjeta + $ventasTransferencia),
                'total_vendido' => (float)$totalVendido,
                'gastos_registrados' => (float)$gastosRegistrados,
                'retiros_caja' => (float)$retirosCaja,
                'ingresos_adicionales' => (float)$ingresosAdicionales,
                'caja_esperada' => (float)$cajaEsperada,
            ]
        ]);
    }

    /**
     * Open a new cash register.
     */
    public function abrir(Request $request)
    {
        $request->validate([
            'monto_inicial' => 'required|numeric|min:0',
            'observacion' => 'nullable|string|max:1000',
        ]);

        // Verificar si ya existe una caja abierta para el cajero
        $exists = Caja::where('usuario_id', auth()->id())
            ->where('estado', 'abierta')
            ->exists();

        if ($exists) {
            return redirect()->back()->withErrors(['monto_inicial' => 'Ya cuenta con una caja abierta.']);
        }

        $caja = Caja::create([
            'usuario_id' => auth()->id(),
            'fecha_apertura' => now()->toDateString(),
            'hora_apertura' => now()->toTimeString(),
            'monto_inicial' => $request->monto_inicial,
            'monto_esperado' => $request->monto_inicial,
            'observacion_apertura' => $request->observacion,
            'estado' => 'abierta',
        ]);

        MovimientoCaja::create([
            'caja_id' => $caja->id,
            'usuario_id' => auth()->id(),
            'tipo' => 'apertura',
            'monto' => $request->monto_inicial,
            'descripcion' => 'Apertura de caja: ' . ($request->observacion ?: 'Monto inicial de efectivo'),
            'fecha' => now()->toDateString(),
            'hora' => now()->toTimeString(),
        ]);

        return redirect()->route('cajas.index')->with('success', 'Caja abierta con éxito.');
    }

    /**
     * Register a cash expense.
     */
    public function registrarGasto(Request $request)
    {
        $request->validate([
            'categoria' => 'required|in:Agua,Luz,Internet,Movilidad,Proveedor,Otros',
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'nullable|string|max:1000',
        ]);

        $caja = Caja::where('usuario_id', auth()->id())
            ->where('estado', 'abierta')
            ->firstOrFail();

        GastoCaja::create([
            'caja_id' => $caja->id,
            'usuario_id' => auth()->id(),
            'categoria' => $request->categoria,
            'monto' => $request->monto,
            'descripcion' => $request->descripcion,
        ]);

        $caja->decrement('monto_esperado', $request->monto);

        MovimientoCaja::create([
            'caja_id' => $caja->id,
            'usuario_id' => auth()->id(),
            'tipo' => 'gasto',
            'monto' => $request->monto,
            'descripcion' => "Gasto ({$request->categoria}): " . ($request->descripcion ?: 'Gasto registrado'),
            'fecha' => now()->toDateString(),
            'hora' => now()->toTimeString(),
        ]);

        return redirect()->route('cajas.index')->with('success', 'Gasto registrado con éxito.');
    }

    /**
     * Register a cash withdrawal.
     */
    public function registrarRetiro(Request $request)
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'motivo' => 'required|string|max:1000',
        ]);

        $caja = Caja::where('usuario_id', auth()->id())
            ->where('estado', 'abierta')
            ->firstOrFail();

        RetiroCaja::create([
            'caja_id' => $caja->id,
            'usuario_id' => auth()->id(),
            'monto' => $request->monto,
            'motivo' => $request->motivo,
        ]);

        $caja->decrement('monto_esperado', $request->monto);

        MovimientoCaja::create([
            'caja_id' => $caja->id,
            'usuario_id' => auth()->id(),
            'tipo' => 'retiro',
            'monto' => $request->monto,
            'descripcion' => 'Retiro de dinero: ' . $request->motivo,
            'fecha' => now()->toDateString(),
            'hora' => now()->toTimeString(),
        ]);

        return redirect()->route('cajas.index')->with('success', 'Retiro registrado con éxito.');
    }

    /**
     * Register an additional income.
     */
    public function registrarIngreso(Request $request)
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'descripcion' => 'required|string|max:1000',
        ]);

        $caja = Caja::where('usuario_id', auth()->id())
            ->where('estado', 'abierta')
            ->firstOrFail();

        IngresoCaja::create([
            'caja_id' => $caja->id,
            'usuario_id' => auth()->id(),
            'monto' => $request->monto,
            'descripcion' => $request->descripcion,
        ]);

        $caja->increment('monto_esperado', $request->monto);

        MovimientoCaja::create([
            'caja_id' => $caja->id,
            'usuario_id' => auth()->id(),
            'tipo' => 'ingreso',
            'monto' => $request->monto,
            'descripcion' => 'Ingreso adicional: ' . $request->descripcion,
            'fecha' => now()->toDateString(),
            'hora' => now()->toTimeString(),
        ]);

        return redirect()->route('cajas.index')->with('success', 'Ingreso adicional registrado con éxito.');
    }

    /**
     * Close the cash register.
     */
    public function cerrar(Request $request)
    {
        $caja = Caja::where('usuario_id', auth()->id())
            ->where('estado', 'abierta')
            ->firstOrFail();

        $request->validate([
            'monto_final' => 'required|numeric|min:0',
            'observacion' => 'nullable|string|max:1000',
            'motivo_diferencia' => 'nullable|string|max:1000|required_if:is_diferente,true',
        ]);

        $diferencia = $request->monto_final - $caja->monto_esperado;

        // Si la diferencia es menor a cero y falta motivo_diferencia
        if ($diferencia < 0 && !$request->motivo_diferencia) {
            return redirect()->back()->withErrors(['motivo_diferencia' => 'El motivo del faltante es obligatorio.']);
        }

        $caja->update([
            'fecha_cierre' => now()->toDateString(),
            'hora_cierre' => now()->toTimeString(),
            'monto_final' => $request->monto_final,
            'diferencia' => $diferencia,
            'motivo_diferencia' => $diferencia !== 0.0 ? $request->motivo_diferencia : null,
            'observacion_cierre' => $request->observacion,
            'estado' => 'cerrada',
        ]);

        MovimientoCaja::create([
            'caja_id' => $caja->id,
            'usuario_id' => auth()->id(),
            'tipo' => 'cierre',
            'monto' => $request->monto_final,
            'descripcion' => "Cierre de caja. Efectivo contado: " . number_format($request->monto_final, 2) . ". Diferencia: " . number_format($diferencia, 2),
            'fecha' => now()->toDateString(),
            'hora' => now()->toTimeString(),
        ]);

        return redirect()->route('cajas.index')->with('success', 'Caja cerrada con éxito.');
    }

    /**
     * Display a historical list of closed cash registers.
     */
    public function historial()
    {
        $cajas = Caja::with(['usuario', 'ventas', 'gastos', 'retiros', 'ingresos'])
            ->orderBy('fecha_apertura', 'desc')
            ->orderBy('hora_apertura', 'desc')
            ->get();

        $usuarios = Usuario::orderBy('nombre')->get();

        return Inertia::render('Cajas/History', [
            'cajas' => $cajas,
            'usuarios' => $usuarios,
        ]);
    }

    /**
     * Show details of a closed register session.
     */
    public function show(Caja $caja)
    {
        $caja->load(['usuario', 'movimientos.usuario', 'gastos', 'retiros', 'ingresos']);

        $ventas = Venta::where('caja_id', $caja->id)->with('cliente', 'usuario')->get();

        $productosVendidos = DB::table('detalle_ventas')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->where('ventas.caja_id', $caja->id)
            ->where('ventas.estado', 'completada')
            ->select(
                'productos.nombre',
                'productos.unidad_medida',
                DB::raw('SUM(detalle_ventas.cantidad) as cantidad'),
                DB::raw('SUM(detalle_ventas.subtotal) as total')
            )
            ->groupBy('productos.id', 'productos.nombre', 'productos.unidad_medida')
            ->orderBy('total', 'desc')
            ->get();

        $ventasEfectivo = $ventas->where('estado', 'completada')->where('metodo_pago', 'efectivo')->sum('total');
        $ventasYapePlin = $ventas->where('estado', 'completada')->where('metodo_pago', 'yape_plin')->sum('total');
        $ventasTarjeta = $ventas->where('estado', 'completada')->where('metodo_pago', 'tarjeta')->sum('total');
        $ventasTransferencia = $ventas->where('estado', 'completada')->where('metodo_pago', 'transferencia')->sum('total');
        
        $anulaciones = $ventas->where('estado', 'anulada')->count();
        $descuentos = $ventas->where('estado', 'completada')->sum('descuento');

        return Inertia::render('Cajas/Show', [
            'caja' => $caja,
            'productos_vendidos' => $productosVendidos,
            'resumen' => [
                'ventas_efectivo' => (float)$ventasEfectivo,
                'ventas_yape_plin' => (float)$ventasYapePlin,
                'ventas_tarjeta' => (float)($ventasTarjeta + $ventasTransferencia),
                'ventas_transferencia' => (float)$ventasTransferencia,
                'anulaciones' => $anulaciones,
                'descuentos' => (float)$descuentos,
            ]
        ]);
    }

    /**
     * Export reports (Excel/CSV or single PDF).
     */
    public function exportarReporte(Caja $caja)
    {
        $caja->load(['usuario', 'movimientos.usuario', 'gastos', 'retiros', 'ingresos']);

        $ventas = Venta::where('caja_id', $caja->id)->with('cliente', 'usuario')->get();

        $productosVendidos = DB::table('detalle_ventas')
            ->join('ventas', 'detalle_ventas.venta_id', '=', 'ventas.id')
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->where('ventas.caja_id', $caja->id)
            ->where('ventas.estado', 'completada')
            ->select(
                'productos.nombre',
                'productos.unidad_medida',
                DB::raw('SUM(detalle_ventas.cantidad) as cantidad'),
                DB::raw('SUM(detalle_ventas.subtotal) as total')
            )
            ->groupBy('productos.id', 'productos.nombre', 'productos.unidad_medida')
            ->get();

        $ventasEfectivo = $ventas->where('estado', 'completada')->where('metodo_pago', 'efectivo')->sum('total');
        $ventasYapePlin = $ventas->where('estado', 'completada')->where('metodo_pago', 'yape_plin')->sum('total');
        $ventasTarjeta = $ventas->where('estado', 'completada')->where('metodo_pago', 'tarjeta')->sum('total');
        $ventasTransferencia = $ventas->where('estado', 'completada')->where('metodo_pago', 'transferencia')->sum('total');

        $descuentos = $ventas->where('estado', 'completada')->sum('descuento');
        $anulacionesCount = $ventas->where('estado', 'anulada')->count();

        $pdf = Pdf::loadView('cajas.reporte', [
            'caja' => $caja,
            'productos_vendidos' => $productosVendidos,
            'ventas_efectivo' => $ventasEfectivo,
            'ventas_yape_plin' => $ventasYapePlin,
            'ventas_tarjeta' => $ventasTarjeta,
            'ventas_transferencia' => $ventasTransferencia,
            'descuentos' => $descuentos,
            'anulaciones' => $anulacionesCount,
            'empresa_nombre' => auth()->user()->empresa_nombre ?: 'Inventex POS'
        ]);

        $pdf->getDomPDF()->set_option('isRemoteEnabled', true);

        return $pdf->download("reporte_caja_sesion_{$caja->id}.pdf");
    }

    /**
     * Export general reports (Daily, Monthly, By Cajero) to Excel/CSV.
     */
    public function exportarFiltros(Request $request)
    {
        $tipo = $request->input('tipo', 'diario'); // diario, mensual, cajero
        $cajeroId = $request->input('usuario_id');
        $fechaInicio = $request->input('fecha_inicio', now()->toDateString());
        $fechaFin = $request->input('fecha_fin', now()->toDateString());

        $query = Caja::with('usuario');

        if ($tipo === 'diario') {
            $query->whereBetween('fecha_apertura', [$fechaInicio, $fechaFin]);
        } elseif ($tipo === 'mensual') {
            $month = date('m', strtotime($fechaInicio));
            $year = date('Y', strtotime($fechaInicio));
            $query->whereMonth('fecha_apertura', $month)->whereYear('fecha_apertura', $year);
        }

        if ($cajeroId) {
            $query->where('usuario_id', $cajeroId);
        }

        $cajas = $query->orderBy('fecha_apertura', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"reporte_cajas_{$tipo}_" . date('Ymd') . ".csv\"",
        ];

        $callback = function() use ($cajas) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header row
            fputcsv($file, [
                'ID Caja',
                'Cajero',
                'Fecha Apertura',
                'Hora Apertura',
                'Fecha Cierre',
                'Hora Cierre',
                'Monto Inicial (S/)',
                'Monto Esperado (S/)',
                'Monto Final (S/)',
                'Diferencia (S/)',
                'Estado'
            ], ';');

            foreach ($cajas as $c) {
                fputcsv($file, [
                    $c->id,
                    $c->usuario->nombre ?? 'Sistema',
                    $c->fecha_apertura->format('Y-m-d'),
                    $c->hora_apertura,
                    $c->fecha_cierre ? $c->fecha_cierre->format('Y-m-d') : '-',
                    $c->hora_cierre ?: '-',
                    number_format($c->monto_inicial, 2),
                    number_format($c->monto_esperado, 2),
                    $c->monto_final !== null ? number_format($c->monto_final, 2) : '-',
                    $c->diferencia !== null ? number_format($c->diferencia, 2) : '-',
                    ucfirst($c->estado)
                ], ';');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
