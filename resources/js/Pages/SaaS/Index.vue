<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, useForm, usePage, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import {
    LayoutDashboard,
    Building2,
    Users,
    CreditCard,
    Receipt,
    Activity,
    FileText,
    Database,
    Settings,
    Cpu,
    HardDrive,
    Server,
    TrendingUp,
    AlertTriangle,
    CheckCircle2,
    Play,
    Plus,
    Edit2,
    Lock,
    Search,
    Shield,
    Terminal,
    ArrowUpRight,
    RefreshCw
} from 'lucide-vue-next';

const props = defineProps({
    planes: {
        type: Array,
        required: true,
    },
    suscripciones: {
        type: Array,
        required: true,
    },
    empresas: {
        type: Array,
        required: true,
    },
    usuarios: {
        type: Array,
        required: true,
    },
    stats: {
        type: Object,
        required: true,
    }
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

// Reactivity synced with Inertia Query Parameter
const activeTab = ref('dashboard');
watch(
    () => page.url,
    (newUrl) => {
        try {
            const urlObj = new URL(newUrl, window.location.origin);
            const tab = urlObj.searchParams.get('tab');
            if (tab) {
                activeTab.value = tab;
            } else {
                activeTab.value = 'dashboard';
            }
        } catch (e) {
            activeTab.value = 'dashboard';
        }
    },
    { immediate: true }
);

// Modals state
const showPlanModal = ref(false);
const editPlanMode = ref(false);
const showSuscripcionModal = ref(false);
const editSuscripcionMode = ref(false);
const showBackupModal = ref(false);
const backupAction = ref(''); // 'backup' or 'restore'
const backupConfirmationText = ref('');

// Forms
const planForm = useForm({
    id: null,
    nombre: '',
    descripcion: '',
    precio: 0,
    limite_usuarios: 5,
    limite_productos: 50,
    limite_sucursales: 1,
    caracteristicas: [],
});

const suscripcionForm = useForm({
    id: null,
    empresa_id: '',
    plan_id: '',
    estado: 'trialing',
    fecha_inicio: new Date().toISOString().substr(0, 10),
    fecha_fin: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().substr(0, 10),
    metodo_pago: 'manual',
});

const impersonateForm = useForm({});
const stopImpersonateForm = useForm({});

// Impersonation calls
const startImpersonation = (empresaId) => {
    impersonateForm.post(route('saas.impersonate', empresaId));
};

// Temp Plan Feature
const tempFeature = ref('');
const addFeature = () => {
    if (tempFeature.value.trim()) {
        planForm.caracteristicas.push(tempFeature.value.trim());
        tempFeature.value = '';
    }
};
const removeFeature = (idx) => {
    planForm.caracteristicas.splice(idx, 1);
};

// Plan handlers
const openCreatePlanModal = () => {
    planForm.clearErrors();
    planForm.reset({
        id: null,
        nombre: '',
        descripcion: '',
        precio: 0,
        limite_usuarios: 5,
        limite_productos: 50,
        limite_sucursales: 1,
        caracteristicas: [],
    });
    editPlanMode.value = false;
    showPlanModal.value = true;
};

const openEditPlanModal = (plan) => {
    planForm.clearErrors();
    planForm.reset({
        id: plan.id,
        nombre: plan.nombre,
        descripcion: plan.descripcion || '',
        precio: Number(plan.precio),
        limite_usuarios: plan.limite_usuarios,
        limite_productos: plan.limite_productos,
        limite_sucursales: plan.limite_sucursales,
        caracteristicas: plan.caracteristicas || [],
    });
    editPlanMode.value = true;
    showPlanModal.value = true;
};

const submitPlan = () => {
    if (editPlanMode.value) {
        planForm.put(route('saas.updatePlan', planForm.id), {
            onSuccess: () => showPlanModal.value = false,
        });
    } else {
        planForm.post(route('saas.storePlan'), {
            onSuccess: () => showPlanModal.value = false,
        });
    }
};

// Suscripcion handlers
const openCreateSuscripcionModal = () => {
    suscripcionForm.clearErrors();
    suscripcionForm.reset({
        id: null,
        empresa_id: '',
        plan_id: '',
        estado: 'trialing',
        fecha_inicio: new Date().toISOString().substr(0, 10),
        fecha_fin: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().substr(0, 10),
        metodo_pago: 'manual',
    });
    editSuscripcionMode.value = false;
    showSuscripcionModal.value = true;
};

const openEditSuscripcionModal = (suscripcion) => {
    suscripcionForm.clearErrors();
    suscripcionForm.reset({
        id: suscripcion.id,
        empresa_id: suscripcion.empresa_id,
        plan_id: suscripcion.plan_id,
        estado: suscripcion.estado,
        fecha_inicio: new Date(suscripcion.fecha_inicio).toISOString().substr(0, 10),
        fecha_fin: new Date(suscripcion.fecha_fin).toISOString().substr(0, 10),
        metodo_pago: suscripcion.metodo_pago,
    });
    editSuscripcionMode.value = true;
    showSuscripcionModal.value = true;
};

const submitSuscripcion = () => {
    if (editSuscripcionMode.value) {
        suscripcionForm.put(route('saas.updateSuscripcion', suscripcionForm.id), {
            onSuccess: () => showSuscripcionModal.value = false,
        });
    } else {
        suscripcionForm.post(route('saas.storeSuscripcion'), {
            onSuccess: () => showSuscripcionModal.value = false,
        });
    }
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(val);
};

// Mock Server Stats
const serverCpu = ref(24);
const serverRam = ref(58);
const serverDisk = ref(42);

// Simulated logs
const logs = ref([
    { time: '10:14:22', level: 'info', service: 'AUTH', msg: 'Intento de inicio de sesión exitoso - Bodega Don Pepe' },
    { time: '10:13:05', level: 'warning', service: 'API', msg: 'Límite de tokens de Groq al 85% de capacidad para bodega_id: 3' },
    { time: '10:11:45', level: 'info', service: 'DB', msg: 'Respaldo automático completado con éxito (inventex_backup_today.sql)' },
    { time: '10:09:12', level: 'error', service: 'SUSCRIPCIONES', msg: 'Error de cobro recurrente en Pasarela Culqi (ID suscripción: #8)' },
    { time: '10:05:00', level: 'info', service: 'SYS', msg: 'Limpieza de caché de vistas Laravel compiladas finalizada' }
]);

const logFilter = ref('all');
const searchLog = ref('');
const filteredLogs = computed(() => {
    return logs.value.filter(l => {
        const matchesLevel = logFilter.value === 'all' || l.level === logFilter.value;
        const matchesSearch = !searchLog.value || 
            l.msg.toLowerCase().includes(searchLog.value.toLowerCase()) || 
            l.service.toLowerCase().includes(searchLog.value.toLowerCase());
        return matchesLevel && matchesSearch;
    });
});

const triggerBackupAction = (action) => {
    backupAction.value = action;
    backupConfirmationText.value = '';
    showBackupModal.value = true;
};

const executeBackup = () => {
    if (backupAction.value === 'backup' && backupConfirmationText.value === 'RESPALDO') {
        showBackupModal.value = false;
        // Simular éxito
        alert('Respaldo de base de datos generado con éxito. Descarga iniciada automáticamente.');
    } else if (backupAction.value === 'restore' && backupConfirmationText.value === 'RESTAURAR') {
        showBackupModal.value = false;
        alert('Base de datos restaurada al punto de control anterior.');
    }
};

const searchCompany = ref('');
const filteredEmpresas = computed(() => {
    if (!searchCompany.value) return props.empresas;
    return props.empresas.filter(e => 
        (e.nombre && e.nombre.toLowerCase().includes(searchCompany.value.toLowerCase())) || 
        (e.ruc && e.ruc.includes(searchCompany.value))
    );
});
</script>

<template>
    <Head title="Consola SaaS - INVENTEX" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-200">
                Consola SaaS • Administrador Global
            </h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                
                <!-- Active Tab Headers (Synced horizontal tabs for console navigation) -->
                <div class="flex flex-wrap border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-xl p-1.5 shadow-2xs gap-1">
                    <button
                        v-for="t in [
                            { id: 'dashboard', label: 'Dashboard Global' },
                            { id: 'empresas', label: 'Empresas' },
                            { id: 'usuarios', label: 'Usuarios Globales' },
                            { id: 'planes', label: 'Planes y Licencias' },
                            { id: 'facturacion', label: 'Facturación' },
                            { id: 'monitoreo', label: 'Monitoreo' },
                            { id: 'logs', label: 'Logs' },
                            { id: 'backups', label: 'Backups' },
                            { id: 'configuracion', label: 'Configuración Global' }
                        ]"
                        :key="t.id"
                        @click="activeTab = t.id"
                        class="px-3.5 py-2 text-xs font-bold rounded-lg transition-all cursor-pointer"
                        :class="activeTab === t.id
                            ? 'bg-indigo-600 text-white shadow'
                            : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700'"
                    >
                        {{ t.label }}
                    </button>
                </div>

                <!-- Toast Notifications -->
                <div v-if="flashSuccess" class="rounded-xl bg-green-50 p-4 text-xs font-bold text-green-800 dark:bg-gray-800 dark:text-green-400 border border-green-200 dark:border-green-800 flex items-center justify-between shadow-2xs">
                    <span>{{ flashSuccess }}</span>
                </div>

                <!-- ========================================== -->
                <!-- 1. DASHBOARD GLOBAL TAB                    -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'dashboard'" class="space-y-6">
                    
                    <!-- KPI Metric Cards Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        
                        <!-- Card 1 -->
                        <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-5 rounded-2xl shadow-2xs flex items-center justify-between">
                            <div>
                                <p class="text-[10px] uppercase font-bold tracking-wider text-gray-400">Total Empresas</p>
                                <h3 class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ stats.total_empresas }}</h3>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/30 flex items-center justify-center text-indigo-600">
                                <Building2 class="w-5 h-5" />
                            </div>
                        </div>

                        <!-- Card 2 -->
                        <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-5 rounded-2xl shadow-2xs flex items-center justify-between">
                            <div>
                                <p class="text-[10px] uppercase font-bold tracking-wider text-gray-400">Licencias Activas</p>
                                <h3 class="text-2xl font-extrabold text-green-600 mt-1">{{ stats.licencias_activas }}</h3>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-green-50 dark:bg-green-950/30 flex items-center justify-center text-green-600">
                                <CheckCircle2 class="w-5 h-5" />
                            </div>
                        </div>

                        <!-- Card 3 -->
                        <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-5 rounded-2xl shadow-2xs flex items-center justify-between">
                            <div>
                                <p class="text-[10px] uppercase font-bold tracking-wider text-gray-400">Usuarios Activos</p>
                                <h3 class="text-2xl font-extrabold text-gray-900 dark:text-white mt-1">{{ stats.total_usuarios }}</h3>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/30 flex items-center justify-center text-blue-600">
                                <Users class="w-5 h-5" />
                            </div>
                        </div>

                        <!-- Card 4 -->
                        <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-5 rounded-2xl shadow-2xs flex items-center justify-between">
                            <div>
                                <p class="text-[10px] uppercase font-bold tracking-wider text-gray-400">MRR Suscripciones</p>
                                <h3 class="text-2xl font-extrabold text-indigo-600 mt-1 font-mono">{{ formatCurrency(stats.ingresos_mensuales) }}</h3>
                            </div>
                            <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/30 flex items-center justify-center text-indigo-600">
                                <Receipt class="w-5 h-5" />
                            </div>
                        </div>

                    </div>

                    <!-- Server Resource & Health Gauges -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        
                        <!-- CPU Usage Card -->
                        <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-6 rounded-2xl shadow-2xs space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                    <Cpu class="w-4 h-4 text-indigo-600" />
                                    Uso de CPU Servidor
                                </span>
                                <span class="text-xs font-mono font-bold text-gray-500">{{ serverCpu }}%</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden">
                                <div class="bg-indigo-600 h-full rounded-full transition-all duration-500" :style="`width: ${serverCpu}%`"></div>
                            </div>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500">Intel Xeon E-2388G Octa-Core @ 3.20GHz</p>
                        </div>

                        <!-- RAM Usage Card -->
                        <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-6 rounded-2xl shadow-2xs space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                    <Server class="w-4 h-4 text-emerald-600" />
                                    Consumo Memoria RAM
                                </span>
                                <span class="text-xs font-mono font-bold text-gray-500">{{ serverRam }}%</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden">
                                <div class="bg-emerald-600 h-full rounded-full transition-all duration-500" :style="`width: ${serverRam}%`"></div>
                            </div>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500">Uso: 9.28 GB de 16.00 GB DDR4 ECC</p>
                        </div>

                        <!-- Disk Usage Card -->
                        <div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 p-6 rounded-2xl shadow-2xs space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                    <HardDrive class="w-4 h-4 text-amber-600" />
                                    Espacio en Disco SSD NVMe
                                </span>
                                <span class="text-xs font-mono font-bold text-gray-500">{{ serverDisk }}%</span>
                            </div>
                            <div class="w-full bg-gray-100 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden">
                                <div class="bg-amber-600 h-full rounded-full transition-all duration-500" :style="`width: ${serverDisk}%`"></div>
                            </div>
                            <p class="text-[10px] text-gray-400 dark:text-gray-500">Almacenamiento: 105 GB de 250 GB Libres</p>
                        </div>

                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 2. EMPRESAS TAB (Tenants list & Access)    -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'empresas'" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 space-y-4 shadow-2xs">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase">Empresas en la Plataforma</h3>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Busque bodegas y acceda en Modo Super Admin a realizar soporte.</p>
                        </div>
                        <div class="relative w-full md:w-72">
                            <Search class="w-4 h-4 text-gray-400 absolute left-3 top-2.5" />
                            <input
                                v-model="searchCompany"
                                type="text"
                                placeholder="Buscar por Nombre o RUC..."
                                class="w-full pl-9 pr-4 py-1.5 text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-700 dark:text-gray-300"
                            />
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-150 dark:border-gray-700">
                        <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold uppercase text-[10px]">
                                <tr>
                                    <th scope="col" class="px-4 py-3.5">Logo</th>
                                    <th scope="col" class="px-4 py-3.5">Nombre Comercial</th>
                                    <th scope="col" class="px-4 py-3.5">RUC</th>
                                    <th scope="col" class="px-4 py-3.5 text-center">Plan Contratado</th>
                                    <th scope="col" class="px-4 py-3.5 text-center">Estado</th>
                                    <th scope="col" class="px-4 py-3.5 text-center">Usuarios</th>
                                    <th scope="col" class="px-4 py-3.5">Fecha Registro</th>
                                    <th scope="col" class="px-4 py-3.5">Último Acceso</th>
                                    <th scope="col" class="px-4 py-3.5 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="emp in filteredEmpresas" :key="emp.id" class="bg-white dark:bg-gray-800 hover:bg-gray-50/50 dark:hover:bg-gray-700/30 transition-colors">
                                    <td class="px-4 py-4">
                                        <div class="w-10 h-10 rounded-xl bg-gray-50 dark:bg-gray-900 border border-gray-150 dark:border-gray-700 flex items-center justify-center text-xs shrink-0 overflow-hidden font-black text-indigo-600 shadow-2xs">
                                            <img v-if="emp.logo_url" :src="emp.logo_url" class="w-full h-full object-contain p-1" alt="Logo" />
                                            <span v-else>{{ emp.nombre ? emp.nombre.substring(0, 2).toUpperCase() : 'EM' }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="font-extrabold text-gray-900 dark:text-white text-xs">{{ emp.nombre }}</div>
                                        <div class="text-[10px] text-gray-400 truncate max-w-xs">{{ emp.direccion || 'Sin dirección registrada' }}</div>
                                    </td>
                                    <td class="px-4 py-4 font-mono font-bold text-gray-700 dark:text-gray-300">{{ emp.ruc || 'S/N' }}</td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 border border-indigo-200">
                                            {{ emp.plan_nombre || 'Plan Estándar' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span 
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                                            :class="emp.estado_suscripcion === 'active' || emp.estado_suscripcion === 'activo' || emp.estado_suscripcion === 'trialing'
                                                ? 'bg-green-50 text-green-700 border-green-200 dark:bg-green-950/40 dark:text-green-300'
                                                : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300'"
                                        >
                                            <span class="w-1.5 h-1.5 rounded-full" :class="emp.estado_suscripcion === 'active' || emp.estado_suscripcion === 'activo' || emp.estado_suscripcion === 'trialing' ? 'bg-green-500' : 'bg-amber-500'"></span>
                                            {{ (emp.estado_suscripcion === 'active' || emp.estado_suscripcion === 'activo') ? 'Activo' : (emp.estado_suscripcion === 'trialing' ? 'Prueba' : 'Suspendido') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 text-center font-mono font-semibold text-gray-600 dark:text-gray-300">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-gray-100 dark:bg-gray-700 rounded-lg text-[11px] font-bold">
                                            <Users class="w-3.5 h-3.5 text-gray-400" />
                                            {{ emp.usuarios_count || 1 }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 font-mono text-[11px] text-gray-500">
                                        {{ new Date(emp.created_at).toLocaleDateString('es-PE') }}
                                    </td>
                                    <td class="px-4 py-4 font-mono text-[11px] text-gray-500">
                                        {{ emp.ultimo_acceso ? new Date(emp.ultimo_acceso).toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Reciente' }}
                                    </td>
                                    <td class="px-4 py-4 text-right">
                                        <button
                                            @click="startImpersonation(emp.id)"
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white rounded-xl text-xs font-bold shadow-xs transition-all cursor-pointer"
                                            title="Ingresar como esta empresa en Modo Super Admin"
                                        >
                                            <Building2 class="w-3.5 h-3.5" />
                                            <span>Administrar Empresa</span>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredEmpresas.length === 0">
                                    <td colspan="9" class="px-5 py-12 text-center text-gray-400">
                                        No se encontraron empresas con esos criterios.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 3. USUARIOS TAB (Global User Registry)     -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'usuarios'" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 space-y-4 shadow-2xs">
                    <div>
                        <h3 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase">Registro Global de Usuarios</h3>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1 font-medium">Lista de todas las cuentas creadas en todas las bodegas de la plataforma.</p>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-150 dark:border-gray-700">
                        <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold uppercase text-[10px]">
                                <tr>
                                    <th scope="col" class="px-5 py-3.5">Nombre Completo</th>
                                    <th scope="col" class="px-5 py-3.5">Correo Electrónico</th>
                                    <th scope="col" class="px-5 py-3.5">Empresa Asignada</th>
                                    <th scope="col" class="px-5 py-3.5 text-center">Rol del Sistema</th>
                                    <th scope="col" class="px-5 py-3.5 text-center">Estado</th>
                                    <th scope="col" class="px-5 py-3.5">Fecha Creación</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr v-for="u in usuarios" :key="u.id" class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 font-bold text-gray-900 dark:text-white">{{ u.nombre }}</td>
                                    <td class="px-5 py-4 font-mono">{{ u.correo }}</td>
                                    <td class="px-5 py-4 font-semibold text-gray-700 dark:text-gray-300">
                                        {{ u.empresa ? u.empresa.nombre : 'Plataforma Inventex' }}
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <span class="px-2 py-0.5 rounded-lg text-[10px] font-mono font-bold capitalize bg-gray-100 text-gray-700 border border-gray-200">
                                            {{ u.rol }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-700 border border-green-200">
                                            Activo
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 font-mono text-gray-400">{{ new Date(u.created_at).toLocaleDateString('es-PE') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 4. PLANES TAB (Subscription Tier Config)   -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'planes'" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    <!-- Form / list left -->
                    <div class="col-span-2 bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 space-y-4 shadow-2xs">
                        <div class="flex justify-between items-center">
                            <div>
                                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase">Planes Comerciales</h3>
                                <p class="text-xs text-gray-450 dark:text-gray-500 mt-1">Defina características y costos mensuales de cada plan de suscripción.</p>
                            </div>
                            <button
                                @click="openCreatePlanModal"
                                class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-2xs flex items-center gap-1"
                            >
                                <Plus class="w-3.5 h-3.5" />
                                Crear Nuevo Plan
                            </button>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-gray-150 dark:border-gray-700">
                            <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400">
                                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold uppercase text-[10px]">
                                    <tr>
                                        <th scope="col" class="px-5 py-3">Nombre</th>
                                        <th scope="col" class="px-5 py-3">Precio Mensual</th>
                                        <th scope="col" class="px-5 py-3 text-center">Límite Users</th>
                                        <th scope="col" class="px-5 py-3 text-center">Límite Prods</th>
                                        <th scope="col" class="px-5 py-3 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                    <tr v-for="plan in planes" :key="plan.id" class="bg-white dark:bg-gray-800">
                                        <td class="px-5 py-4 font-bold text-gray-900 dark:text-white">{{ plan.nombre }}</td>
                                        <td class="px-5 py-4 font-extrabold text-indigo-600 font-mono">{{ formatCurrency(plan.precio) }}/mes</td>
                                        <td class="px-5 py-4 text-center font-mono font-bold text-gray-700 dark:text-gray-300">{{ plan.limite_usuarios }}</td>
                                        <td class="px-5 py-4 text-center font-mono font-bold text-gray-700 dark:text-gray-300">{{ plan.limite_productos }}</td>
                                        <td class="px-5 py-4 text-right">
                                            <button
                                                @click="openEditPlanModal(plan)"
                                                class="text-indigo-600 hover:text-indigo-800 font-bold text-xs"
                                            >
                                                Editar
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Subscriptions Right Panel -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 space-y-4 shadow-2xs">
                        <div class="flex justify-between items-center">
                            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase">Asignar Licencia</h3>
                            <button
                                @click="openCreateSuscripcionModal"
                                class="p-1 bg-gray-50 hover:bg-gray-100 dark:bg-gray-700 rounded-lg text-xs"
                            >
                                <Plus class="w-4 h-4" />
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="s in suscripciones" :key="s.id" class="p-4 border border-gray-100 dark:border-gray-700 rounded-xl space-y-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="text-xs font-bold text-gray-900 dark:text-white">{{ s.empresa?.nombre }}</h4>
                                        <p class="text-[10px] text-gray-400">RUC: {{ s.empresa?.ruc }}</p>
                                    </div>
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase bg-green-50 text-green-700 border border-green-200">
                                        {{ s.estado }}
                                    </span>
                                </div>
                                <div class="flex justify-between text-[10px] text-gray-500 font-mono pt-1">
                                    <span>Plan: {{ s.plan?.nombre }}</span>
                                    <span>Vence: {{ new Date(s.fecha_fin).toLocaleDateString('es-PE') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- ========================================== -->
                <!-- 5. FACTURACIÓN TAB (Revenue Log)           -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'facturacion'" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 space-y-4 shadow-2xs">
                    <div>
                        <h3 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase">Historial de Facturación Global</h3>
                        <p class="text-xs text-gray-450 dark:text-gray-500 mt-1 font-medium font-sans">Registro de todos los pagos recibidos y cobros por pasarela Culqi/Stripe.</p>
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-155 dark:border-gray-700">
                        <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold uppercase text-[10px]">
                                <tr>
                                    <th scope="col" class="px-5 py-3.5">ID Transacción</th>
                                    <th scope="col" class="px-5 py-3.5">Empresa</th>
                                    <th scope="col" class="px-5 py-3.5">Monto de Pago</th>
                                    <th scope="col" class="px-5 py-3.5 text-center">Pasarela</th>
                                    <th scope="col" class="px-5 py-3.5 text-center">Estado</th>
                                    <th scope="col" class="px-5 py-3.5">Fecha y Hora</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 font-mono">TXN_78495032</td>
                                    <td class="px-5 py-4 font-bold text-gray-900 dark:text-white">Minimarket Don Pepe</td>
                                    <td class="px-5 py-4 font-extrabold text-gray-900 dark:text-white font-mono font-sans">S/. 120.00</td>
                                    <td class="px-5 py-4 text-center font-semibold">Culqi</td>
                                    <td class="px-5 py-4 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-700 border border-green-200">
                                            Aprobado
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 font-mono text-gray-400">02/07/2026 10:14:02</td>
                                </tr>
                                <tr class="bg-white dark:bg-gray-800">
                                    <td class="px-5 py-4 font-mono">TXN_78494921</td>
                                    <td class="px-5 py-4 font-bold text-gray-900 dark:text-white">Bodega La Esquina</td>
                                    <td class="px-5 py-4 font-extrabold text-gray-900 dark:text-white font-mono font-sans">S/. 59.00</td>
                                    <td class="px-5 py-4 text-center font-semibold">Culqi</td>
                                    <td class="px-5 py-4 text-center">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-50 text-green-700 border border-green-200">
                                            Aprobado
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 font-mono text-gray-400">01/07/2026 09:44:59</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 6. MONITOREO TAB                           -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'monitoreo'" class="space-y-6">
                    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 space-y-4 shadow-2xs">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                            <div>
                                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase">Estado de Infraestructura y Servicios</h3>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Monitoreo en tiempo real de la disponibilidad de la plataforma y hardware del servidor.</p>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-green-50 text-green-700 border border-green-200">
                                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                                Todos los sistemas operativos (99.98% Uptime)
                            </span>
                        </div>

                        <!-- Services Grid -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
                            <div class="p-4 rounded-xl border border-gray-150 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                                        <Database class="w-4 h-4" />
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-gray-900 dark:text-white">Base de Datos MySQL</h4>
                                        <p class="text-[10px] text-gray-400 font-mono">Puerto 3306 • Conexión Activa</p>
                                    </div>
                                </div>
                                <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                            </div>

                            <div class="p-4 rounded-xl border border-gray-150 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold">
                                        <Server class="w-4 h-4" />
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-gray-900 dark:text-white">Servidor Web</h4>
                                        <p class="text-[10px] text-gray-400 font-mono">Laragon • Apache/Nginx (80)</p>
                                    </div>
                                </div>
                                <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                            </div>

                            <div class="p-4 rounded-xl border border-gray-150 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
                                        <Sparkles class="w-4 h-4 text-violet-600" />
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-gray-900 dark:text-white">Servicio IA (Groq API)</h4>
                                        <p class="text-[10px] text-gray-400 font-mono">Llama 3.3 70B • Activo</p>
                                    </div>
                                </div>
                                <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                            </div>

                            <div class="p-4 rounded-xl border border-gray-150 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/50 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                                        <ShieldCheck class="w-4 h-4 text-amber-600" />
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-gray-900 dark:text-white">Aislamiento Multi-Tenant</h4>
                                        <p class="text-[10px] text-gray-400 font-mono">TenantScope • Activo</p>
                                    </div>
                                </div>
                                <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                            </div>
                        </div>

                        <!-- Hardware Gauges in Monitoreo -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4">
                            <div class="border border-gray-150 dark:border-gray-700 p-5 rounded-2xl space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                        <Cpu class="w-4 h-4 text-indigo-600" />
                                        Carga del CPU
                                    </span>
                                    <span class="text-xs font-mono font-bold text-gray-600">{{ serverCpu }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden">
                                    <div class="bg-indigo-600 h-full rounded-full transition-all duration-500" :style="`width: ${serverCpu}%`"></div>
                                </div>
                                <p class="text-[10px] text-gray-400">Latencia promedio: 38ms • 8 hilos activos</p>
                            </div>

                            <div class="border border-gray-150 dark:border-gray-700 p-5 rounded-2xl space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                        <Server class="w-4 h-4 text-emerald-600" />
                                        Memoria RAM Asignada
                                    </span>
                                    <span class="text-xs font-mono font-bold text-gray-600">{{ serverRam }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden">
                                    <div class="bg-emerald-600 h-full rounded-full transition-all duration-500" :style="`width: ${serverRam}%`"></div>
                                </div>
                                <p class="text-[10px] text-gray-400">Uso: 9.28 GB de 16.00 GB DDR4</p>
                            </div>

                            <div class="border border-gray-150 dark:border-gray-700 p-5 rounded-2xl space-y-3">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
                                        <HardDrive class="w-4 h-4 text-amber-600" />
                                        Disco Almacenamiento NVMe
                                    </span>
                                    <span class="text-xs font-mono font-bold text-gray-600">{{ serverDisk }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-700 h-2.5 rounded-full overflow-hidden">
                                    <div class="bg-amber-600 h-full rounded-full transition-all duration-500" :style="`width: ${serverDisk}%`"></div>
                                </div>
                                <p class="text-[10px] text-gray-400">105 GB libres de 250 GB totales</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 7. LOGS TAB                                -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'logs'" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 space-y-4 shadow-2xs">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                        <div>
                            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase">Registro de Logs del Sistema</h3>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Bitácora en tiempo real para auditorías, accesos de usuarios y eventos técnicos.</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <!-- Filter Buttons -->
                            <div class="flex items-center bg-gray-100 dark:bg-gray-700 p-1 rounded-xl text-[11px] font-bold">
                                <button
                                    v-for="f in [{ id: 'all', label: 'Todos' }, { id: 'info', label: 'Info' }, { id: 'warning', label: 'Avisos' }, { id: 'error', label: 'Errores' }]"
                                    :key="f.id"
                                    @click="logFilter = f.id"
                                    class="px-2.5 py-1 rounded-lg transition-all cursor-pointer"
                                    :class="logFilter === f.id ? 'bg-white dark:bg-gray-900 text-indigo-600 shadow-xs' : 'text-gray-500 hover:text-gray-900'"
                                >
                                    {{ f.label }}
                                </button>
                            </div>

                            <!-- Search Filter -->
                            <div class="relative w-48 sm:w-64">
                                <Search class="w-3.5 h-3.5 text-gray-400 absolute left-3 top-2.5" />
                                <input
                                    v-model="searchLog"
                                    type="text"
                                    placeholder="Buscar en logs..."
                                    class="w-full pl-8 pr-3 py-1.5 text-xs bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-gray-700 dark:text-gray-300"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Code console simulated log stream -->
                    <div class="bg-gray-950 text-gray-100 font-mono text-xs rounded-2xl p-5 border border-gray-800 space-y-3 leading-relaxed max-h-96 overflow-y-auto shadow-inner select-text">
                        <div v-for="(log, idx) in filteredLogs" :key="idx" class="flex items-start gap-3">
                            <span class="text-gray-500 shrink-0 select-none">[{{ log.time }}]</span>
                            <span 
                                class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase shrink-0 select-none"
                                :class="log.level === 'error' ? 'bg-red-900/60 text-red-300 border border-red-800/40' : (log.level === 'warning' ? 'bg-amber-900/60 text-amber-300 border border-amber-800/40' : 'bg-blue-900/60 text-blue-300 border border-blue-800/40')"
                            >
                                {{ log.level }}
                            </span>
                            <span class="text-indigo-400 font-bold shrink-0">[{{ log.service }}]</span>
                            <span class="text-gray-300 flex-1">{{ log.msg }}</span>
                        </div>
                        <div v-if="filteredLogs.length === 0" class="text-gray-500 py-6 text-center">
                            No se encontraron logs con el filtro seleccionado.
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 7. BACKUPS TAB (System Data Restore)       -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'backups'" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 space-y-4 shadow-2xs">
                    <div>
                        <h3 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase">Respaldo y Backups del Servidor</h3>
                        <p class="text-xs text-gray-450 dark:text-gray-500 mt-1">Gestione copias de seguridad de la base de datos MySQL e imágenes cargadas.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                        
                        <!-- Box 1 -->
                        <div class="border border-gray-150 dark:border-gray-700 p-5 rounded-2xl space-y-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-indigo-50 dark:bg-indigo-950/30 flex items-center justify-center text-indigo-600">
                                    <Database class="w-5 h-5" />
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-gray-900 dark:text-white">Generar Copia de Seguridad</h4>
                                    <p class="text-[10px] text-gray-450">Genera y descarga un archivo .SQL comprimido con todas las tablas.</p>
                                </div>
                            </div>
                            <button
                                @click="triggerBackupAction('backup')"
                                class="w-full py-2 bg-indigo-600 hover:bg-indigo-750 text-white rounded-lg text-xs font-bold transition-colors"
                            >
                                Crear Respaldo Completo
                            </button>
                        </div>

                        <!-- Box 2 -->
                        <div class="border border-gray-150 dark:border-gray-700 p-5 rounded-2xl space-y-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-red-50 dark:bg-red-950/30 flex items-center justify-center text-red-600">
                                    <AlertTriangle class="w-5 h-5" />
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-gray-900 dark:text-white">Restaurar Punto de Control</h4>
                                    <p class="text-[10px] text-gray-450">Sobrescribe los datos actuales de la plataforma con un respaldo anterior.</p>
                                </div>
                            </div>
                            <button
                                @click="triggerBackupAction('restore')"
                                class="w-full py-2 bg-red-600 hover:bg-red-750 text-white rounded-lg text-xs font-bold transition-colors"
                            >
                                Restaurar Base de Datos
                            </button>
                        </div>

                    </div>
                </div>

                <!-- ========================================== -->
                <!-- 8. CONFIGURACIÓN GLOBAL TAB                -->
                <!-- ========================================== -->
                <div v-if="activeTab === 'configuracion'" class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-100 dark:border-gray-700 p-6 space-y-4 shadow-2xs">
                    <div>
                        <h3 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase">Configuración Global de la Plataforma</h3>
                        <p class="text-xs text-gray-450 dark:text-gray-500 mt-1 font-sans">Ajuste parámetros de mantenimiento, pasarelas de pago y credenciales globales de API.</p>
                    </div>

                    <form class="space-y-4 pt-2 max-w-xl">
                        <div>
                            <InputLabel value="Nombre de la Plataforma" />
                            <TextInput type="text" class="mt-1 block w-full" value="INVENTEX SaaS" />
                        </div>
                        <div>
                            <InputLabel value="Correo de Soporte Técnico" />
                            <TextInput type="email" class="mt-1 block w-full" value="soporte@inventex.pe" />
                        </div>
                        <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-xl">
                            <div>
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white">Modo de Mantenimiento Global</h4>
                                <p class="text-[10px] text-gray-400 mt-0.5">Muestra pantalla de fuera de servicio temporal a todas las bodegas.</p>
                            </div>
                            <input type="checkbox" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" />
                        </div>
                        <div class="flex justify-end pt-2">
                            <PrimaryButton type="button" @click="alert('Ajustes guardados con éxito en base de datos SaaS.')">
                                Guardar Ajustes SaaS
                            </PrimaryButton>
                        </div>
                    </form>
                </div>

            </div>
        </div>

        <!-- PLAN MODAL -->
        <Modal :show="showPlanModal" @close="showPlanModal = false" max-width="md">
            <div class="p-6">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-gray-100 mb-4 uppercase">
                    {{ editPlanMode ? 'Editar Plan de Precios' : 'Crear Nuevo Plan de Precios' }}
                </h3>

                <form @submit.prevent="submitPlan" class="space-y-4">
                    <!-- Nombre -->
                    <div>
                        <InputLabel for="p_nombre" value="Nombre del Plan" />
                        <TextInput id="p_nombre" type="text" class="mt-1 block w-full" v-model="planForm.nombre" required />
                        <InputError class="mt-2" :message="planForm.errors.nombre" />
                    </div>

                    <!-- Descripcion -->
                    <div>
                        <InputLabel for="p_desc" value="Descripción" />
                        <textarea id="p_desc" rows="2" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm text-xs" v-model="planForm.descripcion"></textarea>
                    </div>

                    <!-- Precio -->
                    <div>
                        <InputLabel for="p_precio" value="Precio Mensual (S/.)" />
                        <TextInput id="p_precio" type="number" step="0.01" class="mt-1 block w-full" v-model="planForm.precio" required />
                        <InputError class="mt-2" :message="planForm.errors.precio" />
                    </div>

                    <!-- Límites en grid -->
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <InputLabel for="p_l_users" value="Usuarios" />
                            <TextInput id="p_l_users" type="number" class="mt-1 block w-full text-xs p-1" v-model="planForm.limite_usuarios" required />
                        </div>
                        <div>
                            <InputLabel for="p_l_prods" value="Productos" />
                            <TextInput id="p_l_prods" type="number" class="mt-1 block w-full text-xs p-1" v-model="planForm.limite_productos" required />
                        </div>
                        <div>
                            <InputLabel for="p_l_suc" value="Sucursales" />
                            <TextInput id="p_l_suc" type="number" class="mt-1 block w-full text-xs p-1" v-model="planForm.limite_sucursales" required />
                        </div>
                    </div>

                    <!-- Características (Array) -->
                    <div class="space-y-2">
                        <InputLabel value="Características / Beneficios" />
                        <div class="flex gap-2">
                            <input
                                v-model="tempFeature"
                                type="text"
                                placeholder="Ej: Facturación Ilimitada"
                                class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm text-xs p-2"
                                @keypress.enter.prevent="addFeature"
                            />
                            <button
                                type="button"
                                @click="addFeature"
                                class="px-3 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-750 text-xs font-semibold rounded-lg"
                            >
                                Agregar
                            </button>
                        </div>
                        
                        <div class="flex flex-wrap gap-1.5 mt-1.5">
                            <span 
                                v-for="(feat, idx) in planForm.caracteristicas" 
                                :key="idx"
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400"
                            >
                                {{ feat }}
                                <button 
                                    type="button" 
                                    @click="removeFeature(idx)" 
                                    class="ms-1 font-bold text-red-500 hover:text-red-700"
                                >x</button>
                            </span>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton @click="showPlanModal = false" :disabled="planForm.processing">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton :class="{ 'opacity-25': planForm.processing }" :disabled="planForm.processing">
                            {{ editPlanMode ? 'Guardar Cambios' : 'Crear' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- SUSCRIPCION MODAL -->
        <Modal :show="showSuscripcionModal" @close="showSuscripcionModal = false" max-width="md">
            <div class="p-6">
                <h3 class="text-sm font-extrabold text-gray-900 dark:text-gray-100 mb-4 uppercase">
                    {{ editSuscripcionMode ? 'Modificar Suscripción' : 'Asignar Suscripción de Cliente' }}
                </h3>

                <form @submit.prevent="submitSuscripcion" class="space-y-4">
                    <!-- Empresa (Disabled if editing) -->
                    <div>
                        <InputLabel for="s_empresa" value="Empresa (Tenant)" />
                        <select
                            id="s_empresa"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm text-xs p-2"
                            v-model="suscripcionForm.empresa_id"
                            required
                            :disabled="editSuscripcionMode"
                        >
                            <option value="">Selecciona Empresa...</option>
                            <option v-for="emp in empresas" :key="emp.id" :value="emp.id">
                                {{ emp.nombre }} (RUC: {{ emp.ruc }})
                            </option>
                        </select>
                        <InputError class="mt-2" :message="suscripcionForm.errors.empresa_id" />
                    </div>

                    <!-- Plan -->
                    <div>
                        <InputLabel for="s_plan" value="Plan a Suscribir" />
                        <select
                            id="s_plan"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm text-xs p-2"
                            v-model="suscripcionForm.plan_id"
                            required
                        >
                            <option value="">Selecciona Plan...</option>
                            <option v-for="pl in planes" :key="pl.id" :value="pl.id">
                                {{ pl.nombre }} (S/. {{ pl.precio }}/mes)
                            </option>
                        </select>
                        <InputError class="mt-2" :message="suscripcionForm.errors.plan_id" />
                    </div>

                    <!-- Estado -->
                    <div>
                        <InputLabel for="s_estado" value="Estado" />
                        <select
                            id="s_estado"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm text-xs p-2"
                            v-model="suscripcionForm.estado"
                            required
                        >
                            <option value="trialing">Prueba (Trialing)</option>
                            <option value="active">Activa</option>
                            <option value="suspended">Suspendida</option>
                            <option value="expired">Expirada</option>
                        </select>
                    </div>

                    <!-- Fechas -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="s_f_ini" value="Fecha Inicio" />
                            <TextInput id="s_f_ini" type="date" class="mt-1 block w-full text-xs" v-model="suscripcionForm.fecha_inicio" required />
                        </div>
                        <div>
                            <InputLabel for="s_f_fin" value="Fecha Fin" />
                            <TextInput id="s_f_fin" type="date" class="mt-1 block w-full text-xs" v-model="suscripcionForm.fecha_fin" required />
                        </div>
                    </div>
                    <InputError class="mt-2" :message="suscripcionForm.errors.fecha_fin" />

                    <!-- Método de Pago -->
                    <div>
                        <InputLabel for="s_met" value="Método de Pago" />
                        <TextInput id="s_met" type="text" class="mt-1 block w-full" v-model="suscripcionForm.metodo_pago" required placeholder="Ej: Culqi, Stripe, manual" />
                        <InputError class="mt-2" :message="suscripcionForm.errors.metodo_pago" />
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <SecondaryButton @click="showSuscripcionModal = false" :disabled="suscripcionForm.processing">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton :class="{ 'opacity-25': suscripcionForm.processing }" :disabled="suscripcionForm.processing">
                            {{ editSuscripcionMode ? 'Guardar Cambios' : 'Crear' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- SECURITY BACKUP MODAL -->
        <Modal :show="showBackupModal" @close="showBackupModal = false" max-width="md">
            <div class="p-6">
                <div class="flex items-center gap-3 text-red-650 mb-3">
                    <AlertTriangle class="w-6 h-6" />
                    <h3 class="text-sm font-extrabold uppercase tracking-wider">Confirmación de Acción Crítica</h3>
                </div>

                <div class="space-y-4">
                    <p class="text-xs text-gray-500 leading-relaxed">
                        Esta acción afecta directamente la base de datos compartida de la plataforma INVENTEX.
                        Por favor ingrese la palabra de seguridad requerida para continuar.
                    </p>

                    <div v-if="backupAction === 'backup'" class="p-3 bg-indigo-50 text-indigo-700 border border-indigo-150 rounded-xl space-y-1">
                        <p class="text-[10px] font-bold">Escriba <strong class="font-mono">RESPALDO</strong> para confirmar el respaldo.</p>
                        <TextInput type="text" class="w-full mt-1.5 font-mono text-xs uppercase" v-model="backupConfirmationText" />
                    </div>

                    <div v-if="backupAction === 'restore'" class="p-3 bg-red-50 text-red-700 border border-red-150 rounded-xl space-y-1">
                        <p class="text-[10px] font-bold text-red-600">Escriba <strong class="font-mono">RESTAURAR</strong> para confirmar la restauración.</p>
                        <TextInput type="text" class="w-full mt-1.5 font-mono text-xs uppercase" v-model="backupConfirmationText" />
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <SecondaryButton @click="showBackupModal = false">Cancelar</SecondaryButton>
                        <button
                            @click="executeBackup"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold disabled:opacity-25"
                            :disabled="(backupAction === 'backup' && backupConfirmationText !== 'RESPALDO') || (backupAction === 'restore' && backupConfirmationText !== 'RESTAURAR')"
                        >
                            Confirmar Ejecución
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>
