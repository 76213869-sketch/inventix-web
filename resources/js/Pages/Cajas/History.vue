<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

// Lucide outline icons
import {
    Clock,
    User,
    Calendar,
    Eye,
    Download,
    Search,
    FileSpreadsheet,
    X,
    Filter,
    Coins,
    BarChart3
} from 'lucide-vue-next';

const props = defineProps({
    cajas: {
        type: Array,
        default: () => [],
    },
    usuarios: {
        type: Array,
        default: () => [],
    },
});

// Filters state
const filterUser = ref('');
const filterDateStart = ref('');
const filterDateEnd = ref('');
const showExportModal = ref(false);

// Export form state
const exportType = ref('diario'); // diario, mensual, cajero
const exportUser = ref('');
const exportDateStart = ref(new Date().toISOString().substring(0, 10));
const exportDateEnd = ref(new Date().toISOString().substring(0, 10));

const filteredCajas = computed(() => {
    let result = props.cajas;

    if (filterUser.value) {
        result = result.filter(c => c.usuario_id === Number(filterUser.value));
    }

    if (filterDateStart.value) {
        result = result.filter(c => c.fecha_apertura >= filterDateStart.value);
    }

    if (filterDateEnd.value) {
        result = result.filter(c => c.fecha_apertura <= filterDateEnd.value);
    }

    return result;
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(val);
};

const getSumVentas = (caja) => {
    if (!caja.ventas) return 0;
    return caja.ventas.filter(v => v.estado === 'completada').reduce((sum, v) => sum + Number(v.total), 0);
};

const getSumIngresos = (caja) => {
    if (!caja.ingresos) return 0;
    return caja.ingresos.reduce((sum, i) => sum + Number(i.monto), 0);
};

const getSumGastos = (caja) => {
    if (!caja.gastos) return 0;
    return caja.gastos.reduce((sum, g) => sum + Number(g.monto), 0);
};

const getSumRetiros = (caja) => {
    if (!caja.retiros) return 0;
    return caja.retiros.reduce((sum, r) => sum + Number(r.monto), 0);
};

const getExportUrl = () => {
    const params = new URLSearchParams({
        tipo: exportType.value,
        usuario_id: exportUser.value,
        fecha_inicio: exportDateStart.value,
        fecha_fin: exportDateEnd.value
    });
    return route('cajas.exportarFiltros') + '?' + params.toString();
};

const handleExportSubmit = () => {
    window.location.href = getExportUrl();
    showExportModal.value = false;
};
</script>

<template>
    <Head title="Historial de Cajas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <div class="flex items-center gap-2 text-xs font-semibold text-blue-600 dark:text-blue-400 mb-1">
                        <Link :href="route('cajas.index')" class="hover:underline">Caja Diaria</Link>
                        <span>&gt;</span>
                        <span class="text-gray-400">Historial</span>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">Historial de Cajas</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-medium">Registro completo de sesiones de caja, arqueos y auditoría histórica.</p>
                </div>

                <div class="flex gap-2">
                    <button
                        @click="showExportModal = true"
                        class="inline-flex h-10 items-center justify-center rounded-xl bg-blue-600 hover:bg-blue-700 text-white px-4 text-xs font-bold shadow-sm active:scale-98 transition-all cursor-pointer"
                    >
                        <BarChart3 class="w-4 h-4 mr-1.5" />
                        Exportar Reportes
                    </button>
                    <Link
                        :href="route('cajas.index')"
                        class="inline-flex h-10 items-center justify-center rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 text-xs font-bold text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 active:scale-98 transition-all"
                    >
                        Volver a Caja Actual
                    </Link>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- FILTERS PANEL -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-6 shadow-3xs">
                <h3 class="text-xs font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                    <Filter class="w-4 h-4" /> Filtros de Búsqueda
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <!-- Cajero Filter -->
                    <div>
                        <InputLabel for="filter_cajero" value="Filtrar por Cajero" class="text-xs" />
                        <select
                            id="filter_cajero"
                            v-model="filterUser"
                            class="mt-1.5 block w-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-850 text-gray-700 dark:text-gray-300 focus:border-blue-500 rounded-xl text-xs h-10 px-3 outline-none cursor-pointer"
                        >
                            <option value="">Todos los Cajeros</option>
                            <option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.nombre }}</option>
                        </select>
                    </div>

                    <!-- Date Start -->
                    <div>
                        <InputLabel for="filter_date_start" value="Fecha de Apertura Desde" class="text-xs" />
                        <input
                            id="filter_date_start"
                            type="date"
                            v-model="filterDateStart"
                            class="mt-1.5 block w-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-850 text-gray-700 dark:text-gray-300 focus:border-blue-500 rounded-xl text-xs h-10 px-3 outline-none font-mono"
                        />
                    </div>

                    <!-- Date End -->
                    <div>
                        <InputLabel for="filter_date_end" value="Fecha de Apertura Hasta" class="text-xs" />
                        <input
                            id="filter_date_end"
                            type="date"
                            v-model="filterDateEnd"
                            class="mt-1.5 block w-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-850 text-gray-700 dark:text-gray-300 focus:border-blue-500 rounded-xl text-xs h-10 px-3 outline-none font-mono"
                        />
                    </div>
                </div>
            </div>

            <!-- MAIN TABLE -->
            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden shadow-3xs">
                <div class="p-6">
                    <div class="relative overflow-x-auto rounded-xl border border-gray-150 dark:border-gray-800">
                        <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400 border-collapse font-sans">
                            <thead class="text-[10px] text-gray-400 uppercase tracking-wider font-bold bg-gray-50/90 dark:bg-gray-950 sticky top-0 backdrop-blur-md z-10 border-b border-gray-150 dark:border-gray-800">
                                <tr>
                                    <th scope="col" class="px-4 py-3.5 font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">ID / Cajero</th>
                                    <th scope="col" class="px-4 py-3.5 font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Horarios Apertura / Cierre</th>
                                    <th scope="col" class="px-4 py-3.5 text-right font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Inicial</th>
                                    <th scope="col" class="px-4 py-3.5 text-right font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Ventas / Ingresos</th>
                                    <th scope="col" class="px-4 py-3.5 text-right font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Gastos / Retiros</th>
                                    <th scope="col" class="px-4 py-3.5 text-right font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Esperado / Real</th>
                                    <th scope="col" class="px-4 py-3.5 text-right font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Diferencia</th>
                                    <th scope="col" class="px-4 py-3.5 text-center font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Estado</th>
                                    <th scope="col" class="px-4 py-3.5 text-center font-bold text-gray-500 dark:text-gray-400 bg-gray-50/90 dark:bg-gray-950">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                <tr 
                                    v-for="c in filteredCajas" 
                                    :key="c.id"
                                    class="hover:bg-gray-50/50 dark:hover:bg-gray-950/50 transition-colors"
                                >
                                    <!-- ID / Cajero -->
                                    <td class="px-4 py-3">
                                        <div class="font-mono font-bold text-gray-900 dark:text-white">#{{ c.id }}</div>
                                        <div class="text-[10px] text-gray-400 dark:text-gray-550 font-semibold mt-0.5">{{ c.usuario?.nombre || 'Cajero' }}</div>
                                    </td>
                                    <!-- Horarios Apertura / Cierre -->
                                    <td class="px-4 py-3 font-mono text-gray-650 dark:text-gray-400">
                                        <div class="flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-500 shrink-0"></span>
                                            <span>{{ new Date(c.fecha_apertura).toLocaleDateString('es-PE') }} {{ c.hora_apertura }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 mt-1" v-if="c.hora_cierre">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-500 shrink-0"></span>
                                            <span>{{ c.fecha_cierre ? new Date(c.fecha_cierre).toLocaleDateString('es-PE') : '' }} {{ c.hora_cierre }}</span>
                                        </div>
                                        <div class="flex items-center gap-1 mt-1 text-emerald-650 dark:text-emerald-400 font-bold font-sans" v-else>
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0 animate-pulse"></span>
                                            <span>Abierta</span>
                                        </div>
                                    </td>
                                    <!-- Inicial -->
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-gray-700 dark:text-gray-300">
                                        {{ formatCurrency(c.monto_inicial) }}
                                    </td>
                                    <!-- Ventas / Ingresos -->
                                    <td class="px-4 py-3 text-right font-mono text-[11px]">
                                        <div class="text-blue-600 dark:text-blue-400 font-bold">+{{ formatCurrency(getSumVentas(c)) }}</div>
                                        <div class="text-emerald-600 dark:text-emerald-450 font-semibold mt-0.5">+{{ formatCurrency(getSumIngresos(c)) }}</div>
                                    </td>
                                    <!-- Gastos / Retiros -->
                                    <td class="px-4 py-3 text-right font-mono text-[11px]">
                                        <div class="text-red-600 dark:text-red-400 font-bold">-{{ formatCurrency(getSumGastos(c)) }}</div>
                                        <div class="text-amber-600 dark:text-amber-450 font-semibold mt-0.5">-{{ formatCurrency(getSumRetiros(c)) }}</div>
                                    </td>
                                    <!-- Esperado / Real -->
                                    <td class="px-4 py-3 text-right font-mono text-[11px]">
                                        <div class="text-gray-400 dark:text-gray-500 font-semibold">{{ formatCurrency(c.monto_esperado) }}</div>
                                        <div class="text-gray-800 dark:text-gray-200 font-bold mt-0.5">{{ c.monto_final !== null ? formatCurrency(c.monto_final) : '-' }}</div>
                                    </td>
                                    <!-- Diferencia -->
                                    <td class="px-4 py-3 text-right font-mono font-bold">
                                        <div v-if="c.diferencia !== null" :class="{
                                            'text-emerald-600 dark:text-emerald-450': Number(c.diferencia) > 0,
                                            'text-red-650 dark:text-red-400': Number(c.diferencia) < 0,
                                            'text-gray-500': Number(c.diferencia) === 0
                                        }">
                                            {{ Number(c.diferencia) > 0 ? '+' : '' }}{{ formatCurrency(c.diferencia) }}
                                        </div>
                                        <div v-else class="text-gray-450">-</div>
                                    </td>
                                    <!-- Estado -->
                                    <td class="px-4 py-3 text-center">
                                        <span 
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[9px] font-bold border"
                                            :class="c.estado === 'abierta' 
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900/30' 
                                                : 'bg-gray-50 text-gray-600 border-gray-150 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'"
                                        >
                                            {{ c.estado === 'abierta' ? 'Abierta' : 'Cerrada' }}
                                        </span>
                                    </td>
                                    <!-- Acciones -->
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <Link
                                                :href="route('cajas.show', c.id)"
                                                class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-950/20 rounded-lg transition-all"
                                                title="Ver Detalle"
                                            >
                                                <Eye class="w-4 h-4" />
                                            </Link>
                                            <a
                                                v-if="c.estado === 'cerrada'"
                                                :href="route('cajas.exportarReporte', c.id)"
                                                class="p-1.5 text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 rounded-lg transition-all"
                                                title="Descargar PDF"
                                            >
                                                <Download class="w-4 h-4" />
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredCajas.length === 0">
                                    <td colspan="9" class="px-6 py-12 text-center text-gray-400 dark:text-gray-650">
                                        No se encontraron registros de caja en el historial.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL REPORTES EXPORTABLES -->
        <Modal :show="showExportModal" @close="showExportModal = false" max-width="md">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800 mb-4">
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">Exportar Reportes de Caja</h3>
                    <button type="button" @click="showExportModal = false" class="text-gray-400 hover:text-gray-650">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="handleExportSubmit" class="space-y-4">
                    <div>
                        <InputLabel for="export_type" value="Tipo de Reporte / Resumen" class="text-xs font-bold" />
                        <select
                            id="export_type"
                            v-model="exportType"
                            class="mt-1.5 block w-full border border-gray-250 dark:border-gray-700 bg-white dark:bg-gray-850 text-gray-700 dark:text-gray-300 focus:border-blue-500 rounded-xl text-xs h-10 px-3 outline-none cursor-pointer"
                        >
                            <option value="diario">Resumen Diario (Rango Fechas)</option>
                            <option value="mensual">Resumen Mensual (Mes Seleccionado)</option>
                            <option value="cajero">Resumen por Cajero</option>
                        </select>
                    </div>

                    <div v-if="exportType === 'cajero'">
                        <InputLabel for="export_user" value="Seleccionar Cajero" class="text-xs" />
                        <select
                            id="export_user"
                            v-model="exportUser"
                            class="mt-1.5 block w-full border border-gray-250 dark:border-gray-700 bg-white dark:bg-gray-850 text-gray-700 dark:text-gray-300 focus:border-blue-500 rounded-xl text-xs h-10 px-3 outline-none cursor-pointer"
                            required
                        >
                            <option value="">Seleccione cajero...</option>
                            <option v-for="u in usuarios" :key="u.id" :value="u.id">{{ u.nombre }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3" v-if="exportType === 'diario' || exportType === 'cajero'">
                        <div>
                            <InputLabel for="export_date_start" value="Fecha Inicio" class="text-xs" />
                            <input
                                id="export_date_start"
                                type="date"
                                v-model="exportDateStart"
                                class="mt-1.5 block w-full border border-gray-250 dark:border-gray-700 bg-white dark:bg-gray-850 text-gray-700 dark:text-gray-300 focus:border-blue-500 rounded-xl text-xs h-10 px-3 outline-none font-mono"
                                required
                            />
                        </div>
                        <div>
                            <InputLabel for="export_date_end" value="Fecha Fin" class="text-xs" />
                            <input
                                id="export_date_end"
                                type="date"
                                v-model="exportDateEnd"
                                class="mt-1.5 block w-full border border-gray-250 dark:border-gray-700 bg-white dark:bg-gray-850 text-gray-700 dark:text-gray-300 focus:border-blue-500 rounded-xl text-xs h-10 px-3 outline-none font-mono"
                                required
                            />
                        </div>
                    </div>

                    <div v-if="exportType === 'mensual'">
                        <InputLabel for="export_date_month" value="Seleccionar Mes" class="text-xs" />
                        <input
                            id="export_date_month"
                            type="month"
                            v-model="exportDateStart"
                            class="mt-1.5 block w-full border border-gray-250 dark:border-gray-700 bg-white dark:bg-gray-850 text-gray-700 dark:text-gray-300 focus:border-blue-500 rounded-xl text-xs h-10 px-3 outline-none font-mono"
                            required
                        />
                    </div>

                    <div class="pt-2 flex gap-3">
                        <SecondaryButton @click="showExportModal = false" class="flex-1 justify-center py-3 rounded-xl text-xs h-11">Cancelar</SecondaryButton>
                        <button
                            type="submit"
                            style="background-color: #10b981; color: #ffffff;"
                            class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all flex items-center justify-center gap-1.5 cursor-pointer h-11 shadow-2xs"
                        >
                            <FileSpreadsheet class="w-4.5 h-4.5" />
                            Exportar Excel / CSV
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
