<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

// Lucide outline icons
import {
    Clock,
    User,
    Calendar,
    Download,
    Coins,
    TrendingUp,
    TrendingDown,
    DollarSign,
    Ban,
    Percent,
    AlertTriangle,
    CheckCircle2,
    FileText,
    Activity,
    ShoppingCart
} from 'lucide-vue-next';

const props = defineProps({
    caja: {
        type: Object,
        required: true,
    },
    productos_vendidos: {
        type: Array,
        default: () => [],
    },
    resumen: {
        type: Object,
        required: true,
    },
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(val);
};

const formatQuantity = (qty, unit) => {
    const isBulk = ['kilogramo', 'gramo', 'litro', 'mililitro'].includes(String(unit).toLowerCase());
    return isBulk ? Number(qty).toFixed(3) : Number(qty).toFixed(0);
};

const getTipoLabel = (tipo) => {
    switch (tipo) {
        case 'apertura': return 'Apertura';
        case 'venta': return 'Venta';
        case 'gasto': return 'Gasto';
        case 'retiro': return 'Retiro';
        case 'ingreso': return 'Ingreso';
        case 'cierre': return 'Cierre';
        default: return tipo;
    }
};

const getTipoBadgeClass = (tipo) => {
    switch (tipo) {
        case 'apertura': return 'bg-blue-50 text-blue-700 border-blue-100';
        case 'venta': return 'bg-emerald-50 text-emerald-700 border-emerald-100';
        case 'gasto': return 'bg-red-50 text-red-700 border-red-100';
        case 'retiro': return 'bg-amber-50 text-amber-700 border-amber-100';
        case 'ingreso': return 'bg-indigo-50 text-indigo-700 border-indigo-100';
        case 'cierre': return 'bg-gray-50 text-gray-700 border-gray-150';
        default: return 'bg-gray-50 text-gray-700 border-gray-150';
    }
};
</script>

<template>
    <Head :title="'Detalle de Caja #' + caja.id" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-blue-600 dark:text-blue-400 mb-1">
                        <Link :href="route('cajas.index')" class="hover:underline">Caja Diaria</Link>
                        <span>&gt;</span>
                        <Link :href="route('cajas.historial')" class="hover:underline">Historial</Link>
                        <span>&gt;</span>
                        <span class="text-gray-400">Detalle #{{ caja.id }}</span>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">Detalle de Caja #{{ caja.id }}</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-medium">Auditoría consolidada y arqueo de la sesión de caja seleccionada.</p>
                </div>

                <div class="flex gap-2">
                    <a
                        :href="route('cajas.exportarReporte', caja.id)"
                        class="inline-flex h-10 items-center justify-center rounded-xl bg-blue-600 hover:bg-blue-700 text-white px-4 text-xs font-bold shadow-sm active:scale-98 transition-all cursor-pointer"
                    >
                        <Download class="w-4 h-4 mr-1.5" />
                        Descargar PDF
                    </a>
                    <Link
                        :href="route('cajas.historial')"
                        class="inline-flex h-10 items-center justify-center rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 text-xs font-bold text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 active:scale-98 transition-all"
                    >
                        Volver al Historial
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- SECCIÓN 1: RESUMEN DE ARQUEO / CIERRE -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Informacion Sesión -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-3xs space-y-4">
                    <h3 class="text-xs font-bold text-gray-400 dark:text-gray-550 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <Clock class="w-4.5 h-4.5 text-blue-500" /> Datos de la Sesión
                    </h3>
                    <div class="space-y-3.5 text-xs">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Cajero:</span>
                            <span class="font-bold text-gray-800 dark:text-gray-200">{{ caja.usuario?.nombre || 'Cajero' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Apertura:</span>
                            <span class="font-mono text-gray-800 dark:text-gray-200">{{ new Date(caja.fecha_apertura).toLocaleDateString('es-PE') }} - {{ caja.hora_apertura }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Cierre:</span>
                            <span class="font-mono text-gray-800 dark:text-gray-200">{{ caja.fecha_cierre ? new Date(caja.fecha_cierre).toLocaleDateString('es-PE') + ' - ' + caja.hora_cierre : 'Sesión Abierta' }}</span>
                        </div>
                        <div class="flex justify-between border-t border-gray-100 dark:border-gray-800 pt-3">
                            <span class="text-gray-400">Estado:</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border" :class="caja.estado === 'abierta' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-gray-50 text-gray-600 border-gray-150'">
                                {{ caja.estado === 'abierta' ? 'Abierta' : 'Cerrada' }}
                            </span>
                        </div>
                        <div class="pt-2" v-if="caja.observacion_apertura">
                            <span class="text-[10px] font-bold text-gray-400 block mb-1">Obs. Apertura:</span>
                            <p class="p-2.5 bg-gray-50 dark:bg-gray-950 border border-gray-100 dark:border-gray-850 rounded-xl italic text-gray-600 dark:text-gray-400">{{ caja.observacion_apertura }}</p>
                        </div>
                        <div v-if="caja.observacion_cierre">
                            <span class="text-[10px] font-bold text-gray-400 block mb-1">Obs. Cierre:</span>
                            <p class="p-2.5 bg-gray-50 dark:bg-gray-950 border border-gray-100 dark:border-gray-850 rounded-xl italic text-gray-600 dark:text-gray-400">{{ caja.observacion_cierre }}</p>
                        </div>
                    </div>
                </div>

                <!-- Arqueo Contable -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-3xs space-y-3.5">
                    <h3 class="text-xs font-bold text-gray-400 dark:text-gray-550 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <Coins class="w-4.5 h-4.5 text-emerald-500" /> Balances y Arqueo
                    </h3>
                    <div class="space-y-3 text-xs font-mono">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Caja Inicial (Efectivo):</span>
                            <span>{{ formatCurrency(caja.monto_inicial) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Ventas en Efectivo:</span>
                            <span>+ {{ formatCurrency(resumen.ventas_efectivo) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Ingresos Adicionales:</span>
                            <span class="text-emerald-600">+ {{ formatCurrency(caja.ingresos ? caja.ingresos.reduce((sum, i) => sum + Number(i.monto), 0) : 0) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Gastos Registrados:</span>
                            <span class="text-red-500">- {{ formatCurrency(caja.gastos ? caja.gastos.reduce((sum, g) => sum + Number(g.monto), 0) : 0) }}</span>
                        </div>
                        <div class="flex justify-between border-b border-dashed border-gray-200 dark:border-gray-800 pb-2.5">
                            <span class="text-gray-400">Retiros de Efectivo:</span>
                            <span class="text-red-500">- {{ formatCurrency(caja.retiros ? caja.retiros.reduce((sum, r) => sum + Number(r.monto), 0) : 0) }}</span>
                        </div>
                        <div class="flex justify-between pt-1 font-bold text-gray-900 dark:text-white text-xs border-b border-gray-150 dark:border-gray-800 pb-2.5">
                            <span>EFECTIVO ESPERADO:</span>
                            <span>{{ formatCurrency(caja.monto_esperado) }}</span>
                        </div>
                        <div class="flex justify-between pt-0.5 font-bold text-gray-900 dark:text-white text-xs">
                            <span>EFECTIVO CONTADO:</span>
                            <span>{{ caja.monto_final !== null ? formatCurrency(caja.monto_final) : 'No realizado' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Diferencia Arqueo -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-3xs flex flex-col justify-between">
                    <h3 class="text-xs font-bold text-gray-400 dark:text-gray-550 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                        <Activity class="w-4.5 h-4.5 text-indigo-500" /> Resultados del Arqueo
                    </h3>
                    
                    <div v-if="caja.monto_final === null" class="py-6 flex flex-col items-center justify-center text-center text-gray-400 dark:text-gray-600">
                        <AlertTriangle class="w-8 h-8 mb-2 opacity-50 text-amber-500 animate-pulse" />
                        <p class="text-xs font-bold uppercase">Arqueo Pendiente</p>
                        <p class="text-[10px] mt-0.5">La caja sigue abierta por el cajero.</p>
                    </div>

                    <div v-else class="space-y-4">
                        <div class="text-center py-4 rounded-2xl border" :class="Number(caja.diferencia) === 0 ? 'bg-emerald-50 border-emerald-100 text-emerald-800 dark:bg-emerald-950/20 dark:border-emerald-900/30 dark:text-emerald-400' : (Number(caja.diferencia) > 0 ? 'bg-emerald-50 border-emerald-100 text-emerald-800 dark:bg-emerald-950/20 dark:border-emerald-900/30 dark:text-emerald-400' : 'bg-red-50 border-red-100 text-red-800 dark:bg-red-950/20 dark:border-red-900/30 dark:text-red-400')">
                            <span class="text-[10px] font-bold uppercase block tracking-wider">Diferencia de Caja</span>
                            <span class="text-2xl font-extrabold font-mono mt-1 block">
                                {{ Number(caja.diferencia) > 0 ? '+' : '' }}{{ formatCurrency(caja.diferencia) }}
                            </span>
                            <span class="text-[9px] font-bold block mt-1 uppercase" :class="Number(caja.diferencia) === 0 ? 'text-emerald-600 dark:text-emerald-400' : (Number(caja.diferencia) > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400')">
                                {{ Number(caja.diferencia) === 0 ? 'Caja Cuadrada' : (Number(caja.diferencia) > 0 ? 'Sobrante' : 'Faltante') }}
                            </span>
                        </div>

                        <div v-if="Number(caja.diferencia) < 0 && caja.motivo_diferencia" class="p-3 bg-red-50/40 dark:bg-red-950/10 border border-red-100 dark:border-red-950/20 rounded-xl text-xs">
                            <span class="text-[10px] font-bold text-red-800 dark:text-red-400 block mb-1">Motivo del Faltante:</span>
                            <p class="text-red-700 dark:text-red-300 italic font-medium leading-relaxed">"{{ caja.motivo_diferencia }}"</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 2: MÉTODOS DE PAGO Y DESCUENTOS / ANULACIONES -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Ventas por Método de Pago -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-3xs">
                    <h4 class="text-xs font-bold text-gray-400 dark:text-gray-550 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                        <Coins class="w-4 h-4" /> Resumen de Cobros
                    </h4>
                    <div class="space-y-3.5 text-xs font-mono">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Ventas Efectivo:</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(resumen.ventas_efectivo) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Ventas Yape / Plin:</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(resumen.ventas_yape_plin) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Ventas Tarjeta:</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(resumen.ventas_tarjeta - resumen.ventas_transferencia) }}</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-100 dark:border-gray-800 pb-2">
                            <span class="text-gray-500">Ventas Transferencia:</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(resumen.ventas_transferencia) }}</span>
                        </div>
                        <div class="flex justify-between pt-1 text-xs font-bold text-blue-600 dark:text-blue-400">
                            <span>TOTAL FACTURADO:</span>
                            <span>{{ formatCurrency(resumen.ventas_efectivo + resumen.ventas_yape_plin + resumen.ventas_tarjeta) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Descuentos y Anulaciones -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-3xs">
                    <h4 class="text-xs font-bold text-gray-400 dark:text-gray-550 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                        <Ban class="w-4 h-4" /> Descuentos y Anulaciones
                    </h4>
                    <div class="space-y-3.5 text-xs">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-500 flex items-center gap-1"><Percent class="w-3.5 h-3.5 text-red-500" /> Descuentos Otorgados:</span>
                            <span class="font-bold font-mono text-red-600">- {{ formatCurrency(resumen.descuentos) }}</span>
                        </div>
                        <div class="flex justify-between items-center border-t border-gray-100 dark:border-gray-800 pt-3.5">
                            <span class="text-gray-500 flex items-center gap-1"><Ban class="w-3.5 h-3.5 text-red-600" /> Ventas Anuladas:</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ resumen.anulaciones }} comprobante(s)</span>
                        </div>
                    </div>
                </div>

                <!-- Gastos, Retiros e Ingresos Extra -->
                <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-5 shadow-3xs">
                    <h4 class="text-xs font-bold text-gray-400 dark:text-gray-550 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                        <Activity class="w-4 h-4" /> Flujos de Efectivo Directos
                    </h4>
                    <div class="space-y-3 text-xs font-mono">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Ingresos extra (+):</span>
                            <span class="font-bold text-emerald-600">+ {{ formatCurrency(caja.ingresos ? caja.ingresos.reduce((sum, i) => sum + Number(i.monto), 0) : 0) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Gastos (-):</span>
                            <span class="font-bold text-red-600">- {{ formatCurrency(caja.gastos ? caja.gastos.reduce((sum, g) => sum + Number(g.monto), 0) : 0) }}</span>
                        </div>
                        <div class="flex justify-between border-b border-gray-100 dark:border-gray-800 pb-2">
                            <span class="text-gray-500">Retiros (-):</span>
                            <span class="font-bold text-red-600">- {{ formatCurrency(caja.retiros ? caja.retiros.reduce((sum, r) => sum + Number(r.monto), 0) : 0) }}</span>
                        </div>
                        <div class="flex justify-between pt-1 text-xs font-bold text-gray-900 dark:text-white">
                            <span>FLUJO EFECTIVO NETO:</span>
                            <span :class="Number(caja.ingresos ? caja.ingresos.reduce((sum, i) => sum + Number(i.monto), 0) : 0) - Number(caja.gastos ? caja.gastos.reduce((sum, g) => sum + Number(g.monto), 0) : 0) - Number(caja.retiros ? caja.retiros.reduce((sum, r) => sum + Number(r.monto), 0) : 0) >= 0 ? 'text-emerald-600' : 'text-red-600'">
                                {{ formatCurrency(Number(caja.ingresos ? caja.ingresos.reduce((sum, i) => sum + Number(i.monto), 0) : 0) - Number(caja.gastos ? caja.gastos.reduce((sum, g) => sum + Number(g.monto), 0) : 0) - Number(caja.retiros ? caja.retiros.reduce((sum, r) => sum + Number(r.monto), 0) : 0)) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 3: PRODUCTOS VENDIDOS -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-3xs">
                <div class="p-6">
                    <h3 class="text-xs font-bold text-gray-400 dark:text-gray-550 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                        <ShoppingCart class="w-4.5 h-4.5 text-blue-500" /> Productos Vendidos en la Sesión
                    </h3>

                    <div class="relative overflow-x-auto rounded-xl border border-gray-150 dark:border-gray-800">
                        <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400 border-collapse">
                            <thead class="text-[10px] text-gray-400 uppercase tracking-wider font-bold bg-gray-50/90 dark:bg-gray-950 sticky top-0 backdrop-blur-md z-10 border-b border-gray-150 dark:border-gray-800">
                                <tr>
                                    <th scope="col" class="px-5 py-3 font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Producto</th>
                                    <th scope="col" class="px-5 py-3 text-center font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Unidad Medida</th>
                                    <th scope="col" class="px-5 py-3 text-center font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Cantidad Vendida</th>
                                    <th scope="col" class="px-5 py-3 text-right font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Total Facturado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                <tr 
                                    v-for="p in productos_vendidos" 
                                    :key="p.nombre"
                                    class="hover:bg-gray-50/50 dark:hover:bg-gray-950/50 transition-colors"
                                >
                                    <td class="px-5 py-3 font-semibold text-gray-900 dark:text-white">
                                        {{ p.nombre }}
                                    </td>
                                    <td class="px-5 py-3 text-center text-gray-500 dark:text-gray-400 capitalize">
                                        {{ p.unidad_medida }}
                                    </td>
                                    <td class="px-5 py-3 text-center font-bold text-gray-800 dark:text-gray-250 font-mono">
                                        {{ formatQuantity(p.cantidad, p.unidad_medida) }}
                                    </td>
                                    <td class="px-5 py-3 text-right font-bold font-mono text-gray-900 dark:text-white">
                                        {{ formatCurrency(p.total) }}
                                    </td>
                                </tr>
                                <tr v-if="productos_vendidos.length === 0">
                                    <td colspan="4" class="px-5 py-8 text-center text-gray-400 dark:text-gray-660">
                                        No se registran productos vendidos en esta sesión.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 4: HISTORIAL DE TODOS LOS MOVIMIENTOS -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-3xs">
                <div class="p-6">
                    <h3 class="text-xs font-bold text-gray-400 dark:text-gray-550 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                        <Activity class="w-4.5 h-4.5 text-blue-500" /> Registro de Auditoría de la Sesión
                    </h3>

                    <div class="relative overflow-x-auto rounded-xl border border-gray-150 dark:border-gray-800">
                        <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400 border-collapse">
                            <thead class="text-[10px] text-gray-400 uppercase tracking-wider font-bold bg-gray-50/90 dark:bg-gray-950 sticky top-0 backdrop-blur-md z-10 border-b border-gray-150 dark:border-gray-800">
                                <tr>
                                    <th scope="col" class="px-5 py-3 font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Fecha / Hora</th>
                                    <th scope="col" class="px-5 py-3 text-center font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Tipo</th>
                                    <th scope="col" class="px-5 py-3 font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Descripción</th>
                                    <th scope="col" class="px-5 py-3 font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Usuario</th>
                                    <th scope="col" class="px-5 py-3 text-right font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Monto</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                <tr 
                                    v-for="mov in caja.movimientos" 
                                    :key="mov.id"
                                    class="hover:bg-gray-50/50 dark:hover:bg-gray-950/50 transition-colors"
                                >
                                    <td class="px-5 py-3 text-gray-500 dark:text-gray-400 font-mono">
                                        {{ new Date(mov.fecha).toLocaleDateString('es-PE') }} - {{ mov.hora }}
                                    </td>
                                    <td class="px-5 py-3 text-center">
                                        <span 
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                                            :class="getTipoBadgeClass(mov.tipo)"
                                        >
                                            {{ getTipoLabel(mov.tipo) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-gray-700 dark:text-gray-300 font-medium">
                                        {{ mov.descripcion }}
                                    </td>
                                    <td class="px-5 py-3 text-gray-500 dark:text-gray-400">
                                        {{ mov.usuario?.nombre || 'Cajero' }}
                                    </td>
                                    <td 
                                        class="px-5 py-3 text-right font-bold font-mono font-semibold"
                                        :class="['gasto', 'retiro'].includes(mov.tipo) ? 'text-red-600' : 'text-gray-900 dark:text-white'"
                                    >
                                        {{ ['gasto', 'retiro'].includes(mov.tipo) ? '-' : '' }}{{ formatCurrency(mov.monto) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
