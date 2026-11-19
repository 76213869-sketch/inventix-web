<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use Illuminate\Http\Request;

class AIController extends Controller
{
    protected AIService $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Handle incoming chat message request.
     */
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'nullable|array',
        ]);

        $message = $request->input('message');
        $history = $request->input('history', []);

        $result = $this->aiService->ask($message, $history);

        return response()->json($result);
    }

    /**
     * Trigger the full business analysis report.
     */
    public function analyze(Request $request)
    {
        $prompt = "Realiza un análisis estratégico completo del negocio. Por favor, revisa todos los datos disponibles y elabora un informe ejecutivo detallado que contenga exactamente las siguientes secciones estructuradas con markdown:

1. **Resumen del estado del negocio** (Un párrafo con el estado general de ventas y stock).
2. **Tres fortalezas detectadas** (Tres puntos breves de lo que va muy bien, ej: productos rentables, clientes VIP o caja sana).
3. **Tres problemas o debilidades detectadas** (Tres puntos de mejora, ej: stock agotado, productos fríos, poco movimiento semanal).
4. **Recomendaciones prioritarias para esta semana** (Acciones rápidas).
5. **Productos que necesitan reposición urgente** (Mencionar los más vendidos que estén bajo stock mínimo).
6. **Productos con baja rotación** (Productos que no se han vendido recientemente).
7. **Tendencia de ventas respecto al período anterior** (Tendencia alcista o bajista y proyección).
8. **Riesgos detectados** (Stock crítico general, caja o alertas logísticas).
9. **Conclusión general** (Un mensaje motivador y amigable en lenguaje sencillo para el bodeguero).";

        $result = $this->aiService->ask($prompt, []);

        return response()->json($result);
    }
}
