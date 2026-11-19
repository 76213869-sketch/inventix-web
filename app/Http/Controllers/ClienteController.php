<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClienteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $clientes = Cliente::with(['ventas.detalles.producto'])->orderBy('nombre')->get();
        return Inertia::render('Clientes/Index', [
            'clientes' => $clientes
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'documento_tipo' => 'required|in:DNI,RUC,CE',
            'documento_numero' => 'nullable|string|max:20|unique:clientes,documento_numero,NULL,id,empresa_id,' . auth()->user()->empresa_id,
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'correo' => 'nullable|email|max:255',
        ]);

        Cliente::create($validated);

        return redirect()->route('clientes.index')->with('success', 'Cliente creado con éxito.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'documento_tipo' => 'required|in:DNI,RUC,CE',
            'documento_numero' => 'nullable|string|max:20|unique:clientes,documento_numero,' . $cliente->id . ',id,empresa_id,' . auth()->user()->empresa_id,
            'direccion' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:50',
            'correo' => 'nullable|email|max:255',
        ]);

        $cliente->update($validated);

        return redirect()->route('clientes.index')->with('success', 'Cliente actualizado con éxito.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Cliente $cliente)
    {
        $cliente->delete();
        return redirect()->route('clientes.index')->with('success', 'Cliente eliminado con éxito.');
    }

    /**
     * Store or update client phone and name quickly during checkout.
     */
    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'nullable|string|max:255',
            'telefono' => 'required|string|max:50',
            'cliente_id' => 'nullable|exists:clientes,id',
        ]);

        if (!empty($validated['cliente_id'])) {
            $cliente = Cliente::findOrFail($validated['cliente_id']);
            $cliente->update([
                'telefono' => $validated['telefono'],
                'nombre' => ($cliente->nombre === 'Público General' || empty($cliente->nombre)) && !empty($validated['nombre'])
                    ? $validated['nombre']
                    : $cliente->nombre,
            ]);
        } else {
            $cliente = Cliente::create([
                'nombre' => $validated['nombre'] ?: 'Cliente WhatsApp',
                'documento_tipo' => 'DNI',
                'documento_numero' => null,
                'telefono' => $validated['telefono'],
                'empresa_id' => auth()->user()->empresa_id,
            ]);
        }

        return response()->json([
            'success' => true,
            'cliente' => $cliente,
        ]);
    }
}
