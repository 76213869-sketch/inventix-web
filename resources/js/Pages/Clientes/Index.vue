<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, useForm, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Dropdown from '@/Components/Dropdown.vue';

// Lucide icon imports
import { 
    Users, Plus, Search, AlertCircle, Phone, Mail, MapPin, 
    X, User, Calendar, CreditCard, DollarSign, Activity, FileText, 
    Trash2, Edit3, ShieldCheck, CheckCircle2, ChevronRight, Briefcase,
    Sparkles, Percent, MoreVertical
} from 'lucide-vue-next';

const props = defineProps({
    clientes: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const showModal = ref(false);
const editMode = ref(false);
const searchQuery = ref('');
const filterFrecuente = ref('');
const filterEstado = ref('');
const selectedFilterStat = ref('todos'); // todos, frecuentes, nuevos, vip
const isLoading = ref(true);

// Lateral Drawer slide-over state
const showDrawer = ref(false);
const drawerCliente = ref(null);

// RENIEC/SUNAT search states
const isSearchingApi = ref(false);
const apiSuccessMessage = ref('');

// Main form fields
const form = useForm({
    id: null,
    nombre: '',
    documento_tipo: 'DNI',
    documento_numero: '',
    direccion: '', // We serialize JSON here: { dir: "Av...", estado: "activo", frecuente: true, credito: false, observaciones: "" }
    telefono: '',
    correo: '',
});

// Decoupled form UI fields
const clientDireccion = ref('');
const clientEstado = ref('activo');
const clientFrecuente = ref(false);
const clientCredito = ref(false);
const clientObservaciones = ref('');

// Duplicate document check
const localDocumentError = ref('');

onMounted(() => {
    setTimeout(() => {
        isLoading.value = false;
    }, 200);
});

// Helper to parse serialized JSON inside direccion
const parseDireccion = (dirStr) => {
    try {
        if (dirStr && dirStr.startsWith('{')) {
            return JSON.parse(dirStr);
        }
    } catch (e) {
        // ignore
    }
    return {
        dir: dirStr || '',
        estado: 'activo',
        frecuente: false,
        credito: false,
        observaciones: ''
    };
};

// Auto generate avatar initials
const getAvatarInitials = (name) => {
    if (!name) return '??';
    if (name === 'Público General') return 'PG';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.substr(0, 2).toUpperCase();
};

// Generates consistent background color for the avatar based on name hash
const getAvatarStyle = (name) => {
    if (name === 'Público General') {
        return 'background-color: #3b82f6; color: white;';
    }
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const h = Math.abs(hash % 360);
    return `background-color: hsl(${h}, 60%, 42%); color: white;`;
};

// Client classification tag helpers
const isCompany = (cliente) => {
    return cliente.documento_tipo === 'RUC' || 
           String(cliente.nombre).toLowerCase().includes('s.a') || 
           String(cliente.nombre).toLowerCase().includes('sac') || 
           String(cliente.nombre).toLowerCase().includes('eirl');
};

const isNewClient = (cliente) => {
    if (!cliente.created_at) return false;
    const regDate = new Date(cliente.created_at);
    const now = new Date();
    // Register within the last 30 days
    return (now - regDate) / (1000 * 60 * 60 * 24) <= 30;
};

const isVipClient = (cliente) => {
    const totalSpent = getClientTotalSpent(cliente);
    return totalSpent >= 500;
};

// Client sales metrics helpers
const getClientTotalSpent = (cliente) => {
    if (!cliente.ventas) return 0;
    return cliente.ventas
        .filter(v => v.estado === 'completada')
        .reduce((sum, v) => sum + Number(v.total), 0);
};

const getClientSalesCount = (cliente) => {
    if (!cliente.ventas) return 0;
    return cliente.ventas.filter(v => v.estado === 'completada').length;
};

const getClientLastPurchaseDate = (cliente) => {
    if (!cliente.ventas || cliente.ventas.length === 0) return null;
    const sorted = [...cliente.ventas].sort((a, b) => new Date(b.fecha_venta) - new Date(a.fecha_venta));
    return sorted[0].fecha_venta;
};

const getClientTicketPromedio = (cliente) => {
    const count = getClientSalesCount(cliente);
    if (count === 0) return 0;
    return getClientTotalSpent(cliente) / count;
};

// Products most bought calculation
const getClientTopProducts = (cliente) => {
    if (!cliente.ventas) return [];
    const productCounts = {};
    
    cliente.ventas.forEach(v => {
        if (v.estado === 'completada' && v.detalles) {
            v.detalles.forEach(d => {
                if (d.producto) {
                    const prodName = d.producto.nombre;
                    productCounts[prodName] = (productCounts[prodName] || 0) + Number(d.cantidad);
                }
            });
        }
    });

    return Object.entries(productCounts)
        .map(([name, qty]) => ({ name, qty }))
        .sort((a, b) => b.qty - a.qty)
        .slice(0, 3);
};

// Dashboard cards calculation
const totalClientesCount = computed(() => props.clientes.length);

const frequentClientesCount = computed(() => {
    return props.clientes.filter(c => {
        const meta = parseDireccion(c.direccion);
        return meta.frecuente || getClientSalesCount(c) > 3;
    }).length;
});

const nuevosClientesEsteMesCount = computed(() => {
    return props.clientes.filter(isNewClient).length;
});

const totalFacturadoClientes = computed(() => {
    let total = 0;
    props.clientes.forEach(c => {
        if (c.nombre !== 'Público General') {
            total += getClientTotalSpent(c);
        }
    });
    return total;
});

// Intelligent List Filters
const filteredClientes = computed(() => {
    let result = props.clientes;

    // 1. Search Query Match
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(c => 
            c.nombre.toLowerCase().includes(query) ||
            (c.documento_numero && c.documento_numero.includes(query)) ||
            (c.correo && c.correo.toLowerCase().includes(query)) ||
            (c.telefono && c.telefono.includes(query))
        );
    }

    // 2. Select filter mapping
    if (filterFrecuente.value === 'frecuente') {
        result = result.filter(c => {
            const meta = parseDireccion(c.direccion);
            return meta.frecuente || getClientSalesCount(c) > 3;
        });
    }

    if (filterEstado.value) {
        result = result.filter(c => {
            const meta = parseDireccion(c.direccion);
            return meta.estado === filterEstado.value;
        });
    }

    // 3. Clickable dashboard metrics filters
    if (selectedFilterStat.value === 'frecuentes') {
        result = result.filter(c => {
            const meta = parseDireccion(c.direccion);
            return meta.frecuente || getClientSalesCount(c) > 3;
        });
    } else if (selectedFilterStat.value === 'nuevos') {
        result = result.filter(isNewClient);
    } else if (selectedFilterStat.value === 'vip') {
        result = result.filter(isVipClient);
    }

    return result;
});

// Modal Actions
const openCreateModal = () => {
    form.clearErrors();
    localDocumentError.value = '';
    apiSuccessMessage.value = '';
    
    form.reset({
        id: null,
        nombre: '',
        documento_tipo: 'DNI',
        documento_numero: '',
        direccion: '',
        telefono: '',
        correo: '',
    });

    clientDireccion.value = '';
    clientEstado.value = 'activo';
    clientFrecuente.value = false;
    clientCredito.value = false;
    clientObservaciones.value = '';

    editMode.value = false;
    showModal.value = true;
};

const openEditModal = (cliente) => {
    form.clearErrors();
    localDocumentError.value = '';
    apiSuccessMessage.value = '';

    const meta = parseDireccion(cliente.direccion);

    form.reset({
        id: cliente.id,
        nombre: cliente.nombre,
        documento_tipo: cliente.documento_tipo,
        documento_numero: cliente.documento_numero || '',
        direccion: cliente.direccion || '',
        telefono: cliente.telefono || '',
        correo: cliente.correo || '',
    });

    clientDireccion.value = meta.dir;
    clientEstado.value = meta.estado || 'activo';
    clientFrecuente.value = meta.frecuente || false;
    clientCredito.value = meta.credito || false;
    clientObservaciones.value = meta.observaciones || '';

    editMode.value = true;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
};

const validateDocumentFormat = () => {
    localDocumentError.value = '';
    if (form.documento_tipo === 'DNI' && form.documento_numero && form.documento_numero.length !== 8) {
        localDocumentError.value = 'El DNI debe tener exactamente 8 dígitos numéricos.';
        return false;
    }
    if (form.documento_tipo === 'RUC' && form.documento_numero && form.documento_numero.length !== 11) {
        localDocumentError.value = 'El RUC debe tener exactamente 11 dígitos numéricos.';
        return false;
    }
    
    // Check duplicates
    const dupe = props.clientes.find(c => 
        c.documento_numero === form.documento_numero && 
        c.documento_numero !== '' && 
        c.id !== form.id
    );
    if (dupe) {
        localDocumentError.value = `Este número de documento ya está registrado para el cliente "${dupe.nombre}".`;
        return false;
    }

    return true;
};

const submit = () => {
    if (!validateDocumentFormat()) return;

    // Serialize extra fields into direccion
    form.direccion = JSON.stringify({
        dir: clientDireccion.value,
        estado: clientEstado.value,
        frecuente: clientFrecuente.value,
        credito: clientCredito.value,
        observaciones: clientObservaciones.value
    });

    if (editMode.value) {
        form.put(route('clientes.update', form.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('clientes.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteCliente = (cliente) => {
    if (cliente.nombre === 'Público General') {
        alert('El cliente maestro "Público General" está protegido y no se puede eliminar.');
        return;
    }
    if (confirm(`¿Estás seguro de que deseas eliminar al cliente "${cliente.nombre}"?`)) {
        form.delete(route('clientes.destroy', cliente.id));
    }
};

const toggleClientEstado = (cliente) => {
    if (cliente.nombre === 'Público General') {
        alert('El cliente maestro "Público General" debe estar siempre activo.');
        return;
    }
    const meta = parseDireccion(cliente.direccion);
    meta.estado = meta.estado === 'activo' ? 'inactivo' : 'activo';

    const serializedDir = JSON.stringify(meta);
    router.put(route('clientes.update', cliente.id), {
        nombre: cliente.nombre,
        documento_tipo: cliente.documento_tipo,
        documento_numero: cliente.documento_numero,
        direccion: serializedDir,
        telefono: cliente.telefono,
        correo: cliente.correo,
    });
};

const openHistory = (cliente) => {
    drawerCliente.value = cliente;
    showDrawer.value = true;
};

const startNewSaleForClient = (cliente) => {
    // Redireccionar al POS pre-seleccionando el cliente
    router.visit(route('pos', { cliente_id: cliente.id }));
};

// RENIEC / SUNAT Simulator integration
const simulateApiLookup = () => {
    if (!form.documento_numero) return;
    
    isSearchingApi.value = true;
    apiSuccessMessage.value = '';
    
    setTimeout(() => {
        isSearchingApi.value = false;
        if (form.documento_tipo === 'DNI') {
            if (form.documento_numero.length === 8) {
                form.nombre = 'ANTONY MORALES FLORES';
                clientObservaciones.value = 'Validado por RENIEC';
                apiSuccessMessage.value = 'Datos consultados con éxito desde la base de datos de RENIEC.';
            } else {
                localDocumentError.value = 'El DNI debe tener 8 dígitos para realizar la consulta.';
            }
        } else if (form.documento_tipo === 'RUC') {
            if (form.documento_numero.length === 11) {
                form.nombre = 'CORPORACIÓN COMERCIAL INVENTEX S.A.C.';
                clientDireccion.value = 'AV. JAVIER PRADO ESTE NRO. 1240, SAN ISIDRO, LIMA';
                clientObservaciones.value = 'Validado por SUNAT - Contribuyente Activo';
                apiSuccessMessage.value = 'Razón social y Dirección Fiscal importados con éxito desde SUNAT.';
            } else {
                localDocumentError.value = 'El RUC debe tener 11 dígitos para la consulta.';
            }
        }
    }, 750);
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(val);
};
</script>

<template>
    <Head title="Clientes" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">Directorio de Clientes</h2>
                    <p class="text-xs text-gray-500 mt-0.5 font-medium">Controla el historial de consumos, créditos y datos de facturación.</p>
                </div>
                <PrimaryButton v-if="clientes.length > 0" @click="openCreateModal" class="h-9 rounded-xl">
                    <Plus class="w-4 h-4 me-1.5" />
                    Nuevo Cliente
                </PrimaryButton>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Toast Notifications / Alertas Modernas -->
            <div v-if="flashSuccess" class="rounded-xl bg-emerald-50 dark:bg-emerald-950/20 p-4 text-xs text-emerald-800 dark:text-emerald-455 border border-emerald-100 dark:border-emerald-900/30 flex items-start gap-3 shadow-3xs">
                <span class="p-1 bg-white dark:bg-gray-900 rounded-lg border border-emerald-200 dark:border-emerald-800 text-emerald-600">✓</span>
                <div>
                    <div class="font-bold">Acción Realizada Exitosamente</div>
                    <div class="opacity-95 mt-0.5">{{ flashSuccess }}</div>
                </div>
            </div>

            <div v-if="flashError" class="rounded-xl bg-red-50 dark:bg-red-950/20 p-4 text-xs text-red-800 dark:text-red-455 border border-red-100 dark:border-red-900/30 flex items-start gap-3 shadow-3xs">
                <AlertCircle class="w-4.5 h-4.5 text-red-600 mt-0.5 shrink-0" />
                <div>
                    <div class="font-bold">Error en la Operación</div>
                    <div class="opacity-95 mt-0.5">{{ flashError }}</div>
                </div>
            </div>

            <!-- SKELETON LOADING STATE -->
            <div v-if="isLoading" class="grid grid-cols-2 md:grid-cols-4 gap-4 animate-pulse">
                <div v-for="i in 4" :key="i" class="h-20 bg-gray-100 dark:bg-gray-800 rounded-2xl"></div>
            </div>

            <!-- 1. DASHBOARD DE CLIENTES (Tarjetas Superiores) -->
            <div v-else-if="clientes.length > 0" class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <!-- Clientes Registrados -->
                <button 
                    @click="selectedFilterStat = 'todos'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between cursor-pointer outline-none"
                    :class="selectedFilterStat === 'todos' ? 'border-blue-500 ring-2 ring-blue-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Clientes Registrados</span>
                    <h4 class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ totalClientesCount }}</h4>
                    <span class="text-[9px] text-gray-400 mt-1 block">Fichas de contacto completas</span>
                </button>

                <!-- Clientes Frecuentes -->
                <button 
                    @click="selectedFilterStat = 'frecuentes'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between cursor-pointer outline-none"
                    :class="selectedFilterStat === 'frecuentes' ? 'border-emerald-500 ring-2 ring-emerald-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Clientes Frecuentes</span>
                    <h4 class="text-xl font-bold text-emerald-600 dark:text-emerald-450 mt-1">{{ frequentClientesCount }}</h4>
                    <span class="text-[9px] text-gray-400 mt-1 block">Con más de 3 consumos o switch activo</span>
                </button>

                <!-- Nuevos este mes -->
                <button 
                    @click="selectedFilterStat = 'nuevos'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between cursor-pointer outline-none"
                    :class="selectedFilterStat === 'nuevos' ? 'border-indigo-500 ring-2 ring-indigo-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Nuevos este Mes</span>
                    <h4 class="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ nuevosClientesEsteMesCount }}</h4>
                    <span class="text-[9px] text-gray-400 mt-1 block">Registrados en los últimos 30 días</span>
                </button>

                <!-- Ventas facturadas a Clientes -->
                <button 
                    @click="selectedFilterStat = 'vip'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between cursor-pointer outline-none"
                    :class="selectedFilterStat === 'vip' ? 'border-amber-500 ring-2 ring-amber-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Ventas Identificadas</span>
                    <h4 class="text-xl font-bold text-amber-600 dark:text-amber-450 mt-1">{{ formatCurrency(totalFacturadoClientes) }}</h4>
                    <span class="text-[9px] text-gray-400 mt-1 block">Total consumos registrados</span>
                </button>
            </div>

            <!-- EMPTY STATE -->
            <div v-if="clientes.length === 0" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-12 shadow-3xs flex flex-col items-center text-center max-w-lg mx-auto mt-8">
                <div class="w-16 h-16 bg-blue-50 dark:bg-blue-950/30 text-blue-600 rounded-2xl flex items-center justify-center mb-6">
                    <Users class="w-8 h-8" />
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">No existen clientes registrados</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 mb-6 max-w-xs leading-relaxed">Registra a tus clientes recurrentes o corporativos para identificarlos en tus comprobantes del POS.</p>
                <PrimaryButton @click="openCreateModal" class="rounded-xl">
                    <Plus class="w-4 h-4 me-1.5" />
                    Registrar primer cliente
                </PrimaryButton>
            </div>

            <!-- MAIN LIST / TABLE -->
            <div v-else class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-855 overflow-hidden shadow-3xs">
                <div class="p-6">
                    <!-- Filters Grid -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full max-w-3xl">
                            <!-- Search -->
                            <div class="relative w-full">
                                <span class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none text-gray-400">
                                    <Search class="w-4 h-4" />
                                </span>
                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    class="block w-full ps-10 p-2.5 h-10 text-xs text-gray-900 dark:text-white border border-gray-200 dark:border-gray-800 rounded-xl bg-gray-50/50 dark:bg-gray-850 hover:bg-white focus:bg-white focus:ring-2 focus:ring-blue-500/25 focus:border-blue-500 transition-all duration-150 outline-none"
                                    placeholder="Buscar por Nombre, RUC, DNI, Correo..."
                                />
                            </div>

                            <!-- Frecuente Filter -->
                            <select
                                v-model="filterFrecuente"
                                class="h-10 border border-gray-255 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl text-xs px-3 transition-colors outline-none cursor-pointer"
                            >
                                <option value="">Cualquier Frecuencia</option>
                                <option value="frecuente">Clientes Frecuentes</option>
                            </select>

                            <!-- Estado Filter -->
                            <select
                                v-model="filterEstado"
                                class="h-10 border border-gray-255 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl text-xs px-3 transition-colors outline-none cursor-pointer"
                            >
                                <option value="">Cualquier Estado</option>
                                <option value="activo">Activos</option>
                                <option value="inactivo">Inactivos</option>
                            </select>
                        </div>
                        <div v-if="selectedFilterStat !== 'todos'" class="flex items-center gap-1.5 shrink-0">
                            <span class="text-[9px] bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-400 px-2 py-0.5 rounded border border-blue-100 dark:border-blue-900/30 font-bold uppercase">
                                Vista: {{ selectedFilterStat }}
                            </span>
                            <button @click="selectedFilterStat = 'todos'" class="text-[10px] text-gray-400 hover:text-gray-650 font-bold hover:underline">Limpiar</button>
                        </div>
                    </div>

                    <!-- 2. Tabla de Clientes Rediseñada con Avatares -->
                    <div class="relative overflow-x-auto rounded-xl border border-gray-150 dark:border-gray-800">
                        <table class="w-full text-xs text-left text-gray-500 dark:text-gray-450 border-collapse">
                            <thead class="text-[10px] text-gray-400 uppercase tracking-wider font-bold bg-gray-50/70 dark:bg-gray-950 sticky top-0 z-10 border-b border-gray-150 dark:border-gray-800">
                                <tr>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Cliente</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Documento</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Contacto / Teléfono</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Última Compra</th>
                                    <th scope="col" class="px-6 py-4 text-center font-bold text-gray-500">Compras</th>
                                    <th scope="col" class="px-6 py-4 text-right font-bold text-gray-500">Total Gastado</th>
                                    <th scope="col" class="px-6 py-4 text-center font-bold text-gray-500">Estado</th>
                                    <th scope="col" class="px-6 py-4 text-right font-bold text-gray-500 w-16">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                <tr 
                                    v-for="cliente in filteredClientes" 
                                    :key="cliente.id"
                                    class="hover:bg-gray-50/50 dark:hover:bg-gray-950/40 transition-colors duration-150"
                                    :class="parseDireccion(cliente.direccion).estado === 'inactivo' ? 'bg-gray-50/40 dark:bg-gray-950/10' : ''"
                                >
                                    <!-- 3 & 4. Cliente (Avatar automático + Badges) -->
                                    <th scope="row" class="px-6 py-5 font-semibold text-gray-900 dark:text-white cursor-pointer" @click="openHistory(cliente)">
                                        <div class="flex items-center gap-3">
                                            <div 
                                                class="w-10 h-10 rounded-full flex items-center justify-center font-extrabold text-sm shadow-3xs uppercase shrink-0 transition-transform duration-250 hover:scale-105"
                                                :style="getAvatarStyle(cliente.nombre)"
                                            >
                                                {{ getAvatarInitials(cliente.nombre) }}
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-xs font-bold text-gray-900 dark:text-white hover:text-blue-650 transition-colors">
                                                    {{ cliente.nombre }}
                                                </span>
                                                <div class="flex flex-wrap gap-1 mt-1 items-center">
                                                    <!-- Persona vs Empresa -->
                                                    <span 
                                                        class="inline-flex items-center px-1.2 py-0.2 rounded text-[7px] font-bold border tracking-wide uppercase"
                                                        :class="isCompany(cliente) ? 'bg-purple-50 text-purple-700 border-purple-100 dark:bg-purple-950/20 dark:text-purple-400' : 'bg-blue-50 text-blue-700 border-blue-100 dark:bg-blue-950/20 dark:text-blue-405'"
                                                    >
                                                        {{ isCompany(cliente) ? 'Empresa' : 'Persona' }}
                                                    </span>

                                                    <!-- Frecuente switch -->
                                                    <span 
                                                        v-if="parseDireccion(cliente.direccion).frecuente || getClientSalesCount(cliente) > 3"
                                                        class="inline-flex items-center px-1.2 py-0.2 rounded text-[7px] font-bold border tracking-wide uppercase bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-400"
                                                    >
                                                        Frecuente
                                                    </span>

                                                    <!-- VIP check -->
                                                    <span 
                                                        v-if="isVipClient(cliente)"
                                                        class="inline-flex items-center px-1.2 py-0.2 rounded text-[7px] font-bold border tracking-wide uppercase bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-950/20 dark:text-amber-400"
                                                    >
                                                        VIP
                                                    </span>

                                                    <!-- Nuevo Check -->
                                                    <span 
                                                        v-if="isNewClient(cliente)"
                                                        class="inline-flex items-center px-1.2 py-0.2 rounded text-[7px] font-bold border tracking-wide uppercase bg-indigo-50 text-indigo-700 border-indigo-100 dark:bg-indigo-950/20 dark:text-indigo-400"
                                                    >
                                                        Nuevo
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </th>

                                    <!-- Documento -->
                                    <td class="px-6 py-5 text-gray-700 dark:text-gray-400 font-mono" :class="parseDireccion(cliente.direccion).estado === 'inactivo' ? 'opacity-50' : ''">
                                        <span v-if="cliente.documento_numero" class="inline-flex items-center">
                                            <span class="text-[9px] font-bold px-1 py-0.5 rounded bg-gray-105 text-gray-700 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 me-1.5 uppercase">
                                                {{ cliente.documento_tipo }}
                                            </span>
                                            <span>{{ cliente.documento_numero }}</span>
                                        </span>
                                        <span v-else class="text-gray-300">-</span>
                                    </td>

                                    <!-- Contacto / Teléfono -->
                                    <td class="px-6 py-5 text-gray-650 dark:text-gray-350" :class="parseDireccion(cliente.direccion).estado === 'inactivo' ? 'opacity-50' : ''">
                                        <div v-if="cliente.telefono" class="flex items-center">
                                            <Phone class="w-3.5 h-3.5 text-gray-400 me-1 shrink-0" />
                                            <span>{{ cliente.telefono }}</span>
                                        </div>
                                        <div v-if="cliente.correo" class="flex items-center text-[10px] text-gray-450 mt-0.5">
                                            <Mail class="w-3 h-3 text-gray-400 me-1 shrink-0" />
                                            <span class="font-mono">{{ cliente.correo }}</span>
                                        </div>
                                        <span v-if="!cliente.telefono && !cliente.correo" class="text-gray-300 italic">Sin contacto</span>
                                    </td>

                                    <!-- Última Compra -->
                                    <td class="px-6 py-5 text-gray-600 dark:text-gray-400 font-mono" :class="parseDireccion(cliente.direccion).estado === 'inactivo' ? 'opacity-50' : ''">
                                        {{ getClientLastPurchaseDate(cliente) ? new Date(getClientLastPurchaseDate(cliente)).toLocaleDateString('es-PE') : 'Nunca' }}
                                    </td>

                                    <!-- Compras realizadas -->
                                    <td class="px-6 py-5 text-center font-mono font-bold" :class="parseDireccion(cliente.direccion).estado === 'inactivo' ? 'opacity-50' : ''">
                                        {{ getClientSalesCount(cliente) }}
                                    </td>

                                    <!-- Total Gastado -->
                                    <td class="px-6 py-5 text-right font-extrabold text-gray-900 dark:text-white font-mono" :class="parseDireccion(cliente.direccion).estado === 'inactivo' ? 'opacity-50' : ''">
                                        {{ formatCurrency(getClientTotalSpent(cliente)) }}
                                    </td>

                                    <!-- Estado -->
                                    <td class="px-6 py-5 text-center">
                                        <span 
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold border uppercase tracking-wider cursor-pointer"
                                            :class="parseDireccion(cliente.direccion).estado !== 'inactivo' 
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-450 dark:border-emerald-900/30' 
                                                : 'bg-red-50 text-red-700 border-red-100 dark:bg-red-950/20 dark:text-red-405 dark:border-red-900/30'"
                                            @click="toggleClientEstado(cliente)"
                                        >
                                            {{ parseDireccion(cliente.direccion).estado !== 'inactivo' ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>

                                    <!-- 5. Acciones Contextuales -->
                                    <td class="px-6 py-5 text-right whitespace-nowrap">
                                        <Dropdown align="right" width="48">
                                            <template #trigger>
                                                <button class="inline-flex items-center justify-center p-2 text-gray-500 hover:text-gray-750 dark:text-gray-400 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-850 transition-colors border border-gray-100 dark:border-gray-800">
                                                    <MoreVertical class="w-4 h-4" />
                                                </button>
                                            </template>
                                            <template #content>
                                                <div class="py-1 text-gray-700 dark:text-gray-300">
                                                    <button @click="openEditModal(cliente)" class="flex w-full px-4 py-2 text-left text-xs hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors items-center gap-2 cursor-pointer">
                                                        <Edit3 class="w-3.5 h-3.5 text-gray-400" />
                                                        Editar cliente
                                                    </button>
                                                    <button @click="openHistory(cliente)" class="flex w-full px-4 py-2 text-left text-xs hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors items-center gap-2 cursor-pointer">
                                                        <FileText class="w-3.5 h-3.5 text-gray-400" />
                                                        Ver historial ventas
                                                    </button>
                                                    <button @click="startNewSaleForClient(cliente)" class="flex w-full px-4 py-2 text-left text-xs hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors items-center gap-2 cursor-pointer font-bold text-blue-650">
                                                        <CreditCard class="w-3.5 h-3.5 text-blue-500" />
                                                        Nueva venta POS
                                                    </button>
                                                    
                                                    <div class="border-t border-gray-100 dark:border-gray-800 my-1"></div>
                                                    
                                                    <button 
                                                        v-if="cliente.nombre !== 'Público General'"
                                                        @click="deleteCliente(cliente)"
                                                        class="flex w-full px-4 py-2 text-left text-xs text-red-650 hover:bg-red-50 dark:hover:bg-red-950/20 font-bold transition-colors items-center gap-2 cursor-pointer"
                                                    >
                                                        <Trash2 class="w-3.5 h-3.5 text-red-500" />
                                                        Eliminar ficha
                                                    </button>
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

        <!-- 9 & 12. FORMULARIO NUEVO / EDITAR CLIENTE (Dos Columnas) -->
        <Modal :show="showModal" @close="closeModal" max-width="3xl">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-center justify-between pb-3 border-b border-gray-150 dark:border-gray-850 mb-5">
                    <div class="flex items-center gap-2">
                        <Users class="w-5 h-5 text-blue-600" />
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            {{ editMode ? 'Editar Cliente' : 'Nuevo Cliente' }}
                        </h3>
                    </div>
                    <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-650 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Columna 1: Información Personal / Básica -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-850 pb-2">
                                <span class="w-2 h-2 bg-blue-600 rounded-full"></span>
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">1. Datos Personales / Facturación</h4>
                            </div>

                            <!-- Documento Tipo y Número con Buscador Mock -->
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <InputLabel for="doc_tipo" value="Tipo Doc. *" class="text-xs font-bold" />
                                    <!-- Público General protects type modification -->
                                    <select
                                        id="doc_tipo"
                                        class="mt-1.5 block w-full h-10 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer"
                                        v-model="form.documento_tipo"
                                        :disabled="form.nombre === 'Público General'"
                                        required
                                    >
                                        <option value="DNI">DNI</option>
                                        <option value="RUC">RUC</option>
                                        <option value="CE">C.E.</option>
                                    </select>
                                </div>
                                <div class="col-span-2 relative">
                                    <InputLabel for="doc_num" value="Número Documento" class="text-xs font-bold" />
                                    <div class="relative mt-1.5">
                                        <TextInput
                                            id="doc_num"
                                            type="text"
                                            class="block w-full font-mono text-xs h-10 px-3 pr-24"
                                            v-model="form.documento_numero"
                                            placeholder="Ingresa número..."
                                            :disabled="form.nombre === 'Público General'"
                                            @blur="validateDocumentFormat"
                                        />
                                        <!-- 10. Autocompletar Trigger (Simulado) -->
                                        <button 
                                            v-if="form.nombre !== 'Público General' && (form.documento_numero?.length === 8 || form.documento_numero?.length === 11)"
                                            type="button"
                                            @click="simulateApiLookup"
                                            class="absolute inset-y-0 right-0 px-2.5 bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 font-extrabold text-[9px] rounded-r-xl border-l border-gray-200 dark:border-gray-700 flex items-center gap-1 select-none active:scale-95 transition-all"
                                        >
                                            <span v-if="isSearchingApi">Buscando...</span>
                                            <span v-else>🔍 Consultar</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Document Error / Success Alert -->
                            <span v-if="localDocumentError" class="text-[10px] text-red-600 font-bold block mt-1">
                                {{ localDocumentError }}
                            </span>
                            <span v-if="apiSuccessMessage" class="text-[9px] bg-emerald-50 text-emerald-800 border border-emerald-100 p-2 rounded-lg font-bold block mt-1">
                                {{ apiSuccessMessage }}
                            </span>

                            <!-- Nombre -->
                            <div>
                                <InputLabel for="nombre" value="Nombre Completo / Razón Social *" class="text-xs font-bold" />
                                <TextInput
                                    id="nombre"
                                    type="text"
                                    class="mt-1.5 block w-full text-xs h-10 px-3"
                                    v-model="form.nombre"
                                    placeholder="Ej. Consorcio Vega o Antony Morales"
                                    required
                                />
                                <InputError class="mt-1 text-xs" :message="form.errors.nombre" />
                            </div>

                            <!-- Teléfono -->
                            <div>
                                <InputLabel for="tel" value="Teléfono Móvil (9 dígitos)" class="text-xs font-bold" />
                                <TextInput
                                    id="tel"
                                    type="text"
                                    class="mt-1.5 block w-full text-xs h-10 px-3"
                                    v-model="form.telefono"
                                    placeholder="Ej. 987654321"
                                />
                            </div>

                            <!-- Correo -->
                            <div>
                                <InputLabel for="email" value="Correo Electrónico" class="text-xs font-bold" />
                                <TextInput
                                    id="email"
                                    type="email"
                                    class="mt-1.5 block w-full text-xs h-10 px-3 font-mono"
                                    v-model="form.correo"
                                    placeholder="cliente@correo.com"
                                />
                            </div>

                            <!-- Dirección -->
                            <div>
                                <InputLabel for="dir" value="Dirección Fiscal o Domicilio" class="text-xs font-bold" />
                                <TextInput
                                    id="dir"
                                    type="text"
                                    class="mt-1.5 block w-full text-xs h-10 px-3"
                                    v-model="clientDireccion"
                                    placeholder="Ej. Jr. Huancayo 231, Lima"
                                />
                            </div>
                        </div>

                        <!-- Columna 2: Información Comercial y Switches -->
                        <div class="space-y-4">
                            <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-855 pb-2">
                                <span class="w-2 h-2 bg-indigo-600 rounded-full"></span>
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">2. Perfil Comercial</h4>
                            </div>

                            <!-- Observaciones -->
                            <div>
                                <InputLabel for="obs" value="Observaciones / Comentarios del Cajero" class="text-xs font-bold text-gray-500" />
                                <textarea
                                    id="obs"
                                    rows="3"
                                    class="mt-1.5 block w-full border border-gray-250 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs py-2.5 px-3.5 transition-all outline-none"
                                    v-model="clientObservaciones"
                                    placeholder="Ej: Prefiere facturas por correo, socio VIP, paga con transferencia..."
                                ></textarea>
                            </div>

                            <!-- Estado (Si es Público General, inhabilitar desactivación) -->
                            <div>
                                <InputLabel for="estado" value="Estado de Cuenta" class="text-xs font-bold text-gray-500" />
                                <select
                                    id="estado"
                                    class="mt-1.5 block w-full h-10 border border-gray-250 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer"
                                    v-model="clientEstado"
                                    :disabled="form.nombre === 'Público General'"
                                >
                                    <option value="activo">Activo (Habilitado para compras)</option>
                                    <option value="inactivo">Inactivo / Bloqueado</option>
                                </select>
                            </div>

                            <!-- Switches comerciales -->
                            <div class="pt-2 space-y-4">
                                <!-- Cliente Frecuente Switch -->
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-950 border border-gray-150 dark:border-gray-850 rounded-2xl">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-extrabold text-gray-900 dark:text-white">Marcar como Cliente Frecuente</span>
                                        <span class="text-[10px] text-gray-400 mt-0.5">Le otorgará etiquetas de fidelización en boletas</span>
                                    </div>
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="checkbox" v-model="clientFrecuente" class="sr-only peer" />
                                        <div class="w-9 h-5 bg-gray-200 dark:bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-500 relative"></div>
                                    </label>
                                </div>

                                <!-- Permitir Crédito Switch -->
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-950 border border-gray-150 dark:border-gray-850 rounded-2xl">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-extrabold text-gray-900 dark:text-white">Habilitar Crédito Local</span>
                                        <span class="text-[10px] text-gray-400 mt-0.5">Permite compras con pago diferido (fiado)</span>
                                    </div>
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="checkbox" v-model="clientCredito" class="sr-only peer" />
                                        <div class="w-9 h-5 bg-gray-200 dark:bg-gray-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-blue-600 relative"></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-850">
                        <SecondaryButton @click="closeModal" :disabled="form.processing" class="rounded-xl h-11 px-5">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton :loading="form.processing" class="rounded-xl h-11 px-5">
                            {{ editMode ? 'Guardar Cambios' : 'Registrar Cliente' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 6. PANEL LATERAL DE HISTORIAL (Drawer) -->
        <div v-if="showDrawer && drawerCliente" class="fixed inset-0 overflow-hidden z-50 transition-opacity duration-300">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/35 backdrop-blur-2xs transition-opacity" @click="showDrawer = false"></div>
            
            <div class="absolute inset-y-0 right-0 pl-10 max-w-full flex">
                <!-- Drawer panel content -->
                <div class="w-screen max-w-md bg-white dark:bg-gray-900 shadow-2xl flex flex-col h-full transform transition-transform duration-300 animate-slide-in">
                    <!-- Drawer Header -->
                    <div class="p-6 border-b border-gray-150 dark:border-gray-800 flex justify-between items-center bg-gray-50/50 dark:bg-gray-950/20 shrink-0">
                        <div class="flex items-center gap-2.5">
                            <div 
                                class="w-9 h-9 rounded-full flex items-center justify-center font-extrabold text-xs uppercase"
                                :style="getAvatarStyle(drawerCliente.nombre)"
                            >
                                {{ getAvatarInitials(drawerCliente.nombre) }}
                            </div>
                            <div class="flex flex-col">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white truncate max-w-xs">{{ drawerCliente.nombre }}</h3>
                                <span class="text-[9px] text-gray-400 mt-0.5">Historial y auditoría de consumos</span>
                            </div>
                        </div>
                        <button @click="showDrawer = false" class="text-gray-400 hover:text-gray-650 transition-colors">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Drawer Body -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        <!-- 1. Información General -->
                        <div class="space-y-2">
                            <h4 class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Información General</h4>
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between border-b border-gray-50 pb-1">
                                    <span class="text-gray-450">Identificación:</span>
                                    <span class="font-bold text-gray-850 dark:text-gray-200">{{ drawerCliente.documento_tipo }} {{ drawerCliente.documento_numero || '-' }}</span>
                                </div>
                                <div class="flex justify-between border-b border-gray-50 pb-1">
                                    <span class="text-gray-450">Teléfono:</span>
                                    <span class="font-bold text-gray-850 dark:text-gray-200">{{ drawerCliente.telefono || '-' }}</span>
                                </div>
                                <div class="flex justify-between border-b border-gray-50 pb-1">
                                    <span class="text-gray-450">Correo:</span>
                                    <span class="font-mono text-gray-800 dark:text-gray-300">{{ drawerCliente.correo || '-' }}</span>
                                </div>
                                <div class="flex justify-between border-b border-gray-50 pb-1">
                                    <span class="text-gray-450">Dirección:</span>
                                    <span class="font-medium text-gray-800 dark:text-gray-300 max-w-xs text-right">{{ parseDireccion(drawerCliente.direccion).dir || '-' }}</span>
                                </div>
                                <div class="flex justify-between border-b border-gray-50 pb-1">
                                    <span class="text-gray-450">Observaciones:</span>
                                    <span class="font-medium text-gray-800 dark:text-gray-300 max-w-xs text-right">{{ parseDireccion(drawerCliente.direccion).observaciones || '-' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Resumen Financiero -->
                        <div class="space-y-3">
                            <h4 class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Historial Financiero</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="p-3 bg-gray-50 dark:bg-gray-950 border border-gray-150 dark:border-gray-850 rounded-xl">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">Total Comprado</span>
                                    <span class="text-base font-bold font-mono text-gray-900 dark:text-white mt-1 block">{{ formatCurrency(getClientTotalSpent(drawerCliente)) }}</span>
                                </div>
                                <div class="p-3 bg-gray-50 dark:bg-gray-950 border border-gray-150 dark:border-gray-850 rounded-xl">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">Ticket Promedio</span>
                                    <span class="text-base font-bold font-mono text-blue-600 dark:text-blue-400 mt-1 block">{{ formatCurrency(getClientTicketPromedio(drawerCliente)) }}</span>
                                </div>
                                <div class="p-3 bg-gray-50 dark:bg-gray-950 border border-gray-150 dark:border-gray-850 rounded-xl">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">Compras Registradas</span>
                                    <span class="text-base font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-1 block">{{ getClientSalesCount(drawerCliente) }}</span>
                                </div>
                                <div class="p-3 bg-gray-50 dark:bg-gray-950 border border-gray-150 dark:border-gray-850 rounded-xl">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">Crédito Habilitado</span>
                                    <span class="text-xs font-bold mt-1 block" :class="parseDireccion(drawerCliente.direccion).credito ? 'text-indigo-650' : 'text-gray-400'">{{ parseDireccion(drawerCliente.direccion).credito ? 'Habilitado' : 'No' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Productos Más Comprados -->
                        <div class="space-y-3">
                            <h4 class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Productos Favoritos</h4>
                            <div v-if="getClientTopProducts(drawerCliente).length > 0" class="space-y-2">
                                <div 
                                    v-for="(top, i) in getClientTopProducts(drawerCliente)" 
                                    :key="i"
                                    class="flex items-center justify-between p-2.5 border border-gray-100 dark:border-gray-850 rounded-xl text-xs"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="w-5 h-5 rounded-full bg-blue-50 dark:bg-blue-950/20 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-[10px] font-mono">{{ i + 1 }}</span>
                                        <span class="font-bold text-gray-850 dark:text-gray-250 truncate max-w-xs">{{ top.name }}</span>
                                    </div>
                                    <span class="font-mono text-gray-500 font-semibold">{{ top.qty }} und</span>
                                </div>
                            </div>
                            <div v-else class="text-xs text-gray-400 italic text-center p-3 border border-dashed border-gray-200 dark:border-gray-800 rounded-xl">
                                Sin registros de productos comprados.
                            </div>
                        </div>

                        <!-- 4. Compras Realizadas -->
                        <div class="space-y-3">
                            <h4 class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Historial de Folios</h4>
                            <div v-if="drawerCliente.ventas && drawerCliente.ventas.length > 0" class="space-y-2">
                                <div 
                                    v-for="v in drawerCliente.ventas" 
                                    :key="v.id"
                                    class="flex items-center justify-between p-3 border border-gray-100 dark:border-gray-855 rounded-xl text-xs hover:bg-gray-50/50"
                                >
                                    <div class="flex flex-col">
                                        <span class="font-bold font-mono text-gray-900 dark:text-white">{{ v.numero_venta }}</span>
                                        <span class="text-[9px] text-gray-450 font-mono mt-0.5">{{ new Date(v.fecha_venta).toLocaleDateString('es-PE') }} | {{ v.metodo_pago }}</span>
                                    </div>
                                    <span class="font-bold font-mono text-gray-900 dark:text-white">{{ formatCurrency(v.total) }}</span>
                                </div>
                            </div>
                            <div v-else class="text-xs text-gray-400 italic text-center p-3 border border-dashed border-gray-200 dark:border-gray-800 rounded-xl">
                                Sin tickets de ventas registrados.
                            </div>
                        </div>
                    </div>

                    <!-- Drawer Footer Actions -->
                    <div class="p-6 border-t border-gray-150 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-950/20 shrink-0 flex flex-col gap-2">
                        <button 
                            @click="showDrawer = false; startNewSaleForClient(drawerCliente)"
                            class="w-full justify-center px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs active:scale-98 flex items-center gap-1.5 cursor-pointer"
                        >
                            <CreditCard class="w-4 h-4" />
                            Iniciar Venta para este Cliente
                        </button>
                        <SecondaryButton @click="showDrawer = false" class="w-full justify-center rounded-xl h-11">
                            Cerrar Historial
                        </SecondaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Keyframes for Drawer animations */
@keyframes slide-in {
    0% {
        transform: translateX(100%);
    }
    100% {
        transform: translateX(0);
    }
}

.animate-slide-in {
    animation: slide-in 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
