<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reporte de Caja #{{ $caja->id }}</title>
    <style>
        @page {
            size: A4;
            margin: 40px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #334155;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
            font-size: 11px;
            line-height: 1.5;
        }
        .header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-title {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }
        .header-company {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            margin-top: 2px;
            text-transform: uppercase;
        }
        .header-meta {
            text-align: right;
            font-size: 10px;
            color: #64748b;
            line-height: 1.4;
        }
        .section-title {
            font-size: 12px;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            margin-top: 22px;
            margin-bottom: 8px;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .info-grid td {
            padding: 6px 10px;
            border: 1px solid #f1f5f9;
            vertical-align: top;
        }
        .info-label {
            font-weight: 600;
            color: #64748b;
            width: 30%;
        }
        .info-value {
            color: #0f172a;
        }
        .info-value-mono {
            font-family: monospace;
            font-weight: 600;
        }
        .summary-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 20px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 5px 0;
            font-size: 11px;
        }
        .summary-label {
            color: #475569;
            font-weight: 500;
        }
        .summary-value {
            text-align: right;
            font-weight: 600;
            color: #0f172a;
            font-family: monospace;
        }
        .summary-total {
            border-top: 1px solid #cbd5e1;
            padding-top: 6px;
            font-weight: 800;
            color: #0f172a;
        }
        .summary-total-value {
            border-top: 1px solid #cbd5e1;
            padding-top: 6px;
            font-size: 13px;
            font-weight: 800;
            color: #2563eb;
            text-align: right;
            font-family: monospace;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            margin-bottom: 15px;
        }
        .data-table th {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 9px;
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }
        .data-table td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            font-size: 10.5px;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
        .font-mono {
            font-family: monospace;
        }
        .font-bold {
            font-weight: 700;
        }
        .alert-box {
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 10.5px;
            margin-top: 10px;
            border: 1px solid;
        }
        .alert-success {
            background-color: #f0fdf4;
            color: #15803d;
            border-color: #bbf7d0;
        }
        .alert-danger {
            background-color: #fef2f2;
            color: #b91c1c;
            border-color: #fecaca;
        }
        .badge {
            font-size: 8.5px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
            display: inline-block;
            border: 1px solid;
        }
        .badge-apertura { background-color: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .badge-venta { background-color: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .badge-gasto { background-color: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .badge-retiro { background-color: #fffbeb; color: #854d0e; border-color: #fef08a; }
        .badge-ingreso { background-color: #faf5ff; color: #5b21b6; border-color: #e9d5ff; }
        .badge-cierre { background-color: #f8fafc; color: #334155; border-color: #e2e8f0; }
    </style>
</head>
<body>

    <!-- CABECERA -->
    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <h1 class="header-title">Arqueo y Cierre de Caja</h1>
                    <div class="header-company">{{ $empresa_nombre }}</div>
                </td>
                <td class="header-meta">
                    <strong>Código Caja:</strong> #{{ $caja->id }}<br>
                    <strong>Estado:</strong> {{ strtoupper($caja->estado) }}<br>
                    <strong>Cajero:</strong> {{ $caja->usuario->nombre ?? 'N/A' }}
                </td>
            </tr>
        </table>
    </div>

    <!-- DATOS GENERALES -->
    <table class="info-grid">
        <tr>
            <td class="info-label">Fecha y Hora Apertura</td>
            <td class="info-value">{{ $caja->fecha_apertura->format('d/m/Y') }} - {{ $caja->hora_apertura }}</td>
            <td class="info-label">Monto Inicial</td>
            <td class="info-value info-value-mono">S/ {{ number_format($caja->monto_inicial, 2) }}</td>
        </tr>
        <tr>
            <td class="info-label">Fecha y Hora Cierre</td>
            <td class="info-value">
                {{ $caja->fecha_cierre ? $caja->fecha_cierre->format('d/m/Y') . ' - ' . $caja->hora_cierre : 'No cerrado aún' }}
            </td>
            <td class="info-label">Caja Esperada (Efectivo)</td>
            <td class="info-value info-value-mono">S/ {{ number_format($caja->monto_esperado, 2) }}</td>
        </tr>
        <tr>
            <td class="info-label">Efectivo Físico Contado</td>
            <td class="info-value info-value-mono">
                {{ $caja->monto_final !== null ? 'S/ ' . number_format($caja->monto_final, 2) : 'N/A' }}
            </td>
            <td class="info-label">Diferencia de Arqueo</td>
            <td class="info-value info-value-mono font-bold" style="color: {{ $caja->diferencia == 0 ? '#166534' : ($caja->diferencia > 0 ? '#166534' : '#b91c1c') }}">
                {{ $caja->diferencia !== null ? ($caja->diferencia > 0 ? '+' : '') . 'S/ ' . number_format($caja->diferencia, 2) : 'N/A' }}
            </td>
        </tr>
    </table>

    @if($caja->diferencia !== null)
        @if($caja->diferencia == 0)
            <div class="alert-box alert-success">
                <strong>Arqueo Conforme:</strong> La caja fue cuadrada perfectamente sin registrar diferencias.
            </div>
        @else
            <div class="alert-box {{ $caja->diferencia > 0 ? 'alert-success' : 'alert-danger' }}">
                <strong>Arqueo con {{ $caja->diferencia > 0 ? 'Sobrante' : 'Faltante' }}:</strong> 
                Se detectó una diferencia de S/ {{ number_format($caja->diferencia, 2) }}.
                @if($caja->diferencia < 0 && $caja->motivo_diferencia)
                    <br><strong>Motivo declarado:</strong> "{{ $caja->motivo_diferencia }}"
                @endif
            </div>
        @endif
    @endif

    <!-- CONSOLIDADO DE COBROS / INGRESOS / EGRESOS -->
    <div class="section-title">Consolidado Financiero</div>
    <div class="summary-card">
        <table class="summary-table">
            <tr>
                <td class="summary-label">Ventas cobradas en Efectivo</td>
                <td class="summary-value">S/ {{ number_format($ventas_efectivo, 2) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Ventas cobradas por Yape / Plin</td>
                <td class="summary-value">S/ {{ number_format($ventas_yape_plin, 2) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Ventas cobradas con Tarjeta</td>
                <td class="summary-value">S/ {{ number_format($ventas_tarjeta - $ventas_transferencia, 2) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Ventas cobradas por Transferencia Bancaria</td>
                <td class="summary-value">S/ {{ number_format($ventas_transferencia, 2) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Ingresos Adicionales de Caja (+)</td>
                <td class="summary-value">S/ {{ number_format($caja->ingresos ? $caja->ingresos->sum('monto') : 0, 2) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Gastos de Caja (-)</td>
                <td class="summary-value">- S/ {{ number_format($caja->gastos ? $caja->gastos->sum('monto') : 0, 2) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Retiros / Extracciones de Caja (-)</td>
                <td class="summary-value">- S/ {{ number_format($caja->retiros ? $caja->retiros->sum('monto') : 0, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td class="summary-total total-label">EFECTIVO NETO ESPERADO EN CAJA</td>
                <td class="summary-total-value">S/ {{ number_format($caja->monto_esperado, 2) }}</td>
            </tr>
        </table>
    </div>

    <!-- PRODUCTOS VENDIDOS -->
    <div class="section-title">Productos Vendidos</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Descripción del Producto</th>
                <th class="text-center" style="width: 20%;">Unidad</th>
                <th class="text-center" style="width: 20%;">Cantidad</th>
                <th class="text-right" style="width: 25%;">Total Facturado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($productos_vendidos as $p)
                <tr>
                    <td class="font-bold">{{ $p->nombre }}</td>
                    <td class="text-center" style="text-transform: capitalize;">{{ $p->unidad_medida }}</td>
                    <td class="text-center font-mono">
                        @if(in_array(strtolower($p->unidad_medida), ['kilogramo', 'gramo', 'litro', 'mililitro']))
                            {{ number_format($p->cantidad, 3) }}
                        @else
                            {{ number_format($p->cantidad, 0) }}
                        @endif
                    </td>
                    <td class="text-right font-mono font-bold">S/ {{ number_format($p->total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center" style="color: #94a3b8; padding: 15px 0;">No se registraron productos vendidos en esta sesión.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- REGISTRO DE AUDITORÍA (MOVIMIENTOS) -->
    <div class="section-title">Historial de Movimientos de Caja</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 18%;">Hora</th>
                <th class="text-center" style="width: 15%;">Tipo</th>
                <th>Descripción</th>
                <th class="text-right" style="width: 20%;">Monto</th>
            </tr>
        </thead>
        <tbody>
            @foreach($caja->movimientos as $mov)
                <tr>
                    <td class="font-mono text-center">{{ $mov->hora }}</td>
                    <td class="text-center">
                        <span class="badge badge-{{ $mov->tipo }}">
                            @switch($mov->tipo)
                                @case('apertura') Apertura @break
                                @case('venta') Venta @break
                                @case('gasto') Gasto @break
                                @case('retiro') Retiro @break
                                @case('ingreso') Ingreso @break
                                @case('cierre') Cierre @break
                                @default {{ $mov->tipo }}
                            @endswitch
                        </span>
                    </td>
                    <td>{{ $mov->descripcion }}</td>
                    <td class="text-right font-mono font-bold" style="color: {{ in_array($mov->tipo, ['gasto', 'retiro']) ? '#991b1b' : '#334155' }}">
                        {{ in_array($mov->tipo, ['gasto', 'retiro']) ? '-' : '' }}S/ {{ number_format($mov->monto, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
