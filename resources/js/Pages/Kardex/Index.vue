<script setup>
import { ref, computed, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

// Lucide Icons
import { 
    ArrowLeft, ArrowLeftRight, Activity, Package, Scale, TrendingUp, DollarSign, 
    Calendar, Clock, User, FileText, UserCheck, AlertCircle, ShoppingBag
} from 'lucide-vue-next';

const props = defineProps({
    productos: {
        type: Array,
        required: true,
    },
    movimientos: {
        type: Array,
        required: true,
    },
    productoSeleccionado: {
        type: Object,
        default: null,
    },
    filtros: {
        type: Object,
        default: () => ({ producto_id: '' }),
    },
});

const selectedProductoId = ref(props.filtros.producto_id ? Number(props.filtros.producto_id) : '');

// Sync selectedProductoId if filters change from outside (e.g., page navigation)
watch(() => props.filtros.producto_id, (newVal) => {
    selectedProductoId.value = newVal ? Number(newVal) : '';
});

// Fetch Kardex when product changes
watch(selectedProductoId, (newVal) => {
    router.get(route('kardex.index'), 
        { producto_id: newVal },
        { preserveState: true, preserveScroll: true }
    );
});

// Compute running ledger balances chronologically and map stock_antes/stock_despues
const ledgerMovements = computed(() => {
    let runningBalance = 0;
    
    // Sort by ID ascending to compute balances chronologically
    const sorted = [...props.movimientos].sort((a, b) => a.id - b.id);
    
    const mapped = sorted.map((m) => {
        const qty = Number(m.cantidad);
        const stockAntes = runningBalance;
        
        if (m.tipo === 'entrada') {
            runningBalance += qty;
        } else if (m.tipo === 'salida') {
            runningBalance -= qty;
        } else if (m.tipo === 'ajuste') {
            runningBalance += qty;
        }
        
        return {
            ...m,
            stock_antes: stockAntes,
            stock_despues: runningBalance
        };
    });

    // Reverse to show latest movement at the top of the table
    return mapped.reverse();
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(val);
};

const formatQuantity = (qty, unit) => {
    const isBulk = ['kilogramo', 'gramo', 'litro', 'mililitro'].includes(String(unit).toLowerCase());
    return isBulk ? Number(qty).toFixed(3) : Number(qty).toFixed(0);
};

const getMotivoLabel = (motivo) => {
    switch (motivo) {
        case 'compra': return 'Compra de Mercadería';
        case 'venta': return 'Venta Registrada (POS)';
        case 'devolucion': return 'Devolución / Anulación';
        case 'merma': return 'Merma / Pérdida';
        case 'ajuste_inventario': return 'Ajuste Manual de Inventario';
        default: return motivo;
    }
};

const getMovementBadgeClass = (mov) => {
    if (mov.motivo === 'compra') return 'bg-blue-50 text-blue-700 border-blue-100 dark:bg-blue-950/20 dark:text-blue-400 dark:border-blue-900/30';
    if (mov.motivo === 'venta') return 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900/30';
    if (['ajuste_inventario', 'ajuste'].includes(mov.motivo) || mov.tipo === 'ajuste') {
        return 'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/30';
    }
    if (mov.motivo === 'devolucion') return 'bg-purple-50 text-purple-700 border-purple-100 dark:bg-purple-950/20 dark:text-purple-400 dark:border-purple-900/30';
    if (mov.motivo === 'merma') return 'bg-red-50 text-red-700 border-red-100 dark:bg-red-950/20 dark:text-red-400 dark:border-red-900/30';
    return 'bg-gray-50 text-gray-700 border-gray-150 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700';
};

const getMovementLabel = (mov) => {
    if (mov.motivo === 'compra') return 'Compra';
    if (mov.motivo === 'venta') return 'Venta';
    if (['ajuste_inventario', 'ajuste'].includes(mov.motivo) || mov.tipo === 'ajuste') return 'Ajuste';
    if (mov.motivo === 'devolucion') return 'Devolución';
    if (mov.motivo === 'merma') return 'Merma';
    return mov.tipo;
};

const getReferenciaLabel = (mov) => {
    const ref = mov.referencia;
    if (!ref) return '-';
    if (ref.numero_compra) return `Compra #${ref.numero_compra}`;
    if (ref.numero_venta) return `Venta #${ref.numero_venta}`;
    return '-';
};

// Computed Stats for Header Cards
const lastMovementText = computed(() => {
    if (props.movimientos.length === 0) return 'Sin movimientos';
    const latest = props.movimientos[0];
    const date = new Date(latest.created_at).toLocaleDateString('es-PE');
    return `${getMovementLabel(latest)} (${date})`;
});
</script>

<template>
    <Head title="Kardex de Inventario" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-3">
                    <Link :href="route('productos.index')" class="p-2 bg-gray-50 hover:bg-gray-100 dark:bg-gray-850 dark:hover:bg-gray-800 rounded-xl transition-colors">
                        <ArrowLeft class="w-4 h-4 text-gray-600 dark:text-gray-300" />
                    </Link>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">Kardex de Inventario</h2>
                        <p class="text-xs text-gray-500 mt-0.5 font-medium">Auditoría detallada de entradas, salidas y existencias.</p>
                    </div>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Selector de Producto -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-3xs">
                <div class="max-w-md">
                    <InputLabel for="producto_id" value="Seleccionar Producto para Auditoría" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                    <select
                        id="producto_id"
                        v-model="selectedProductoId"
                        class="mt-2 block w-full h-11 border border-gray-250 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer"
                    >
                        <option value="">-- Elige un producto de la lista --</option>
                        <option v-for="p in productos" :key="p.id" :value="p.id">
                            {{ p.nombre }} ({{ p.unidad_medida }}) - Stock: {{ formatQuantity(p.stock_actual, p.unidad_medida) }}
                        </option>
                    </select>
                </div>
            </div>

            <!-- DETALLES Y TABLA KARDEX (Si se selecciona un producto) -->
            <div v-if="productoSeleccionado" class="space-y-6">
                
                <!-- 10. Tarjetas de Encabezado -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <!-- Producto -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-3xs flex items-center justify-between col-span-2 md:col-span-1">
                        <div>
                            <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500 block">Producto</span>
                            <span class="text-xs font-bold text-gray-900 dark:text-white mt-1 block truncate max-w-[150px]" :title="productoSeleccionado.nombre">
                                {{ productoSeleccionado.nombre }}
                            </span>
                        </div>
                        <div class="w-8 h-8 rounded-xl bg-blue-50 dark:bg-blue-950/20 text-blue-600 flex items-center justify-center shrink-0">
                            <Package class="w-4.5 h-4.5" />
                        </div>
                    </div>

                    <!-- Stock Actual -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-3xs flex items-center justify-between">
                        <div>
                            <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500 block">Stock Actual</span>
                            <span 
                                class="text-base font-extrabold font-mono mt-1 block"
                                :class="Number(productoSeleccionado.stock_actual) <= Number(productoSeleccionado.stock_minimo) ? 'text-red-650' : 'text-emerald-600'"
                            >
                                {{ formatQuantity(productoSeleccionado.stock_actual, productoSeleccionado.unidad_medida) }}
                            </span>
                        </div>
                        <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 text-emerald-650 flex items-center justify-center shrink-0">
                            <Scale class="w-4.5 h-4.5" />
                        </div>
                    </div>

                    <!-- Costo Promedio -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-3xs flex items-center justify-between">
                        <div>
                            <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500 block">Costo (Compra)</span>
                            <span class="text-base font-extrabold font-mono text-gray-900 dark:text-white mt-1 block">
                                {{ formatCurrency(productoSeleccionado.precio_compra) }}
                            </span>
                        </div>
                        <div class="w-8 h-8 rounded-xl bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 flex items-center justify-center shrink-0">
                            <DollarSign class="w-4.5 h-4.5" />
                        </div>
                    </div>

                    <!-- Último Movimiento -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-3xs flex items-center justify-between">
                        <div>
                            <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500 block">Último Movimiento</span>
                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 mt-1 block truncate max-w-[130px]">
                                {{ lastMovementText }}
                            </span>
                        </div>
                        <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/20 text-amber-600 flex items-center justify-center shrink-0">
                            <Activity class="w-4.5 h-4.5" />
                        </div>
                    </div>

                    <!-- Proveedor Principal -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-3xs flex items-center justify-between">
                        <div>
                            <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500 block">Proveedor Principal</span>
                            <span class="text-xs font-bold text-gray-700 dark:text-gray-300 mt-1 block">
                                Proveedor General
                            </span>
                        </div>
                        <div class="w-8 h-8 rounded-xl bg-purple-50 dark:bg-purple-950/20 text-purple-650 flex items-center justify-center shrink-0">
                            <UserCheck class="w-4.5 h-4.5" />
                        </div>
                    </div>
                </div>

                <!-- Ledger Table -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-3xs">
                    <h3 class="text-sm font-bold text-gray-900 dark:text-white mb-4">
                        Bitácora Cronológica de Movimientos de Inventario
                    </h3>
                    
                    <div class="relative overflow-x-auto rounded-xl border border-gray-150 dark:border-gray-800">
                        <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400 border-collapse">
                            <thead class="text-[10px] text-gray-400 uppercase tracking-wider font-bold bg-gray-50/70 dark:bg-gray-950 border-b border-gray-150 dark:border-gray-800">
                                <tr>
                                    <th scope="col" class="px-5 py-3.5 font-bold text-gray-550">Fecha / Hora</th>
                                    <th scope="col" class="px-5 py-3.5 font-bold text-center text-gray-555">Tipo</th>
                                    <th scope="col" class="px-5 py-3.5 font-bold text-gray-555">Operación / Motivo</th>
                                    <th scope="col" class="px-5 py-3.5 text-right font-bold text-gray-555">Stock Antes</th>
                                    <th scope="col" class="px-5 py-3.5 text-right font-bold text-gray-555">Cantidad Movida</th>
                                    <th scope="col" class="px-5 py-3.5 text-right font-bold text-gray-555">Stock Después</th>
                                    <th scope="col" class="px-5 py-3.5 font-bold text-gray-555">Usuario</th>
                                    <th scope="col" class="px-5 py-3.5 font-bold text-gray-555">Documento Ref.</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                <tr 
                                    v-for="mov in ledgerMovements" 
                                    :key="mov.id"
                                    class="hover:bg-gray-50/50 dark:hover:bg-gray-950/40 transition-colors font-mono text-[11px]"
                                >
                                    <!-- Fecha / Hora -->
                                    <td class="px-5 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5">
                                            <Calendar class="w-3.5 h-3.5 text-gray-400 shrink-0" />
                                            <span>{{ new Date(mov.created_at).toLocaleDateString('es-PE') }}</span>
                                            <Clock class="w-3.5 h-3.5 text-gray-450 shrink-0 ml-1" />
                                            <span>{{ new Date(mov.created_at).toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit' }) }}</span>
                                        </div>
                                    </td>
                                    <!-- Tipo Badge -->
                                    <td class="px-5 py-3 text-center">
                                        <span 
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold border uppercase tracking-wide"
                                            :class="getMovementBadgeClass(mov)"
                                        >
                                            {{ getMovementLabel(mov) }}
                                        </span>
                                    </td>
                                    <!-- Motivo / Descripción -->
                                    <td class="px-5 py-3 text-gray-900 dark:text-white font-sans">
                                        <div class="flex flex-col">
                                            <span class="font-bold text-xs">{{ getMotivoLabel(mov.motivo) }}</span>
                                            <span class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5 font-medium leading-relaxed">{{ mov.descripcion }}</span>
                                        </div>
                                    </td>
                                    <!-- Stock Antes -->
                                    <td class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">
                                        {{ formatQuantity(mov.stock_antes, productoSeleccionado.unidad_medida) }}
                                    </td>
                                    <!-- Cantidad Movida -->
                                    <td 
                                        class="px-5 py-3 text-right font-extrabold"
                                        :class="mov.tipo === 'entrada' ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400'"
                                    >
                                        {{ mov.tipo === 'entrada' ? '+' : '-' }}{{ formatQuantity(mov.cantidad, productoSeleccionado.unidad_medida) }}
                                    </td>
                                    <!-- Stock Después -->
                                    <td class="px-5 py-3 text-right font-bold text-gray-900 dark:text-white">
                                        {{ formatQuantity(mov.stock_despues, productoSeleccionado.unidad_medida) }}
                                    </td>
                                    <!-- Usuario -->
                                    <td class="px-5 py-3 text-gray-655 dark:text-gray-450 font-sans text-xs">
                                        <div class="flex items-center gap-1">
                                            <User class="w-3.5 h-3.5 text-gray-400" />
                                            <span>{{ mov.creador?.nombre || 'Sistema' }}</span>
                                        </div>
                                    </td>
                                    <!-- Documento Ref -->
                                    <td class="px-5 py-3 text-gray-600 dark:text-gray-400 font-sans text-xs whitespace-nowrap">
                                        <div class="flex items-center gap-1" v-if="mov.referencia">
                                            <FileText class="w-3.5 h-3.5 text-gray-400" />
                                            <span>{{ getReferenciaLabel(mov) }}</span>
                                        </div>
                                        <span v-else class="text-gray-350 dark:text-gray-600">-</span>
                                    </td>
                                </tr>
                                <tr v-if="ledgerMovements.length === 0">
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-400 dark:text-gray-600 italic font-sans font-medium">
                                        <AlertCircle class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-700 mb-2" />
                                        Este producto aún no cuenta con movimientos de inventario registrados.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            <!-- SELECT PRODUCT EMPTY STATE -->
            <div 
                v-else 
                class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-12 text-center text-gray-400 dark:text-gray-600 font-medium flex flex-col items-center justify-center"
            >
                <ArrowLeftRight class="w-10 h-10 text-gray-300 dark:text-gray-700 mb-3 animate-pulse" />
                <span>Selecciona un producto de la lista desplegable para consultar la bitácora del Kardex.</span>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
