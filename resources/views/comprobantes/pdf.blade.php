<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Comprobante {{ $venta->numero_venta }}</title>
    <style>
        @page {
            size: 300pt 750pt;
            margin: 0;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #334155;
            margin: 0;
            padding: 18px;
            background-color: #ffffff;
            font-size: 11px;
            line-height: 1.4;
        }
        .header {
            text-align: center;
            margin-bottom: 16px;
        }
        .company-logo {
            max-height: 48px;
            max-width: 140px;
            display: block;
            margin: 0 auto 10px auto;
        }
        .company-name {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }
        .badge-container {
            margin-bottom: 10px;
        }
        .badge {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: 9.5px;
            padding: 3px 10px;
            border-radius: 9999px;
            text-transform: uppercase;
            font-family: monospace;
            display: inline-block;
            border: 1px solid #e2e8f0;
        }
        .company-details {
            font-size: 9.5px;
            color: #64748b;
            line-height: 1.5;
        }
        
        .divider {
            border-top: 1px dashed #cbd5e1;
            margin: 14px 0;
        }
        
        .info-card {
            background-color: #f8fafc;
            border-radius: 12px;
            padding: 12px 14px;
            border: 1px solid #e2e8f0;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 3px 0;
            font-size: 10px;
            vertical-align: middle;
        }
        .info-label {
            color: #64748b;
            font-weight: 500;
        }
        .info-value {
            color: #0f172a;
            font-weight: 600;
            text-align: right;
        }
        
        .payment-badge {
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 6px;
            text-transform: uppercase;
            display: inline-block;
        }
        .badge-efectivo {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        .badge-tarjeta {
            background-color: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
        .badge-transferencia {
            background-color: #fffbeb;
            color: #854d0e;
            border: 1px solid #fef08a;
        }
        .badge-yape {
            background-color: #faf5ff;
            color: #5b21b6;
            border: 1px solid #e9d5ff;
        }
        
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .products-table th {
            font-size: 9px;
            text-transform: uppercase;
            color: #94a3b8;
            font-weight: 700;
            padding-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
        }
        .products-table td {
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 11px;
            vertical-align: middle;
        }
        .product-name {
            font-weight: 600;
            color: #1e293b;
        }
        .product-meta {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }
        .total-col {
            font-weight: 700;
            color: #0f172a;
        }
        
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        .summary-table td {
            padding: 4px 0;
            font-size: 11px;
        }
        .summary-label {
            color: #64748b;
            font-weight: 500;
        }
        .summary-value {
            color: #1e293b;
            font-weight: 600;
            text-align: right;
        }
        .discount-label {
            color: #ef4444;
        }
        .discount-value {
            color: #ef4444;
            text-align: right;
            font-weight: 600;
        }
        .total-row td {
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
        }
        .total-label {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
        }
        .total-value {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            text-align: right;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
        }
        .thanks-message {
            font-size: 11px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 2px;
        }
        .conserve-message {
            font-size: 9px;
            color: #64748b;
            margin-bottom: 12px;
        }
        .footer-contacts {
            margin-bottom: 12px;
        }
        .contact-pill {
            display: inline-block;
            background-color: #f1f5f9;
            color: #475569;
            font-size: 8.5px;
            padding: 2.5px 8px;
            border-radius: 9999px;
            font-weight: 500;
            margin: 2px;
            border: 1px solid #e2e8f0;
        }
        .brand-signature {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            font-weight: 700;
            margin-top: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        @if(isset($logoBase64) && $logoBase64)
            <img src="{{ $logoBase64 }}" class="company-logo" />
        @else
            <div class="company-name">{{ $empresa->nombre }}</div>
        @endif
        
        <div class="badge-container">
            <span class="badge">N° {{ $venta->numero_venta }}</span>
        </div>
        
        <div class="company-details">
            @if($empresa->ruc)
                <div>RUC: {{ $empresa->ruc }}</div>
            @endif
            @if($empresa->direccion)
                <div>{{ $empresa->direccion }}</div>
            @endif
            <div>Fecha: {{ $venta->created_at->format('d/m/Y H:i') }}</div>
        </div>
    </div>
    
    <div class="info-card">
        <table class="info-table">
            <tr>
                <td class="info-label">Cliente</td>
                <td class="info-value">{{ $venta->cliente ? $venta->cliente->nombre : 'Público General' }}</td>
            </tr>
            @if($venta->cliente && $venta->cliente->documento_numero)
                <tr>
                    <td class="info-label">{{ $venta->cliente->documento_tipo }}</td>
                    <td class="info-value">{{ $venta->cliente->documento_numero }}</td>
                </tr>
            @endif
            <tr>
                <td class="info-label">Vendedor</td>
                <td class="info-value">{{ $venta->usuario ? $venta->usuario->nombre : 'Cajero' }}</td>
            </tr>
            <tr>
                <td class="info-label">Pago</td>
                <td class="info-value">
                    @php
                        $badgeClass = '';
                        $paymentLabel = '';
                        switch($venta->metodo_pago) {
                            case 'efectivo':
                                $badgeClass = 'badge-efectivo';
                                $paymentLabel = 'Efectivo';
                                break;
                            case 'tarjeta':
                                $badgeClass = 'badge-tarjeta';
                                $paymentLabel = 'Tarjeta';
                                break;
                            case 'transferencia':
                                $badgeClass = 'badge-transferencia';
                                $paymentLabel = 'Transferencia';
                                break;
                            case 'yape_plin':
                                $badgeClass = 'badge-yape';
                                $paymentLabel = 'Yape / Plin';
                                break;
                            default:
                                $badgeClass = 'badge-tarjeta';
                                $paymentLabel = ucfirst($venta->metodo_pago);
                        }
                    @endphp
                    <span class="payment-badge {{ $badgeClass }}">
                        @if($venta->metodo_pago === 'efectivo')
                            <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 1px; display: inline-block;">
                                <rect x="2" y="6" width="20" height="12" rx="2" />
                                <circle cx="12" cy="12" r="2" />
                            </svg>
                        @elseif($venta->metodo_pago === 'tarjeta')
                            <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 1px; display: inline-block;">
                                <rect x="2" y="5" width="20" height="14" rx="2" />
                                <line x1="2" y1="10" x2="22" y2="10" />
                            </svg>
                        @elseif($venta->metodo_pago === 'transferencia')
                            <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 1px; display: inline-block;">
                                <path d="M3 21h18M3 10h18M5 10v11M19 10v11M12 10v11M11 3l-9 7h18l-9-7z" />
                            </svg>
                        @else
                            <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 1px; display: inline-block;">
                                <rect x="5" y="2" width="14" height="20" rx="2" />
                                <line x1="12" y1="18" x2="12" y2="18.01" />
                            </svg>
                        @endif
                        <span style="vertical-align: middle;">{{ $paymentLabel }}</span>
                    </span>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="divider"></div>
    
    <table class="products-table">
        <thead>
            <tr>
                <th align="left">Producto</th>
                <th align="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $detalle)
                @php
                    $producto = $detalle->producto;
                    $unidad = strtolower($producto->unidad_medida ?? 'unidad');
                    $isBulk = in_array($unidad, ['kilogramo', 'gramo', 'litro', 'mililitro', 'kg', 'l']) || in_array($producto->tipo_venta, ['peso', 'volumen']);
                    $cantidadFormatted = $isBulk ? number_format($detalle->cantidad, 3) : ($detalle->cantidad == floor($detalle->cantidad) ? number_format($detalle->cantidad, 0) : number_format($detalle->cantidad, 2));
                    $unidadLabel = $isBulk ? $producto->unidad_medida : 'u';
                @endphp
                <tr>
                    <td>
                        <div class="product-name">{{ $producto->nombre }}</div>
                        <div class="product-meta">
                            {{ $cantidadFormatted }} {{ $unidadLabel }} x S/ {{ number_format($detalle->precio_unitario, 2) }}
                        </div>
                    </td>
                    <td align="right" class="total-col">
                        S/ {{ number_format($detalle->subtotal, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    
    <table class="summary-table">
        <tr>
            <td class="summary-label">Subtotal</td>
            <td class="summary-value">S/ {{ number_format($venta->subtotal, 2) }}</td>
        </tr>
        @if($venta->descuento > 0)
            <tr>
                <td class="summary-label discount-label">Descuento</td>
                <td class="summary-value discount-value">-S/ {{ number_format($venta->descuento, 2) }}</td>
            </tr>
        @endif
        @if($venta->metodo_pago === 'efectivo' && isset($vuelto) && $vuelto > 0)
            <tr>
                <td class="summary-label">Efectivo Recibido</td>
                <td class="summary-value">S/ {{ number_format($venta->total + $vuelto, 2) }}</td>
            </tr>
            <tr>
                <td class="summary-label">Vuelto</td>
                <td class="summary-value">S/ {{ number_format($vuelto, 2) }}</td>
            </tr>
        @endif
        <tr class="total-row">
            <td class="total-label">Total</td>
            <td class="total-value">S/ {{ number_format($venta->total, 2) }}</td>
        </tr>
    </table>
    
    <div class="divider"></div>
    
    <div class="footer">
        <div class="thanks-message">¡Gracias por comprar en {{ $empresa->nombre }}!</div>
        <div class="conserve-message">Conserve este comprobante digital.</div>
        
        <div class="footer-contacts">
            @if($empresa->telefono)
                <span class="contact-pill">
                    <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="vertical-align: middle; margin-right: 2px; display: inline-block;">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    {{ $empresa->telefono }}
                </span>
            @endif
            @if($empresa->correo)
                <span class="contact-pill">
                    <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="vertical-align: middle; margin-right: 2px; display: inline-block;">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                    {{ $empresa->correo }}
                </span>
            @endif
        </div>
        
        <div class="brand-signature">Generado por Inventex ERP</div>
    </div>
</body>
</html>
