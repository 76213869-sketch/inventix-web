<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

// Lucide Icons - outline, consistent sizing
import {
    Coins,
    Plus,
    Minus,
    ArrowUpRight,
    ArrowDownRight,
    Lock,
    Unlock,
    AlertTriangle,
    CheckCircle2,
    Clock,
    X,
    Briefcase,
    Calendar,
    User,
    Calculator,
    FileText,
    TrendingUp,
    TrendingDown,
    CreditCard,
    Smartphone,
    Banknote,
    ShoppingBag,
    ArrowRightLeft,
    DollarSign,
    Activity,
    QrCode,
    Sparkles,
    ChevronRight
} from 'lucide-vue-next';

const props = defineProps({
    caja: {
        type: Object,
        default: null,
    },
    movimientos: {
        type: Array,
        default: () => [],
    },
    resumen: {
        type: Object,
        default: null,
    },
});

// Modals state
const showAperturaModal = ref(false);
const showGastoModal = ref(false);
const showRetiroModal = ref(false);
const showIngresoModal = ref(false);
const showCierreModal = ref(false);

// Filter state
const filterTipo = ref('');

// Forms
const aperturaForm = useForm({
    monto_inicial: 100,
    observacion: '',
});

const gastoForm = useForm({
    categoria: 'Otros',
    monto: '',
    descripcion: '',
});

const retiroForm = useForm({
    monto: '',
    motivo: '',
});

const ingresoForm = useForm({
    monto: '',
    descripcion: '',
});

const cierreForm = useForm({
    monto_final: '',
    observacion: '',
    motivo_diferencia: '',
    is_diferente: false,
    confirmado: false,
});

// Live clock logic
const currentDateTime = ref('');
let timerId = null;

const updateTime = () => {
    const now = new Date();
    currentDateTime.value = now.toLocaleDateString('es-PE') + ' ' + now.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

onMounted(() => {
    updateTime();
    timerId = setInterval(updateTime, 1000);
});

onUnmounted(() => {
    if (timerId) clearInterval(timerId);
});

// Computed properties
const cajaEsperada = computed(() => props.resumen ? Number(props.resumen.caja_esperada) : 0);
const diferenciaArqueo = computed(() => {
    if (cierreForm.monto_final === '' || cierreForm.monto_final === null) return 0;
    return Number(cierreForm.monto_final) - cajaEsperada.value;
});

const isDiferente = computed(() => diferenciaArqueo.value !== 0);

// Watch differences to set required fields
const handleMontoFinalInput = () => {
    cierreForm.is_diferente = isDiferente.value;
};

// Form submits
const submitApertura = () => {
    aperturaForm.post(route('cajas.abrir'), {
        onSuccess: () => {
            showAperturaModal.value = false;
            aperturaForm.reset();
        }
    });
};

const submitGasto = () => {
    gastoForm.post(route('cajas.gasto'), {
        onSuccess: () => {
            showGastoModal.value = false;
            gastoForm.reset();
        }
    });
};

const submitRetiro = () => {
    retiroForm.post(route('cajas.retiro'), {
        onSuccess: () => {
            showRetiroModal.value = false;
            retiroForm.reset();
        }
    });
};

const submitIngreso = () => {
    ingresoForm.post(route('cajas.ingreso'), {
        onSuccess: () => {
            showIngresoModal.value = false;
            ingresoForm.reset();
        }
    });
};

const submitCierre = () => {
    if (!cierreForm.confirmado) return;
    cierreForm.post(route('cajas.cerrar'), {
        onSuccess: () => {
            showCierreModal.value = false;
            cierreForm.reset();
        }
    });
};

// Filters
const filteredMovimientos = computed(() => {
    if (!filterTipo.value) return props.movimientos;
    return props.movimientos.filter(m => m.tipo === filterTipo.value);
});

// Helpers
const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(val);
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

// Dynamic stats for lateral card
const totalVentasCount = computed(() => props.movimientos.filter(m => m.tipo === 'venta').length);

const ticketPromedio = computed(() => {
    if (!props.resumen || totalVentasCount.value === 0) return 0;
    return props.resumen.total_vendido / totalVentasCount.value;
});

const clientesAtendidos = computed(() => totalVentasCount.value);

const metodoPagoMasUsado = computed(() => {
    if (!props.resumen) return 'Ninguno';
    const methods = [
        { name: 'Efectivo', value: props.resumen.ventas_efectivo || 0 },
        { name: 'Yape / Plin', value: props.resumen.ventas_yape_plin || 0 },
        { name: 'Tarjeta', value: props.resumen.ventas_tarjeta || 0 }
    ];
    // Sort in descending order
    methods.sort((a, b) => b.value - a.value);
    // If all are zero, return 'Ninguno'
    if (methods[0].value === 0) return 'Ninguno';
    return methods[0].name;
});
</script>

<template>
    <Head title="Caja Diaria" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 w-full">
                <!-- Title / Subtitle -->
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">Caja Diaria</h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">Control del efectivo de la jornada</p>
                </div>
                <!-- Right Header Actions & Current DateTime -->
                <div v-if="caja" class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
                    <!-- Estado Badge -->
                    <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-350 border border-emerald-100 dark:border-emerald-900/30 shadow-3xs">
                        <span class="w-1.5 h-1.5 mr-2 bg-emerald-500 rounded-full animate-pulse"></span>
                        Caja Abierta
                    </span>
                    
                    <!-- Live running Date and Time -->
                    <span class="flex items-center gap-1.5 px-3 py-1.5 bg-gray-50 dark:bg-gray-900 border border-gray-150 dark:border-gray-800 rounded-xl text-xs font-semibold text-gray-700 dark:text-gray-300 shadow-3xs">
                        <Clock class="w-3.5 h-3.5 text-blue-500" />
                        <span class="font-mono">{{ currentDateTime }}</span>
                    </span>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 ml-auto lg:ml-0">
                        <Link
                            :href="route('cajas.historial')"
                            class="inline-flex h-9 items-center justify-center rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 text-xs font-bold text-gray-700 dark:text-gray-200 shadow-3xs hover:bg-gray-50 dark:hover:bg-gray-750 active:scale-98 transition-all duration-200"
                        >
                            <Clock class="w-3.5 h-3.5 mr-1.5 text-gray-400" />
                            Historial
                        </Link>
                        <button
                            @click="showCierreModal = true"
                            style="background-color: #dc2626; color: #ffffff;"
                            class="inline-flex h-9 items-center justify-center rounded-xl px-4 text-xs font-bold shadow-3xs hover:bg-red-700 active:scale-98 transition-all duration-200 cursor-pointer"
                        >
                            <Lock class="w-3.5 h-3.5 mr-1.5" />
                            Cerrar Caja
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- CAJA CERRADA STATE -->
            <div v-if="!caja" class="max-w-md mx-auto mt-12 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-8 shadow-sm flex flex-col items-center text-center">
                <div class="w-16 h-16 bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 rounded-2xl flex items-center justify-center mb-6">
                    <Unlock class="w-8 h-8" />
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Caja Cerrada</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 mb-6 max-w-xs leading-relaxed">
                    No cuenta con ninguna sesión de caja abierta en este momento. Abra la caja para poder registrar movimientos y realizar ventas.
                </p>
                <div class="flex flex-col gap-3 w-full">
                    <button
                        @click="showAperturaModal = true"
                        style="background-color: #2563eb; color: #ffffff;"
                        class="w-full inline-flex h-11 items-center justify-center rounded-xl font-bold text-xs shadow-sm hover:bg-blue-700 active:scale-98 transition-all cursor-pointer"
                    >
                        Iniciar Apertura de Caja
                    </button>
                    <Link
                        :href="route('cajas.historial')"
                        class="w-full inline-flex h-11 items-center justify-center rounded-xl bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 font-bold text-xs text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 active:scale-98 transition-all"
                    >
                        Consultar Historial de Cajas
                    </Link>
                </div>
            </div>

            <!-- CAJA ABIERTA: MAIN POS BOARD -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- LEFT CONTENT AREA: STATS, ACTIONS & TIMELINE (8 COLS) -->
                <div class="lg:col-span-8 space-y-6">
                                        <!-- RESUMEN PRINCIPAL (Cards Grid Grouped) -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-550">Resumen Financiero</h3>
                        </div>

                        <!-- Fila 1: Resumen de Flujo General (3 columnas horizontales compactas) -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Caja Inicial -->
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-3xs flex items-center justify-between gap-3 hover:-translate-y-0.5 transition-all duration-300">
                                <div>
                                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Caja Inicial</span>
                                    <h4 class="text-lg sm:text-xl font-bold font-mono text-gray-900 dark:text-white mt-1">{{ formatCurrency(resumen.caja_inicial) }}</h4>
                                </div>
                                <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/20 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                    <Coins class="w-5 h-5" />
                                </div>
                            </div>

                            <!-- Total Vendido -->
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-3xs flex items-center justify-between gap-3 hover:-translate-y-0.5 transition-all duration-300">
                                <div>
                                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Total Vendido</span>
                                    <h4 class="text-lg sm:text-xl font-bold font-mono text-violet-600 dark:text-violet-400 mt-1">{{ formatCurrency(resumen.total_vendido) }}</h4>
                                </div>
                                <div class="w-10 h-10 rounded-xl bg-violet-50 dark:bg-violet-950/20 text-violet-600 dark:text-violet-400 flex items-center justify-center shrink-0">
                                    <TrendingUp class="w-5 h-5" />
                                </div>
                            </div>

                            <!-- Caja Esperada (Efectivo) -->
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-2xl p-4 shadow-3xs flex items-center justify-between gap-3 hover:-translate-y-0.5 transition-all duration-300">
                                <div>
                                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Caja Esperada</span>
                                    <h4 class="text-lg sm:text-xl font-bold font-mono text-emerald-600 dark:text-emerald-450 mt-1">{{ formatCurrency(resumen.caja_esperada) }}</h4>
                                </div>
                                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-450 flex items-center justify-center shrink-0">
                                    <Calculator class="w-5 h-5" />
                                </div>
                            </div>
                        </div>

                        <!-- Fila 2: Desglose y Operaciones de Caja (6 columnas de micro-tarjetas) -->
                        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                            <!-- Ventas Efectivo -->
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800/60 rounded-xl p-3 shadow-3xs flex flex-col justify-between h-full">
                                <div class="flex items-center gap-1.5 text-gray-400 dark:text-gray-500 mb-2">
                                    <div class="w-7 h-7 rounded-lg bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-450 flex items-center justify-center shrink-0">
                                        <Banknote class="w-4 h-4" />
                                    </div>
                                    <span class="text-[9px] font-extrabold uppercase tracking-wider">Efectivo</span>
                                </div>
                                <div class="font-extrabold font-mono text-gray-800 dark:text-gray-200 text-xs sm:text-sm">{{ formatCurrency(resumen.ventas_efectivo) }}</div>
                            </div>

                            <!-- Yape / Plin -->
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800/60 rounded-xl p-3 shadow-3xs flex flex-col justify-between h-full">
                                <div class="flex items-center gap-1.5 text-gray-400 dark:text-gray-550 mb-2">
                                    <div class="w-7 h-7 rounded-lg bg-purple-50 dark:bg-purple-950/20 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0">
                                        <Smartphone class="w-4 h-4" />
                                    </div>
                                    <span class="text-[9px] font-extrabold uppercase tracking-wider">Yape / Plin</span>
                                </div>
                                <div class="font-extrabold font-mono text-gray-800 dark:text-gray-200 text-xs sm:text-sm">{{ formatCurrency(resumen.ventas_yape_plin) }}</div>
                            </div>

                            <!-- Tarjeta -->
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800/60 rounded-xl p-3 shadow-3xs flex flex-col justify-between h-full">
                                <div class="flex items-center gap-1.5 text-gray-400 dark:text-gray-500 mb-2">
                                    <div class="w-7 h-7 rounded-lg bg-blue-50 dark:bg-blue-950/20 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                        <CreditCard class="w-4 h-4" />
                                    </div>
                                    <span class="text-[9px] font-extrabold uppercase tracking-wider">Tarjeta</span>
                                </div>
                                <div class="font-extrabold font-mono text-gray-800 dark:text-gray-200 text-xs sm:text-sm">{{ formatCurrency(resumen.ventas_tarjeta) }}</div>
                            </div>

                            <!-- Gastos -->
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800/60 rounded-xl p-3 shadow-3xs flex flex-col justify-between h-full">
                                <div class="flex items-center gap-1.5 text-gray-400 dark:text-gray-500 mb-2">
                                    <div class="w-7 h-7 rounded-lg bg-red-50 dark:bg-red-950/20 text-red-650 dark:text-red-400 flex items-center justify-center shrink-0">
                                        <TrendingDown class="w-4 h-4" />
                                    </div>
                                    <span class="text-[9px] font-extrabold uppercase tracking-wider">Gastos</span>
                                </div>
                                <div class="font-extrabold font-mono text-red-600 dark:text-red-400 text-xs sm:text-sm">- {{ formatCurrency(resumen.gastos_registrados) }}</div>
                            </div>

                            <!-- Ingresos Extra -->
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800/60 rounded-xl p-3 shadow-3xs flex flex-col justify-between h-full">
                                <div class="flex items-center gap-1.5 text-gray-400 dark:text-gray-505 mb-2">
                                    <div class="w-7 h-7 rounded-lg bg-teal-50 dark:bg-teal-950/20 text-teal-600 dark:text-teal-400 flex items-center justify-center shrink-0">
                                        <ArrowUpRight class="w-4 h-4" />
                                    </div>
                                    <span class="text-[9px] font-extrabold uppercase tracking-wider">Ingresos</span>
                                </div>
                                <div class="font-extrabold font-mono text-emerald-600 dark:text-emerald-400 text-xs sm:text-sm">+ {{ formatCurrency(resumen.ingresos_adicionales) }}</div>
                            </div>

                            <!-- Retiros -->
                            <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800/60 rounded-xl p-3 shadow-3xs flex flex-col justify-between h-full">
                                <div class="flex items-center gap-1.5 text-gray-400 dark:text-gray-500 mb-2">
                                    <div class="w-7 h-7 rounded-lg bg-amber-50 dark:bg-amber-950/20 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                                        <ArrowDownRight class="w-4 h-4" />
                                    </div>
                                    <span class="text-[9px] font-extrabold uppercase tracking-wider">Retiros</span>
                                </div>
                                <div class="font-extrabold font-mono text-amber-600 dark:text-amber-400 text-xs sm:text-sm">- {{ formatCurrency(resumen.retiros_caja) }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- ACCIONES RÁPIDAS (Modern Compact Buttons) -->
                    <div class="space-y-4">
                        <h3 class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-550">Acciones rápidas</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <!-- Registrar Gasto -->
                            <button
                                @click="showGastoModal = true"
                                class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-red-50 hover:bg-red-100 dark:bg-red-950/20 dark:hover:bg-red-950/30 text-red-700 dark:text-red-300 font-bold text-xs border border-red-100 dark:border-red-900/30 transition-all duration-200 active:scale-98 cursor-pointer shadow-3xs"
                            >
                                <TrendingDown class="w-4.5 h-4.5 shrink-0" />
                                Registrar Gasto
                            </button>

                            <!-- Retirar Dinero -->
                            <button
                                @click="showRetiroModal = true"
                                class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/20 dark:hover:bg-amber-950/30 text-amber-700 dark:text-amber-300 font-bold text-xs border border-amber-100 dark:border-amber-900/30 transition-all duration-200 active:scale-98 cursor-pointer shadow-3xs"
                            >
                                <ArrowDownRight class="w-4.5 h-4.5 shrink-0" />
                                Retirar Dinero
                            </button>

                            <!-- Ingreso Adicional -->
                            <button
                                @click="showIngresoModal = true"
                                class="flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/20 dark:hover:bg-emerald-950/30 text-emerald-700 dark:text-emerald-350 font-bold text-xs border border-emerald-100 dark:border-emerald-900/30 transition-all duration-200 active:scale-98 cursor-pointer shadow-3xs"
                            >
                                <Plus class="w-4.5 h-4.5 shrink-0" />
                                Ingreso Adicional
                            </button>
                        </div>
                    </div>

                    <!-- TIMELINE DE MOVIMIENTOS -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl p-6 shadow-3xs space-y-5">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h3 class="text-base font-bold text-gray-900 dark:text-white">Movimientos Recientes</h3>
                                <p class="text-xs text-gray-400 mt-0.5 font-medium">Historial cronológico de la sesión en curso.</p>
                            </div>
                            <!-- Filtro de tipo -->
                            <select
                                v-model="filterTipo"
                                class="h-9 border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-850 text-gray-750 dark:text-gray-350 focus:border-blue-500 rounded-xl text-xs px-3 transition-colors outline-none cursor-pointer w-full sm:w-44"
                            >
                                <option value="">Todos los Tipos</option>
                                <option value="apertura">Aperturas</option>
                                <option value="venta">Ventas</option>
                                <option value="gasto">Gastos</option>
                                <option value="retiro">Retiros</option>
                                <option value="ingreso">Ingresos</option>
                                <option value="cierre">Cierres</option>
                            </select>
                        </div>

                        <!-- Timeline Wrapper -->
                        <div class="relative pl-6 border-l border-gray-200 dark:border-gray-800 space-y-6 max-h-[50vh] overflow-y-auto pr-2 mt-4">
                            <div 
                                v-for="mov in filteredMovimientos" 
                                :key="mov.id"
                                class="relative group"
                            >
                                <!-- Timeline Point (Colored indicator dot) -->
                                <span 
                                    class="absolute -left-[31px] top-1.5 w-2.5 h-2.5 rounded-full border-2 border-white dark:border-gray-900 shadow-3xs ring-4 ring-white dark:ring-gray-900"
                                    :class="{
                                        'bg-gray-400': mov.tipo === 'apertura' || mov.tipo === 'cierre',
                                        'bg-blue-500': mov.tipo === 'venta',
                                        'bg-emerald-500': mov.tipo === 'ingreso',
                                        'bg-red-500': mov.tipo === 'gasto',
                                        'bg-orange-500': mov.tipo === 'retiro'
                                    }"
                                ></span>

                                <!-- Movement Card Info -->
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-3.5 bg-gray-50/40 dark:bg-gray-950/20 hover:bg-gray-50 dark:hover:bg-gray-950/40 border border-gray-100/50 dark:border-gray-800/40 rounded-xl transition-all duration-200">
                                    <div class="flex items-start gap-3">
                                        <!-- Time Badge -->
                                        <span class="text-xs font-bold font-mono text-gray-400 dark:text-gray-550 bg-gray-100 dark:bg-gray-800 px-2 py-1 rounded-md shrink-0 self-start mt-0.5">
                                            {{ mov.hora }}
                                        </span>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs font-bold text-gray-850 dark:text-white">{{ getTipoLabel(mov.tipo) }}</span>
                                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-mono">| Cajero: {{ mov.usuario?.nombre || 'Cajero' }}</span>
                                            </div>
                                            <p class="text-xs text-gray-550 dark:text-gray-450 mt-1 font-medium">{{ mov.descripcion }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span 
                                            class="text-sm font-extrabold font-mono"
                                            :class="{
                                                'text-blue-600 dark:text-blue-400': mov.tipo === 'venta',
                                                'text-emerald-600 dark:text-emerald-450': mov.tipo === 'ingreso',
                                                'text-red-600 dark:text-red-400': mov.tipo === 'gasto',
                                                'text-orange-600 dark:text-orange-400': mov.tipo === 'retiro',
                                                'text-gray-500': ['apertura', 'cierre'].includes(mov.tipo)
                                            }"
                                        >
                                            {{ ['gasto', 'retiro'].includes(mov.tipo) ? '-' : '+' }}{{ formatCurrency(mov.monto) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Empty State -->
                            <div v-if="filteredMovimientos.length === 0" class="py-10 text-center text-gray-400 dark:text-gray-505 flex flex-col items-center justify-center">
                                <Activity class="w-10 h-10 text-gray-350 dark:text-gray-700 mb-3 animate-pulse" />
                                <span class="text-xs font-semibold">No se registran movimientos para el filtro seleccionado.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RIGHT SIDE PANEL: RESUMEN LATERAL (4 COLS - WHITE DESIGN) -->
                <div class="lg:col-span-4 sticky top-6">
                    <div class="bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-3xl p-6 shadow-3xs space-y-6">
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white tracking-wide flex items-center gap-2">
                                <Sparkles class="w-4 h-4 text-amber-500" />
                                Resumen del Día
                            </h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 font-medium">Indicadores clave de la sesión actual.</p>
                        </div>

                        <!-- Stats list -->
                        <div class="divide-y divide-gray-100 dark:divide-gray-800 space-y-4">
                            <!-- Ventas -->
                            <div class="flex justify-between items-center pt-4 first:pt-0">
                                <div class="flex items-center gap-2">
                                    <ShoppingBag class="w-4 h-4 text-teal-500" />
                                    <span class="text-xs text-gray-600 dark:text-gray-300 font-medium">Ventas Realizadas</span>
                                </div>
                                <span class="text-sm font-bold font-mono text-gray-850 dark:text-white">{{ totalVentasCount }}</span>
                            </div>

                            <!-- Clientes Atendidos -->
                            <div class="flex justify-between items-center pt-4">
                                <div class="flex items-center gap-2">
                                    <User class="w-4 h-4 text-blue-500" />
                                    <span class="text-xs text-gray-600 dark:text-gray-300 font-medium">Clientes Atendidos</span>
                                </div>
                                <span class="text-sm font-bold font-mono text-gray-850 dark:text-white">{{ clientesAtendidos }}</span>
                            </div>

                            <!-- Ticket Promedio -->
                            <div class="flex justify-between items-center pt-4">
                                <div class="flex items-center gap-2">
                                    <Calculator class="w-4 h-4 text-purple-550" />
                                    <span class="text-xs text-gray-600 dark:text-gray-300 font-medium">Ticket Promedio</span>
                                </div>
                                <span class="text-sm font-bold font-mono text-emerald-600 dark:text-emerald-400">{{ formatCurrency(ticketPromedio) }}</span>
                            </div>

                            <!-- Producto Mas Vendido -->
                            <div class="flex justify-between items-center pt-4">
                                <div class="flex items-center gap-2">
                                    <Sparkles class="w-4 h-4 text-indigo-505" />
                                    <span class="text-xs text-gray-600 dark:text-gray-300 font-medium">Producto Más Vendido</span>
                                </div>
                                <span class="text-xs text-gray-400 dark:text-gray-500 font-medium italic">Consultar en PDF</span>
                            </div>
                        </div>

                        <!-- PDF Export Banner Quick -->
                        <div class="pt-4 border-t border-gray-155 dark:border-gray-800">
                            <a
                                :href="route('cajas.exportarReporte', { caja: caja.id })"
                                target="_blank"
                                class="w-full flex h-10 items-center justify-center rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-gray-800 dark:hover:bg-gray-700/80 text-xs font-bold text-gray-700 dark:text-gray-255 border border-gray-200 dark:border-gray-700 active:scale-98 transition-all gap-1.5 cursor-pointer shadow-3xs"
                            >
                                <FileText class="w-4 h-4" />
                                Descargar Reporte PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL APERTURA DE CAJA -->
        <Modal :show="showAperturaModal" @close="showAperturaModal = false" max-width="md">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-850 mb-5">
                    <div class="flex items-center gap-2">
                        <Unlock class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Apertura de Caja</h3>
                    </div>
                    <button type="button" @click="showAperturaModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitApertura" class="space-y-4">
                    <div>
                        <InputLabel for="monto_inicial" value="Monto Inicial en Efectivo (S/.)" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                        <div class="relative mt-2 rounded-xl shadow-3xs">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-xs font-bold text-gray-400">S/</span>
                            </div>
                            <input
                                id="monto_inicial"
                                type="number"
                                step="0.01"
                                min="0"
                                v-model="aperturaForm.monto_inicial"
                                class="block w-full pl-8 pr-3 py-3 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/25 focus:border-blue-500 bg-gray-50/50 dark:bg-gray-850 hover:bg-white focus:bg-white transition-all font-mono font-bold"
                                required
                            />
                        </div>
                        <InputError class="mt-1.5" :message="aperturaForm.errors.monto_inicial" />
                    </div>

                    <div>
                        <InputLabel for="observacion" value="Observación (Opcional)" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                        <textarea
                            id="observacion"
                            v-model="aperturaForm.observacion"
                            class="mt-2 block w-full border border-gray-200 dark:border-gray-700 rounded-xl shadow-3xs text-xs p-3 h-20 focus:outline-none focus:ring-2 focus:ring-blue-500/25 focus:border-blue-500 bg-gray-5/50 dark:bg-gray-850 hover:bg-white focus:bg-white resize-none transition-all outline-none"
                            placeholder="Ej. Sencillado de monedas o billetes..."
                        ></textarea>
                    </div>

                    <div class="pt-2 flex gap-3">
                        <SecondaryButton @click="showAperturaModal = false" class="flex-1 justify-center py-3 rounded-xl text-xs h-11">Cancelar</SecondaryButton>
                        <button
                            type="submit"
                            :disabled="aperturaForm.processing"
                            style="background-color: #2563eb; color: #ffffff;"
                            class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center cursor-pointer h-11 shadow-3xs"
                        >
                            Abrir Caja
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL GASTO -->
        <Modal :show="showGastoModal" @close="showGastoModal = false" max-width="md">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-850 mb-5">
                    <div class="flex items-center gap-2">
                        <TrendingDown class="w-5 h-5 text-red-500" />
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Registrar Gasto</h3>
                    </div>
                    <button type="button" @click="showGastoModal = false" class="text-gray-400 hover:text-gray-650 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitGasto" class="space-y-4">
                    <div>
                        <InputLabel for="gasto_categoria" value="Categoría" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                        <select
                            id="gasto_categoria"
                            v-model="gastoForm.categoria"
                            class="mt-2 block w-full border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-850 text-gray-750 dark:text-gray-300 focus:border-blue-500 rounded-xl text-xs h-10 px-3 outline-none cursor-pointer focus:ring-2 focus:ring-blue-500/25"
                        >
                            <option value="Agua">Agua</option>
                            <option value="Luz">Luz</option>
                            <option value="Internet">Internet</option>
                            <option value="Movilidad">Movilidad</option>
                            <option value="Proveedor">Proveedor</option>
                            <option value="Otros">Otros</option>
                        </select>
                        <InputError class="mt-1.5" :message="gastoForm.errors.categoria" />
                    </div>

                    <div>
                        <InputLabel for="gasto_monto" value="Monto del Gasto (S/.)" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                        <div class="relative mt-2 rounded-xl shadow-3xs">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-xs font-bold text-gray-400">S/</span>
                            </div>
                            <input
                                id="gasto_monto"
                                type="number"
                                step="0.01"
                                min="0.01"
                                v-model="gastoForm.monto"
                                class="block w-full pl-8 pr-3 py-3 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/25 focus:border-blue-500 bg-gray-50/50 dark:bg-gray-850 hover:bg-white focus:bg-white transition-all font-mono font-bold"
                                required
                            />
                        </div>
                        <InputError class="mt-1.5" :message="gastoForm.errors.monto" />
                    </div>

                    <div>
                        <InputLabel for="gasto_descripcion" value="Descripción / Motivo" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                        <textarea
                            id="gasto_descripcion"
                            v-model="gastoForm.descripcion"
                            class="mt-2 block w-full border border-gray-200 dark:border-gray-700 rounded-xl shadow-3xs text-xs p-3 h-20 focus:outline-none focus:ring-2 focus:ring-blue-500/25 focus:border-blue-500 bg-gray-5/50 dark:bg-gray-850 hover:bg-white focus:bg-white resize-none transition-all outline-none"
                            placeholder="Detalle el motivo o adjunte recibo..."
                        ></textarea>
                    </div>

                    <div class="pt-2 flex gap-3">
                        <SecondaryButton @click="showGastoModal = false" class="flex-1 justify-center py-3 rounded-xl text-xs h-11">Cancelar</SecondaryButton>
                        <button
                            type="submit"
                            :disabled="gastoForm.processing"
                            style="background-color: #2563eb; color: #ffffff;"
                            class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center cursor-pointer h-11 shadow-3xs"
                        >
                            Guardar Gasto
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL RETIRO -->
        <Modal :show="showRetiroModal" @close="showRetiroModal = false" max-width="md">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-855 mb-5">
                    <div class="flex items-center gap-2">
                        <ArrowDownRight class="w-5 h-5 text-amber-500" />
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Retirar Dinero</h3>
                    </div>
                    <button type="button" @click="showRetiroModal = false" class="text-gray-400 hover:text-gray-650 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitRetiro" class="space-y-4">
                    <div>
                        <InputLabel for="retiro_monto" value="Monto a Retirar (S/.)" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                        <div class="relative mt-2 rounded-xl shadow-3xs">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-xs font-bold text-gray-400">S/</span>
                            </div>
                            <input
                                id="retiro_monto"
                                type="number"
                                step="0.01"
                                min="0.01"
                                v-model="retiroForm.monto"
                                class="block w-full pl-8 pr-3 py-3 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/25 focus:border-blue-500 bg-gray-50/50 dark:bg-gray-850 hover:bg-white focus:bg-white transition-all font-mono font-bold"
                                required
                            />
                        </div>
                        <InputError class="mt-1.5" :message="retiroForm.errors.monto" />
                    </div>

                    <div>
                        <InputLabel for="retiro_motivo" value="Motivo del Retiro" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                        <textarea
                            id="retiro_motivo"
                            v-model="retiroForm.motivo"
                            class="mt-2 block w-full border border-gray-200 dark:border-gray-700 rounded-xl shadow-3xs text-xs p-3 h-20 focus:outline-none focus:ring-2 focus:ring-blue-500/25 focus:border-blue-500 bg-gray-5/50 dark:bg-gray-850 hover:bg-white focus:bg-white resize-none transition-all outline-none"
                            placeholder="Ej. Retiro por exceso de efectivo en caja..."
                            required
                        ></textarea>
                        <InputError class="mt-1.5" :message="retiroForm.errors.motivo" />
                    </div>

                    <div class="pt-2 flex gap-3">
                        <SecondaryButton @click="showRetiroModal = false" class="flex-1 justify-center py-3 rounded-xl text-xs h-11">Cancelar</SecondaryButton>
                        <button
                            type="submit"
                            :disabled="retiroForm.processing"
                            style="background-color: #2563eb; color: #ffffff;"
                            class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center cursor-pointer h-11 shadow-3xs"
                        >
                            Confirmar Retiro
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL INGRESO ADICIONAL -->
        <Modal :show="showIngresoModal" @close="showIngresoModal = false" max-width="md">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-855 mb-5">
                    <div class="flex items-center gap-2">
                        <ArrowUpRight class="w-5 h-5 text-emerald-555" />
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Ingreso Adicional</h3>
                    </div>
                    <button type="button" @click="showIngresoModal = false" class="text-gray-400 hover:text-gray-650 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitIngreso" class="space-y-4">
                    <div>
                        <InputLabel for="ingreso_monto" value="Monto de Ingreso (S/.)" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                        <div class="relative mt-2 rounded-xl shadow-3xs">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-xs font-bold text-gray-400">S/</span>
                            </div>
                            <input
                                id="ingreso_monto"
                                type="number"
                                step="0.01"
                                min="0.01"
                                v-model="ingresoForm.monto"
                                class="block w-full pl-8 pr-3 py-3 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/25 focus:border-blue-500 bg-gray-50/50 dark:bg-gray-850 hover:bg-white focus:bg-white transition-all font-mono font-bold"
                                required
                            />
                        </div>
                        <InputError class="mt-1.5" :message="ingresoForm.errors.monto" />
                    </div>

                    <div>
                        <InputLabel for="ingreso_descripcion" value="Descripción / Procedencia" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                        <textarea
                            id="ingreso_descripcion"
                            v-model="ingresoForm.descripcion"
                            class="mt-2 block w-full border border-gray-200 dark:border-gray-700 rounded-xl shadow-3xs text-xs p-3 h-20 focus:outline-none focus:ring-2 focus:ring-blue-500/25 focus:border-blue-500 bg-gray-5/50 dark:bg-gray-850 hover:bg-white focus:bg-white resize-none transition-all outline-none"
                            placeholder="Ej. Ingreso de sencillo adicional para caja..."
                            required
                        ></textarea>
                        <InputError class="mt-1.5" :message="ingresoForm.errors.descripcion" />
                    </div>

                    <div class="pt-2 flex gap-3">
                        <SecondaryButton @click="showIngresoModal = false" class="flex-1 justify-center py-3 rounded-xl text-xs h-11">Cancelar</SecondaryButton>
                        <button
                            type="submit"
                            :disabled="ingresoForm.processing"
                            style="background-color: #2563eb; color: #ffffff;"
                            class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center cursor-pointer h-11 shadow-3xs"
                        >
                            Guardar Ingreso
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- MODAL CIERRE DE CAJA -->
        <Modal :show="showCierreModal" @close="showCierreModal = false" max-width="md">
            <div class="p-6 text-gray-900 dark:text-gray-100" v-if="caja">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-850 mb-5">
                    <div class="flex items-center gap-2">
                        <Lock class="w-5 h-5 text-red-500" />
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Arqueo y Cierre de Caja</h3>
                    </div>
                    <button type="button" @click="showCierreModal = false" class="text-gray-400 hover:text-gray-650 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Consolidated figures card -->
                <div class="space-y-3 mb-5 p-4 bg-gray-50 dark:bg-gray-950 border border-gray-150 dark:border-gray-800/80 rounded-2xl text-xs font-mono">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Caja Inicial:</span>
                        <span class="text-gray-800 dark:text-gray-205 font-bold">{{ formatCurrency(resumen.caja_inicial) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Ventas en Efectivo:</span>
                        <span class="text-gray-800 dark:text-gray-205 font-bold">{{ formatCurrency(resumen.ventas_efectivo) }}</span>
                    </div>
                    <div class="flex justify-between border-b border-dashed border-gray-200 dark:border-gray-850 pb-2">
                        <span class="text-gray-400">Ingresos adicionales:</span>
                        <span class="text-emerald-600 dark:text-emerald-400 font-bold">+ {{ formatCurrency(resumen.ingresos_adicionales) }}</span>
                    </div>
                    <div class="flex justify-between border-b border-dashed border-gray-200 dark:border-gray-850 pt-1 pb-2">
                        <span class="text-gray-400">Gastos + Retiros:</span>
                        <span class="text-red-550 dark:text-red-400 font-bold">- {{ formatCurrency(resumen.gastos_registrados + resumen.retiros_caja) }}</span>
                    </div>
                    <div class="flex justify-between pt-2 font-bold text-gray-900 dark:text-white text-sm">
                        <span>EFECTIVO ESPERADO:</span>
                        <span class="text-emerald-600 dark:text-emerald-400">{{ formatCurrency(resumen.caja_esperada) }}</span>
                    </div>
                </div>

                <form @submit.prevent="submitCierre" class="space-y-4">
                    <div>
                        <InputLabel for="cierre_monto_final" value="Efectivo Físico Contado (S/.)" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                        <div class="relative mt-2 rounded-xl shadow-3xs">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-xs font-bold text-gray-400">S/</span>
                            </div>
                            <input
                                id="cierre_monto_final"
                                type="number"
                                step="0.01"
                                min="0"
                                v-model="cierreForm.monto_final"
                                @input="handleMontoFinalInput"
                                class="block w-full pl-8 pr-3 py-3 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/25 focus:border-blue-500 bg-gray-50/50 dark:bg-gray-850 hover:bg-white focus:bg-white transition-all font-mono font-bold text-lg"
                                placeholder="0.00"
                                required
                            />
                        </div>
                        <InputError class="mt-1.5" :message="cierreForm.errors.monto_final" />
                    </div>

                    <!-- Alertas de Diferencia (Arqueo) -->
                    <div v-if="cierreForm.monto_final !== '' && cierreForm.monto_final !== null">
                        <!-- Diferencia = 0 (Todo Correcto) -->
                        <div v-if="diferenciaArqueo === 0" class="p-3.5 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-800 dark:text-emerald-350 border border-emerald-100 dark:border-emerald-900/30 rounded-xl text-xs flex gap-2 items-center font-bold">
                            <CheckCircle2 class="w-4.5 h-4.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                            <span>Arqueo correcto. Caja cuadrada perfectamente.</span>
                        </div>

                        <!-- Sobrante (Diferencia > 0) -->
                        <div v-else-if="diferenciaArqueo > 0" class="p-3.5 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-800 dark:text-emerald-350 border border-emerald-100 dark:border-emerald-900/30 rounded-xl text-xs space-y-1">
                            <div class="flex gap-2 items-center font-bold">
                                <CheckCircle2 class="w-4.5 h-4.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                <span>Sobrante detectado: {{ formatCurrency(diferenciaArqueo) }}</span>
                            </div>
                            <p class="text-[11px] opacity-90">El monto físico contado es mayor al esperado en el sistema (sobran billetes o monedas).</p>
                        </div>

                        <!-- Faltante (Diferencia < 0) -->
                        <div v-else-if="diferenciaArqueo < 0" class="p-3.5 bg-red-50 dark:bg-red-950/20 text-red-800 dark:text-red-350 border border-red-100 dark:border-red-900/30 rounded-xl text-xs space-y-2.5">
                            <div class="flex gap-2 items-center font-bold">
                                <AlertTriangle class="w-4.5 h-4.5 text-red-650 dark:text-red-400 shrink-0" />
                                <span>Faltante detectado: {{ formatCurrency(diferenciaArqueo) }}</span>
                            </div>
                            <p class="text-[11px] opacity-90">El monto físico es menor al esperado (falta dinero). Indique obligatoriamente el motivo.</p>
                            <div>
                                <InputLabel for="motivo_diferencia" value="Motivo del Faltante (Obligatorio)" class="text-[10px] font-extrabold text-red-900 dark:text-red-300 block mb-1.5 uppercase" />
                                <input
                                    id="motivo_diferencia"
                                    type="text"
                                    v-model="cierreForm.motivo_diferencia"
                                    class="w-full border border-red-200 dark:border-red-900/40 rounded-xl text-xs p-2.5 focus:outline-none bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-200"
                                    placeholder="Ej. error en vuelto, gasto no registrado, etc."
                                    required
                                />
                                <InputError class="mt-1.5" :message="cierreForm.errors.motivo_diferencia" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="cierre_observacion" value="Observación de Cierre" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                        <textarea
                            id="cierre_observacion"
                            v-model="cierreForm.observacion"
                            class="mt-2 block w-full border border-gray-200 dark:border-gray-700 rounded-xl shadow-3xs text-xs p-3 h-20 focus:outline-none focus:ring-2 focus:ring-blue-500/25 focus:border-blue-500 bg-gray-5/50 dark:bg-gray-850 hover:bg-white focus:bg-white resize-none transition-all outline-none"
                            placeholder="Notas o comentarios sobre el cierre de caja..."
                        ></textarea>
                    </div>

                    <!-- Checkbox de Confirmación Física -->
                    <div class="flex items-start gap-2.5 p-3.5 bg-gray-50/50 dark:bg-gray-950/30 border border-gray-150 dark:border-gray-800 rounded-xl mt-4">
                        <input
                            id="confirmar_cierre"
                            type="checkbox"
                            v-model="cierreForm.confirmado"
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 focus:ring-2 mt-0.5"
                        />
                        <label for="confirmar_cierre" class="text-xs text-gray-600 dark:text-gray-400 font-medium select-none cursor-pointer">
                            Confirmo que he contado el efectivo físico detallado arriba y acepto el arqueo actual de caja.
                        </label>
                    </div>

                    <div class="pt-2 flex gap-3">
                        <SecondaryButton @click="showCierreModal = false" class="flex-1 justify-center py-3 rounded-xl text-xs h-11">Cancelar</SecondaryButton>
                        <button
                            type="submit"
                            :disabled="cierreForm.processing || !cierreForm.confirmado"
                            style="background-color: #dc2626; color: #ffffff;"
                            class="flex-1 py-3 bg-red-650 hover:bg-red-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center cursor-pointer h-11 shadow-3xs"
                        >
                            Cerrar Sesión de Caja
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
