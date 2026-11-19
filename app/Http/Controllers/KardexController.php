<?php

namespace App\Http\Controllers;

use App\Models\MovimientoStock;
use App\Models\Producto;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KardexController extends Controller
{
    /**
     * Display a listing of stock movements (Kardex ledger).
     */
    public function index(Request $request)
    {
        $productos = Producto::orderBy('nombre')->get();

        $productoId = $request->query('producto_id');
        $movimientos = [];
        $productoSeleccionado = null;

        if ($productoId) {
            $productoSeleccionado = Producto::find($productoId);
            
            if ($productoSeleccionado) {
                // Cargar movimientos ordenados cronológicamente
                $movimientos = MovimientoStock::with(['creador', 'referencia'])
                    ->where('producto_id', $productoId)
                    ->orderBy('created_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->get();
            }
        }

        return Inertia::render('Kardex/Index', [
            'productos' => $productos,
            'movimientos' => $movimientos,
            'productoSeleccionado' => $productoSeleccionado,
            'filtros' => [
                'producto_id' => $productoId,
            ],
        ]);
    }
}
