<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Receta;
use App\Models\MovimientoStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProductoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Cargar productos con su categoría y producto padre (si es variante)
        $productos = Producto::with(['categoria', 'parent', 'recetas.productoInsumo'])
            ->orderBy('nombre')
            ->get();

        $categorias = Categoria::where('estado', 'activo')->orderBy('nombre')->get();
        
        // Cargar insumos potenciales (productos estándar o insumos activos)
        $insumos = Producto::whereIn('tipo_producto', ['estandar', 'insumo'])
            ->where('estado', 'activo')
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Productos/Index', [
            'productos' => $productos,
            'categorias' => $categorias,
            'insumos' => $insumos,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'categoria_id' => 'nullable|exists:categorias,id',
            'parent_id' => 'nullable|exists:productos,id',
            'descripcion' => 'nullable|string',
            'codigo_barras' => 'nullable|string|max:255|unique:productos,codigo_barras,NULL,id,empresa_id,' . auth()->user()->empresa_id,
            'tipo_producto' => 'required|in:estandar,variante,insumo,receta',
            'tipo_venta' => 'required|in:unidad,peso,volumen',
            'unidad_medida' => 'required|string|max:50',
            'precio_compra' => 'required|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
            'stock_minimo' => 'required|numeric|min:0',
            'stock_actual' => 'required|numeric|min:0',
            'metadatos' => 'nullable|array',
            'estado' => 'required|in:activo,inactivo',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'eliminar_imagen' => 'nullable|boolean',
            'imagen_tipo' => 'nullable|string|in:archivo,url',
            'imagen_url' => 'nullable|string',
            
            // Elementos de receta si es producto tipo receta
            'receta_insumos' => 'nullable|array',
            'receta_insumos.*.producto_insumo_id' => 'required|exists:productos,id',
            'receta_insumos.*.cantidad' => 'required|numeric|gt:0',
        ]);

        $producto = DB::transaction(function () use ($validated, $request) {
            $metadatos = $validated['metadatos'] ?? [];
            $imagenTipo = $request->input('imagen_tipo', 'archivo');
            $metadatos['imagen_tipo'] = $imagenTipo;

            if ($imagenTipo === 'url' && $request->input('imagen_url')) {
                $metadatos['imagen'] = $request->input('imagen_url');
            } elseif ($request->hasFile('imagen')) {
                $path = $request->file('imagen')->store('productos', 'public');
                $metadatos['imagen'] = $path;
            }
            
            $validated['metadatos'] = $metadatos;

            $producto = Producto::create(collect($validated)->except(['receta_insumos', 'imagen', 'eliminar_imagen', 'imagen_tipo', 'imagen_url'])->toArray());

            // Registrar composición si es tipo receta
            if ($validated['tipo_producto'] === 'receta' && !empty($validated['receta_insumos'])) {
                foreach ($validated['receta_insumos'] as $insumo) {
                    Receta::create([
                        'producto_padre_id' => $producto->id,
                        'producto_insumo_id' => $insumo['producto_insumo_id'],
                        'cantidad' => $insumo['cantidad'],
                    ]);
                }
            }

            return $producto;
        });

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Producto creado con éxito.',
                'producto' => $producto
            ]);
        }

        return redirect()->route('productos.index')->with('success', 'Producto creado con éxito.');
    }

    public function update(Request $request, Producto $producto)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'categoria_id' => 'nullable|exists:categorias,id',
            'parent_id' => 'nullable|exists:productos,id',
            'descripcion' => 'nullable|string',
            'codigo_barras' => 'nullable|string|max:255|unique:productos,codigo_barras,' . $producto->id . ',id,empresa_id,' . auth()->user()->empresa_id,
            'tipo_producto' => 'required|in:estandar,variante,insumo,receta',
            'tipo_venta' => 'required|in:unidad,peso,volumen',
            'unidad_medida' => 'required|string|max:50',
            'precio_compra' => 'required|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
            'stock_minimo' => 'required|numeric|min:0',
            'stock_actual' => 'required|numeric|min:0',
            'metadatos' => 'nullable|array',
            'estado' => 'required|in:activo,inactivo',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'eliminar_imagen' => 'nullable|boolean',
            'imagen_tipo' => 'nullable|string|in:archivo,url',
            'imagen_url' => 'nullable|string',
            
            // Receta
            'receta_insumos' => 'nullable|array',
            'receta_insumos.*.producto_insumo_id' => 'required|exists:productos,id',
            'receta_insumos.*.cantidad' => 'required|numeric|gt:0',
        ]);

        DB::transaction(function () use ($producto, $validated, $request) {
            $oldMetadatos = $producto->metadatos ?? [];
            $metadatos = $validated['metadatos'] ?? [];

            $imagenTipo = $request->input('imagen_tipo', $oldMetadatos['imagen_tipo'] ?? 'archivo');
            $metadatos['imagen_tipo'] = $imagenTipo;

            if ($request->input('eliminar_imagen') == 1) {
                $existing = $oldMetadatos['imagen'] ?? null;
                if ($existing && !str_starts_with($existing, 'http://') && !str_starts_with($existing, 'https://')) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($existing);
                }
            } else {
                if ($imagenTipo === 'url') {
                    $newUrl = $request->input('imagen_url');
                    $existing = $oldMetadatos['imagen'] ?? null;
                    if ($existing && $existing !== $newUrl && !str_starts_with($existing, 'http://') && !str_starts_with($existing, 'https://')) {
                        \Illuminate\Support\Facades\Storage::disk('public')->delete($existing);
                    }
                    if ($newUrl) {
                        $metadatos['imagen'] = $newUrl;
                    }
                } else {
                    if ($request->hasFile('imagen')) {
                        $existing = $oldMetadatos['imagen'] ?? null;
                        if ($existing && !str_starts_with($existing, 'http://') && !str_starts_with($existing, 'https://')) {
                            \Illuminate\Support\Facades\Storage::disk('public')->delete($existing);
                        }
                        $path = $request->file('imagen')->store('productos', 'public');
                        $metadatos['imagen'] = $path;
                    } else {
                        if (isset($oldMetadatos['imagen'])) {
                            $metadatos['imagen'] = $oldMetadatos['imagen'];
                        }
                    }
                }
            }

            $validated['metadatos'] = $metadatos;

            $producto->update(collect($validated)->except(['receta_insumos', 'imagen', 'eliminar_imagen', 'imagen_tipo', 'imagen_url'])->toArray());

            // Actualizar receta
            if ($validated['tipo_producto'] === 'receta') {
                $producto->recetas()->delete();

                if (!empty($validated['receta_insumos'])) {
                    foreach ($validated['receta_insumos'] as $insumo) {
                        Receta::create([
                            'producto_padre_id' => $producto->id,
                            'producto_insumo_id' => $insumo['producto_insumo_id'],
                            'cantidad' => $insumo['cantidad'],
                        ]);
                    }
                }
            } else {
                $producto->recetas()->delete();
            }
        });

        return redirect()->route('productos.index')->with('success', 'Producto actualizado con éxito.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Producto $producto)
    {
        $existing = $producto->metadatos['imagen'] ?? null;
        if ($existing && !str_starts_with($existing, 'http://') && !str_starts_with($existing, 'https://')) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($existing);
        }
        $producto->delete();
        return redirect()->route('productos.index')->with('success', 'Producto eliminado con éxito.');
    }

    /**
     * Duplicate a product.
     */
    public function duplicar(Producto $producto)
    {
        $nuevoProducto = $producto->replicate();
        
        $nuevoProducto->nombre = $producto->nombre . ' (Copia)';
        $nuevoProducto->codigo_barras = null;
        $nuevoProducto->stock_actual = 0;
        
        $metadatos = $producto->metadatos ?? [];
        $nuevoProducto->metadatos = $metadatos;

        $nuevoProducto->save();

        return redirect()->route('productos.index')->with('success', 'Producto duplicado con éxito.');
    }

    /**
     * Adjust stock for a product.
     */
    public function ajustarStock(Request $request, Producto $producto)
    {
        $validated = $request->validate([
            'tipo' => 'required|in:entrada,salida,ajuste',
            'cantidad' => 'required|numeric|min:0',
            'motivo' => 'required|in:compra,venta,devolucion,merma,ajuste_inventario',
            'observaciones' => 'nullable|string',
        ]);

        $tipoMovimiento = $validated['tipo'];
        $cantidadInput = $validated['cantidad'];

        DB::transaction(function () use ($producto, $tipoMovimiento, $cantidadInput, $validated) {
            $cantidadMovida = $cantidadInput;

            if ($tipoMovimiento === 'entrada') {
                $producto->increment('stock_actual', $cantidadInput);
            } elseif ($tipoMovimiento === 'salida') {
                $producto->decrement('stock_actual', $cantidadInput);
            } elseif ($tipoMovimiento === 'ajuste') {
                $diferencia = $cantidadInput - $producto->stock_actual;
                if ($diferencia > 0) {
                    $producto->increment('stock_actual', $diferencia);
                    $tipoMovimiento = 'entrada';
                    $cantidadMovida = $diferencia;
                } else if ($diferencia < 0) {
                    $producto->decrement('stock_actual', abs($diferencia));
                    $tipoMovimiento = 'salida';
                    $cantidadMovida = abs($diferencia);
                } else {
                    $tipoMovimiento = 'ajuste';
                    $cantidadMovida = 0;
                }
            }

            // Registrar movimiento de stock
            MovimientoStock::create([
                'producto_id' => $producto->id,
                'tipo' => $tipoMovimiento,
                'cantidad' => $cantidadMovida,
                'motivo' => $validated['motivo'],
                'descripcion' => $validated['observaciones'] ?? 'Ajuste manual de stock',
            ]);
        });

        return redirect()->route('productos.index')->with('success', 'Stock ajustado con éxito.');
    }
}
