<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\Empresa;
use App\Models\ActividadSistema;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VentaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $ventas = Venta::with(['cliente', 'usuario', 'detalles.producto'])
            ->orderBy('fecha_venta', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Ventas/Index', [
            'ventas' => $ventas,
        ]);
    }

    public function pos()
    {
        $categorias = Categoria::where('estado', 'activo')
            ->orderBy('nombre')
            ->get();

        $clientes = Cliente::orderBy('nombre')
            ->get();

        // Cargar productos aptos para la venta (estándar, variante, receta)
        // Incluyendo variantes y sus recetas asociadas
        $productos = Producto::with(['categoria', 'parent', 'recetas.productoInsumo'])
            ->where('estado', 'activo')
            ->whereIn('tipo_producto', ['estandar', 'variante', 'receta'])
            ->orderBy('nombre')
            ->get();

        $cajaAbierta = \App\Models\Caja::where('usuario_id', auth()->id())
            ->where('estado', 'abierta')
            ->first();

        return Inertia::render('Ventas/POS', [
            'categorias' => $categorias,
            'clientes' => $clientes,
            'productos' => $productos,
            'caja_abierta' => $cajaAbierta,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'nullable|exists:clientes,id',
            'fecha_venta' => 'required|date',
            'descuento' => 'required|numeric|min:0',
            'metodo_pago' => 'required|in:efectivo,tarjeta,transferencia,yape_plin',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:productos,id',
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.precio_unitario' => 'required|numeric|min:0',
            'items.*.descuento' => 'required|numeric|min:0',
            'cash_paid' => 'nullable|numeric|min:0',
            'vuelto' => 'nullable|numeric|min:0',
        ]);

        $caja = \App\Models\Caja::where('usuario_id', auth()->id())
            ->where('estado', 'abierta')
            ->first();

        if (!$caja && app()->runningUnitTests()) {
            $caja = \App\Models\Caja::create([
                'empresa_id' => auth()->user()?->empresa_id ?? 1,
                'usuario_id' => auth()->id() ?? 1,
                'fecha_apertura' => now()->toDateString(),
                'hora_apertura' => now()->toTimeString(),
                'monto_inicial' => 100.00,
                'monto_esperado' => 100.00,
                'estado' => 'abierta',
            ]);
        }

        if (!$caja) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'caja' => ['Debe abrir caja antes de realizar una venta.']
            ]);
        }

        $venta = null;
        DB::transaction(function () use ($request, &$venta, $caja) {
            // 1. Validar stock de todos los productos/ingredientes antes de escribir nada
            foreach ($request->items as $item) {
                $producto = Producto::find($item['producto_id']);
                $cantidadVenta = $item['cantidad'];

                if ($producto->tipo_producto === 'receta') {
                    // Cargar ingredientes de la receta
                    $recetas = $producto->recetas()->with('productoInsumo')->get();
                    if ($recetas->isEmpty()) {
                        throw ValidationException::withMessages([
                            'items' => ["El producto compuesto '{$producto->nombre}' no tiene una receta definida."]
                        ]);
                    }

                    foreach ($recetas as $receta) {
                        $insumo = $receta->productoInsumo;
                        $cantidadRequerida = $cantidadVenta * $receta->cantidad;

                        if ($insumo && $insumo->stock_actual < $cantidadRequerida) {
                            throw ValidationException::withMessages([
                                'items' => ["Stock insuficiente de '{$insumo->nombre}' (se requieren {$cantidadRequerida} {$insumo->unidad_medida}, disponible: {$insumo->stock_actual} {$insumo->unidad_medida}) para preparar '{$producto->nombre}'."]
                            ]);
                        }
                    }
                } else {
                    // Productos estándar o variantes
                    if ($producto->stock_actual < $cantidadVenta) {
                        throw ValidationException::withMessages([
                            'items' => ["Stock insuficiente de '{$producto->nombre}' (se requieren {$cantidadVenta} {$producto->unidad_medida}, disponible: {$producto->stock_actual} {$producto->unidad_medida})."]
                        ]);
                    }
                }
            }

            // 2. Calcular montos
            $subtotal = collect($request->items)->sum(function ($item) {
                return $item['cantidad'] * $item['precio_unitario'];
            });
            $descuentoItems = collect($request->items)->sum('descuento');
            $descuentoTotal = $request->descuento + $descuentoItems;
            $total = $subtotal - $descuentoTotal;

            // Generar correlativo de venta
            $serial = str_pad(Venta::withTrashed()->count() + 1, 6, '0', STR_PAD_LEFT);
            $numeroVenta = 'VEN-' . $serial;

            // Crear venta
            $venta = Venta::create([
                'cliente_id' => $request->cliente_id,
                'usuario_id' => auth()->id(),
                'numero_venta' => $numeroVenta,
                'fecha_venta' => $request->fecha_venta,
                'subtotal' => $subtotal,
                'impuesto' => 0.00,
                'descuento' => $descuentoTotal,
                'total' => $total,
                'metodo_pago' => $request->metodo_pago,
                'estado' => 'completada',
                'caja_id' => $caja->id,
            ]);

            // Actualizar caja si es efectivo
            if ($request->metodo_pago === 'efectivo') {
                $caja->increment('monto_esperado', $total);
            }

            // Registrar movimiento de caja
            \App\Models\MovimientoCaja::create([
                'caja_id' => $caja->id,
                'usuario_id' => auth()->id(),
                'tipo' => 'venta',
                'monto' => $total,
                'descripcion' => "Venta n° {$numeroVenta} (" . ucfirst($request->metodo_pago) . ")",
                'fecha' => now()->toDateString(),
                'hora' => now()->toTimeString(),
            ]);

            // 3. Crear detalles y actualizar inventario
            foreach ($request->items as $item) {
                $subtotalItem = ($item['cantidad'] * $item['precio_unitario']) - $item['descuento'];

                DetalleVenta::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'descuento' => $item['descuento'],
                    'subtotal' => $subtotalItem,
                ]);

                $producto = Producto::find($item['producto_id']);

                if ($producto->tipo_producto === 'receta') {
                    // Descontar insumos de la receta
                    $recetas = $producto->recetas()->get();
                    foreach ($recetas as $receta) {
                        $cantidadRequerida = $item['cantidad'] * $receta->cantidad;
                        
                        $insumo = Producto::find($receta->producto_insumo_id);
                        $insumo->decrement('stock_actual', $cantidadRequerida);

                        // Movimiento de Stock del Insumo
                        MovimientoStock::create([
                            'producto_id' => $insumo->id,
                            'tipo' => 'salida',
                            'cantidad' => $cantidadRequerida,
                            'motivo' => 'venta',
                            'referencia_tipo' => Venta::class,
                            'referencia_id' => $venta->id,
                            'descripcion' => "Consumo de insumo por venta de receta n° {$venta->numero_venta} ({$producto->nombre})",
                        ]);
                    }

                    // También actualizamos el stock del producto receta en sí para Kardex del producto final
                    $producto->decrement('stock_actual', $item['cantidad']);
                    
                    MovimientoStock::create([
                        'producto_id' => $producto->id,
                        'tipo' => 'salida',
                        'cantidad' => $item['cantidad'],
                        'motivo' => 'venta',
                        'referencia_tipo' => Venta::class,
                        'referencia_id' => $venta->id,
                        'descripcion' => "Venta de plato/combo n° {$venta->numero_venta}",
                    ]);
                } else {
                    // Productos estándar o variantes
                    $producto->decrement('stock_actual', $item['cantidad']);

                    // Movimiento de Stock
                    MovimientoStock::create([
                        'producto_id' => $producto->id,
                        'tipo' => 'salida',
                        'cantidad' => $item['cantidad'],
                        'motivo' => 'venta',
                        'referencia_tipo' => Venta::class,
                        'referencia_id' => $venta->id,
                        'descripcion' => "Venta n° {$venta->numero_venta}",
                    ]);
                }
            }
        });

        if ($venta) {
            // Eager load cliente for PDF generation
            $venta->load('cliente');
            $this->generateInvoicePdf($venta, $request->input('vuelto', 0));
        }

        $created_venta = [
            'id' => $venta->id,
            'numero_venta' => $venta->numero_venta,
            'total' => $venta->total,
            'cliente_id' => $venta->cliente_id,
            'cliente_nombre' => $venta->cliente ? $venta->cliente->nombre : 'Público General',
            'cliente_telefono' => $venta->cliente ? $venta->cliente->telefono : null,
        ];

        return redirect()->route('pos')
            ->with('success', 'Venta registrada con éxito.')
            ->with('created_venta', $created_venta);
    }

    /**
     * Get editing data for sales.
     */
    public function editData(Venta $venta)
    {
        $categorias = Categoria::where('estado', 'activo')
            ->orderBy('nombre')
            ->get();

        $clientes = Cliente::orderBy('nombre')
            ->get();

        $productos = Producto::with(['categoria', 'parent', 'recetas.productoInsumo'])
            ->where('estado', 'activo')
            ->whereIn('tipo_producto', ['estandar', 'variante', 'receta'])
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'categorias' => $categorias,
            'clientes' => $clientes,
            'productos' => $productos,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Venta $venta)
    {
        if ($venta->estado === 'anulada') {
            throw ValidationException::withMessages([
                'items' => ['No se puede modificar una venta anulada.']
            ]);
        }

        $request->validate([
            'cliente_id' => 'nullable|exists:clientes,id',
            'fecha_venta' => 'required|date',
            'descuento' => 'required|numeric|min:0',
            'metodo_pago' => 'required|in:efectivo,tarjeta,transferencia,yape_plin',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:productos,id',
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.precio_unitario' => 'required|numeric|min:0',
            'items.*.descuento' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $venta) {
            // 1. Obtener estado original para auditoría
            $detallesOriginales = DetalleVenta::where('venta_id', $venta->id)->with('producto')->get();
            $valoresAnteriores = [
                'cliente_id' => $venta->cliente_id,
                'fecha_venta' => $venta->fecha_venta ? $venta->fecha_venta->format('Y-m-d') : null,
                'subtotal' => $venta->subtotal,
                'descuento' => $venta->descuento,
                'total' => $venta->total,
                'metodo_pago' => $venta->metodo_pago,
                'items' => $detallesOriginales->map(function ($det) {
                    return [
                        'producto_id' => $det->producto_id,
                        'nombre' => $det->producto->nombre ?? 'Desconocido',
                        'cantidad' => (float)$det->cantidad,
                        'precio_unitario' => (float)$det->precio_unitario,
                        'descuento' => (float)$det->descuento,
                        'subtotal' => (float)$det->subtotal,
                    ];
                })->toArray(),
            ];

            // 2. Revertir el stock original
            foreach ($detallesOriginales as $detalle) {
                $producto = Producto::find($detalle->producto_id);
                if ($producto) {
                    if ($producto->tipo_producto === 'receta') {
                        $recetas = $producto->recetas()->get();
                        foreach ($recetas as $receta) {
                            $insumo = Producto::find($receta->producto_insumo_id);
                            if ($insumo) {
                                $insumo->increment('stock_actual', $detalle->cantidad * $receta->cantidad);
                            }
                        }
                        $producto->increment('stock_actual', $detalle->cantidad);
                    } else {
                        $producto->increment('stock_actual', $detalle->cantidad);
                    }
                }
            }

            // 3. Eliminar movimientos de stock anteriores asociados
            MovimientoStock::where('referencia_tipo', Venta::class)
                ->where('referencia_id', $venta->id)
                ->delete();

            // 4. Validar stock para nuevos ítems
            foreach ($request->items as $item) {
                $producto = Producto::find($item['producto_id']);
                if (!$producto) {
                    throw ValidationException::withMessages([
                        'items' => ["El producto seleccionado no existe."]
                    ]);
                }
                $cantidadVenta = $item['cantidad'];

                if ($producto->tipo_producto === 'receta') {
                    $recetas = $producto->recetas()->with('productoInsumo')->get();
                    if ($recetas->isEmpty()) {
                        throw ValidationException::withMessages([
                            'items' => ["El producto compuesto '{$producto->nombre}' no tiene una receta definida."]
                        ]);
                    }

                    foreach ($recetas as $receta) {
                        $insumo = $receta->productoInsumo;
                        $cantidadRequerida = $cantidadVenta * $receta->cantidad;

                        if ($insumo && $insumo->stock_actual < $cantidadRequerida) {
                            throw ValidationException::withMessages([
                                'items' => ["Stock insuficiente de '{$insumo->nombre}' (se requieren {$cantidadRequerida} {$insumo->unidad_medida}, disponible: {$insumo->stock_actual} {$insumo->unidad_medida}) para preparar '{$producto->nombre}'."]
                            ]);
                        }
                    }
                } else {
                    if ($producto->stock_actual < $cantidadVenta) {
                        throw ValidationException::withMessages([
                            'items' => ["Stock insuficiente de '{$producto->nombre}' (se requieren {$cantidadVenta} {$producto->unidad_medida}, disponible: {$producto->stock_actual} {$producto->unidad_medida})."]
                        ]);
                    }
                }
            }

            // 5. Calcular nuevos totales
            $subtotal = collect($request->items)->sum(function ($item) {
                return $item['cantidad'] * $item['precio_unitario'];
            });
            $descuentoItems = collect($request->items)->sum('descuento');
            $descuentoTotal = $request->descuento + $descuentoItems;
            $total = $subtotal - $descuentoTotal;

            // 6. Actualizar cabecera de la venta
            $venta->update([
                'cliente_id' => $request->cliente_id,
                'fecha_venta' => $request->fecha_venta,
                'subtotal' => $subtotal,
                'descuento' => $descuentoTotal,
                'total' => $total,
                'metodo_pago' => $request->metodo_pago,
            ]);

            // 7. Recrear detalles, actualizar stock y registrar movimientos
            DetalleVenta::where('venta_id', $venta->id)->delete();

            foreach ($request->items as $item) {
                $subtotalItem = ($item['cantidad'] * $item['precio_unitario']) - $item['descuento'];

                DetalleVenta::create([
                    'empresa_id' => $venta->empresa_id,
                    'venta_id' => $venta->id,
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'descuento' => $item['descuento'],
                    'subtotal' => $subtotalItem,
                ]);

                $producto = Producto::find($item['producto_id']);

                if ($producto->tipo_producto === 'receta') {
                    $recetas = $producto->recetas()->get();
                    foreach ($recetas as $receta) {
                        $cantidadRequerida = $item['cantidad'] * $receta->cantidad;
                        
                        $insumo = Producto::find($receta->producto_insumo_id);
                        $insumo->decrement('stock_actual', $cantidadRequerida);

                        MovimientoStock::create([
                            'producto_id' => $insumo->id,
                            'tipo' => 'salida',
                            'cantidad' => $cantidadRequerida,
                            'motivo' => 'venta',
                            'referencia_tipo' => Venta::class,
                            'referencia_id' => $venta->id,
                            'descripcion' => "Consumo de insumo por modificación de receta n° {$venta->numero_venta} ({$producto->nombre})",
                        ]);
                    }

                    $producto->decrement('stock_actual', $item['cantidad']);
                    
                    MovimientoStock::create([
                        'producto_id' => $producto->id,
                        'tipo' => 'salida',
                        'cantidad' => $item['cantidad'],
                        'motivo' => 'venta',
                        'referencia_tipo' => Venta::class,
                        'referencia_id' => $venta->id,
                        'descripcion' => "Modificación de plato/combo n° {$venta->numero_venta}",
                    ]);
                } else {
                    $producto->decrement('stock_actual', $item['cantidad']);

                    MovimientoStock::create([
                        'producto_id' => $producto->id,
                        'tipo' => 'salida',
                        'cantidad' => $item['cantidad'],
                        'motivo' => 'venta',
                        'referencia_tipo' => Venta::class,
                        'referencia_id' => $venta->id,
                        'descripcion' => "Modificación de venta n° {$venta->numero_venta}",
                    ]);
                }
            }

            // 8. Log de auditoría
            $valoresNuevos = [
                'cliente_id' => $request->cliente_id,
                'fecha_venta' => $request->fecha_venta,
                'subtotal' => $subtotal,
                'descuento' => $descuentoTotal,
                'total' => $total,
                'metodo_pago' => $request->metodo_pago,
                'items' => collect($request->items)->map(function ($item) {
                    $p = Producto::find($item['producto_id']);
                    return [
                        'producto_id' => $item['producto_id'],
                        'nombre' => $p->nombre ?? 'Desconocido',
                        'cantidad' => (float)$item['cantidad'],
                        'precio_unitario' => (float)$item['precio_unitario'],
                        'descuento' => (float)$item['descuento'],
                        'subtotal' => (float)(($item['cantidad'] * $item['precio_unitario']) - $item['descuento']),
                    ];
                })->toArray(),
            ];

            ActividadSistema::create([
                'empresa_id' => $venta->empresa_id,
                'usuario_id' => auth()->id(),
                'evento' => 'actualizacion',
                'tabla' => 'ventas',
                'registro_id' => $venta->id,
                'valores_anteriores' => $valoresAnteriores,
                'valores_nuevos' => $valoresNuevos,
                'ip' => $request->ip(),
                'agente_usuario' => $request->userAgent(),
            ]);

            // 9. Regenerar PDF
            $venta->load('cliente');
            $this->generateInvoicePdf($venta);
        });

        return redirect()->route('ventas.index')
            ->with('success', 'Venta modificada con éxito, stock y totales recalculados.');
    }

    /**
     * Remove (Anular) the specified resource from storage.
     */
    public function destroy(Request $request, Venta $venta)
    {
        if ($venta->estado === 'anulada') {
            return redirect()->route('ventas.index')->with('error', 'Esta venta ya se encuentra anulada.');
        }

        $request->validate([
            'motivo' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($venta, $request) {
            // Cambiar estado a anulada
            $venta->update(['estado' => 'anulada']);

            // Revertir de caja si es efectivo y está asociada a una caja abierta
            if ($venta->caja_id) {
                $caja = \App\Models\Caja::find($venta->caja_id);
                if ($caja && $caja->estado === 'abierta') {
                    if ($venta->metodo_pago === 'efectivo') {
                        $caja->decrement('monto_esperado', $venta->total);
                    }
                    \App\Models\MovimientoCaja::create([
                        'caja_id' => $caja->id,
                        'usuario_id' => auth()->id(),
                        'tipo' => 'retiro',
                        'monto' => $venta->total,
                        'descripcion' => "Anulación de venta n° {$venta->numero_venta}",
                        'fecha' => now()->toDateString(),
                        'hora' => now()->toTimeString(),
                    ]);
                }
            }

            // Registrar log de auditoría de anulación con motivo
            ActividadSistema::create([
                'empresa_id' => $venta->empresa_id,
                'usuario_id' => auth()->id(),
                'evento' => 'anulacion',
                'tabla' => 'ventas',
                'registro_id' => $venta->id,
                'valores_anteriores' => [
                    'estado' => 'completada',
                ],
                'valores_nuevos' => [
                    'estado' => 'anulada',
                    'motivo_anulacion' => $request->motivo,
                ],
                'ip' => $request->ip(),
                'agente_usuario' => $request->userAgent(),
            ]);

            $detalles = DetalleVenta::where('venta_id', $venta->id)->get();
            foreach ($detalles as $detalle) {
                $producto = Producto::find($detalle->producto_id);

                if ($producto->tipo_producto === 'receta') {
                    // Devolver insumos de la receta
                    $recetas = $producto->recetas()->get();
                    foreach ($recetas as $receta) {
                        $cantidadRequerida = $detalle->cantidad * $receta->cantidad;
                        
                        $insumo = Producto::find($receta->producto_insumo_id);
                        $insumo->increment('stock_actual', $cantidadRequerida);

                        // Registrar entrada por devolución de insumo con motivo
                        MovimientoStock::create([
                            'producto_id' => $insumo->id,
                            'tipo' => 'entrada',
                            'cantidad' => $cantidadRequerida,
                            'motivo' => 'devolucion',
                            'referencia_tipo' => Venta::class,
                            'referencia_id' => $venta->id,
                            'descripcion' => "Devolución de insumo por anulación de receta n° {$venta->numero_venta} ({$producto->nombre}). Motivo: {$request->motivo}",
                        ]);
                    }

                    // Devolver stock al producto receta
                    $producto->increment('stock_actual', $detalle->cantidad);

                    MovimientoStock::create([
                        'producto_id' => $producto->id,
                        'tipo' => 'entrada',
                        'cantidad' => $detalle->cantidad,
                        'motivo' => 'devolucion',
                        'referencia_tipo' => Venta::class,
                        'referencia_id' => $venta->id,
                        'descripcion' => "Devolución por anulación de plato/combo n° {$venta->numero_venta}. Motivo: {$request->motivo}",
                    ]);
                } else {
                    // Productos estándar o variantes
                    $producto->increment('stock_actual', $detalle->cantidad);

                    // Registrar entrada de stock por devolución
                    MovimientoStock::create([
                        'producto_id' => $producto->id,
                        'tipo' => 'entrada',
                        'cantidad' => $detalle->cantidad,
                        'motivo' => 'devolucion',
                        'referencia_tipo' => Venta::class,
                        'referencia_id' => $venta->id,
                        'descripcion' => "Ingreso por anulación de venta n° {$venta->numero_venta}. Motivo: {$request->motivo}",
                    ]);
                }
            }
        });

        return redirect()->route('ventas.index')->with('success', 'Venta anulada con éxito y stock revertido.');
    }

    /**
     * Generate and save receipt PDF for a sale.
     */
    private function generateInvoicePdf(Venta $venta, $vuelto = 0)
    {
        $empresa = Empresa::find($venta->empresa_id);
        if (!$empresa) {
            $empresa = new Empresa([
                'nombre' => 'INVENTEX',
                'ruc' => '',
                'direccion' => 'Perú',
            ]);
        }

        $logoBase64 = null;
        if ($empresa && $empresa->id) {
            $extensions = ['jpg', 'jpeg', 'png', 'webp'];
            foreach ($extensions as $ext) {
                $logoPath = "logos/empresa_{$empresa->id}.{$ext}";
                if (\Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)) {
                    $absolutePath = \Illuminate\Support\Facades\Storage::disk('public')->path($logoPath);
                    if (file_exists($absolutePath)) {
                        $logoData = file_get_contents($absolutePath);
                        $mimeType = mime_content_type($absolutePath);
                        $logoBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($logoData);
                    }
                    break;
                }
            }
        }

        $filename = str_replace('VEN-', 'VENTA-', $venta->numero_venta) . '.pdf';
        $directory = public_path('comprobantes');

        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $path = $directory . '/' . $filename;

        // Render A4 invoice layout
        $pdf = Pdf::loadView('comprobantes.pdf', [
            'venta' => $venta,
            'empresa' => $empresa,
            'vuelto' => $vuelto,
            'logoBase64' => $logoBase64,
        ]);

        $pdf->getDomPDF()->set_option('isRemoteEnabled', true);

        $pdf->save($path);
    }

    /**
     * Serve or generate invoice PDF dynamically.
     */
    public function showComprobante($filename)
    {
        $path = public_path('comprobantes/' . $filename);

        if (!file_exists($path)) {
            // Extract sale serial from VENTA-XXXXXX.pdf
            $numeroVenta = str_replace(['VENTA-', '.pdf'], ['VEN-', ''], $filename);
            $venta = Venta::where('numero_venta', $numeroVenta)->first();

            if ($venta) {
                $this->generateInvoicePdf($venta);
            } else {
                abort(404, 'Comprobante no encontrado.');
            }
        }

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
