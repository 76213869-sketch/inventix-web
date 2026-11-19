<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CompraController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $compras = Compra::with(['proveedor', 'usuario', 'detalles.producto'])
            ->orderBy('fecha_compra', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $proveedores = Proveedor::where('estado', 'activo')
            ->orderBy('nombre')
            ->get();

        $productos = Producto::where('estado', 'activo')
            ->whereIn('tipo_producto', ['estandar', 'insumo']) // Solo se abastecen productos físicos o insumos
            ->orderBy('nombre')
            ->get();

        $categorias = \App\Models\Categoria::where('estado', 'activo')
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Compras/Index', [
            'compras' => $compras,
            'proveedores' => $proveedores,
            'productos' => $productos,
            'categorias' => $categorias,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'fecha_compra' => 'required|date',
            'notas' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.producto_id' => 'required|exists:productos,id',
            'items.*.cantidad' => 'required|numeric|gt:0',
            'items.*.precio_unitario' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request) {
            $total = collect($request->items)->sum(function ($item) {
                return $item['cantidad'] * $item['precio_unitario'];
            });

            // Generar número de compra secuencial
            $serial = str_pad(Compra::withTrashed()->count() + 1, 6, '0', STR_PAD_LEFT);
            $numeroCompra = 'COM-' . $serial;

            // Crear Compra
            $compra = Compra::create([
                'proveedor_id' => $request->proveedor_id,
                'usuario_id' => auth()->id(),
                'numero_compra' => $numeroCompra,
                'fecha_compra' => $request->fecha_compra,
                'total' => $total,
                'estado' => 'completada',
                'notas' => $request->notas,
            ]);

            // Crear detalles y registrar movimientos de stock
            foreach ($request->items as $item) {
                $subtotal = $item['cantidad'] * $item['precio_unitario'];

                DetalleCompra::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => $subtotal,
                ]);

                // Actualizar stock del producto
                $producto = Producto::find($item['producto_id']);
                $producto->increment('stock_actual', $item['cantidad']);

                // Crear Movimiento de Stock
                MovimientoStock::create([
                    'producto_id' => $item['producto_id'],
                    'tipo' => 'entrada',
                    'cantidad' => $item['cantidad'],
                    'motivo' => 'compra',
                    'referencia_tipo' => Compra::class,
                    'referencia_id' => $compra->id,
                    'descripcion' => 'Ingreso por compra n° ' . $compra->numero_compra,
                ]);
            }
        });

        return redirect()->route('compras.index')->with('success', 'Compra registrada con éxito y stock actualizado.');
    }

    /**
     * Remove (Anular) the specified resource from storage.
     */
    public function destroy(Compra $compra)
    {
        if ($compra->estado === 'anulada') {
            return redirect()->route('compras.index')->with('error', 'Esta compra ya se encuentra anulada.');
        }

        DB::transaction(function () use ($compra) {
            // Cambiar estado a anulada
            $compra->update(['estado' => 'anulada']);

            // Revertir el stock y registrar el movimiento de salida
            $detalles = DetalleCompra::where('compra_id', $compra->id)->get();
            foreach ($detalles as $detalle) {
                $producto = Producto::find($detalle->producto_id);
                $producto->decrement('stock_actual', $detalle->cantidad);

                // Registrar salida de stock por anulación de compra
                MovimientoStock::create([
                    'producto_id' => $detalle->producto_id,
                    'tipo' => 'salida',
                    'cantidad' => $detalle->cantidad,
                    'motivo' => 'ajuste_inventario',
                    'referencia_tipo' => Compra::class,
                    'referencia_id' => $compra->id,
                    'descripcion' => 'Salida por anulación de compra n° ' . $compra->numero_compra,
                ]);
            }
        });

        return redirect()->route('compras.index')->with('success', 'Compra anulada con éxito y stock revertido.');
    }
}
