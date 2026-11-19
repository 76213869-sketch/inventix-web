<?php

namespace App\Http\Controllers;

use App\Models\ActividadSistema;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\Cliente;
use App\Models\Proveedor;
use App\Models\DetalleVenta;
use App\Models\MovimientoStock;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $panel = $request->query('panel', 'control');
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        // -----------------------------------------------------------------
        // BASE METRICS (Fast aggregated calculations computed for fallbacks & panels)
        // -----------------------------------------------------------------
        $ventasHoy = Venta::where('estado', 'completada')->whereDate('fecha_venta', $today)->sum('total');
        $ventasAyer = Venta::where('estado', 'completada')->whereDate('fecha_venta', $yesterday)->sum('total');
        $kpiVentas = $this->getKPIStats($ventasHoy, $ventasAyer);

        $comprasHoy = Compra::where('estado', 'completada')->whereDate('fecha_compra', $today)->sum('total');
        $comprasAyer = Compra::where('estado', 'completada')->whereDate('fecha_compra', $yesterday)->sum('total');
        $kpiCompras = $this->getKPIStats($comprasHoy, $comprasAyer);

        $utilidadHoy = DetalleVenta::whereHas('venta', function($q) use ($today) {
                $q->where('estado', 'completada')->whereDate('fecha_venta', $today);
            })
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->selectRaw('SUM(detalle_ventas.cantidad * (detalle_ventas.precio_unitario - productos.precio_compra)) as total')
            ->value('total') ?? 0;

        $utilidadAyer = DetalleVenta::whereHas('venta', function($q) use ($yesterday) {
                $q->where('estado', 'completada')->whereDate('fecha_venta', $yesterday);
            })
            ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
            ->selectRaw('SUM(detalle_ventas.cantidad * (detalle_ventas.precio_unitario - productos.precio_compra)) as total')
            ->value('total') ?? 0;
        $kpiUtilidad = $this->getKPIStats($utilidadHoy, $utilidadAyer);

        $cajaHoy = Venta::where('estado', 'completada')->where('metodo_pago', 'efectivo')->sum('total') 
            - Compra::where('estado', 'completada')->sum('total');
        $cajaAyer = Venta::where('estado', 'completada')->where('metodo_pago', 'efectivo')->whereDate('fecha_venta', '<=', $yesterday)->sum('total') 
            - Compra::where('estado', 'completada')->whereDate('fecha_compra', '<=', $yesterday)->sum('total');
        $kpiCaja = $this->getKPIStats($cajaHoy, $cajaAyer);

        $prodHoy = Producto::where('estado', 'activo')->count();
        $prodAyer = Producto::where('estado', 'activo')->whereDate('created_at', '<', $today)->count();
        $kpiProductos = $this->getKPIStatsInt($prodHoy, $prodAyer);

        // Under stock calculations
        $productos = Producto::where('estado', 'activo')
            ->whereIn('tipo_producto', ['estandar', 'variante', 'insumo'])
            ->get(['id', 'stock_actual', 'stock_minimo']);

        $movsHoy = MovimientoStock::whereDate('created_at', $today)
            ->selectRaw("producto_id, SUM(CASE WHEN tipo = 'entrada' THEN cantidad WHEN tipo = 'salida' THEN -cantidad ELSE 0 END) as diff")
            ->groupBy('producto_id')
            ->pluck('diff', 'producto_id')
            ->toArray();

        $bajoStockHoy = 0;
        $bajoStockAyer = 0;
        foreach ($productos as $p) {
            $stockHoy = (float)$p->stock_actual;
            $diff = isset($movsHoy[$p->id]) ? (float)$movsHoy[$p->id] : 0.0;
            $stockAyer = $stockHoy - $diff;

            if ($stockHoy <= (float)$p->stock_minimo) {
                $bajoStockHoy++;
            }
            if ($stockAyer <= (float)$p->stock_minimo) {
                $bajoStockAyer++;
            }
        }
        $kpiBajoStock = $this->getKPIStatsInt($bajoStockHoy, $bajoStockAyer);

        $clientesHoy = Cliente::count();
        $clientesAyer = Cliente::whereDate('created_at', '<', $today)->count();
        $kpiClientes = $this->getKPIStatsInt($clientesHoy, $clientesAyer);

        $provHoy = Proveedor::count();
        $provAyer = Proveedor::whereDate('created_at', '<', $today)->count();
        $kpiProveedores = $this->getKPIStatsInt($provHoy, $provAyer);

        // Fallback variables required by tests and/or UI components
        $productosStockCritico = Producto::where('estado', 'activo')
            ->whereIn('tipo_producto', ['estandar', 'variante', 'insumo'])
            ->where('stock_actual', '>', 0)
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->orderBy('stock_actual')
            ->limit(5)
            ->get(['id', 'nombre', 'stock_actual', 'stock_minimo', 'unidad_medida']);

        $actividadesRecientes = ActividadSistema::with('usuario')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // -----------------------------------------------------------------
        // BASE RESPONSE STRUCTURE
        // -----------------------------------------------------------------
        $data = [
            'panel' => $panel,
            // Always return basic backward compatibility metrics for test stability
            'kpis' => [
                'ventas' => $kpiVentas,
                'compras' => $kpiCompras,
                'utilidad' => $kpiUtilidad,
                'caja' => $kpiCaja,
                'productos' => $kpiProductos,
                'bajo_stock' => $kpiBajoStock,
                'clientes' => $kpiClientes,
                'proveedores' => $kpiProveedores,
                'ventas_hoy' => $kpiVentas['value'],
                'compras_hoy' => $kpiCompras['value'],
                'total_productos' => $kpiProductos['value'],
                'bajo_stock_count' => $kpiBajoStock['value'],
            ],
            'productosBajoStock' => $productosStockCritico,
            'actividadesRecientes' => $actividadesRecientes,
            'alerts' => [],
        ];

        // System warnings / alerts for Ejecutivo & Control dashboards
        $recentSoldProductIds = DetalleVenta::whereHas('venta', function($q) {
                $q->where('estado', 'completada')->where('fecha_venta', '>=', now()->subDays(7)->toDateString());
            })
            ->pluck('producto_id')
            ->unique()
            ->toArray();

        $sinMovimientoCount = Producto::where('estado', 'activo')
            ->whereIn('tipo_producto', ['estandar', 'variante'])
            ->whereNotIn('id', $recentSoldProductIds)
            ->count();

        $comprasHoyCount = Compra::where('estado', 'completada')->whereDate('created_at', $today)->count();
        $ventasHoyCount = Venta::where('estado', 'completada')->whereDate('created_at', $today)->count();

        $alerts = [];
        if ($bajoStockHoy > 0) {
            $alerts[] = [
                'type' => 'critical',
                'title' => 'Stock Crítico',
                'message' => "Atención: Hay {$bajoStockHoy} productos con stock por debajo del mínimo.",
                'icon' => 'exclamation'
            ];
        }
        if ($sinMovimientoCount > 0) {
            $alerts[] = [
                'type' => 'warning',
                'title' => 'Sin Movimiento',
                'message' => "Hay {$sinMovimientoCount} productos activos sin ventas en la última semana.",
                'icon' => 'clock'
            ];
        }
        if ($comprasHoyCount > 0) {
            $alerts[] = [
                'type' => 'success',
                'title' => 'Abastecimiento',
                'message' => "Se registraron {$comprasHoyCount} compras de mercadería el día de hoy.",
                'icon' => 'plus'
            ];
        }
        if ($ventasHoyCount > 0) {
            $alerts[] = [
                'type' => 'info',
                'title' => 'Ventas Activas',
                'message' => "Se han concretado {$ventasHoyCount} ventas el día de hoy.",
                'icon' => 'shopping'
            ];
        }
        $data['alerts'] = $alerts;

        switch ($panel) {
            case 'control':
                // Dashboard Control Center main summary KPI values for modules
                $data['resumenCards'] = [
                    'ejecutivo' => [
                        'value' => 'S/ ' . number_format($ventasHoy, 2),
                        'subtext' => 'Ventas de hoy',
                        'trend' => $kpiVentas['trend'],
                        'pct' => $kpiVentas['pct']
                    ],
                    'financiero' => [
                        'value' => 'S/ ' . number_format($utilidadHoy, 2),
                        'subtext' => 'Utilidad del día',
                        'trend' => $kpiUtilidad['trend'],
                        'pct' => $kpiUtilidad['pct']
                    ],
                    'ventas' => [
                        'value' => Venta::where('estado', 'completada')->whereMonth('fecha_venta', now()->month)->count() . ' vtas.',
                        'subtext' => 'En este mes',
                        'trend' => 'neutral',
                        'pct' => '0'
                    ],
                    'inventario' => [
                        'value' => $bajoStockHoy . ' prod.',
                        'subtext' => 'Bajo stock crítico',
                        'trend' => $kpiBajoStock['trend'],
                        'pct' => $kpiBajoStock['pct']
                    ],
                    'compras' => [
                        'value' => Compra::where('estado', 'pendiente')->count() . ' ord.',
                        'subtext' => 'Compras pendientes',
                        'trend' => 'neutral',
                        'pct' => '0'
                    ],
                    'clientes' => [
                        'value' => $clientesHoy . ' reg.',
                        'subtext' => 'Clientes en total',
                        'trend' => $kpiClientes['trend'],
                        'pct' => $kpiClientes['pct']
                    ],
                    'proveedores' => [
                        'value' => $provHoy . ' reg.',
                        'subtext' => 'Proveedores activos',
                        'trend' => $kpiProveedores['trend'],
                        'pct' => $kpiProveedores['pct']
                    ],
                    'ia' => [
                        'value' => 'Activa',
                        'subtext' => 'Predicciones de stock',
                        'trend' => 'up',
                        'pct' => '100'
                    ]
                ];
                break;

            case 'ejecutivo':
                // 1 weekly sales vs purchases chart
                $chartVentasCompras = [];
                for ($i = 6; $i >= 0; $i--) {
                    $dateObj = now()->subDays($i);
                    $date = $dateObj->toDateString();
                    $dayName = $dateObj->locale('es')->minDayName;
                    $dayNum = $dateObj->format('d');
                    $dateLabel = ucfirst($dayName) . ' ' . $dayNum;

                    $ventas = Venta::where('estado', 'completada')->whereDate('fecha_venta', $date)->sum('total');
                    $compras = Compra::where('estado', 'completada')->whereDate('fecha_compra', $date)->sum('total');

                    $chartVentasCompras[] = [
                        'label' => $dateLabel,
                        'ventas' => (float)$ventas,
                        'compras' => (float)$compras,
                    ];
                }
                $data['chartVentasCompras'] = $chartVentasCompras;
                break;

            case 'ventas':
                // Sales grouped by day (last 30 days)
                $ventas30 = Venta::where('estado', 'completada')
                    ->whereDate('fecha_venta', '>=', now()->subDays(30)->toDateString())
                    ->selectRaw('DATE(fecha_venta) as fecha, SUM(total) as total')
                    ->groupBy('fecha')
                    ->orderBy('fecha')
                    ->get();
                $chartVentas30 = [];
                foreach ($ventas30 as $v) {
                    $chartVentas30[] = [
                        'label' => date('d/m', strtotime($v->fecha)),
                        'value' => (float)$v->total
                    ];
                }
                $data['chartVentas30'] = $chartVentas30;

                // Sales hourly distribution (last 30 days)
                $ventasHora = Venta::where('estado', 'completada')
                    ->whereDate('fecha_venta', '>=', now()->subDays(30)->toDateString())
                    ->selectRaw('HOUR(created_at) as hora, SUM(total) as total')
                    ->groupBy('hora')
                    ->orderBy('hora')
                    ->get()
                    ->pluck('total', 'hora')
                    ->toArray();
                $chartVentasHora = [];
                for ($h = 0; $h < 24; $h += 2) {
                    $val = 0.0;
                    if (isset($ventasHora[$h])) $val += (float)$ventasHora[$h];
                    if (isset($ventasHora[$h+1])) $val += (float)$ventasHora[$h+1];
                    $chartVentasHora[] = [
                        'label' => sprintf('%02d:00', $h),
                        'value' => $val
                    ];
                }
                $data['chartVentasHora'] = $chartVentasHora;

                // Methods of payment distribution
                $ventasPorMetodo = Venta::where('estado', 'completada')
                    ->selectRaw('metodo_pago, SUM(total) as total')
                    ->groupBy('metodo_pago')
                    ->get()
                    ->pluck('total', 'metodo_pago')
                    ->toArray();
                $data['chartVentasMetodoPago'] = [
                    ['label' => 'Efectivo', 'total' => (float)($ventasPorMetodo['efectivo'] ?? 0)],
                    ['label' => 'Tarjeta', 'total' => (float)($ventasPorMetodo['tarjeta'] ?? 0)],
                    ['label' => 'Transferencia', 'total' => (float)($ventasPorMetodo['transferencia'] ?? 0)],
                    ['label' => 'Yape / Plin', 'total' => (float)($ventasPorMetodo['yape_plin'] ?? 0)],
                ];

                // Top 10 products
                $data['chartProductosMasVendidos'] = DetalleVenta::whereHas('venta', fn($q) => $q->where('estado', 'completada'))
                    ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
                    ->selectRaw('productos.nombre, productos.unidad_medida, productos.tipo_venta, SUM(detalle_ventas.cantidad) as total_qty, SUM(detalle_ventas.subtotal) as total_amount')
                    ->groupBy('productos.id', 'productos.nombre', 'productos.unidad_medida', 'productos.tipo_venta')
                    ->orderByDesc('total_qty')
                    ->limit(10)
                    ->get()
                    ->map(fn($item) => [
                        'nombre' => $item->nombre,
                        'unidad_medida' => $item->unidad_medida,
                        'tipo_venta' => $item->tipo_venta,
                        'cantidad' => (float)$item->total_qty,
                        'total' => (float)$item->total_amount
                    ]);

                // Sellers ranking (by user sales total)
                $data['rankingVendedores'] = Venta::where('estado', 'completada')
                    ->selectRaw('usuario_id, SUM(total) as total, COUNT(id) as ventas_count')
                    ->groupBy('usuario_id')
                    ->orderByDesc('total')
                    ->with('usuario:id,nombre')
                    ->get()
                    ->map(fn($v) => [
                        'nombre' => $v->usuario->nombre ?? 'Vendedor',
                        'total' => (float)$v->total,
                        'cantidad' => $v->ventas_count
                    ]);

                // Ticket promedio
                $totalVentasM = Venta::where('estado', 'completada')->sum('total');
                $cantidadVentasM = Venta::where('estado', 'completada')->count();
                $data['ticketPromedio'] = $cantidadVentasM > 0 ? (float)($totalVentasM / $cantidadVentasM) : 0.0;

                // Sales by Category
                $data['chartVentasCategoria'] = DetalleVenta::whereHas('venta', fn($q) => $q->where('estado', 'completada'))
                    ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
                    ->join('categorias', 'productos.categoria_id', '=', 'categorias.id')
                    ->selectRaw('categorias.nombre, SUM(detalle_ventas.subtotal) as total')
                    ->groupBy('categorias.id', 'categorias.nombre')
                    ->orderByDesc('total')
                    ->get()
                    ->map(fn($item) => [
                        'categoria' => $item->nombre,
                        'total' => (float)$item->total
                    ]);

                // Frequent clients
                $data['clientesFrecuentes'] = Venta::where('estado', 'completada')
                    ->whereNotNull('cliente_id')
                    ->selectRaw('cliente_id, SUM(total) as total, COUNT(id) as count')
                    ->groupBy('cliente_id')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->with('cliente:id,nombre')
                    ->get()
                    ->map(fn($v) => [
                        'nombre' => $v->cliente->nombre ?? 'Cliente',
                        'total' => (float)$v->total,
                        'cantidad' => $v->count
                    ]);
                break;

            case 'compras':
                // Monthly purchase totals (last 6 months)
                $chartComprasMensual = [];
                for ($i = 5; $i >= 0; $i--) {
                    $monthStart = now()->subMonths($i)->startOfMonth();
                    $monthEnd = now()->subMonths($i)->endOfMonth();
                    $monthName = $monthStart->locale('es')->monthName;
                    $monthLabel = ucfirst(substr($monthName, 0, 3)) . ' ' . $monthStart->format('y');

                    $comprasMonth = Compra::where('estado', 'completada')
                        ->whereBetween('fecha_compra', [$monthStart->toDateString(), $monthEnd->toDateString()])
                        ->sum('total');

                    $chartComprasMensual[] = [
                        'label' => $monthLabel,
                        'value' => (float)$comprasMonth
                    ];
                }
                $data['chartComprasMensual'] = $chartComprasMensual;

                // Purchase KPIs
                $data['comprasMes'] = Compra::where('estado', 'completada')
                    ->whereMonth('fecha_compra', now()->month)
                    ->sum('total');
                $data['montoInvertidoTotal'] = Compra::where('estado', 'completada')->sum('total');

                // Top suppliers
                $data['proveedoresPrincipales'] = Compra::where('estado', 'completada')
                    ->selectRaw('proveedor_id, SUM(total) as total, COUNT(id) as count')
                    ->groupBy('proveedor_id')
                    ->orderByDesc('total')
                    ->limit(5)
                    ->with('proveedor:id,nombre')
                    ->get()
                    ->map(fn($c) => [
                        'nombre' => $c->proveedor->nombre ?? 'Proveedor',
                        'total' => (float)$c->total,
                        'cantidad' => $c->count
                    ]);

                // Last purchases
                $data['ultimasCompras'] = Compra::with(['proveedor', 'usuario'])
                    ->orderByDesc('created_at')
                    ->limit(8)
                    ->get();

                // Pending purchases list
                $data['comprasPendientes'] = Compra::with('proveedor')
                    ->where('estado', 'pendiente')
                    ->orderByDesc('created_at')
                    ->get();
                break;

            case 'inventario':
                // Low stock, critical, and out of stock lists
                $data['productosStockCritico'] = Producto::where('estado', 'activo')
                    ->whereIn('tipo_producto', ['estandar', 'variante', 'insumo'])
                    ->where('stock_actual', '>', 0)
                    ->whereColumn('stock_actual', '<=', 'stock_minimo')
                    ->with('categoria')
                    ->orderBy('stock_actual')
                    ->get();

                $data['productosAgotados'] = Producto::where('estado', 'activo')
                    ->whereIn('tipo_producto', ['estandar', 'variante', 'insumo'])
                    ->where('stock_actual', 0)
                    ->with('categoria')
                    ->get();

                // Rotacion ratios
                $entradas = MovimientoStock::where('tipo', 'entrada')->where('created_at', '>=', now()->subDays(30))->sum('cantidad');
                $salidas = MovimientoStock::where('tipo', 'salida')->where('created_at', '>=', now()->subDays(30))->sum('cantidad');
                $data['rotacion'] = [
                    'entradas' => (float)$entradas,
                    'salidas' => (float)$salidas
                ];

                // Stock valuation
                $data['valorizacionCosto'] = (float)Producto::where('estado', 'activo')->selectRaw('SUM(stock_actual * precio_compra) as total')->value('total') ?? 0.0;
                $data['valorizacionVenta'] = (float)Producto::where('estado', 'activo')->selectRaw('SUM(stock_actual * precio_venta) as total')->value('total') ?? 0.0;

                // Recent movements
                $data['movimientosRecientes'] = MovimientoStock::with(['producto', 'creador'])
                    ->orderByDesc('created_at')
                    ->limit(12)
                    ->get();
                break;

            case 'financiero':
                // Monthly Cash Flow evolution (last 6 months)
                $chartFinanciero = [];
                for ($i = 5; $i >= 0; $i--) {
                    $monthStart = now()->subMonths($i)->startOfMonth();
                    $monthEnd = now()->subMonths($i)->endOfMonth();
                    $monthName = $monthStart->locale('es')->monthName;
                    $monthLabel = ucfirst(substr($monthName, 0, 3)) . ' ' . $monthStart->format('y');

                    $ingresos = Venta::where('estado', 'completada')
                        ->whereBetween('fecha_venta', [$monthStart->toDateString(), $monthEnd->toDateString()])
                        ->sum('total');

                    $egresos = Compra::where('estado', 'completada')
                        ->whereBetween('fecha_compra', [$monthStart->toDateString(), $monthEnd->toDateString()])
                        ->sum('total');

                    $utilidad = DetalleVenta::whereHas('venta', function($query) use ($monthStart, $monthEnd) {
                            $query->where('estado', 'completada')->whereBetween('fecha_venta', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                        })->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
                        ->selectRaw('SUM(detalle_ventas.cantidad * (detalle_ventas.precio_unitario - productos.precio_compra)) as total')
                        ->value('total') ?? 0;

                    $chartFinanciero[] = [
                        'label' => $monthLabel,
                        'ingresos' => (float)$ingresos,
                        'egresos' => (float)$egresos,
                        'utilidad' => (float)$utilidad
                    ];
                }
                $data['chartFinanciero'] = $chartFinanciero;

                // Totals
                $data['balanceCaja'] = Venta::where('estado', 'completada')->where('metodo_pago', 'efectivo')->sum('total') 
                    - Compra::where('estado', 'completada')->sum('total');
                $data['totalIngresos'] = Venta::where('estado', 'completada')->sum('total');
                $data['totalEgresos'] = Compra::where('estado', 'completada')->sum('total');
                
                $utilidadTotal = DetalleVenta::whereHas('venta', fn($q) => $q->where('estado', 'completada'))
                    ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
                    ->selectRaw('SUM(detalle_ventas.cantidad * (detalle_ventas.precio_unitario - productos.precio_compra)) as total')
                    ->value('total') ?? 0;
                $data['utilidadTotal'] = (float)$utilidadTotal;
                $data['margenRentabilidad'] = $data['totalIngresos'] > 0 ? ($utilidadTotal / $data['totalIngresos']) * 100 : 0.0;
                break;

            case 'clientes':
                // New clients this month
                $data['clientesNuevos'] = Cliente::whereMonth('created_at', now()->month)
                    ->orderByDesc('created_at')
                    ->get();

                // Top purchasing clients
                $data['clientesVIP'] = Venta::where('estado', 'completada')
                    ->whereNotNull('cliente_id')
                    ->selectRaw('cliente_id, SUM(total) as total, COUNT(id) as count')
                    ->groupBy('cliente_id')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->with('cliente')
                    ->get()
                    ->map(fn($v) => [
                        'nombre' => $v->cliente->nombre ?? 'Cliente',
                        'email' => $v->cliente->email ?? '-',
                        'telefono' => $v->cliente->telefono ?? '-',
                        'total' => (float)$v->total,
                        'cantidad' => $v->count
                    ]);

                // Inactive clients (no purchases in last 30 days)
                $activeClientIds = Venta::where('estado', 'completada')
                    ->whereDate('fecha_venta', '>=', now()->subDays(30)->toDateString())
                    ->whereNotNull('cliente_id')
                    ->pluck('cliente_id')
                    ->unique()
                    ->toArray();
                $data['clientesInactivos'] = Cliente::whereNotIn('id', $activeClientIds)
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get();

                // Client registrations by month
                $chartRegistros = [];
                for ($i = 5; $i >= 0; $i--) {
                    $monthStart = now()->subMonths($i)->startOfMonth();
                    $monthEnd = now()->subMonths($i)->endOfMonth();
                    $monthName = $monthStart->locale('es')->monthName;
                    $monthLabel = ucfirst(substr($monthName, 0, 3)) . ' ' . $monthStart->format('y');

                    $count = Cliente::whereBetween('created_at', [$monthStart, $monthEnd])->count();
                    $chartRegistros[] = [
                        'label' => $monthLabel,
                        'value' => $count
                    ];
                }
                $data['chartRegistros'] = $chartRegistros;
                break;

            case 'proveedores':
                // Supplier rankings by order total
                $data['rankingProveedores'] = Compra::where('estado', 'completada')
                    ->selectRaw('proveedor_id, SUM(total) as total, COUNT(id) as count')
                    ->groupBy('proveedor_id')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->with('proveedor')
                    ->get()
                    ->map(fn($c) => [
                        'nombre' => $c->proveedor->nombre ?? 'Proveedor',
                        'ruc' => $c->proveedor->ruc ?? '-',
                        'telefono' => $c->proveedor->telefono ?? '-',
                        'total' => (float)$c->total,
                        'cantidad' => $c->count
                    ]);

                // Last purchases
                $data['ultimasCompras'] = Compra::with(['proveedor', 'usuario'])
                    ->orderByDesc('created_at')
                    ->limit(12)
                    ->get();
                
                // Purchase state counts
                $data['comprasPorEstado'] = [
                    'completadas' => Compra::where('estado', 'completada')->count(),
                    'pendientes' => Compra::where('estado', 'pendiente')->count(),
                    'anuladas' => Compra::where('estado', 'anulada')->count()
                ];
                break;

            case 'ia':
                // Local Predictive Heuristics Engine
                // 1. Sales forecast (next week)
                $semanas = [];
                for ($w = 3; $w >= 0; $w--) {
                    $start = now()->subWeeks($w + 1)->startOfWeek()->toDateString();
                    $end = now()->subWeeks($w + 1)->endOfWeek()->toDateString();
                    $semanas[] = Venta::where('estado', 'completada')->whereBetween('fecha_venta', [$start, $end])->sum('total');
                }
                $avgWeeklySales = array_sum($semanas) / 4;
                $diffWeekly = $semanas[3] - $semanas[0];
                $predictedSales = $avgWeeklySales + ($diffWeekly * 0.15); // Simple trend factor projection
                if ($predictedSales < 0) $predictedSales = $avgWeeklySales;
                $data['prediccionVentas'] = [
                    'historico_semanal' => $semanas,
                    'prediccion' => (float)$predictedSales,
                    'tendencia' => $diffWeekly >= 0 ? 'up' : 'down'
                ];

                // 2. Expiration and low-stock velocities
                $ventas30 = DetalleVenta::whereHas('venta', fn($q) => $q->where('estado', 'completada')->whereDate('fecha_venta', '>=', now()->subDays(30)->toDateString()))
                    ->selectRaw('producto_id, SUM(cantidad) as total_qty')
                    ->groupBy('producto_id')
                    ->pluck('total_qty', 'producto_id')
                    ->toArray();

                $recomendaciones = [];
                $pocoRentables = [];
                $productosEstrella = [];

                foreach ($productos as $p) {
                    $pFull = Producto::with('categoria')->find($p->id);
                    $qty30 = isset($ventas30[$p->id]) ? (float)$ventas30[$p->id] : 0.0;
                    $velocidadDiaria = $qty30 / 30.0;
                    
                    // Days coverage
                    if ($velocidadDiaria > 0) {
                        $coberturaDias = $pFull->stock_actual / $velocidadDiaria;
                        if ($coberturaDias <= 7 || $pFull->stock_actual <= $pFull->stock_minimo) {
                            $sugerido = ($velocidadDiaria * 30) - $pFull->stock_actual;
                            if ($sugerido > 0) {
                                $recomendaciones[] = [
                                    'producto' => $pFull->nombre,
                                    'categoria' => $pFull->categoria->nombre ?? 'General',
                                    'stock_actual' => (float)$pFull->stock_actual,
                                    'velocidad' => number_format($velocidadDiaria, 2),
                                    'cobertura_dias' => number_format($coberturaDias, 1),
                                    'sugerido' => ceil($sugerido)
                                ];
                            }
                        }
                    } elseif ($pFull->stock_actual <= $pFull->stock_minimo) {
                        // Recommend ordering at least a batch if stock is below min even if no recent sales velocity
                        $recomendaciones[] = [
                            'producto' => $pFull->nombre,
                            'categoria' => $pFull->categoria->nombre ?? 'General',
                            'stock_actual' => (float)$pFull->stock_actual,
                            'velocidad' => '0.00',
                            'cobertura_dias' => '0.0',
                            'sugerido' => ceil($pFull->stock_minimo * 2)
                        ];
                    }

                    // Profitability heuristic
                    if ($pFull->precio_compra > 0) {
                        $margen = ($pFull->precio_venta - $pFull->precio_compra) / $pFull->precio_compra;
                        if ($margen < 0.15) {
                            $pocoRentables[] = [
                                'nombre' => $pFull->nombre,
                                'precio_compra' => (float)$pFull->precio_compra,
                                'precio_venta' => (float)$pFull->precio_venta,
                                'margen_pct' => number_format($margen * 100, 1)
                            ];
                        }
                    }
                }
                $data['recomendacionesCompra'] = array_slice($recomendaciones, 0, 5);
                $data['productosPocoRentables'] = array_slice($pocoRentables, 0, 5);

                // Star Products by accumulated total profit
                $data['productosEstrella'] = DetalleVenta::whereHas('venta', fn($q) => $q->where('estado', 'completada'))
                    ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
                    ->selectRaw('productos.nombre, SUM(detalle_ventas.cantidad) as total_qty, SUM(detalle_ventas.cantidad * (detalle_ventas.precio_unitario - productos.precio_compra)) as total_profit')
                    ->groupBy('productos.id', 'productos.nombre')
                    ->orderByDesc('total_profit')
                    ->limit(3)
                    ->get()
                    ->map(fn($item) => [
                        'nombre' => $item->nombre,
                        'cantidad' => (float)$item->total_qty,
                        'utilidad' => (float)$item->total_profit
                    ]);

                // Risk of churn clients
                $data['clientesRiesgoAbandono'] = Venta::where('estado', 'completada')
                    ->whereNotNull('cliente_id')
                    ->selectRaw('cliente_id, MAX(fecha_venta) as ultima_fecha')
                    ->groupBy('cliente_id')
                    ->having('ultima_fecha', '<', now()->subDays(30)->toDateString())
                    ->with('cliente')
                    ->limit(5)
                    ->get()
                    ->map(fn($v) => [
                        'nombre' => $v->cliente->nombre ?? 'Cliente',
                        'telefono' => $v->cliente->telefono ?? '-',
                        'ultima_compra' => date('d/m/Y', strtotime($v->ultima_fecha))
                    ]);

                // Natural-Language AI Insights
                $insights = [
                    "IA: Predicción del clima de negocios activa. Se estima que las ventas de la próxima semana ascenderán a S/ " . number_format($predictedSales, 2) . ($diffWeekly >= 0 ? " con tendencia alcista." : " con tendencia bajista."),
                ];
                if (count($recomendaciones) > 0) {
                    $insights[] = "IA Alerta Stock: Hay " . count($recomendaciones) . " productos en riesgo de desabastecimiento. El producto '" . $recomendaciones[0]['producto'] . "' se agotará en aproximadamente " . $recomendaciones[0]['cobertura_dias'] . " días. Sugerimos ordenar " . $recomendaciones[0]['sugerido'] . " unidades.";
                }
                if (count($pocoRentables) > 0) {
                    $insights[] = "IA Alerta Finanzas: El producto '" . $pocoRentables[0]['nombre'] . "' está operando con un margen crítico de " . $pocoRentables[0]['margen_pct'] . "%. Sugerimos renegociar costos de adquisición o ajustar el precio de venta.";
                }
                if (count($data['clientesRiesgoAbandono']) > 0) {
                    $insights[] = "IA Alerta Clientes: Detectamos que " . count($data['clientesRiesgoAbandono']) . " clientes recurrentes no han comprado en los últimos 30 días, incluyendo a '" . $data['clientesRiesgoAbandono'][0]['nombre'] . "'. Recomendamos activar un cupón de descuento.";
                }
                if (count($data['productosEstrella']) > 0) {
                    $star = $data['productosEstrella'][0];
                    $insights[] = "IA Logística: El producto estrella del negocio es '" . $star['nombre'] . "', aportando S/ " . number_format($star['utilidad'], 2) . " en utilidad neta. Garantice la prioridad en el inventario.";
                }
                $data['consejosInteligentes'] = $insights;
                break;
        }

        return Inertia::render('Dashboard', $data);
    }

    private function getKPIStats($todayVal, $yesterdayVal)
    {
        $diff = $todayVal - $yesterdayVal;
        $trend = 'neutral';
        if ($diff > 0) {
            $trend = 'up';
        } elseif ($diff < 0) {
            $trend = 'down';
        }
        
        if ($yesterdayVal == 0) {
            $pct = $todayVal > 0 ? 100 : 0;
        } else {
            $pct = ($diff / $yesterdayVal) * 100;
        }
        
        return [
            'value' => number_format($todayVal, 2, '.', ''),
            'yesterday' => number_format($yesterdayVal, 2, '.', ''),
            'diff' => number_format(abs($diff), 2, '.', ''),
            'pct' => number_format($pct, 1, '.', ''),
            'trend' => $trend,
        ];
    }

    private function getKPIStatsInt($todayVal, $yesterdayVal)
    {
        $diff = $todayVal - $yesterdayVal;
        $trend = 'neutral';
        if ($diff > 0) {
            $trend = 'up';
        } elseif ($diff < 0) {
            $trend = 'down';
        }
        
        if ($yesterdayVal == 0) {
            $pct = $todayVal > 0 ? 100 : 0;
        } else {
            $pct = ($diff / $yesterdayVal) * 100;
        }
        
        return [
            'value' => $todayVal,
            'yesterday' => $yesterdayVal,
            'diff' => abs($diff),
            'pct' => number_format($pct, 1, '.', ''),
            'trend' => $trend,
        ];
    }
}
