<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Compra;
use App\Models\DetalleCompra;
use App\Models\Empresa;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Receta;
use App\Models\Usuario;
use App\Models\Venta;
use App\Models\DetalleVenta;
use App\Models\ActividadSistema;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Resolve Empresa and Users
        $empresa = Empresa::where('ruc', '20123456789')->first();
        if (!$empresa) {
            echo "Empresa Bodega Don Pepe not found. Run main DatabaseSeeder first.\n";
            return;
        }

        $admin = Usuario::where('correo', 'donpepe@inventex.com')->first();
        $cajero = Usuario::where('correo', 'cajero@donpepe.com')->first();

        if (!$admin || !$cajero) {
            echo "Users not found. Run main DatabaseSeeder first.\n";
            return;
        }

        // Clean existing tables for tenant (except plans, empresas, usuarios)
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Categoria::where('empresa_id', $empresa->id)->forceDelete();
        Proveedor::where('empresa_id', $empresa->id)->forceDelete();
        Cliente::where('empresa_id', $empresa->id)->forceDelete();
        Producto::where('empresa_id', $empresa->id)->forceDelete();
        Receta::whereHas('productoPadre', function($q) use ($empresa) {
            $q->where('empresa_id', $empresa->id);
        })->delete();
        Compra::where('empresa_id', $empresa->id)->forceDelete();
        DetalleCompra::where('empresa_id', $empresa->id)->forceDelete();
        Venta::where('empresa_id', $empresa->id)->forceDelete();
        DetalleVenta::where('empresa_id', $empresa->id)->forceDelete();
        MovimientoStock::where('empresa_id', $empresa->id)->forceDelete();
        ActividadSistema::where('empresa_id', $empresa->id)->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        echo "Cleaning previous data completed.\n";

        // 2. Seed Categorías
        $catAbarrotes = Categoria::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Abarrotes',
            'descripcion' => 'Arroz, aceites, conservas y abarrotes en general.',
            'estado' => 'activo',
            'creado_por' => $admin->id,
        ]);

        $catFrutas = Categoria::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Frutas',
            'descripcion' => 'Frutas frescas y de estación.',
            'estado' => 'activo',
            'creado_por' => $admin->id,
        ]);

        $catVerduras = Categoria::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Verduras',
            'descripcion' => 'Verduras, hortalizas y tubérculos frescos.',
            'estado' => 'activo',
            'creado_por' => $admin->id,
        ]);

        $catCarnes = Categoria::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Carnes',
            'descripcion' => 'Pollo, carnes, embutidos y derivados.',
            'estado' => 'activo',
            'creado_por' => $admin->id,
        ]);

        $catLacteos = Categoria::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Lácteos',
            'descripcion' => 'Leche, quesos, yogures y derivados lácteos.',
            'estado' => 'activo',
            'creado_por' => $admin->id,
        ]);

        $catBebidas = Categoria::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Bebidas',
            'descripcion' => 'Gaseosas, jugos, aguas y licores.',
            'estado' => 'activo',
            'creado_por' => $admin->id,
        ]);

        $catLimpieza = Categoria::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Limpieza',
            'descripcion' => 'Artículos de limpieza para el hogar.',
            'estado' => 'activo',
            'creado_por' => $admin->id,
        ]);

        $catHigiene = Categoria::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Higiene',
            'descripcion' => 'Cuidado e higiene personal.',
            'estado' => 'activo',
            'creado_por' => $admin->id,
        ]);

        $catGranel = Categoria::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Granel',
            'descripcion' => 'Cereales, legumbres y granos a granel.',
            'estado' => 'activo',
            'creado_por' => $admin->id,
        ]);

        echo "Categorías seeded.\n";

        // 3. Seed Proveedores
        $provGloria = Proveedor::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Distribuidora Gloria S.A.',
            'ruc' => '20100222333',
            'direccion' => 'Av. República de Panamá 2461, Lima',
            'telefono' => '999111222',
            'correo' => 'ventas@gloria.com.pe',
            'estado' => 'activo',
            'creado_por' => $admin->id,
        ]);

        $provBackus = Proveedor::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Cervecería Backus y Johnston',
            'ruc' => '20100111222',
            'direccion' => 'Av. El Directo 123, Ate, Lima',
            'telefono' => '999333444',
            'correo' => 'pedidos@backus.com.pe',
            'estado' => 'activo',
            'creado_por' => $admin->id,
        ]);

        $provAlicorp = Proveedor::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Alicorp Distribuciones S.A.C.',
            'ruc' => '20100444555',
            'direccion' => 'Av. Argentina 4791, Callao',
            'telefono' => '999555666',
            'correo' => 'ventas@alicorp.com.pe',
            'estado' => 'activo',
            'creado_por' => $admin->id,
        ]);

        $provMercado = Proveedor::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Mercado Mayorista de Productores',
            'ruc' => '20500999888',
            'direccion' => 'Av. Metropolitana 450, Santa Anita',
            'telefono' => '988555111',
            'correo' => 'pedidos@mayoristasantaanita.com',
            'estado' => 'activo',
            'creado_por' => $admin->id,
        ]);

        echo "Proveedores seeded.\n";

        // 4. Seed Clientes
        $cliGeneral = Cliente::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Público General',
            'documento_tipo' => 'DNI',
            'documento_numero' => '00000000',
            'direccion' => 'Tienda Principal',
            'telefono' => '0000000',
            'correo' => 'cajageneral@donpepe.com',
        ]);

        $cliJuan = Cliente::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Juan Pérez',
            'documento_tipo' => 'DNI',
            'documento_numero' => '08462837',
            'direccion' => 'Av. Arequipa 1200, Lince',
            'telefono' => '987654321',
            'correo' => 'juan.perez@gmail.com',
        ]);

        $cliMaria = Cliente::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'María Rodríguez',
            'documento_tipo' => 'DNI',
            'documento_numero' => '41234567',
            'direccion' => 'Av. La Mar 1050, San Isidro',
            'telefono' => '933444555',
            'correo' => 'maria@gmail.com',
        ]);

        $cliCarlos = Cliente::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Carlos Quispe',
            'documento_tipo' => 'DNI',
            'documento_numero' => '12345678',
            'direccion' => 'Jr. Puno 450, Cercado de Lima',
            'telefono' => '922333444',
            'correo' => 'carlos.quispe@outlook.com',
        ]);

        $cliRosa = Cliente::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Rosa Huamán',
            'documento_tipo' => 'DNI',
            'documento_numero' => '23456789',
            'direccion' => 'Av. Larco 780, Miraflores',
            'telefono' => '955666777',
            'correo' => 'rosa.huaman@hotmail.com',
        ]);

        $cliLuis = Cliente::create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Luis Flores',
            'documento_tipo' => 'DNI',
            'documento_numero' => '34567890',
            'direccion' => 'Av. Javier Prado 2300, San Borja',
            'telefono' => '966777888',
            'correo' => 'luis.flores@yahoo.com',
        ]);

        echo "Clientes seeded.\n";

        // 5. Seed Productos
        // Define products by unit
        $prodUnits = [
            ['nombre' => 'Gaseosa Coca Cola 1.5L', 'categoria_id' => $catBebidas->id, 'codigo_barras' => '7750000000001', 'precio_compra' => 4.80, 'precio_venta' => 6.50, 'stock_actual' => 150.0],
            ['nombre' => 'Cerveza Pilsen Callao Botella 630ml', 'categoria_id' => $catBebidas->id, 'codigo_barras' => '7750000000002', 'precio_compra' => 5.00, 'precio_venta' => 7.50, 'stock_actual' => 200.0],
            ['nombre' => 'Leche Gloria Evaporada Azul 400g', 'categoria_id' => $catLacteos->id, 'codigo_barras' => '7750123456789', 'precio_compra' => 3.20, 'precio_venta' => 4.50, 'stock_actual' => 180.0],
            ['nombre' => 'Fideos Don Vittorio Spaguetti 500g', 'categoria_id' => $catAbarrotes->id, 'codigo_barras' => '7750000000004', 'precio_compra' => 2.50, 'precio_venta' => 3.50, 'stock_actual' => 120.0],
            ['nombre' => 'Atún Florida Trozos de Atún', 'categoria_id' => $catAbarrotes->id, 'codigo_barras' => '7750000000005', 'precio_compra' => 4.20, 'precio_venta' => 5.80, 'stock_actual' => 90.0],
            ['nombre' => 'Galletas Oreo Original', 'categoria_id' => $catAbarrotes->id, 'codigo_barras' => '7750000000006', 'precio_compra' => 1.00, 'precio_venta' => 1.50, 'stock_actual' => 300.0],
            ['nombre' => 'Aceite Primor Premium 1L', 'categoria_id' => $catAbarrotes->id, 'codigo_barras' => '7750000000007', 'precio_compra' => 6.80, 'precio_venta' => 8.50, 'stock_actual' => 80.0],
            ['nombre' => 'Papel Higiénico Elite Doble Hoja', 'categoria_id' => $catHigiene->id, 'codigo_barras' => '7750000000008', 'precio_compra' => 3.80, 'precio_venta' => 5.00, 'stock_actual' => 70.0],
            ['nombre' => 'Jabón Bolívar Antibacterial', 'categoria_id' => $catLimpieza->id, 'codigo_barras' => '7750000000009', 'precio_compra' => 2.80, 'precio_venta' => 4.00, 'stock_actual' => 110.0],
            ['nombre' => 'Detergente Ariel Power Liquid 1L', 'categoria_id' => $catLimpieza->id, 'codigo_barras' => '7750000000010', 'precio_compra' => 5.20, 'precio_venta' => 7.20, 'stock_actual' => 60.0],
        ];

        // Define products by weight (kilogram)
        $prodWeights = [
            ['nombre' => 'Arroz Costeño a granel', 'categoria_id' => $catGranel->id, 'codigo_barras' => '7750000000011', 'precio_compra' => 3.80, 'precio_venta' => 5.20, 'stock_actual' => 1000.0],
            ['nombre' => 'Azúcar Rubia a granel', 'categoria_id' => $catGranel->id, 'codigo_barras' => '7750000000012', 'precio_compra' => 3.00, 'precio_venta' => 4.20, 'stock_actual' => 800.0],
            ['nombre' => 'Tomate Italiano', 'categoria_id' => $catVerduras->id, 'codigo_barras' => '7750000000013', 'precio_compra' => 2.80, 'precio_venta' => 4.00, 'stock_actual' => 400.0],
            ['nombre' => 'Papa Blanca', 'categoria_id' => $catVerduras->id, 'codigo_barras' => '7750000000014', 'precio_compra' => 1.80, 'precio_venta' => 2.80, 'stock_actual' => 600.0],
            ['nombre' => 'Papa Yungay', 'categoria_id' => $catVerduras->id, 'codigo_barras' => '7750000000015', 'precio_compra' => 2.00, 'precio_venta' => 3.00, 'stock_actual' => 500.0],
            ['nombre' => 'Cebolla Roja', 'categoria_id' => $catVerduras->id, 'codigo_barras' => '7750000000016', 'precio_compra' => 2.20, 'precio_venta' => 3.20, 'stock_actual' => 400.0],
            ['nombre' => 'Limón Sutil', 'categoria_id' => $catVerduras->id, 'codigo_barras' => '7750000000017', 'precio_compra' => 3.50, 'precio_venta' => 5.00, 'stock_actual' => 300.0],
            ['nombre' => 'Palta Hass', 'categoria_id' => $catFrutas->id, 'codigo_barras' => '7750000000018', 'precio_compra' => 5.50, 'precio_venta' => 8.00, 'stock_actual' => 200.0],
            ['nombre' => 'Zanahoria Criolla', 'categoria_id' => $catVerduras->id, 'codigo_barras' => '7750000000019', 'precio_compra' => 1.80, 'precio_venta' => 2.50, 'stock_actual' => 250.0],
            ['nombre' => 'Queso Fresco Cajamarquino', 'categoria_id' => $catLacteos->id, 'codigo_barras' => '7750000000020', 'precio_compra' => 12.00, 'precio_venta' => 16.50, 'stock_actual' => 150.0],
            ['nombre' => 'Pollo Entero Limpio', 'categoria_id' => $catCarnes->id, 'codigo_barras' => '7750000000021', 'precio_compra' => 7.80, 'precio_venta' => 10.50, 'stock_actual' => 350.0],
            ['nombre' => 'Carne Molida Especial', 'categoria_id' => $catCarnes->id, 'codigo_barras' => '7750000000022', 'precio_compra' => 16.00, 'precio_venta' => 22.00, 'stock_actual' => 150.0],
            ['nombre' => 'Manzana Israel', 'categoria_id' => $catFrutas->id, 'codigo_barras' => '7750000000023', 'precio_compra' => 3.20, 'precio_venta' => 4.50, 'stock_actual' => 200.0],
            ['nombre' => 'Plátano Bellaco', 'categoria_id' => $catFrutas->id, 'codigo_barras' => '7750000000024', 'precio_compra' => 3.00, 'precio_venta' => 4.50, 'stock_actual' => 250.0],
            ['nombre' => 'Avena a granel', 'categoria_id' => $catGranel->id, 'codigo_barras' => '7750000000025', 'precio_compra' => 4.50, 'precio_venta' => 6.20, 'stock_actual' => 300.0],
            ['nombre' => 'Café a granel', 'categoria_id' => $catGranel->id, 'codigo_barras' => '7750000000026', 'precio_compra' => 18.00, 'precio_venta' => 25.00, 'stock_actual' => 100.0],
        ];

        $allProducts = [];

        // Insert Unit Products
        foreach ($prodUnits as $pu) {
            $allProducts[] = Producto::create([
                'empresa_id' => $empresa->id,
                'categoria_id' => $pu['categoria_id'],
                'nombre' => $pu['nombre'],
                'codigo_barras' => $pu['codigo_barras'],
                'tipo_producto' => 'estandar',
                'tipo_venta' => 'unidad',
                'unidad_medida' => 'unidad',
                'precio_compra' => $pu['precio_compra'],
                'precio_venta' => $pu['precio_venta'],
                'stock_minimo' => 15.00,
                'stock_actual' => $pu['stock_actual'],
                'estado' => 'activo',
                'creado_por' => $admin->id,
            ]);
        }

        // Insert Weight Products
        foreach ($prodWeights as $pw) {
            $allProducts[] = Producto::create([
                'empresa_id' => $empresa->id,
                'categoria_id' => $pw['categoria_id'],
                'nombre' => $pw['nombre'],
                'codigo_barras' => $pw['codigo_barras'],
                'tipo_producto' => 'estandar',
                'tipo_venta' => 'peso',
                'unidad_medida' => 'kilogramo',
                'precio_compra' => $pw['precio_compra'],
                'precio_venta' => $pw['precio_venta'],
                'stock_minimo' => 10.00,
                'stock_actual' => $pw['stock_actual'],
                'estado' => 'activo',
                'creado_por' => $admin->id,
            ]);
        }

        echo "Productos seeded.\n";

        // 6. Seed Compras (Abastecimiento Inicial - Hace 35 días)
        $comprasData = [
            ['proveedor' => $provAlicorp, 'items' => [
                ['name' => 'Fideos Don Vittorio Spaguetti 500g', 'qty' => 500],
                ['name' => 'Atún Florida Trozos de Atún', 'qty' => 400],
                ['name' => 'Galletas Oreo Original', 'qty' => 1000],
                ['name' => 'Aceite Primor Premium 1L', 'qty' => 300],
            ]],
            ['proveedor' => $provGloria, 'items' => [
                ['name' => 'Leche Gloria Evaporada Azul 400g', 'qty' => 600],
                ['name' => 'Queso Fresco Cajamarquino', 'qty' => 200],
            ]],
            ['proveedor' => $provBackus, 'items' => [
                ['name' => 'Gaseosa Coca Cola 1.5L', 'qty' => 500],
                ['name' => 'Cerveza Pilsen Callao Botella 630ml', 'qty' => 600],
            ]],
            ['proveedor' => $provMercado, 'items' => [
                ['name' => 'Arroz Costeño a granel', 'qty' => 2000],
                ['name' => 'Azúcar Rubia a granel', 'qty' => 1500],
                ['name' => 'Tomate Italiano', 'qty' => 1000],
                ['name' => 'Papa Blanca', 'qty' => 1500],
                ['name' => 'Papa Yungay', 'qty' => 1000],
                ['name' => 'Cebolla Roja', 'qty' => 1000],
                ['name' => 'Limón Sutil', 'qty' => 800],
                ['name' => 'Pollo Entero Limpio', 'qty' => 1200],
                ['name' => 'Carne Molida Especial', 'qty' => 500],
            ]],
        ];

        foreach ($comprasData as $idx => $cData) {
            $prov = $cData['proveedor'];
            $compraItems = [];
            $totalC = 0;

            foreach ($cData['items'] as $cItem) {
                $p = collect($allProducts)->first(fn($prod) => $prod->nombre === $cItem['name']);
                if ($p) {
                    $subC = $cItem['qty'] * $p->precio_compra;
                    $compraItems[] = [
                        'producto_id' => $p->id,
                        'cantidad' => $cItem['qty'],
                        'precio_unitario' => $p->precio_compra,
                        'subtotal' => $subC
                    ];
                    $totalC += $subC;
                }
            }

            $compra = Compra::create([
                'empresa_id' => $empresa->id,
                'proveedor_id' => $prov->id,
                'usuario_id' => $admin->id,
                'numero_compra' => 'COM-00' . ($idx + 1),
                'fecha_compra' => now()->subDays(35),
                'total' => $totalC,
                'estado' => 'completada',
                'notas' => 'Abastecimiento inicial de mercadería',
                'creado_por' => $admin->id,
                'created_at' => now()->subDays(35),
                'updated_at' => now()->subDays(35),
            ]);

            foreach ($compraItems as $item) {
                DetalleCompra::create([
                    'empresa_id' => $empresa->id,
                    'compra_id' => $compra->id,
                    'producto_id' => $item['producto_id'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal' => $item['subtotal']
                ]);

                MovimientoStock::create([
                    'empresa_id' => $empresa->id,
                    'producto_id' => $item['producto_id'],
                    'tipo' => 'entrada',
                    'cantidad' => $item['cantidad'],
                    'motivo' => 'compra',
                    'referencia_tipo' => Compra::class,
                    'referencia_id' => $compra->id,
                    'descripcion' => "Ingreso por compra " . $compra->numero_compra,
                    'creado_por' => $admin->id,
                    'created_at' => now()->subDays(35),
                    'updated_at' => now()->subDays(35),
                ]);
            }
        }

        echo "Compras seeded.\n";

        // 7. Seed 50 Ventas (Distribuidas en los últimos 30 días)
        $clientesList = [$cliGeneral, $cliJuan, $cliMaria, $cliCarlos, $cliRosa, $cliLuis];
        
        for ($i = 1; $i <= 50; $i++) {
            $cliente = $clientesList[array_rand($clientesList)];
            // Distribución equitativa en los últimos 30 días
            $fecha = now()->subDays(30 - ($i * 0.58))->subHours(rand(0, 8))->subMinutes(rand(0, 59));
            
            // Metodos de pago más usados en bodegas peruanas
            $randPago = rand(1, 100);
            if ($randPago <= 40) {
                $metodoPago = 'efectivo';
            } elseif ($randPago <= 80) {
                $metodoPago = 'yape_plin';
            } elseif ($randPago <= 95) {
                $metodoPago = 'tarjeta';
            } else {
                $metodoPago = 'transferencia';
            }

            // Entre 1 y 4 productos por venta
            $numItems = rand(1, 4);
            $saleItems = [];
            $shuffledProds = collect($allProducts)->shuffle();
            $subtotal = 0;
            $descuentoItems = 0;

            for ($j = 0; $j < $numItems; $j++) {
                $prod = $shuffledProds[$j];
                
                if ($prod->tipo_venta === 'peso') {
                    // Pesos peruanos típicos en bodegas: 0.250kg, 0.500kg, 0.750kg, 1kg, 1.5kg, 2kg, 3kg
                    $weights = [0.250, 0.500, 0.750, 1.000, 1.250, 1.500, 2.000, 2.500, 3.000, 5.000];
                    $qty = $weights[array_rand($weights)];
                } else {
                    // Unidades: 1 a 4
                    $qty = rand(1, 4);
                }

                $precioUnit = (float)$prod->precio_venta;
                $itemSubtotal = $qty * $precioUnit;
                
                // Descuento aleatorio ocasional (5% de probabilidad)
                $disc = 0;
                if (rand(1, 20) === 1) {
                    $disc = round($itemSubtotal * 0.1, 2);
                }

                $itemNet = $itemSubtotal - $disc;

                $saleItems[] = [
                    'producto_id' => $prod->id,
                    'cantidad' => $qty,
                    'precio_unitario' => $precioUnit,
                    'descuento' => $disc,
                    'subtotal' => $itemNet,
                    'producto' => $prod
                ];

                $subtotal += $itemSubtotal;
                $descuentoItems += $disc;
            }

            $total = $subtotal - $descuentoItems;
            $serial = str_pad($i, 6, '0', STR_PAD_LEFT);
            $numeroVenta = 'VEN-' . $serial;

            $venta = Venta::create([
                'empresa_id' => $empresa->id,
                'cliente_id' => $cliente->id,
                'usuario_id' => $cajero->id,
                'numero_venta' => $numeroVenta,
                'fecha_venta' => $fecha,
                'subtotal' => $subtotal,
                'impuesto' => 0.00,
                'descuento' => $descuentoItems,
                'total' => $total,
                'metodo_pago' => $metodoPago,
                'estado' => 'completada',
                'creado_por' => $cajero->id,
                'created_at' => $fecha,
                'updated_at' => $fecha,
            ]);

            foreach ($saleItems as $sItem) {
                DetalleVenta::create([
                    'empresa_id' => $empresa->id,
                    'venta_id' => $venta->id,
                    'producto_id' => $sItem['producto_id'],
                    'cantidad' => $sItem['cantidad'],
                    'precio_unitario' => $sItem['precio_unitario'],
                    'descuento' => $sItem['descuento'],
                    'subtotal' => $sItem['subtotal'],
                ]);

                // Decrement stock
                $sItem['producto']->decrement('stock_actual', $sItem['cantidad']);

                MovimientoStock::create([
                    'empresa_id' => $empresa->id,
                    'producto_id' => $sItem['producto_id'],
                    'tipo' => 'salida',
                    'cantidad' => $sItem['cantidad'],
                    'motivo' => 'venta',
                    'referencia_tipo' => Venta::class,
                    'referencia_id' => $venta->id,
                    'descripcion' => "Venta n° " . $venta->numero_venta,
                    'creado_por' => $cajero->id,
                    'created_at' => $fecha,
                    'updated_at' => $fecha,
                ]);
            }
        }

        echo "Ventas seeded.\n";

        // 8. Adjust stock values to match the exact user requirements
        // We set the stock_actual directly and create a stock entry adjustment so that it's clean and exact.
        $targetStocks = [
            ['name' => 'Tomate Italiano', 'stock' => 125.500],
            ['name' => 'Papa Blanca', 'stock' => 250.250],
            ['name' => 'Arroz Costeño a granel', 'stock' => 500.750],
            ['name' => 'Azúcar Rubia a granel', 'stock' => 300.500],
            ['name' => 'Queso Fresco Cajamarquino', 'stock' => 45.250],
            ['name' => 'Pollo Entero Limpio', 'stock' => 80.500],
        ];

        foreach ($targetStocks as $ts) {
            $p = collect($allProducts)->first(fn($prod) => $prod->nombre === $ts['name']);
            if ($p) {
                $oldStock = (float)$p->stock_actual;
                $newStock = $ts['stock'];
                $diff = $newStock - $oldStock;

                $p->update(['stock_actual' => $newStock]);

                if (abs($diff) > 0.001) {
                    MovimientoStock::create([
                        'empresa_id' => $empresa->id,
                        'producto_id' => $p->id,
                        'tipo' => $diff > 0 ? 'entrada' : 'salida',
                        'cantidad' => abs($diff),
                        'motivo' => 'ajuste_inventario',
                        'referencia_tipo' => null,
                        'referencia_id' => null,
                        'descripcion' => "Ajuste de inventario para cuadre de stock inicial/real",
                        'creado_por' => $admin->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        echo "Stock adjustments applied to target products.\n";

        // 9. Seed Audit/Actividades
        ActividadSistema::create([
            'empresa_id' => $empresa->id,
            'usuario_id' => $admin->id,
            'evento' => 'actualizacion',
            'tabla' => 'productos',
            'registro_id' => $allProducts[0]->id,
            'valores_anteriores' => ['stock_actual' => 100],
            'valores_nuevos' => ['stock_actual' => 150],
            'ip' => '127.0.0.1',
            'agente_usuario' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.0.0 Safari/537.36',
        ]);

        echo "Audits seeded.\n";
        echo "DemoDataSeeder successfully completed!\n";
    }
}
