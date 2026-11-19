<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Dropdown from '@/Components/Dropdown.vue';
import axios from 'axios';

// Lucide Icons
import { 
    Truck, Plus, Search, AlertCircle, Calendar, FileText, X, DollarSign, 
    CheckCircle2, ShieldAlert, Trash2, Edit3, Scale, MoreVertical, Download, 
    ArrowLeftRight, User, Clock, ArrowUpRight, ArrowDownRight, Sparkles, Percent,
    Eye, Info, Activity, AlertTriangle
} from 'lucide-vue-next';

const props = defineProps({
    compras: {
        type: Array,
        required: true,
    },
    proveedores: {
        type: Array,
        required: true,
    },
    productos: {
        type: Array,
        required: true,
    },
    categorias: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const showModal = ref(false);
const searchQuery = ref('');
const filterProveedor = ref('');
const filterEstado = ref('');
const filterMetodo = ref('');
const selectedFilterStat = ref('todos'); // todos, hoy, mes, granel, proveedores
const isLoading = ref(true);

// Success overlay/modal state after creating purchase
const showSuccessScreen = ref(false);
const registeredCompra = ref(null);

// Detail Modal state
const showDetailModal = ref(false);
const detailCompra = ref(null);

// Quick Product Modal state
const showNuevoProductoModal = ref(false);
const isSavingProducto = ref(false);
const nuevoProductoErrors = ref({});
const nuevoProductoForm = ref({
    nombre: '',
    codigo_barras: '',
    categoria_id: '',
    tipo_producto: 'estandar',
    tipo_venta: 'unidad',
    unidad_medida: 'unidad',
    precio_compra: 0,
    precio_venta: 0,
    stock_minimo: 0,
    stock_actual: 0,
    estado: 'activo',
});

// Main purchase form state
const form = useForm({
    proveedor_id: '',
    fecha_compra: new Date().toISOString().substr(0, 10),
    notas: '', // Will serialize JSON metadata here
    items: [], // Array of { producto_id: X, nombre: Y, cantidad: Z, precio_unitario: W, unidad_medida: U }
});

// Extra form UI fields
const tipoDocumento = ref('boleta'); // boleta, factura, ticket, guia
const numeroDocumento = ref('');
const metodoPago = ref('efectivo'); // efectivo, tarjeta, transferencia, credito
const comentarios = ref('');
const descuento = ref(0);
const aplicaIgv = ref(false);
const gastoTransporte = ref(0);
const gastoFlete = ref(0);
const gastoCarga = ref(0);
const gastoDescarga = ref(0);
const gastoOtros = ref(0);
const gastosAfectanCosto = ref(true);

// Selected product to inspect details before adding
const searchProductQuery = ref('');
const selectedProducto = ref(null);

onMounted(() => {
    window.addEventListener('keydown', handleGlobalBarcodeScan);
    setTimeout(() => {
        isLoading.value = false;
    }, 200);

    // Auto select supplier and open create modal if passed in query params
    const params = new URLSearchParams(window.location.search);
    const provParam = params.get('proveedor_id') || params.get('proveedor');
    if (provParam) {
        const exists = props.proveedores.some(p => String(p.id) === String(provParam));
        if (exists) {
            form.proveedor_id = Number(provParam);
            showModal.value = true;
        }
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalBarcodeScan);
});

// Sync main notes with serialized JSON metadata on change
watch([tipoDocumento, numeroDocumento, metodoPago, comentarios, descuento, aplicaIgv, gastoTransporte, gastoFlete, gastoCarga, gastoDescarga, gastoOtros, gastosAfectanCosto], () => {
    const meta = {
        tipo_documento: tipoDocumento.value,
        numero_documento: numeroDocumento.value,
        metodo_pago: metodoPago.value,
        comentarios: comentarios.value,
        descuento: Number(descuento.value),
        aplica_igv: aplicaIgv.value,
        gastos: {
            transporte: Number(gastoTransporte.value),
            flete: Number(gastoFlete.value),
            carga: Number(gastoCarga.value),
            descarga: Number(gastoDescarga.value),
            otros: Number(gastoOtros.value),
        },
        gastos_afectan_costo: gastosAfectanCosto.value
    };
    form.notas = JSON.stringify(meta);
});

// Helper to deserialize notes JSON
const parseNotas = (notasStr) => {
    try {
        if (notasStr && notasStr.startsWith('{')) {
            return JSON.parse(notasStr);
        }
    } catch (e) {
        // Fallback
    }
    return {
        tipo_documento: 'boleta',
        numero_documento: '-',
        metodo_pago: 'efectivo',
        comentarios: notasStr || '',
        descuento: 0,
        aplica_igv: false,
        gastos: { transporte: 0, flete: 0, carga: 0, descarga: 0, otros: 0 },
        gastos_afectan_costo: true
    };
};

// Barcode scanner listener
let barcodeBuffer = '';
let lastKeyTime = 0;
const handleGlobalBarcodeScan = (e) => {
    if (e.target.tagName === 'INPUT' && e.target.id !== 'product_search_input') {
        return;
    }
    const now = Date.now();
    if (now - lastKeyTime > 120) {
        barcodeBuffer = '';
    }
    lastKeyTime = now;

    if (e.key === 'Enter') {
        if (barcodeBuffer.length > 3) {
            const found = props.productos.find(p => p.codigo_barras === barcodeBuffer);
            if (found) {
                addProductToPurchase(found);
                barcodeBuffer = '';
                e.preventDefault();
            }
        }
    } else if (e.key.length === 1) {
        barcodeBuffer += e.key;
    }
};

// Computed metrics for dashboard
const comprasHoy = computed(() => {
    const today = new Date().toISOString().substr(0, 10);
    return props.compras.filter(c => c.fecha_compra === today && c.estado === 'completada');
});

const totalComprasHoy = computed(() => {
    return comprasHoy.value.reduce((sum, c) => sum + Number(c.total), 0);
});

const comprasMes = computed(() => {
    const currentMonth = new Date().toISOString().substr(0, 7); // YYYY-MM
    return props.compras.filter(c => c.fecha_compra.startsWith(currentMonth) && c.estado === 'completada');
});

const totalComprasMes = computed(() => {
    return comprasMes.value.reduce((sum, c) => sum + Number(c.total), 0);
});

const activeProveedoresCount = computed(() => props.proveedores.length);

const productosCompradosHoyCount = computed(() => {
    let count = 0;
    comprasHoy.value.forEach(c => {
        if (c.detalles) {
            c.detalles.forEach(d => {
                count += Number(d.cantidad);
            });
        }
    });
    return count;
});

const ultimaCompraText = computed(() => {
    if (props.compras.length === 0) return 'Ninguna';
    const latest = props.compras[0];
    return `${latest.numero_compra} (${latest.proveedor?.nombre || 'General'})`;
});

// Product search for supplying
const searchFilteredProductos = computed(() => {
    if (!searchProductQuery.value) return [];
    const query = searchProductQuery.value.toLowerCase();
    return props.productos.filter(p => 
        p.nombre.toLowerCase().includes(query) ||
        (p.codigo_barras && p.codigo_barras.toLowerCase().includes(query)) ||
        (p.categoria?.nombre && p.categoria.nombre.toLowerCase().includes(query))
    ).slice(0, 5);
});

// Intelligent List Filter
const filteredCompras = computed(() => {
    let result = props.compras;

    // 1. Search Query Filters
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(c => 
            c.numero_compra.toLowerCase().includes(query) ||
            (c.proveedor?.nombre && c.proveedor.nombre.toLowerCase().includes(query)) ||
            (c.usuario?.nombre && c.usuario.nombre.toLowerCase().includes(query))
        );
    }

    // 2. Select Dropdowns Filters
    if (filterProveedor.value) {
        result = result.filter(c => String(c.proveedor_id) === String(filterProveedor.value));
    }

    if (filterEstado.value) {
        result = result.filter(c => c.estado === filterEstado.value);
    }

    if (filterMetodo.value) {
        result = result.filter(c => {
            const meta = parseNotas(c.notas);
            return meta.metodo_pago === filterMetodo.value;
        });
    }

    // 3. Clickable Dashboard Card Filters
    if (selectedFilterStat.value === 'hoy') {
        const today = new Date().toISOString().substr(0, 10);
        result = result.filter(c => c.fecha_compra === today);
    } else if (selectedFilterStat.value === 'mes') {
        const currentMonth = new Date().toISOString().substr(0, 7);
        result = result.filter(c => c.fecha_compra.startsWith(currentMonth));
    } else if (selectedFilterStat.value === 'inversion') {
        const currentMonth = new Date().toISOString().substr(0, 7);
        result = result.filter(c => c.fecha_compra.startsWith(currentMonth) && c.estado === 'completada');
    } else if (selectedFilterStat.value === 'proveedores') {
        result = result.filter(c => c.proveedor_id !== null);
    } else if (selectedFilterStat.value === 'items_hoy') {
        const today = new Date().toISOString().substr(0, 10);
        result = result.filter(c => c.fecha_compra === today && c.detalles && c.detalles.length > 0);
    } else if (selectedFilterStat.value === 'granel') {
        result = result.filter(c => {
            return c.detalles?.some(d => ['peso', 'volumen'].includes(d.producto?.tipo_venta));
        });
    }

    return result;
});

// Add selected product to purchase details table
const addProductToPurchase = (prod) => {
    selectedProducto.value = prod;
    
    // Auto add with default last purchased cost or standard cost
    const existing = form.items.find(item => String(item.producto_id) === String(prod.id));
    if (existing) {
        existing.cantidad += 1;
    } else {
        form.items.push({
            producto_id: prod.id,
            nombre: prod.nombre,
            unidad_medida: prod.unidad_medida,
            tipo_venta: prod.tipo_venta,
            cantidad: 1,
            precio_unitario: Number(prod.precio_compra) || 0,
            ultimo_costo: Number(prod.precio_compra) || 0,
            actualizar_costo_estandar: false
        });
    }
    searchProductQuery.value = '';
};

const removeItem = (idx) => {
    form.items.splice(idx, 1);
};

// Calculate purchase totals
const subtotalItems = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.cantidad * item.precio_unitario), 0);
});

const totalGastosAdicionales = computed(() => {
    return Number(gastoTransporte.value) + Number(gastoFlete.value) + Number(gastoCarga.value) + Number(gastoDescarga.value) + Number(gastoOtros.value);
});

const finalTotal = computed(() => {
    let sub = subtotalItems.value;
    if (aplicaIgv.value) {
        sub = sub * 1.18; // Apply 18% IGV
    }
    sub -= Number(descuento.value);
    sub += totalGastosAdicionales.value;
    return Math.max(0, sub);
});

const openCreateModal = () => {
    form.clearErrors();
    form.reset();

    // Reset extra UI fields
    tipoDocumento.value = 'boleta';
    numeroDocumento.value = '';
    metodoPago.value = 'efectivo';
    comentarios.value = '';
    descuento.value = 0;
    aplicaIgv.value = false;
    gastoTransporte.value = 0;
    gastoFlete.value = 0;
    gastoCarga.value = 0;
    gastoDescarga.value = 0;
    gastoOtros.value = 0;
    gastosAfectanCosto.value = true;
    selectedProducto.value = null;
    searchProductQuery.value = '';

    showModal.value = true;
};

const submit = () => {
    form.post(route('compras.store'), {
        onSuccess: (page) => {
            showModal.value = false;
            // Capture registered compra from flash/session or general prop
            if (props.compras.length > 0) {
                registeredCompra.value = props.compras[0];
            }
            showSuccessScreen.value = true;
        },
    });
};

const checkCanAnular = (compra) => {
    if (!compra.detalles) return true;
    
    // Check if current product stock is less than the quantity purchased
    // If it is, then deleting this purchase will result in negative stock
    for (const d of compra.detalles) {
        const prod = props.productos.find(p => p.id === d.producto_id);
        if (prod && Number(prod.stock_actual) < Number(d.cantidad)) {
            return {
                can: false,
                product: prod.nombre,
                current: prod.stock_actual,
                purchased: d.cantidad
            };
        }
    }
    return { can: true };
};

const handleAnular = (compra) => {
    const check = checkCanAnular(compra);
    if (!check.can) {
        alert(`No es posible anular esta compra. El producto "${check.product}" ya fue vendido o transferido (Stock actual: ${check.current}, Cantidad en esta compra: ${check.purchased}).`);
        return;
    }

    if (confirm(`¿Estás seguro de que deseas ANULAR la compra "${compra.numero_compra}"? Se descontará el stock de los productos.`)) {
        router.delete(route('compras.destroy', compra.id));
    }
};

const handleUpdateStandardCost = (item) => {
    // Send quick PUT to update standard purchase price of the product
    axios.put(route('productos.update', item.producto_id), {
        nombre: item.nombre,
        precio_compra: Number(item.precio_unitario),
        // Send other properties unchanged
        _method: 'PUT'
    }).then(() => {
        item.ultimo_costo = item.precio_unitario;
        alert('Costo estándar del producto actualizado en su catálogo.');
    }).catch(e => {
        console.error('Error updating standard cost:', e);
    });
};

const openDetail = (compra) => {
    detailCompra.value = compra;
    showDetailModal.value = true;
};

const printDetail = () => {
    window.print();
};

// Dialog for fast product creation inside supplies modal
const openNuevoProductoModal = () => {
    nuevoProductoForm.value = {
        nombre: '',
        codigo_barras: '',
        categoria_id: props.categorias.length > 0 ? props.categorias[0].id : '',
        tipo_producto: 'estandar',
        tipo_venta: 'unidad',
        unidad_medida: 'unidad',
        precio_compra: 0,
        precio_venta: 0,
        stock_minimo: 5,
        stock_actual: 0,
        estado: 'activo',
    };
    nuevoProductoErrors.value = {};
    showNuevoProductoModal.value = true;
};

const submitNuevoProducto = async () => {
    isSavingProducto.value = true;
    nuevoProductoErrors.value = {};
    try {
        const response = await axios.post(route('productos.store'), nuevoProductoForm.value, {
            headers: { 'Accept': 'application/json' }
        });
        
        if (response.data.success && response.data.producto) {
            const newProd = response.data.producto;
            router.reload({
                only: ['productos'],
                onSuccess: () => {
                    addProductToPurchase(newProd);
                    showNuevoProductoModal.value = false;
                }
            });
        }
    } catch (error) {
        if (error.response && error.response.status === 422) {
            nuevoProductoErrors.value = error.response.data.errors;
        } else {
            alert('Error al registrar el producto.');
        }
    } finally {
        isSavingProducto.value = false;
    }
};

const getPriceDiffPct = (item) => {
    const old = Number(item.ultimo_costo);
    const n = Number(item.precio_unitario);
    if (old <= 0) return 0;
    return (((n - old) / old) * 100).toFixed(1);
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(val);
};

const formatQuantity = (qty, unit) => {
    const isBulk = ['kilogramo', 'gramo', 'litro', 'mililitro'].includes(String(unit).toLowerCase());
    return isBulk ? Number(qty).toFixed(3) : Number(qty).toFixed(0);
};
</script>

<template>
    <Head title="Compras y Abastecimiento" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">Compras y Abastecimiento</h2>
                    <p class="text-xs text-gray-500 mt-0.5 font-medium">Registra el ingreso de mercaderías, fletes y actualiza los costos.</p>
                </div>
                <PrimaryButton v-if="compras.length > 0" @click="openCreateModal" class="h-9 rounded-xl">
                    <Plus class="w-4 h-4 me-1.5" />
                    Registrar Compra
                </PrimaryButton>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Toast Notifications -->
            <div v-if="flashSuccess" class="rounded-xl bg-emerald-50 dark:bg-emerald-950/20 p-4 text-xs text-emerald-800 dark:text-emerald-450 border border-emerald-100 dark:border-emerald-900/30 flex items-start gap-3 shadow-3xs">
                <span class="p-1 bg-white dark:bg-gray-900 rounded-lg border border-emerald-200 dark:border-emerald-800 text-emerald-600">✓</span>
                <div>
                    <div class="font-bold">Acción Realizada Exitosamente</div>
                    <div class="opacity-95 mt-0.5">{{ flashSuccess }}</div>
                </div>
            </div>

            <div v-if="flashError" class="rounded-xl bg-red-50 dark:bg-red-950/20 p-4 text-xs text-red-800 dark:text-red-450 border border-red-100 dark:border-red-900/30 flex items-start gap-3 shadow-3xs">
                <AlertCircle class="w-4.5 h-4.5 text-red-600 mt-0.5 shrink-0" />
                <div>
                    <div class="font-bold">Error en la Operación</div>
                    <div class="opacity-95 mt-0.5">{{ flashError }}</div>
                </div>
            </div>

            <!-- SKELETON LOADING -->
            <div v-if="isLoading" class="grid grid-cols-2 md:grid-cols-6 gap-4 animate-pulse mb-6">
                <div v-for="i in 6" :key="i" class="h-20 bg-gray-100 dark:bg-gray-800 rounded-2xl"></div>
            </div>

            <!-- 1. DASHBOARD SUPERIOR INTERACTIVO -->
            <div v-else-if="compras.length > 0" class="grid grid-cols-2 md:grid-cols-6 gap-4">
                <!-- Compras hoy -->
                <button 
                    @click="selectedFilterStat = 'hoy'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between outline-none cursor-pointer"
                    :class="selectedFilterStat === 'hoy' ? 'border-blue-500 ring-2 ring-blue-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Compras de Hoy</span>
                    <h4 class="text-lg font-bold font-mono text-gray-900 dark:text-white mt-1">{{ formatCurrency(totalComprasHoy) }}</h4>
                    <span class="text-[9px] text-gray-400 mt-1 block">{{ comprasHoy.length }} registros hoy</span>
                </button>

                <!-- Compras Mes -->
                <button 
                    @click="selectedFilterStat = 'mes'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between outline-none cursor-pointer"
                    :class="selectedFilterStat === 'mes' ? 'border-emerald-500 ring-2 ring-emerald-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Compras del Mes</span>
                    <h4 class="text-lg font-bold font-mono text-emerald-600 dark:text-emerald-450 mt-1">{{ formatCurrency(totalComprasMes) }}</h4>
                    <span class="text-[9px] text-gray-400 mt-1 block">{{ comprasMes.length }} registros este mes</span>
                </button>

                <!-- Invertido este mes -->
                <button 
                    @click="selectedFilterStat = 'inversion'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between outline-none cursor-pointer"
                    :class="selectedFilterStat === 'inversion' ? 'border-indigo-500 ring-2 ring-indigo-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Inversión Almacén</span>
                    <h4 class="text-lg font-bold font-mono text-indigo-600 dark:text-indigo-400 mt-1">{{ formatCurrency(totalComprasMes) }}</h4>
                    <span class="text-[9px] text-gray-400 mt-1 block">Afecta costo inventario</span>
                </button>

                <!-- Proveedores activos -->
                <button 
                    @click="selectedFilterStat = 'proveedores'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between outline-none cursor-pointer"
                    :class="selectedFilterStat === 'proveedores' ? 'border-amber-500 ring-2 ring-amber-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Proveedores Activos</span>
                    <h4 class="text-lg font-bold font-mono text-amber-600 dark:text-amber-450 mt-1">{{ activeProveedoresCount }}</h4>
                    <span class="text-[9px] text-gray-400 mt-1 block">Fichas de contacto</span>
                </button>

                <!-- Productos comprados hoy -->
                <button 
                    @click="selectedFilterStat = 'items_hoy'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between outline-none cursor-pointer"
                    :class="selectedFilterStat === 'items_hoy' ? 'border-teal-500 ring-2 ring-teal-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Items Comprados</span>
                    <h4 class="text-lg font-bold font-mono text-teal-650 dark:text-teal-400 mt-1">{{ productosCompradosHoyCount }}</h4>
                    <span class="text-[9px] text-gray-400 mt-1 block">Unidades ingresadas hoy</span>
                </button>

                <!-- Última compra registrada -->
                <button 
                    @click="compras.length > 0 && openDetail(compras[0])"
                    class="text-left bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between outline-none cursor-pointer"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Último Folio</span>
                    <h4 class="text-xs font-bold text-gray-900 dark:text-white mt-1.5 truncate" :title="ultimaCompraText">{{ ultimaCompraText }}</h4>
                    <span class="text-[9px] text-gray-400 mt-1 block">Último registro Kardex</span>
                </button>
            </div>

            <!-- EMPTY STATE -->
            <div v-if="compras.length === 0" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-12 shadow-3xs flex flex-col items-center text-center max-w-lg mx-auto mt-8">
                <div class="w-16 h-16 bg-blue-50 dark:bg-blue-950/30 text-blue-600 rounded-2xl flex items-center justify-center mb-6">
                    <Truck class="w-8 h-8" />
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">No existen compras registradas</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 mb-6 max-w-xs leading-relaxed">Registra tus abastecimientos de inventario para cargar existencias y costos de mercadería.</p>
                <PrimaryButton @click="openCreateModal" class="rounded-xl">
                    <Plus class="w-4 h-4 me-1.5" />
                    Registrar primera compra
                </PrimaryButton>
            </div>

            <!-- MAIN LIST / TABLE -->
            <div v-else class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden shadow-3xs">
                <div class="p-6">
                    <!-- Filters Grid -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 w-full max-w-4xl">
                            <!-- Search -->
                            <div class="relative w-full">
                                <span class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none text-gray-400">
                                    <Search class="w-4 h-4" />
                                </span>
                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    class="block w-full ps-10 p-2.5 h-10 text-xs text-gray-900 dark:text-white border border-gray-250 dark:border-gray-800 rounded-xl bg-gray-50/50 dark:bg-gray-850 hover:bg-white focus:bg-white focus:ring-2 focus:ring-blue-500/25 focus:border-blue-500 transition-all duration-150 outline-none"
                                    placeholder="N° compra o proveedor..."
                                />
                            </div>

                            <!-- Proveedor Filter -->
                            <select
                                v-model="filterProveedor"
                                class="h-10 border border-gray-255 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl text-xs px-3 transition-colors outline-none cursor-pointer"
                            >
                                <option value="">Todos los Proveedores</option>
                                <option v-for="prov in proveedores" :key="prov.id" :value="prov.id">
                                    {{ prov.nombre }}
                                </option>
                            </select>

                            <!-- Estado Filter -->
                            <select
                                v-model="filterEstado"
                                class="h-10 border border-gray-255 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl text-xs px-3 transition-colors outline-none cursor-pointer"
                            >
                                <option value="">Todos los Estados</option>
                                <option value="completada">Completada</option>
                                <option value="anulada">Anulada</option>
                            </select>

                            <!-- Pago Filter -->
                            <select
                                v-model="filterMetodo"
                                class="h-10 border border-gray-255 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl text-xs px-3 transition-colors outline-none cursor-pointer"
                            >
                                <option value="">Cualquier Medio de Pago</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta (Débito/Crédito)</option>
                                <option value="transferencia">Transferencia bancaria</option>
                                <option value="credito">Crédito Proveedor</option>
                            </select>
                        </div>
                        <div v-if="selectedFilterStat !== 'todos'" class="flex items-center gap-1.5 shrink-0">
                            <span class="text-[9px] bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-400 px-2 py-0.5 rounded border border-blue-100 dark:border-blue-900/30 font-bold uppercase">
                                Vista: {{ selectedFilterStat }}
                            </span>
                            <button @click="selectedFilterStat = 'todos'" class="text-[10px] text-gray-400 hover:text-gray-600 font-bold hover:underline">Limpiar</button>
                        </div>
                    </div>

                    <!-- 2. Tabla de Compras Rediseñada -->
                    <div class="relative overflow-x-auto rounded-xl border border-gray-150 dark:border-gray-800">
                        <table class="w-full text-xs text-left text-gray-500 dark:text-gray-450 border-collapse">
                            <thead class="text-[10px] text-gray-400 uppercase tracking-wider font-bold bg-gray-50/70 dark:bg-gray-950 sticky top-0 z-10 border-b border-gray-150 dark:border-gray-800">
                                <tr>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Folio / N° Compra</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Fecha</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Proveedor</th>
                                    <th scope="col" class="px-6 py-4 text-center font-bold text-gray-500">Productos</th>
                                    <th scope="col" class="px-6 py-4 text-right font-bold text-gray-500">Total</th>
                                    <th scope="col" class="px-6 py-4 text-center font-bold text-gray-500">Medio Pago</th>
                                    <th scope="col" class="px-6 py-4 text-center font-bold text-gray-500">Documento</th>
                                    <th scope="col" class="px-6 py-4 text-center font-bold text-gray-500">Estado</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Registrado por</th>
                                    <th scope="col" class="px-6 py-4 text-right font-bold text-gray-500 w-16">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                <tr 
                                    v-for="compra in filteredCompras" 
                                    :key="compra.id"
                                    class="hover:bg-gray-50/50 dark:hover:bg-gray-950/40 transition-colors duration-150"
                                >
                                    <!-- N° Compra -->
                                    <th scope="row" class="px-6 py-5 font-mono font-bold text-gray-900 dark:text-white whitespace-nowrap cursor-pointer hover:text-blue-600" @click="openDetail(compra)">
                                        {{ compra.numero_compra }}
                                    </th>
                                    <!-- Fecha -->
                                    <td class="px-6 py-5 text-gray-600 dark:text-gray-400 font-mono">
                                        {{ new Date(compra.fecha_compra).toLocaleDateString('es-PE') }}
                                    </td>
                                    <!-- Proveedor -->
                                    <td class="px-6 py-5 font-bold text-gray-850 dark:text-gray-200">
                                        {{ compra.proveedor?.nombre || 'General' }}
                                    </td>
                                    <!-- Cantidad de productos -->
                                    <td class="px-6 py-5 text-center font-mono font-bold">
                                        {{ compra.detalles ? compra.detalles.length : '-' }}
                                    </td>
                                    <!-- Total -->
                                    <td class="px-6 py-5 text-right font-extrabold text-gray-900 dark:text-white font-mono">
                                        {{ formatCurrency(compra.total) }}
                                    </td>
                                    <!-- Método Pago -->
                                    <td class="px-6 py-5 text-center capitalize font-medium text-gray-700 dark:text-gray-300">
                                        {{ parseNotas(compra.notes || compra.notas).metodo_pago }}
                                    </td>
                                    <!-- Documento -->
                                    <td class="px-6 py-5 text-center font-mono text-[10px] text-gray-500">
                                        <span class="uppercase font-bold">{{ parseNotas(compra.notes || compra.notas).tipo_documento }}</span> 
                                        <span class="block text-[9px] text-gray-400 mt-0.5">{{ parseNotas(compra.notes || compra.notas).numero_documento || '-' }}</span>
                                    </td>
                                    <!-- Estado -->
                                    <td class="px-6 py-5 text-center">
                                        <span 
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold border uppercase tracking-wider"
                                            :class="compra.estado === 'completada' 
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-450 dark:border-emerald-900/30' 
                                                : 'bg-red-50 text-red-700 border-red-100 dark:bg-red-950/20 dark:text-red-405 dark:border-red-900/30'"
                                        >
                                            {{ compra.estado === 'completada' ? 'Completada' : 'Anulada' }}
                                        </span>
                                    </td>
                                    <!-- Usuario Registra -->
                                    <td class="px-6 py-5 text-gray-600 dark:text-gray-450 font-medium">
                                        {{ compra.usuario?.nombre || 'Sistema' }}
                                    </td>
                                    <!-- 3. Acciones (Tres puntos contextuales siempre visibles) -->
                                    <td class="px-6 py-5 text-right whitespace-nowrap">
                                        <Dropdown align="right" width="48">
                                            <template #trigger>
                                                <button class="inline-flex items-center justify-center p-2 text-gray-500 hover:text-gray-750 dark:text-gray-400 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-850 transition-colors border border-gray-100 dark:border-gray-800">
                                                    <MoreVertical class="w-4 h-4" />
                                                </button>
                                            </template>
                                            <template #content>
                                                <div class="py-1 text-gray-700 dark:text-gray-300">
                                                    <button @click="openDetail(compra)" class="flex w-full px-4 py-2.5 text-left text-xs hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors items-center gap-2 cursor-pointer">
                                                        <Eye class="w-3.5 h-3.5 text-gray-400" />
                                                        Ver detalle compra
                                                    </button>
                                                    <button @click="openDetail(compra)" class="flex w-full px-4 py-2.5 text-left text-xs hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors items-center gap-2 cursor-pointer">
                                                        <Download class="w-3.5 h-3.5 text-gray-400" />
                                                        Descargar en PDF
                                                    </button>
                                                    
                                                    <!-- Divisor visual para destructivo -->
                                                    <div class="border-t border-gray-100 dark:border-gray-800 my-1"></div>

                                                    <button 
                                                        v-if="compra.estado === 'completada'"
                                                        @click="handleAnular(compra)"
                                                        class="flex w-full px-4 py-2.5 text-left text-xs text-red-650 dark:text-red-405 hover:bg-red-50 dark:hover:bg-red-950/20 font-bold transition-colors items-center gap-2 cursor-pointer"
                                                    >
                                                        <Trash2 class="w-3.5 h-3.5 text-red-500" />
                                                        Anular compra
                                                    </button>
                                                    <span v-else class="flex w-full px-4 py-2.5 text-left text-xs text-gray-400 italic">Folio Anulado</span>
                                                </div>
                                            </template>
                                        </Dropdown>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. MODAL NUEVA COMPRA (Dividido por Bloques) -->
        <Modal :show="showModal" @close="closeModal" max-width="4xl">
            <div class="p-6 text-gray-900 dark:text-gray-100 max-h-[90vh] overflow-y-auto pr-1">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-850 mb-5">
                    <div class="flex items-center gap-2">
                        <Truck class="w-5 h-5 text-blue-600" />
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Registrar Ingreso de Inventario (Compras)</h3>
                    </div>
                    <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-650 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- BLOQUE 1: Información General y Proveedor -->
                    <div class="bg-gray-50/50 dark:bg-gray-950/10 p-5 border border-gray-150 dark:border-gray-800 rounded-2xl space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-850 pb-2">
                            <span class="w-2 h-2 bg-blue-600 rounded-full"></span>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">1. Datos del Proveedor y Comprobante</h4>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <InputLabel for="prov_id" value="Proveedor *" class="text-xs font-bold" />
                                <select id="prov_id" class="mt-1.5 block w-full h-10 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer" v-model="form.proveedor_id" required>
                                    <option value="">Selecciona Proveedor...</option>
                                    <option v-for="prov in proveedores" :key="prov.id" :value="prov.id">{{ prov.nombre }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="f_compra" value="Fecha de Compra *" class="text-xs font-bold" />
                                <TextInput id="f_compra" type="date" class="mt-1.5 block w-full text-xs h-10 px-3" v-model="form.fecha_compra" required />
                            </div>
                            <div>
                                <InputLabel for="m_pago" value="Método de Pago" class="text-xs font-bold" />
                                <select id="m_pago" class="mt-1.5 block w-full h-10 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer" v-model="metodoPago">
                                    <option value="efectivo">Efectivo</option>
                                    <option value="tarjeta">Tarjeta (Débito/Crédito)</option>
                                    <option value="transferencia">Transferencia bancaria</option>
                                    <option value="credito">Crédito Proveedor</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="t_doc" value="Tipo de Documento" class="text-xs font-bold" />
                                <select id="t_doc" class="mt-1.5 block w-full h-10 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer" v-model="tipoDocumento">
                                    <option value="boleta">Boleta</option>
                                    <option value="factura">Factura</option>
                                    <option value="ticket">Ticket de compra</option>
                                    <option value="guia">Guía de remisión</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="n_doc" value="Número de Documento" class="text-xs font-bold" />
                                <TextInput id="n_doc" type="text" class="mt-1.5 block w-full text-xs h-10 px-3" v-model="numeroDocumento" placeholder="Ej. F001-000456" />
                            </div>
                            <div>
                                <InputLabel for="obs" value="Notas internas (Opcional)" class="text-xs font-bold" />
                                <TextInput id="obs" type="text" class="mt-1.5 block w-full text-xs h-10 px-3" v-model="comentarios" placeholder="Notas de flete, entrega..." />
                            </div>
                        </div>
                    </div>

                    <!-- BLOQUE 2: Selección y Carga de Productos -->
                    <div class="bg-gray-50/50 dark:bg-gray-950/10 p-5 border border-gray-150 dark:border-gray-800 rounded-2xl space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-850 pb-2">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-emerald-600 rounded-full"></span>
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">2. Carga de Productos e Insumos</h4>
                            </div>
                            <button type="button" @click="openNuevoProductoModal" class="text-[10px] text-blue-600 hover:text-blue-700 font-bold flex items-center gap-1 select-none">
                                <Plus class="w-3.5 h-3.5" />
                                Nuevo Producto / Insumo
                            </button>
                        </div>

                        <!-- Search Barcode / Name -->
                        <div class="relative w-full max-w-xl">
                            <span class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none text-gray-400">
                                <Search class="w-4 h-4" />
                            </span>
                            <input
                                id="product_search_input"
                                v-model="searchProductQuery"
                                type="search"
                                class="block w-full ps-10 p-2.5 h-10 text-xs text-gray-900 dark:text-white border border-gray-250 dark:border-gray-800 rounded-xl bg-white dark:bg-gray-900 focus:ring-2 focus:ring-blue-500/25 focus:border-blue-500 outline-none"
                                placeholder="Escribe nombre o escanea código de barras del producto..."
                            />
                            
                            <!-- Search Results Dropdown -->
                            <div v-if="searchFilteredProductos.length > 0" class="absolute left-0 right-0 mt-1.5 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-lg z-30 max-h-56 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-850">
                                <button
                                    v-for="p in searchFilteredProductos"
                                    :key="p.id"
                                    type="button"
                                    @click="addProductToPurchase(p)"
                                    class="flex w-full px-4 py-2.5 text-left text-xs text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors items-center justify-between"
                                >
                                    <div class="flex flex-col">
                                        <span class="font-bold">{{ p.nombre }}</span>
                                        <span class="text-[10px] text-gray-450 mt-0.5">Barra: {{ p.codigo_barras || '-' }} | Stock: {{ formatQuantity(p.stock_actual, p.unidad_medida) }} {{ p.unidad_medida }}</span>
                                    </div>
                                    <span class="text-[10px] bg-gray-50 dark:bg-gray-800 text-gray-500 font-bold px-2 py-0.5 rounded border border-gray-150">S/. {{ p.precio_compra }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- 7. COMPARACIÓN AUTOMÁTICA DE PRECIOS (Si hay un producto seleccionado recientemente) -->
                        <div v-if="selectedProducto" class="bg-blue-50/50 dark:bg-blue-950/10 p-4 border border-blue-150 dark:border-blue-900/30 rounded-2xl text-xs flex flex-wrap gap-4 items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Info class="w-4.5 h-4.5 text-blue-600 shrink-0" />
                                <div>
                                    <span class="font-bold text-blue-900 dark:text-blue-400">Comparación del Producto: </span>
                                    <span class="font-semibold">{{ selectedProducto.nombre }}</span>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-4 font-mono text-[10px] text-gray-700 dark:text-gray-300">
                                <div><span class="text-gray-450">Último Proveedor:</span> <span class="font-bold">Proveedor General</span></div>
                                <div><span class="text-gray-450">Último Costo:</span> <span class="font-bold">{{ formatCurrency(selectedProducto.precio_compra) }}</span></div>
                                <div><span class="text-gray-450">Costo Promedio:</span> <span class="font-bold">{{ formatCurrency(selectedProducto.precio_compra) }}</span></div>
                                <div><span class="text-gray-450">Stock Actual:</span> <span class="font-bold">{{ formatQuantity(selectedProducto.stock_actual, selectedProducto.unidad_medida) }} {{ selectedProducto.unidad_medida }}</span></div>
                            </div>
                        </div>

                        <!-- Items details editable table -->
                        <div class="overflow-hidden border border-gray-200 dark:border-gray-800 rounded-xl mt-3">
                            <table class="w-full text-xs text-left text-gray-500 dark:text-gray-450">
                                <thead class="bg-gray-50 dark:bg-gray-950 text-[10px] uppercase text-gray-450 font-bold border-b border-gray-150 dark:border-gray-800">
                                    <tr>
                                        <th class="px-4 py-2.5 font-bold">Producto / Insumo</th>
                                        <th class="px-4 py-2.5 font-bold text-center w-28">Cant. Comprada</th>
                                        <th class="px-4 py-2.5 font-bold text-center w-24">Medida</th>
                                        <th class="px-4 py-2.5 font-bold text-right w-32">Costo Unit. (S/.)</th>
                                        <th class="px-4 py-2.5 font-bold text-right w-24">Var. Costo</th>
                                        <th class="px-4 py-2.5 font-bold text-right w-32">Subtotal</th>
                                        <th class="px-4 py-2.5 font-bold text-right w-20">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-850 bg-white dark:bg-gray-900">
                                    <tr v-for="(item, idx) in form.items" :key="idx" class="hover:bg-gray-50/50">
                                        <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                            <div class="flex flex-col">
                                                <span>{{ item.nombre }}</span>
                                                <!-- Action to save as default cost if changed -->
                                                <button 
                                                    v-if="getPriceDiffPct(item) !== '0.0'"
                                                    type="button"
                                                    @click="handleUpdateStandardCost(item)"
                                                    class="text-[9px] text-blue-600 hover:text-blue-750 font-bold mt-1 text-left select-none"
                                                >
                                                    ¿Establecer como costo estándar?
                                                </button>
                                            </div>
                                        </td>
                                        <!-- Editable Cantidad -->
                                        <td class="px-4 py-3 text-center">
                                            <input
                                                v-model="item.cantidad"
                                                type="number"
                                                step="0.001"
                                                min="0.001"
                                                class="w-full text-center h-8 text-xs font-bold font-mono border border-gray-200 dark:border-gray-800 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/25 bg-gray-50/50"
                                            />
                                        </td>
                                        <td class="px-4 py-3 text-center capitalize font-medium text-gray-500 font-mono text-[10px]">
                                            {{ item.unidad_medida }}
                                        </td>
                                        <!-- Editable Costo Unitario -->
                                        <td class="px-4 py-3 text-right">
                                            <input
                                                v-model="item.precio_unitario"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                class="w-full text-right h-8 text-xs font-bold font-mono border border-gray-200 dark:border-gray-800 rounded-lg outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500/25 bg-gray-50/50"
                                            />
                                        </td>
                                        <!-- Cost Price Difference Pct Indicator -->
                                        <td class="px-4 py-3 text-right font-mono text-[9px] font-bold">
                                            <span 
                                                v-if="getPriceDiffPct(item) !== '0.0'"
                                                :class="Number(getPriceDiffPct(item)) > 0 ? 'text-red-600 bg-red-50 px-1 py-0.5 rounded border border-red-100' : 'text-emerald-650 bg-emerald-50 px-1 py-0.5 rounded border border-emerald-100'"
                                            >
                                                {{ Number(getPriceDiffPct(item)) > 0 ? '+' : '' }}{{ getPriceDiffPct(item) }}%
                                            </span>
                                            <span v-else class="text-gray-300">-</span>
                                        </td>
                                        <!-- Subtotal -->
                                        <td class="px-4 py-3 text-right font-mono font-bold text-gray-900 dark:text-white">
                                            {{ formatCurrency(item.cantidad * item.precio_unitario) }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button type="button" @click="removeItem(idx)" class="text-red-600 hover:text-red-800 font-bold">
                                                Quitar
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="form.items.length === 0">
                                        <td colspan="7" class="px-4 py-8 text-center text-gray-400 italic">
                                            Ingresa productos utilizando el buscador de arriba.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- BLOQUE 3: Gastos Adicionales (Transporte, flete, etc.) -->
                    <div class="bg-gray-50/50 dark:bg-gray-950/10 p-5 border border-gray-150 dark:border-gray-800 rounded-2xl space-y-4">
                        <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-855 pb-2">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-indigo-600 rounded-full"></span>
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">3. Gastos Operativos y Logísticos Adicionales</h4>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                            <div>
                                <InputLabel for="g_trans" value="Transporte (S/.)" class="text-xs font-bold text-gray-500" />
                                <TextInput id="g_trans" type="number" step="0.01" min="0" class="mt-1 block w-full text-xs h-9" v-model="gastoTransporte" />
                            </div>
                            <div>
                                <InputLabel for="g_flete" value="Flete (S/.)" class="text-xs font-bold text-gray-500" />
                                <TextInput id="g_flete" type="number" step="0.01" min="0" class="mt-1 block w-full text-xs h-9" v-model="gastoFlete" />
                            </div>
                            <div>
                                <InputLabel for="g_carga" value="Carga (S/.)" class="text-xs font-bold text-gray-500" />
                                <TextInput id="g_carga" type="number" step="0.01" min="0" class="mt-1 block w-full text-xs h-9" v-model="gastoCarga" />
                            </div>
                            <div>
                                <InputLabel for="g_descarga" value="Descarga (S/.)" class="text-xs font-bold text-gray-500" />
                                <TextInput id="g_descarga" type="number" step="0.01" min="0" class="mt-1 block w-full text-xs h-9" v-model="gastoDescarga" />
                            </div>
                            <div>
                                <InputLabel for="g_otros" value="Otros Gastos" class="text-xs font-bold text-gray-500" />
                                <TextInput id="g_otros" type="number" step="0.01" min="0" class="mt-1 block w-full text-xs h-9" v-model="gastoOtros" />
                            </div>
                        </div>

                        <div class="flex items-center gap-2 mt-2">
                            <input id="afecta_costo" type="checkbox" v-model="gastosAfectanCosto" class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-gray-300" />
                            <label for="afecta_costo" class="text-xs text-gray-700 dark:text-gray-300 font-bold select-none cursor-pointer">
                                Estos gastos forman parte del costo total del inventario (distribución proporcional)
                            </label>
                        </div>
                    </div>

                    <!-- BLOQUE 4: Resumen de Totales e Impuestos -->
                    <div class="bg-gray-900 text-white p-6 rounded-2xl flex flex-col md:flex-row justify-between items-start md:items-center gap-6 shadow-md font-mono">
                        <div class="space-y-1.5 text-xs text-gray-400">
                            <div class="flex justify-between w-64">
                                <span>Subtotal Items:</span>
                                <span class="font-bold text-white">{{ formatCurrency(subtotalItems) }}</span>
                            </div>
                            <div class="flex justify-between w-64 items-center">
                                <label class="flex items-center gap-1 cursor-pointer">
                                    <input type="checkbox" v-model="aplicaIgv" class="w-3.5 h-3.5 text-blue-600 rounded bg-gray-800 border-gray-700" />
                                    <span>IGV (18%):</span>
                                </label>
                                <span class="font-bold text-white">{{ aplicaIgv ? formatCurrency(subtotalItems * 0.18) : 'S/. 0.00' }}</span>
                            </div>
                            <div class="flex justify-between w-64 items-center">
                                <span>Otros Gastos:</span>
                                <span class="font-bold text-white">{{ formatCurrency(totalGastosAdicionales) }}</span>
                            </div>
                            <div class="flex justify-between w-64 items-center">
                                <span class="flex items-center gap-1">Descuento:</span>
                                <input type="number" v-model="descuento" class="w-20 text-right h-6 text-xs text-black border-none rounded bg-white font-bold p-1 outline-none" />
                            </div>
                        </div>

                        <div class="text-center md:text-right shrink-0">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400 block mb-1">TOTAL FACTURA DE COMPRA</span>
                            <span class="text-3xl font-extrabold text-blue-400 font-mono tracking-tight">{{ formatCurrency(finalTotal) }}</span>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-850">
                        <SecondaryButton @click="closeModal" class="rounded-xl h-11 px-6">Cancelar</SecondaryButton>
                        <PrimaryButton type="submit" class="rounded-xl h-11 px-6" :disabled="form.items.length === 0">Registrar Abastecimiento</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 12. PANTALLA DE ÉXITO DE COMPRA -->
        <Modal :show="showSuccessScreen" @close="showSuccessScreen = false" max-width="md">
            <div class="p-6 text-center text-gray-900 dark:text-gray-100">
                <div class="w-16 h-16 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-450 rounded-full flex items-center justify-center mx-auto mb-5 shadow-3xs">
                    <CheckCircle2 class="w-8 h-8" />
                </div>
                <h3 class="text-base font-extrabold text-gray-900 dark:text-white">Compra Registrada Correctamente</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 mb-6 leading-relaxed">
                    Se han ingresado las mercaderías del proveedor al almacén e insertado los movimientos en el libro Kardex de manera exitosa.
                </p>

                <!-- Checkbox checklists -->
                <div class="bg-gray-50 dark:bg-gray-950 p-4 border border-gray-150 dark:border-gray-850 rounded-2xl space-y-2.5 text-xs text-left mb-6 font-mono">
                    <div class="flex items-center gap-2 text-emerald-600 font-bold">✓ <span class="text-gray-700 dark:text-gray-300">Inventario y Stock actualizado</span></div>
                    <div class="flex items-center gap-2 text-emerald-600 font-bold">✓ <span class="text-gray-700 dark:text-gray-300">Libro Kardex de movimientos registrado</span></div>
                    <div class="flex items-center gap-2 text-emerald-600 font-bold">✓ <span class="text-gray-700 dark:text-gray-300">Historial de Folios archivado</span></div>
                </div>

                <div class="flex flex-col gap-2">
                    <PrimaryButton @click="showSuccessScreen = false; openCreateModal()" class="w-full justify-center rounded-xl h-11">Registrar otra compra</PrimaryButton>
                    <SecondaryButton @click="showSuccessScreen = false; openDetail(registeredCompra)" class="w-full justify-center rounded-xl h-11">Ver comprobante de compra</SecondaryButton>
                    <SecondaryButton @click="showSuccessScreen = false" class="w-full justify-center rounded-xl h-11 border-none bg-transparent hover:bg-gray-50">Volver al listado principal</SecondaryButton>
                </div>
            </div>
        </Modal>

        <!-- 3. MODAL DETALLE DE COMPRA (Ver Detalle / Descargar PDF) -->
        <Modal :show="showDetailModal" @close="showDetailModal = false" max-width="3xl">
            <div class="p-6 text-gray-900 dark:text-gray-100 print:p-0" id="print-area" v-if="detailCompra">
                <div class="flex justify-between items-center pb-4 border-b border-gray-150 dark:border-gray-850 mb-5 print:hidden">
                    <div class="flex items-center gap-2">
                        <FileText class="w-5 h-5 text-indigo-600" />
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Resumen de Folio de Compra: <span class="text-indigo-600">{{ detailCompra.numero_compra }}</span></h3>
                    </div>
                    <button type="button" @click="showDetailModal = false" class="text-gray-400 hover:text-gray-650 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Print header info -->
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h2 class="text-base font-bold text-gray-900 dark:text-white">INVENTEX SAC</h2>
                        <p class="text-[10px] text-gray-500">RUC: 20123456789 | Tel: (01) 456-7890</p>
                        <p class="text-[10px] text-gray-500 mt-0.5">Av. Javier Prado Este 1234, Lima</p>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400 block mb-1">FOLIO DE COMPRA</span>
                        <span class="text-base font-extrabold font-mono text-indigo-650">{{ detailCompra.numero_compra }}</span>
                        <p class="text-[10px] text-gray-500 font-mono mt-1">Registrado: {{ new Date(detailCompra.created_at).toLocaleString('es-PE') }}</p>
                    </div>
                </div>

                <!-- Details block -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-4 bg-gray-50 dark:bg-gray-950 border border-gray-150 dark:border-gray-850 rounded-2xl text-xs mb-6 font-sans">
                    <div>
                        <span class="text-gray-400 block">Proveedor:</span>
                        <span class="font-bold text-gray-850 dark:text-gray-200">{{ detailCompra.proveedor?.nombre || 'General' }}</span>
                        <span class="block text-[10px] text-gray-500 font-mono mt-0.5">RUC: {{ detailCompra.proveedor?.ruc || '-' }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block">Fecha Compra:</span>
                        <span class="font-bold text-gray-850 dark:text-gray-200 font-mono">{{ new Date(detailCompra.fecha_compra).toLocaleDateString('es-PE') }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block">Método de Pago:</span>
                        <span class="font-bold text-gray-850 dark:text-gray-200 capitalize">{{ parseNotas(detailCompra.notas).metodo_pago }}</span>
                    </div>
                    <div>
                        <span class="text-gray-400 block">Tipo Documento:</span>
                        <span class="font-bold text-gray-850 dark:text-gray-200 uppercase">{{ parseNotas(detailCompra.notas).tipo_documento }} {{ parseNotas(detailCompra.notas).numero_documento }}</span>
                    </div>
                </div>

                <!-- Items purchased table -->
                <div class="overflow-hidden border border-gray-200 dark:border-gray-800 rounded-xl mb-6">
                    <table class="w-full text-xs text-left text-gray-500 dark:text-gray-450 border-collapse">
                        <thead class="bg-gray-50 dark:bg-gray-950 text-[10px] uppercase text-gray-450 font-bold border-b border-gray-150 dark:border-gray-800">
                            <tr>
                                <th class="px-4 py-2.5 font-bold">Producto</th>
                                <th class="px-4 py-2.5 font-bold text-center w-28">Cant. Comprada</th>
                                <th class="px-4 py-2.5 font-bold text-right w-32">Costo Unitario</th>
                                <th class="px-4 py-2.5 font-bold text-right w-32">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-850 bg-white dark:bg-gray-900">
                            <tr v-for="item in detailCompra.detalles" :key="item.id" class="font-mono text-gray-800 dark:text-gray-300">
                                <td class="px-4 py-3 font-sans font-bold text-gray-900 dark:text-white">
                                    {{ item.producto?.nombre || 'Producto eliminado' }}
                                    <span class="block text-[10px] text-gray-400 font-mono font-medium">Barra: {{ item.producto?.codigo_barras || '-' }}</span>
                                </td>
                                <td class="px-4 py-3 text-center font-bold">
                                    {{ formatQuantity(item.cantidad, item.producto?.unidad_medida) }} {{ item.producto?.unidad_medida }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    {{ formatCurrency(item.precio_unitario) }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-white">
                                    {{ formatCurrency(item.subtotal) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Financial totals summary -->
                <div class="flex justify-end font-mono text-xs mb-6">
                    <div class="w-64 space-y-1.5 text-gray-500">
                        <div class="flex justify-between border-b border-gray-100 pb-1">
                            <span>Subtotal Items:</span>
                            <span class="font-bold text-gray-850 dark:text-white">{{ formatCurrency(Number(detailCompra.total) - Number(parseNotas(detailCompra.notas).gastos?.transporte || 0) - Number(parseNotas(detailCompra.notas).gastos?.flete || 0)) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>Gastos flete/logística:</span>
                            <span class="font-bold text-gray-850 dark:text-white">{{ formatCurrency(Number(parseNotas(detailCompra.notas).gastos?.flete || 0) + Number(parseNotas(detailCompra.notas).gastos?.transporte || 0)) }}</span>
                        </div>
                        <div class="flex justify-between border-t border-gray-200 pt-2 text-sm text-gray-900 dark:text-white font-extrabold">
                            <span>TOTAL FACTURADO:</span>
                            <span>{{ formatCurrency(detailCompra.total) }}</span>
                        </div>
                    </div>
                </div>

                <div class="text-[9px] text-gray-400 italic text-center border-t border-gray-100 dark:border-gray-855 pt-4">
                    Este documento es una copia de auditoría del sistema de abastecimiento de Inventex.
                </div>

                <div class="flex justify-end gap-3 pt-6 border-t border-gray-100 dark:border-gray-855 mt-6 print:hidden">
                    <button type="button" @click="printDetail" class="inline-flex items-center px-4 py-2 bg-indigo-650 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer">
                        <Download class="w-4 h-4 me-1.5" />
                        Imprimir / Descargar PDF
                    </button>
                    <SecondaryButton @click="showDetailModal = false" class="rounded-xl h-10 px-5">Cerrar</SecondaryButton>
                </div>
            </div>
        </Modal>

        <!-- MÓDULO AUXILIAR: MODAL REGISTRAR NUEVO PRODUCTO DESDE COMPRA -->
        <Modal :show="showNuevoProductoModal" @close="showNuevoProductoModal = false" max-width="xl">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex justify-between items-center mb-5 pb-3 border-b border-gray-150 dark:border-gray-850">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white">
                        Crear Nuevo Producto sin salir de Compras
                    </h3>
                    <button type="button" @click="showNuevoProductoModal = false" class="text-gray-400 hover:text-gray-650 cursor-pointer">
                        <X class="w-4.5 h-4.5" />
                    </button>
                </div>

                <form @submit.prevent="submitNuevoProducto" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="new_nombre" value="Nombre del Producto *" class="text-xs font-bold" />
                            <TextInput id="new_nombre" type="text" class="mt-1.5 block w-full text-xs h-10 px-3" v-model="nuevoProductoForm.nombre" placeholder="Ej. Coca Cola 3L" required />
                            <span v-if="nuevoProductoErrors.nombre" class="text-xs text-red-600 mt-1 block">{{ nuevoProductoErrors.nombre[0] }}</span>
                        </div>
                        <div>
                            <InputLabel for="new_codigo_barras" value="Código de Barras" class="text-xs font-bold" />
                            <TextInput id="new_codigo_barras" type="text" class="mt-1.5 block w-full text-xs h-10 px-3" v-model="nuevoProductoForm.codigo_barras" placeholder="Escanear barra..." />
                        </div>
                        <div>
                            <InputLabel for="new_categoria_id" value="Categoría *" class="text-xs font-bold" />
                            <select id="new_categoria_id" class="mt-1.5 block w-full h-10 border border-gray-250 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-950 dark:text-white text-xs px-3 outline-none cursor-pointer" v-model="nuevoProductoForm.categoria_id" required>
                                <option value="">Selecciona Categoría...</option>
                                <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombre }}</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="new_unidad_medida" value="Unidad de Medida *" class="text-xs font-bold" />
                            <select id="new_unidad_medida" class="mt-1.5 block w-full h-10 border border-gray-255 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-950 dark:text-white text-xs px-3 outline-none cursor-pointer" v-model="nuevoProductoForm.unidad_medida" required>
                                <option value="unidad">Unidades</option>
                                <option value="kilogramo">Kilogramos</option>
                                <option value="litro">Litros</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel for="new_precio_compra" value="Precio Compra (Costo) *" class="text-xs font-bold" />
                            <TextInput id="new_precio_compra" type="number" step="0.01" class="mt-1.5 block w-full text-xs h-10 px-3" v-model="nuevoProductoForm.precio_compra" required />
                        </div>
                        <div>
                            <InputLabel for="new_precio_venta" value="Precio Venta (Público) *" class="text-xs font-bold" />
                            <TextInput id="new_precio_venta" type="number" step="0.01" class="mt-1.5 block w-full text-xs h-10 px-3" v-model="nuevoProductoForm.precio_venta" required />
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end space-x-3 pt-4 border-t border-gray-100">
                        <SecondaryButton @click="showNuevoProductoModal = false" :disabled="isSavingProducto">Cancelar</SecondaryButton>
                        <PrimaryButton :loading="isSavingProducto" :disabled="isSavingProducto">Crear Producto</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
