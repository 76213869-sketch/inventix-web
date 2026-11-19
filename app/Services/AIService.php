<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Venta;
use App\Models\Compra;
use App\Models\Cliente;
use App\Models\Proveedor;
use App\Models\DetalleVenta;
use App\Models\MovimientoStock;
use App\Models\ActividadSistema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected string $apiKey;
    protected string $endpoint = 'https://api.groq.com/openai/v1/chat/completions';
    protected string $model = 'llama-3.3-70b-versatile';

    public function __construct()
    {
        $this->apiKey = env('GROQ_API_KEY', '');
    }

    /**
     * Main entry point for chatting with the business data.
     *
     * @param string $message User query
     * @param array $history Previous messages: [['role' => 'user', 'content' => '...'], ...]
     * @return array
     */
    public function ask(string $message, array $history = []): array
    {
        if (empty($this->apiKey)) {
            return [
                'text' => 'Disculpa, el asistente de IA no está configurado. Por favor, agrega la clave `GROQ_API_KEY` en el archivo `.env`.',
                'visuals' => [],
                'actions' => []
            ];
        }

        try {
            // Step 1: Detect which database query tools are needed based on intent
            $calledFunctions = $this->detectIntent($message, $history);

            // Step 2: Execute safe query tools and collect context
            $context = $this->executeToolsAndGatherContext($calledFunctions);

            // Step 3: Call Groq to generate the final response
            return $this->generateFinalResponse($message, $history, $context);

        } catch (\Exception $e) {
            Log::error('AIService Error: ' . $e->getMessage());
            return [
                'text' => 'Lo siento, ocurrió un inconveniente al procesar tu consulta. Por favor, inténtalo nuevamente.',
                'visuals' => [],
                'actions' => []
            ];
        }
    }

    /**
     * Step 1: Detect intent and identify database calls.
     */
    protected function detectIntent(string $message, array $history): array
    {
        $currentDate = now()->toDateString();
        $currentDayName = now()->locale('es')->dayName;
        $currentTime = now()->toTimeString();

        $prompt = "Identify which of these data query functions the user's message needs. Return ONLY a JSON object with a 'calls' key, which is an array of objects.
Available functions:
1. `getProductosBajoStock()` - Returns active products that are below minimum stock.
2. `getProductosAgotados()` - Returns active products that are out of stock (<= 0).
3. `getProductosMasVendidos(limit)` - Returns top products by quantity sold.
4. `getProductosMenosVendidos(limit)` - Returns products with the lowest sales.
5. `getVentasPeriodo(startDate, endDate)` - Returns sales total and sales list in date range (YYYY-MM-DD).
6. `getComprasPeriodo(startDate, endDate)` - Returns purchases total and list in date range.
7. `getClientesVIP(limit)` - Returns customers who spend the most money.
8. `getProveedoresRanking(limit)` - Returns suppliers ranked by purchase volume.
9. `getCajaBalance()` - Returns cash balance estimate.
10. `getDetalleProducto(query)` - Search detailed info for a product by name or barcode.
11. `getDetalleCliente(query)` - Search detailed info for a customer by name, DNI, or RUC.
12. `getDetalleProveedor(query)` - Search detailed info for a supplier by name or RUC.
13. `getResumenNegocio()` - General summary of business metrics (sales today, sales this month, stock status, active count).
14. `getRotacionStock()` - Returns recent kardex stock movements.
15. `getBitacoraReciente()` - Returns recent system actions log.

Rules:
- Today is {$currentDate} ({$currentDayName}). Current time is {$currentTime}.
- Calculate relative dates accordingly (e.g., 'ayer' means sub 1 day: " . now()->subDay()->toDateString() . ", 'este mes' means from " . now()->startOfMonth()->toDateString() . " to " . now()->toDateString() . ").
- If the user query does not require any database data (e.g. greetings, simple thank yous, or following up on already provided metrics), return an empty array under 'calls'.
- Return ONLY valid JSON in this format: { \"calls\": [ { \"function\": \"functionName\", \"parameters\": { ... } } ] }

User Message: \"{$message}\"";

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->endpoint, [
            'model' => $this->model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.0,
            'response_format' => ['type' => 'json_object'],
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '{}';
            $parsed = json_decode($content, true);
            return $parsed['calls'] ?? [];
        }

        return [];
    }

    /**
     * Step 2: Execute query tools safe for the user's role.
     */
    protected function executeToolsAndGatherContext(array $calls): string
    {
        $user = auth()->user();
        $role = $user->rol ?? 'vendedor';
        $isAdmin = in_array($role, ['administrador', 'super_admin']);

        $contextParts = [];

        foreach ($calls as $call) {
            $func = $call['function'] ?? '';
            $params = $call['parameters'] ?? [];

            switch ($func) {
                case 'getProductosBajoStock':
                    $data = Producto::where('estado', 'activo')
                        ->whereIn('tipo_producto', ['estandar', 'variante', 'insumo'])
                        ->whereColumn('stock_actual', '<=', 'stock_minimo')
                        ->get(['id', 'nombre', 'codigo_barras', 'stock_actual', 'stock_minimo', 'precio_venta'])
                        ->toArray();
                    $contextParts[] = "Productos Bajo Stock Crítico: " . json_encode($data);
                    break;

                case 'getProductosAgotados':
                    $data = Producto::where('estado', 'activo')
                        ->whereIn('tipo_producto', ['estandar', 'variante', 'insumo'])
                        ->where('stock_actual', '<=', 0)
                        ->get(['id', 'nombre', 'codigo_barras', 'precio_venta'])
                        ->toArray();
                    $contextParts[] = "Productos Agotados (Stock 0): " . json_encode($data);
                    break;

                case 'getProductosMasVendidos':
                    $limit = $params['limit'] ?? 5;
                    $data = DetalleVenta::whereHas('venta', fn($q) => $q->where('estado', 'completada'))
                        ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
                        ->selectRaw('productos.id, productos.nombre, SUM(detalle_ventas.cantidad) as total_cantidad, SUM(detalle_ventas.total) as total_monto')
                        ->groupBy('productos.id', 'productos.nombre')
                        ->orderByDesc('total_cantidad')
                        ->limit($limit)
                        ->get()
                        ->toArray();
                    $contextParts[] = "Productos Más Vendidos: " . json_encode($data);
                    break;

                case 'getProductosMenosVendidos':
                    $limit = $params['limit'] ?? 5;
                    $data = DetalleVenta::whereHas('venta', fn($q) => $q->where('estado', 'completada'))
                        ->join('productos', 'detalle_ventas.producto_id', '=', 'productos.id')
                        ->selectRaw('productos.id, productos.nombre, SUM(detalle_ventas.cantidad) as total_cantidad, SUM(detalle_ventas.total) as total_monto')
                        ->groupBy('productos.id', 'productos.nombre')
                        ->orderBy('total_cantidad')
                        ->limit($limit)
                        ->get()
                        ->toArray();
                    $contextParts[] = "Productos Menos Vendidos (Fríos): " . json_encode($data);
                    break;

                case 'getVentasPeriodo':
                    $start = $params['startDate'] ?? now()->toDateString();
                    $end = $params['endDate'] ?? now()->toDateString();
                    $q = Venta::where('estado', 'completada')
                        ->whereDate('fecha_venta', '>=', $start)
                        ->whereDate('fecha_venta', '<=', $end);
                    
                    $total = $q->sum('total');
                    $count = $q->count();
                    $recientes = $q->with('cliente:id,nombre')->latest()->limit(10)->get(['id', 'numero_venta', 'cliente_id', 'total', 'metodo_pago', 'fecha_venta'])->toArray();
                    
                    $contextParts[] = "Ventas en el período ($start a $end): Total facturado = S/ $total, Cantidad de ventas = $count. Ventas recientes: " . json_encode($recientes);
                    break;

                case 'getComprasPeriodo':
                    if (!$isAdmin) {
                        $contextParts[] = "getComprasPeriodo: No tienes permiso para ver egresos/compras.";
                        break;
                    }
                    $start = $params['startDate'] ?? now()->toDateString();
                    $end = $params['endDate'] ?? now()->toDateString();
                    $q = Compra::where('estado', 'completada')
                        ->whereDate('fecha_compra', '>=', $start)
                        ->whereDate('fecha_compra', '<=', $end);
                    
                    $total = $q->sum('total');
                    $count = $q->count();
                    $recientes = $q->with('proveedor:id,nombre')->latest()->limit(10)->get(['id', 'numero_compra', 'proveedor_id', 'total', 'fecha_compra'])->toArray();
                    
                    $contextParts[] = "Compras/Egresos en el período ($start a $end): Total invertido = S/ $total, Cantidad de órdenes = $count. Compras recientes: " . json_encode($recientes);
                    break;

                case 'getClientesVIP':
                    $limit = $params['limit'] ?? 5;
                    $data = Cliente::join('ventas', 'clientes.id', '=', 'ventas.cliente_id')
                        ->where('ventas.estado', 'completada')
                        ->selectRaw('clientes.id, clientes.nombre, clientes.documento_numero, COUNT(ventas.id) as compras_count, SUM(ventas.total) as total_gastado')
                        ->groupBy('clientes.id', 'clientes.nombre', 'clientes.documento_numero')
                        ->orderByDesc('total_gastado')
                        ->limit($limit)
                        ->get()
                        ->toArray();
                    $contextParts[] = "Clientes VIP: " . json_encode($data);
                    break;

                case 'getProveedoresRanking':
                    if (!$isAdmin) {
                        $contextParts[] = "getProveedoresRanking: No tienes permiso para ver datos de proveedores.";
                        break;
                    }
                    $limit = $params['limit'] ?? 5;
                    $data = Proveedor::join('compras', 'proveedores.id', '=', 'compras.proveedor_id')
                        ->where('compras.estado', 'completada')
                        ->selectRaw('proveedores.id, proveedores.nombre, proveedores.ruc, COUNT(compras.id) as compras_count, SUM(compras.total) as total_facturado')
                        ->groupBy('proveedores.id', 'proveedores.nombre', 'proveedores.ruc')
                        ->orderByDesc('total_facturado')
                        ->limit($limit)
                        ->get()
                        ->toArray();
                    $contextParts[] = "Ranking de Proveedores: " . json_encode($data);
                    break;

                case 'getCajaBalance':
                    $ventasEfectivo = Venta::where('estado', 'completada')->where('metodo_pago', 'efectivo')->sum('total');
                    $comprasEfectivo = $isAdmin ? Compra::where('estado', 'completada')->sum('total') : 0;
                    $contextParts[] = "Balance de Caja estimado (Efectivo): Ventas = S/ $ventasEfectivo" . ($isAdmin ? ", Compras = S/ $comprasEfectivo, Saldo = S/ " . ($ventasEfectivo - $comprasEfectivo) : "");
                    break;

                case 'getDetalleProducto':
                    $qStr = $params['query'] ?? '';
                    $data = Producto::where('nombre', 'like', "%$qStr%")
                        ->orWhere('codigo_barras', $qStr)
                        ->first(['id', 'nombre', 'codigo_barras', 'stock_actual', 'stock_minimo', 'precio_venta']);
                    $contextParts[] = "Detalle del Producto solicitado ($qStr): " . json_encode($data);
                    break;

                case 'getDetalleCliente':
                    $qStr = $params['query'] ?? '';
                    $data = Cliente::where('nombre', 'like', "%$qStr%")
                        ->orWhere('documento_numero', $qStr)
                        ->first(['id', 'nombre', 'documento_tipo', 'documento_numero', 'telefono', 'correo']);
                    $contextParts[] = "Detalle del Cliente solicitado ($qStr): " . json_encode($data);
                    break;

                case 'getDetalleProveedor':
                    if (!$isAdmin) {
                        $contextParts[] = "getDetalleProveedor: No tienes permiso para ver detalles de proveedores.";
                        break;
                    }
                    $qStr = $params['query'] ?? '';
                    $data = Proveedor::where('nombre', 'like', "%$qStr%")
                        ->orWhere('ruc', $qStr)
                        ->first(['id', 'nombre', 'ruc', 'telefono', 'correo']);
                    $contextParts[] = "Detalle del Proveedor solicitado ($qStr): " . json_encode($data);
                    break;

                case 'getResumenNegocio':
                    $today = now()->toDateString();
                    $monthStart = now()->startOfMonth()->toDateString();
                    
                    $ventasHoy = Venta::where('estado', 'completada')->whereDate('fecha_venta', $today)->sum('total');
                    $ventasMes = Venta::where('estado', 'completada')->whereDate('fecha_venta', '>=', $monthStart)->sum('total');
                    $bajoStock = Producto::where('estado', 'activo')
                        ->whereIn('tipo_producto', ['estandar', 'variante', 'insumo'])
                        ->whereColumn('stock_actual', '<=', 'stock_minimo')
                        ->count();
                    $totalProd = Producto::where('estado', 'activo')->count();
                    
                    $res = [
                        'fecha_de_hoy' => $today,
                        'ventas_hoy' => $ventasHoy,
                        'ventas_mes' => $ventasMes,
                        'productos_bajo_stock' => $bajoStock,
                        'total_productos_activos' => $totalProd
                    ];

                    if ($isAdmin) {
                        $comprasHoy = Compra::where('estado', 'completada')->whereDate('fecha_compra', $today)->sum('total');
                        $comprasMes = Compra::where('estado', 'completada')->whereDate('fecha_compra', '>=', $monthStart)->sum('total');
                        $res['compras_hoy'] = $comprasHoy;
                        $res['compras_mes'] = $comprasMes;
                    }

                    $contextParts[] = "Resumen General del Negocio: " . json_encode($res);
                    break;

                case 'getRotacionStock':
                    $data = MovimientoStock::with('producto:id,nombre')
                        ->latest()
                        ->limit(10)
                        ->get(['id', 'producto_id', 'tipo', 'cantidad', 'motivo', 'created_at'])
                        ->toArray();
                    $contextParts[] = "Últimos Movimientos de Kardex (Rotación): " . json_encode($data);
                    break;

                case 'getBitacoraReciente':
                    if (!$isAdmin) {
                        $contextParts[] = "getBitacoraReciente: No tienes permiso para ver logs de auditoría.";
                        break;
                    }
                    $data = ActividadSistema::with('usuario:id,nombre')
                        ->latest()
                        ->limit(10)
                        ->get(['id', 'usuario_id', 'evento', 'tabla_afectada', 'created_at'])
                        ->toArray();
                    $contextParts[] = "Log de Actividad del Sistema: " . json_encode($data);
                    break;
            }
        }

        return implode("\n\n", $contextParts);
    }

    /**
     * Step 3: Ask Groq for the final response in a structured JSON schema.
     */
    protected function generateFinalResponse(string $message, array $history, string $context): array
    {
        $user = auth()->user();
        $nombreUsuario = $user->nombre ?? 'Usuario';
        $role = $user->rol ?? 'vendedor';
        $empresaNombre = ($user->empresa ? $user->empresa->nombre : null) 
            ?? (session()->has('impersonated_empresa_id') ? \App\Models\Empresa::find(session('impersonated_empresa_id'))?->nombre : null)
            ?? 'la bodega';

        $systemPrompt = "Eres el Asistente Inteligente de INVENTEX. Ayudas a {$empresaNombre} (al dueño o administrador del negocio) a entender los números de su comercio.
Actualmente estás conversando con: {$nombreUsuario} (Rol: {$role}).
Tus respuestas deben estar redactadas en español, utilizando un tono claro, cálido, motivador y sencillo. Habla como un consultor o asesor cercano, sin tecnicismos ni menciones a bases de datos, consultas SQL o tablas.

INSTRUCCIONES CLAVE:
1. Responde ÚNICAMENTE utilizando los datos de negocio proporcionados en la sección de Contexto.
2. Si no hay datos suficientes para responder una pregunta, indícalo con amabilidad sin inventar información.
3. Al final del texto de respuesta, cuando sea oportuno, agrega una recomendación comercial breve y práctica de una oración.
4. Responde en formato JSON obligatorio con esta estructura exacta:
{
  \"text\": \"Texto principal de tu respuesta en formato markdown. Puedes incluir viñetas o tablas markdown si es oportuno.\",
  \"visuals\": [
    // Si mencionas productos, clientes o proveedores específicos, agrégalos aquí para renderizar tarjetas interactivas de ayuda en pantalla.
    // Ejemplos:
    // { \"type\": \"producto\", \"id\": 3, \"nombre\": \"Gaseosa Inka Cola 1L\", \"stock\": 12, \"precio\": 4.50 }
    // { \"type\": \"cliente\", \"id\": 10, \"nombre\": \"Ana Delgado\", \"telefono\": \"987654321\" }
    // { \"type\": \"proveedor\", \"id\": 2, \"nombre\": \"Distribuidora Gloria\", \"ruc\": \"20102030401\" }
  ],
  \"actions\": [
    // Si el usuario te pide expresamente realizar una acción del sistema (como abrir un producto, ir a clientes, abrir caja, ver reporte financiero), agrega la acción aquí.
    // Ejemplos:
    // { \"type\": \"abrir_producto\", \"id\": 3, \"nombre\": \"Gaseosa Inka Cola 1L\" }
    // { \"type\": \"ir_a_clientes\" }
    // { \"type\": \"mostrar_reporte_financiero\" }
  ]
}

CONTEXTO DE DATOS DISPONIBLES:
{$context}

Escribe tu respuesta final en JSON:";

        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];

        // Append historical conversation memory
        foreach (array_slice($history, -10) as $msg) {
            $messages[] = [
                'role' => $msg['role'] ?? 'user',
                'content' => is_array($msg['content']) ? ($msg['content']['text'] ?? '') : $msg['content']
            ];
        }

        // Current message
        $messages[] = ['role' => 'user', 'content' => $message];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->endpoint, [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'],
        ]);

        if ($response->successful()) {
            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '{}';
            $parsed = json_decode($content, true);
            if (isset($parsed['text'])) {
                return $parsed;
            }
        }

        return [
            'text' => 'Lo siento, no pude estructurar una respuesta en este momento. Por favor, reintenta tu consulta.',
            'visuals' => [],
            'actions' => []
        ];
    }
}
