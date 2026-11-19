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
    Truck, Plus, Search, AlertCircle, Phone, Mail, MapPin, 
    X, MoreVertical, Calendar, DollarSign, Activity, FileText, 
    Trash2, Edit3, ShieldCheck, CheckCircle2, ChevronRight, Briefcase,
    Clock, CreditCard, TrendingUp, Archive, Layers
} from 'lucide-vue-next';

const props = defineProps({
    proveedores: {
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
const filterTipo = ref('');
const filterEstado = ref('');
const selectedFilterStat = ref('todos'); // todos, activos, frecuentes, mayoristas
const isLoading = ref(true);

// Lateral Drawer slide-over state
const showDrawer = ref(false);
const drawerProveedor = ref(null);

// SUNAT search states
const isSearchingApi = ref(false);
const apiSuccessMessage = ref('');

// Main form fields
const form = useForm({
    id: null,
    nombre: '',
    ruc: '',
    direccion: '', // We serialize JSON here
    telefono: '',
    correo: '',
    estado: 'activo',
});

// Decoupled form UI fields
const clientDireccion = ref('');
const clientCiudad = ref('');
const clientDepartamento = ref('');
const clientContacto = ref('');
const clientCargo = ref('');
const clientTipo = ref('Distribuidor'); // Mayorista, Distribuidor, Fabricante, Importador
const clientObservaciones = ref('');

// Conditions of payment
const clientCondicionPago = ref('Contado'); // Contado, 7 dias, 15 dias, 30 dias, Personalizado
const clientTiempoEntrega = ref(2);
const clientDescuentoHabitual = ref(0);
const clientCompraMinima = ref(0);
const clientObservacionesComerciales = ref('');

// Duplicate document check
const localRucError = ref('');

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
        ciudad: '',
        departamento: '',
        contacto: '',
        cargo: '',
        tipo: 'Distribuidor',
        observaciones: '',
        condicion_pago: 'Contado',
        tiempo_entrega: 2,
        descuento_habitual: 0,
        compra_minima: 0,
        observaciones_comerciales: ''
    };
};

// Auto generate avatar initials
const getAvatarInitials = (name) => {
    if (!name) return '??';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[1][0]).toUpperCase();
    }
    return name.substr(0, 2).toUpperCase();
};

// Generates consistent background color for the avatar based on name hash
const getAvatarStyle = (name) => {
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const h = Math.abs(hash % 360);
    return `background-color: hsl(${h}, 55%, 40%); color: white;`;
};

// Metrics helpers
const getProveedorTotalSpent = (prov) => {
    if (!prov.compras) return 0;
    return prov.compras
        .filter(c => c.estado === 'completada')
        .reduce((sum, c) => sum + Number(c.total), 0);
};

const getProveedorComprasCount = (prov) => {
    if (!prov.compras) return 0;
    return prov.compras.filter(c => c.estado === 'completada').length;
};

const getProveedorLastPurchaseDate = (prov) => {
    if (!prov.compras || prov.compras.length === 0) return null;
    const sorted = [...prov.compras].sort((a, b) => new Date(b.fecha_compra) - new Date(a.fecha_compra));
    return sorted[0].fecha_compra;
};

const getProveedorAverageTicket = (prov) => {
    const count = getProveedorComprasCount(prov);
    if (count === 0) return 0;
    return getProveedorTotalSpent(prov) / count;
};

const getProveedorHighestTicket = (prov) => {
    if (!prov.compras || prov.compras.length === 0) return 0;
    return Math.max(...prov.compras.filter(c => c.estado === 'completada').map(c => Number(c.total)), 0);
};

// Supplier status tags
const isNewProveedor = (prov) => {
    if (!prov.created_at) return false;
    const regDate = new Date(prov.created_at);
    const now = new Date();
    return (now - regDate) / (1000 * 60 * 60 * 24) <= 30;
};

const isFrequentProveedor = (prov) => {
    return getProveedorComprasCount(prov) > 3;
};

const isPrincipalProveedor = (prov) => {
    return getProveedorTotalSpent(prov) >= 1500;
};

const hasNoRecentPurchases = (prov) => {
    const lastDate = getProveedorLastPurchaseDate(prov);
    if (!lastDate) return true;
    const diff = (new Date() - new Date(lastDate)) / (1000 * 60 * 60 * 24);
    return diff > 60; // 60 days
};

// Supplier products list
const getSupplierProducts = (prov) => {
    if (!prov.compras) return [];
    const productsMap = {};

    prov.compras.forEach(c => {
        if (c.estado === 'completada' && c.detalles) {
            c.detalles.forEach(d => {
                if (d.producto) {
                    const prodId = d.producto.id;
                    const prodName = d.producto.nombre;
                    const cost = Number(d.precio_unitario);
                    const qty = Number(d.cantidad);
                    const date = c.fecha_compra;

                    if (!productsMap[prodId]) {
                        productsMap[prodId] = {
                            id: prodId,
                            nombre: prodName,
                            ultimo_costo: cost,
                            ultima_cantidad: qty,
                            ultima_fecha: date,
                            total_qty: qty,
                            purchase_count: 1
                        };
                    } else {
                        productsMap[prodId].total_qty += qty;
                        productsMap[prodId].purchase_count += 1;
                        if (new Date(date) > new Date(productsMap[prodId].ultima_fecha)) {
                            productsMap[prodId].ultimo_costo = cost;
                            productsMap[prodId].ultima_cantidad = qty;
                            productsMap[prodId].ultima_fecha = date;
                        }
                    }
                }
            });
        }
    });

    return Object.values(productsMap)
        .map(p => ({
            ...p,
            cantidad_promedio: p.total_qty / p.purchase_count
        }))
        .sort((a, b) => b.purchase_count - a.purchase_count);
};

// Purchase stats inside drawer (Last 30 days & Last 6 months)
const getSpentInPeriod = (prov, days) => {
    if (!prov.compras) return 0;
    const threshold = new Date();
    threshold.setDate(threshold.getDate() - days);
    return prov.compras
        .filter(c => c.estado === 'completada' && new Date(c.fecha_compra) >= threshold)
        .reduce((sum, c) => sum + Number(c.total), 0);
};

// Dashboard counts
const totalProveedoresCount = computed(() => props.proveedores.length);

const activeProveedoresCount = computed(() => {
    return props.proveedores.filter(p => p.estado === 'activo').length;
});

const comprasEsteMesCount = computed(() => {
    const currentMonth = new Date().toISOString().substr(0, 7); // YYYY-MM
    let count = 0;
    props.proveedores.forEach(p => {
        if (p.compras) {
            count += p.compras.filter(c => c.estado === 'completada' && c.fecha_compra.startsWith(currentMonth)).length;
        }
    });
    return count;
});

const totalCompradoEsteMes = computed(() => {
    const currentMonth = new Date().toISOString().substr(0, 7);
    let total = 0;
    props.proveedores.forEach(p => {
        if (p.compras) {
            total += p.compras
                .filter(c => c.estado === 'completada' && c.fecha_compra.startsWith(currentMonth))
                .reduce((sum, c) => sum + Number(c.total), 0);
        }
    });
    return total;
});

const proveedorMasUtilizado = computed(() => {
    if (props.proveedores.length === 0) return null;
    let top = null;
    let max = -1;
    props.proveedores.forEach(p => {
        const count = getProveedorComprasCount(p);
        if (count > max) {
            max = count;
            top = p;
        }
    });
    return top;
});

// Intelligent List Filters
const filteredProveedores = computed(() => {
    let result = props.proveedores;

    // 1. Search Query
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(p => {
            const meta = parseDireccion(p.direccion);
            return p.nombre.toLowerCase().includes(query) ||
                   (p.ruc && p.ruc.includes(query)) ||
                   (p.correo && p.correo.toLowerCase().includes(query)) ||
                   (p.telefono && p.telefono.includes(query)) ||
                   (meta.contacto && meta.contacto.toLowerCase().includes(query));
        });
    }

    // 2. Select Filter Tipo
    if (filterTipo.value) {
        result = result.filter(p => {
            const meta = parseDireccion(p.direccion);
            return meta.tipo === filterTipo.value;
        });
    }

    // 3. Select Filter Estado
    if (filterEstado.value) {
        result = result.filter(p => p.estado === filterEstado.value);
    }

    // 4. Clickable Dashboard Metrics filters
    if (selectedFilterStat.value === 'activos') {
        result = result.filter(p => p.estado === 'activo');
    } else if (selectedFilterStat.value === 'frecuentes') {
        result = result.filter(isFrequentProveedor);
    } else if (selectedFilterStat.value === 'vip') {
        result = result.filter(isPrincipalProveedor);
    }

    return result;
});

// Modal Actions
const openCreateModal = () => {
    form.clearErrors();
    localRucError.value = '';
    apiSuccessMessage.value = '';
    
    form.reset({
        id: null,
        nombre: '',
        ruc: '',
        direccion: '',
        telefono: '',
        correo: '',
        estado: 'activo',
    });

    clientDireccion.value = '';
    clientCiudad.value = '';
    clientDepartamento.value = '';
    clientContacto.value = '';
    clientCargo.value = '';
    clientTipo.value = 'Distribuidor';
    clientObservaciones.value = '';
    clientCondicionPago.value = 'Contado';
    clientTiempoEntrega.value = 2;
    clientDescuentoHabitual.value = 0;
    clientCompraMinima.value = 0;
    clientObservacionesComerciales.value = '';

    editMode.value = false;
    showModal.value = true;
};

const openEditModal = (prov) => {
    form.clearErrors();
    localRucError.value = '';
    apiSuccessMessage.value = '';

    const meta = parseDireccion(prov.direccion);

    form.reset({
        id: prov.id,
        nombre: prov.nombre,
        ruc: prov.ruc || '',
        direccion: prov.direccion || '',
        telefono: prov.telefono || '',
        correo: prov.correo || '',
        estado: prov.estado,
    });

    clientDireccion.value = meta.dir;
    clientCiudad.value = meta.ciudad || '';
    clientDepartamento.value = meta.departamento || '';
    clientContacto.value = meta.contacto || '';
    clientCargo.value = meta.cargo || '';
    clientTipo.value = meta.tipo || 'Distribuidor';
    clientObservaciones.value = meta.observaciones || '';
    clientCondicionPago.value = meta.condicion_pago || 'Contado';
    clientTiempoEntrega.value = meta.tiempo_entrega || 2;
    clientDescuentoHabitual.value = meta.descuento_habitual || 0;
    clientCompraMinima.value = meta.compra_minima || 0;
    clientObservacionesComerciales.value = meta.observaciones_comerciales || '';

    editMode.value = true;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
};

const validateRucFormat = () => {
    localRucError.value = '';
    if (form.ruc && form.ruc.length !== 11) {
        localRucError.value = 'El RUC debe tener exactamente 11 dígitos.';
        return false;
    }
    
    // Check duplicates
    const dupe = props.proveedores.find(p => 
        p.ruc === form.ruc && 
        p.ruc !== '' && 
        p.id !== form.id
    );
    if (dupe) {
        localRucError.value = `Este número de RUC ya está registrado para el proveedor "${dupe.nombre}".`;
        return false;
    }

    return true;
};

const submit = () => {
    if (!validateRucFormat()) return;

    // Serialize extra fields into direccion
    form.direccion = JSON.stringify({
        dir: clientDireccion.value,
        ciudad: clientCiudad.value,
        departamento: clientDepartamento.value,
        contacto: clientContacto.value,
        cargo: clientCargo.value,
        tipo: clientTipo.value,
        observaciones: clientObservaciones.value,
        condicion_pago: clientCondicionPago.value,
        tiempo_entrega: Number(clientTiempoEntrega.value),
        descuento_habitual: Number(clientDescuentoHabitual.value),
        compra_minima: Number(clientCompraMinima.value),
        observaciones_comerciales: clientObservacionesComerciales.value
    });

    if (editMode.value) {
        form.put(route('proveedores.update', form.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('proveedores.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteProveedor = (prov) => {
    if (confirm(`¿Estás seguro de que deseas eliminar al proveedor "${prov.nombre}"?`)) {
        form.delete(route('proveedores.destroy', prov.id));
    }
};

const toggleProveedorEstado = (prov) => {
    const meta = parseDireccion(prov.direccion);
    const newEstado = prov.estado === 'activo' ? 'inactivo' : 'activo';

    router.put(route('proveedores.update', prov.id), {
        nombre: prov.nombre,
        ruc: prov.ruc,
        direccion: prov.direccion,
        telefono: prov.telefono,
        correo: prov.correo,
        estado: newEstado,
    });
};

const openHistory = (prov) => {
    drawerProveedor.value = prov;
    showDrawer.value = true;
};

const startNewPurchaseForProveedor = (prov) => {
    // Redireccionar a Compras pre-seleccionando el proveedor
    router.visit(route('compras.index', { proveedor_id: prov.id }));
};

// SUNAT Simulator integration
const simulateSunatLookup = () => {
    if (!form.ruc) return;
    
    isSearchingApi.value = true;
    apiSuccessMessage.value = '';
    
    setTimeout(() => {
        isSearchingApi.value = false;
        if (form.ruc.length === 11) {
            if (form.ruc.startsWith('20')) {
                form.nombre = 'DISTRIBUIDORA DE ALIMENTOS LIMA NORTE S.A.C.';
                clientDireccion.value = 'AV. TUPAC AMARU NRO. 1450, COMAS, LIMA';
                clientCiudad.value = 'LIMA';
                clientDepartamento.value = 'LIMA';
                clientTipo.value = 'Mayorista';
                clientObservaciones.value = 'Validado por SUNAT - Activo y Habido';
                apiSuccessMessage.value = 'Datos obtenidos desde SUNAT: Contribuyente ACTIVO / HABIDO.';
            } else {
                form.nombre = 'PRODUCTOS LÁCTEOS ANDINOS E.I.R.L.';
                clientDireccion.value = 'AV. PARTICIPACIÓN S/N - MERCADO CENTRAL DE LORETO';
                clientCiudad.value = 'IQUITOS';
                clientDepartamento.value = 'LORETO';
                clientTipo.value = 'Fabricante';
                clientObservaciones.value = 'Validado por SUNAT - Contribuyente Activo';
                apiSuccessMessage.value = 'Datos obtenidos desde SUNAT: Contribuyente ACTIVO / HABIDO.';
            }
        } else {
            localRucError.value = 'El RUC debe tener 11 dígitos para consultar SUNAT.';
        }
    }, 750);
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(val);
};
</script>

<template>
    <Head title="Proveedores" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">Directorio de Proveedores</h2>
                    <p class="text-xs text-gray-500 mt-0.5 font-medium">Controla tus socios logísticos, plazos de entrega y costos históricos.</p>
                </div>
                <PrimaryButton v-if="proveedores.length > 0" @click="openCreateModal" class="h-9 rounded-xl">
                    <Plus class="w-4 h-4 me-1.5" />
                    Nuevo Proveedor
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
            <div v-if="isLoading" class="grid grid-cols-2 md:grid-cols-5 gap-4 animate-pulse">
                <div v-for="i in 5" :key="i" class="h-20 bg-gray-100 dark:bg-gray-800 rounded-2xl"></div>
            </div>

            <!-- 1. DASHBOARD DE PROVEEDORES (Tarjetas Superiores) -->
            <div v-else-if="proveedores.length > 0" class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <!-- Total Proveedores -->
                <button 
                    @click="selectedFilterStat = 'todos'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between cursor-pointer outline-none text-gray-500"
                    :class="selectedFilterStat === 'todos' ? 'border-blue-500 ring-2 ring-blue-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Total Proveedores</span>
                    <h4 class="text-xl font-bold text-gray-900 dark:text-white mt-1">{{ totalProveedoresCount }}</h4>
                    <span class="text-[9px] text-gray-400 mt-1 block">Logística & marcas activas</span>
                </button>

                <!-- Proveedores Activos -->
                <button 
                    @click="selectedFilterStat = 'activos'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between cursor-pointer outline-none text-gray-500"
                    :class="selectedFilterStat === 'activos' ? 'border-emerald-500 ring-2 ring-emerald-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Proveedores Activos</span>
                    <h4 class="text-xl font-bold text-emerald-600 dark:text-emerald-450 mt-1">{{ activeProveedoresCount }}</h4>
                    <span class="text-[9px] text-gray-400 mt-1 block">Habilitados para compras</span>
                </button>

                <!-- Compras este mes -->
                <button 
                    @click="selectedFilterStat = 'todos'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between cursor-pointer outline-none text-gray-500"
                    :class="selectedFilterStat === 'todos' ? 'border-indigo-500 ring-1 ring-indigo-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Compras este Mes</span>
                    <h4 class="text-xl font-bold text-indigo-600 dark:text-indigo-400 mt-1">{{ comprasEsteMesCount }}</h4>
                    <span class="text-[9px] text-gray-400 mt-1 block">Folios de compras recibidos</span>
                </button>

                <!-- Total comprado este mes -->
                <button 
                    @click="selectedFilterStat = 'vip'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between cursor-pointer outline-none text-gray-500"
                    :class="selectedFilterStat === 'vip' ? 'border-amber-500 ring-2 ring-amber-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Inversión este Mes</span>
                    <h4 class="text-xl font-bold text-amber-600 dark:text-amber-450 mt-1">{{ formatCurrency(totalCompradoEsteMes) }}</h4>
                    <span class="text-[9px] text-gray-400 mt-1 block">Invertido este mes en almacén</span>
                </button>

                <!-- Proveedor más utilizado -->
                <button 
                    v-if="proveedorMasUtilizado"
                    @click="openHistory(proveedorMasUtilizado)"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex flex-col justify-between cursor-pointer outline-none text-gray-500 border-gray-250 dark:border-gray-800"
                >
                    <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Socio Logístico Principal</span>
                    <h4 class="text-xs font-black text-gray-900 dark:text-white mt-1.5 truncate">{{ proveedorMasUtilizado.nombre }}</h4>
                    <span class="text-[9px] text-blue-600 dark:text-blue-400 mt-1 block font-bold">Ver historial →</span>
                </button>
            </div>

            <!-- EMPTY STATE -->
            <div v-if="proveedores.length === 0" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-12 shadow-3xs flex flex-col items-center text-center max-w-lg mx-auto mt-8">
                <div class="w-16 h-16 bg-blue-50 dark:bg-blue-950/30 text-blue-600 rounded-2xl flex items-center justify-center mb-6">
                    <Truck class="w-8 h-8" />
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">No existen proveedores registrados</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 mb-6 max-w-xs leading-relaxed">Registra a tus distribuidores mayoristas de bebidas, abarrotes o insumos para agilizar el registro de mercadería.</p>
                <PrimaryButton @click="openCreateModal" class="rounded-xl">
                    <Plus class="w-4 h-4 me-1.5" />
                    Registrar primer proveedor
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
                                    placeholder="Buscar por Razón Social, RUC, Contacto..."
                                />
                            </div>

                            <!-- Tipo Filter -->
                            <select
                                v-model="filterTipo"
                                class="h-10 border border-gray-255 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl text-xs px-3 transition-colors outline-none cursor-pointer"
                            >
                                <option value="">Cualquier Tipo</option>
                                <option value="Mayorista">Mayorista</option>
                                <option value="Distribuidor">Distribuidor</option>
                                <option value="Fabricante">Fabricante</option>
                                <option value="Importador">Importador</option>
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

                    <!-- 2. Tabla de Proveedores Rediseñada -->
                    <div class="relative overflow-x-auto rounded-xl border border-gray-150 dark:border-gray-800">
                        <table class="w-full text-xs text-left text-gray-500 dark:text-gray-450 border-collapse">
                            <thead class="text-[10px] text-gray-400 uppercase tracking-wider font-bold bg-gray-50/70 dark:bg-gray-950 sticky top-0 z-10 border-b border-gray-150 dark:border-gray-800">
                                <tr>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Proveedor</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">RUC</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Contacto Principal</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Última Compra</th>
                                    <th scope="col" class="px-6 py-4 text-center font-bold text-gray-500">Compras</th>
                                    <th scope="col" class="px-6 py-4 text-right font-bold text-gray-500">Total Comprado</th>
                                    <th scope="col" class="px-6 py-4 text-center font-bold text-gray-500">Estado</th>
                                    <th scope="col" class="px-6 py-4 text-right font-bold text-gray-500 w-16">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                <tr 
                                    v-for="prov in filteredProveedores" 
                                    :key="prov.id"
                                    class="hover:bg-gray-50/50 dark:hover:bg-gray-950/40 transition-colors duration-150"
                                    :class="prov.estado === 'inactivo' ? 'bg-gray-50/40 dark:bg-gray-950/10' : ''"
                                >
                                    <!-- 3. Proveedor (Avatar automático + Badges) -->
                                    <th scope="row" class="px-6 py-5 font-semibold text-gray-900 dark:text-white cursor-pointer" @click="openHistory(prov)">
                                        <div class="flex items-center gap-3">
                                            <div 
                                                class="w-10 h-10 rounded-full flex items-center justify-center font-extrabold text-sm shadow-3xs uppercase shrink-0 transition-transform duration-250 hover:scale-105"
                                                :style="getAvatarStyle(prov.nombre)"
                                            >
                                                {{ getAvatarInitials(prov.nombre) }}
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-xs font-bold text-gray-900 dark:text-white hover:text-blue-650 transition-colors">
                                                    {{ prov.nombre }}
                                                </span>
                                                <div class="flex flex-wrap gap-1 mt-1 items-center">
                                                    <!-- Tipo de proveedor tag -->
                                                    <span 
                                                        class="inline-flex items-center px-1.2 py-0.2 rounded text-[7px] font-bold border tracking-wide uppercase bg-purple-50 text-purple-700 border-purple-100 dark:bg-purple-950/20 dark:text-purple-400"
                                                    >
                                                        {{ parseDireccion(prov.direccion).tipo }}
                                                    </span>

                                                    <!-- Frecuente switch tag -->
                                                    <span 
                                                        v-if="isFrequentProveedor(prov)"
                                                        class="inline-flex items-center px-1.2 py-0.2 rounded text-[7px] font-bold border tracking-wide uppercase bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-400"
                                                    >
                                                        Frecuente
                                                    </span>

                                                    <!-- Principal check tag -->
                                                    <span 
                                                        v-if="isPrincipalProveedor(prov)"
                                                        class="inline-flex items-center px-1.2 py-0.2 rounded text-[7px] font-bold border tracking-wide uppercase bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-950/20 dark:text-amber-400"
                                                    >
                                                        Principal
                                                    </span>

                                                    <!-- Nuevo Check tag -->
                                                    <span 
                                                        v-if="isNewProveedor(prov)"
                                                        class="inline-flex items-center px-1.2 py-0.2 rounded text-[7px] font-bold border tracking-wide uppercase bg-indigo-50 text-indigo-700 border-indigo-100 dark:bg-indigo-950/20 dark:text-indigo-400"
                                                    >
                                                        Nuevo
                                                    </span>

                                                    <!-- Sin compras recientes tag -->
                                                    <span 
                                                        v-if="hasNoRecentPurchases(prov)"
                                                        class="inline-flex items-center px-1.2 py-0.2 rounded text-[7px] font-bold border tracking-wide uppercase bg-red-50 text-red-700 border-red-100 dark:bg-red-950/20 dark:text-red-400"
                                                    >
                                                        Sin Compras
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </th>

                                    <!-- RUC -->
                                    <td class="px-6 py-5 text-gray-700 dark:text-gray-400 font-mono" :class="prov.estado === 'inactivo' ? 'opacity-50' : ''">
                                        {{ prov.ruc || '-' }}
                                    </td>

                                    <!-- Contacto Principal -->
                                    <td class="px-6 py-5 text-gray-650 dark:text-gray-350" :class="prov.estado === 'inactivo' ? 'opacity-50' : ''">
                                        <div class="font-bold text-gray-800 dark:text-gray-200">
                                            {{ parseDireccion(prov.direccion).contacto || 'Sin contacto' }}
                                        </div>
                                        <div v-if="prov.telefono" class="flex items-center mt-1 text-[10px] text-gray-450">
                                            <Phone class="w-3 h-3 text-gray-400 me-1 shrink-0" />
                                            <span>{{ prov.telefono }}</span>
                                        </div>
                                    </td>

                                    <!-- Última Compra -->
                                    <td class="px-6 py-5 text-gray-600 dark:text-gray-400 font-mono" :class="prov.estado === 'inactivo' ? 'opacity-50' : ''">
                                        {{ getProveedorLastPurchaseDate(prov) ? new Date(getProveedorLastPurchaseDate(prov)).toLocaleDateString('es-PE') : 'Nunca' }}
                                    </td>

                                    <!-- Compras realizadas -->
                                    <td class="px-6 py-5 text-center font-mono font-bold" :class="prov.estado === 'inactivo' ? 'opacity-50' : ''">
                                        {{ getProveedorComprasCount(prov) }}
                                    </td>

                                    <!-- Total Comprado -->
                                    <td class="px-6 py-5 text-right font-extrabold text-gray-900 dark:text-white font-mono" :class="prov.estado === 'inactivo' ? 'opacity-50' : ''">
                                        {{ formatCurrency(getProveedorTotalSpent(prov)) }}
                                    </td>

                                    <!-- Estado -->
                                    <td class="px-6 py-5 text-center">
                                        <span 
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-bold border uppercase tracking-wider cursor-pointer"
                                            :class="prov.estado !== 'inactivo' 
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-450 dark:border-emerald-900/30' 
                                                : 'bg-red-50 text-red-700 border-red-100 dark:bg-red-950/20 dark:text-red-405 dark:border-red-900/30'"
                                            @click="toggleProveedorEstado(prov)"
                                        >
                                            {{ prov.estado }}
                                        </span>
                                    </td>

                                    <!-- 6. Acciones Contextuales -->
                                    <td class="px-6 py-5 text-right whitespace-nowrap">
                                        <Dropdown align="right" width="48">
                                            <template #trigger>
                                                <button class="inline-flex items-center justify-center p-2 text-gray-500 hover:text-gray-750 dark:text-gray-400 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-850 transition-colors border border-gray-100 dark:border-gray-800">
                                                    <MoreVertical class="w-4 h-4" />
                                                </button>
                                            </template>
                                            <template #content>
                                                <div class="py-1 text-gray-700 dark:text-gray-300">
                                                    <button @click="openEditModal(prov)" class="flex w-full px-4 py-2 text-left text-xs hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors items-center gap-2 cursor-pointer">
                                                        <Edit3 class="w-3.5 h-3.5 text-gray-400" />
                                                        Editar
                                                    </button>
                                                    <button @click="openHistory(prov)" class="flex w-full px-4 py-2 text-left text-xs hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors items-center gap-2 cursor-pointer">
                                                        <FileText class="w-3.5 h-3.5 text-gray-400" />
                                                        Ver historial
                                                    </button>
                                                    <!-- 7. Registrar Compra Rápido -->
                                                    <button @click="startNewPurchaseForProveedor(prov)" class="flex w-full px-4 py-2 text-left text-xs hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors items-center gap-2 cursor-pointer font-bold text-blue-650">
                                                        <Plus class="w-3.5 h-3.5 text-blue-500" />
                                                        Registrar compra
                                                    </button>
                                                    
                                                    <div class="border-t border-gray-100 dark:border-gray-800 my-1"></div>
                                                    
                                                    <button 
                                                        @click="deleteProveedor(prov)"
                                                        class="flex w-full px-4 py-2 text-left text-xs text-red-650 hover:bg-red-50 dark:hover:bg-red-950/20 font-bold transition-colors items-center gap-2 cursor-pointer"
                                                    >
                                                        <Trash2 class="w-3.5 h-3.5 text-red-500" />
                                                        Eliminar
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

        <!-- 9, 12 & 18. FORMULARIO NUEVO / EDITAR PROVEEDOR (Dos Columnas) -->
        <Modal :show="showModal" @close="closeModal" max-width="3xl">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-center justify-between pb-3 border-b border-gray-150 dark:border-gray-850 mb-5">
                    <div class="flex items-center gap-2">
                        <Truck class="w-5 h-5 text-blue-650" />
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            {{ editMode ? 'Editar Proveedor' : 'Nuevo Proveedor' }}
                        </h3>
                    </div>
                    <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-650 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Columna 1: Información Fiscal / General -->
                        <div class="space-y-3.5">
                            <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-850 pb-2">
                                <span class="w-2 h-2 bg-blue-650 rounded-full"></span>
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">1. Datos Fiscales</h4>
                            </div>

                            <!-- RUC con Buscador SUNAT -->
                            <div class="relative">
                                <InputLabel for="ruc" value="Número RUC *" class="text-xs font-bold" />
                                <div class="relative mt-1">
                                    <TextInput
                                        id="ruc"
                                        type="text"
                                        class="block w-full font-mono text-xs h-9 px-3 pr-24"
                                        v-model="form.ruc"
                                        placeholder="11 dígitos"
                                        @blur="validateRucFormat"
                                        required
                                    />
                                    <!-- 10. Autocompletar Trigger (Simulado) -->
                                    <button 
                                        v-if="form.ruc?.length === 11"
                                        type="button"
                                        @click="simulateSunatLookup"
                                        class="absolute inset-y-0 right-0 px-2.5 bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 font-extrabold text-[9px] rounded-r-xl border-l border-gray-200 dark:border-gray-700 flex items-center gap-1 select-none active:scale-95 transition-all"
                                    >
                                        <span v-if="isSearchingApi">Buscando...</span>
                                        <span v-else>🔍 SUNAT</span>
                                    </button>
                                </div>
                                <span v-if="localRucError" class="text-[9px] text-red-600 font-bold block mt-1">
                                    {{ localRucError }}
                                </span>
                                <span v-if="apiSuccessMessage" class="text-[9px] bg-emerald-50 text-emerald-800 border border-emerald-100 p-2 rounded-lg font-bold block mt-1">
                                    {{ apiSuccessMessage }}
                                </span>
                            </div>

                            <!-- Razón Social -->
                            <div>
                                <InputLabel for="nombre" value="Razón Social / Nombre *" class="text-xs font-bold" />
                                <TextInput
                                    id="nombre"
                                    type="text"
                                    class="mt-1 block w-full text-xs h-9 px-3"
                                    v-model="form.nombre"
                                    placeholder="Nombre del proveedor o marca"
                                    required
                                />
                                <InputError class="mt-1 text-xs" :message="form.errors.nombre" />
                            </div>

                            <!-- Tipo de proveedor -->
                            <div>
                                <InputLabel for="tipo" value="Clasificación / Tipo Proveedor" class="text-xs font-bold" />
                                <select
                                    id="tipo"
                                    class="mt-1 block w-full h-9 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer"
                                    v-model="clientTipo"
                                >
                                    <option value="Mayorista">Mayorista</option>
                                    <option value="Distribuidor">Distribuidor</option>
                                    <option value="Fabricante">Fabricante</option>
                                    <option value="Importador">Importador</option>
                                </select>
                            </div>

                            <!-- Estado de cuenta -->
                            <div>
                                <InputLabel for="estado" value="Estado de Cuenta" class="text-xs font-bold" />
                                <select
                                    id="estado"
                                    class="mt-1 block w-full h-9 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer"
                                    v-model="form.estado"
                                >
                                    <option value="activo">Activo (Habilitado para compras)</option>
                                    <option value="inactivo">Inactivo / Bloqueado</option>
                                </select>
                            </div>

                            <!-- Observaciones básicas -->
                            <div>
                                <InputLabel for="obs" value="Observaciones Generales" class="text-xs font-bold text-gray-500" />
                                <textarea
                                    id="obs"
                                    rows="2"
                                    maxlength="100"
                                    class="mt-1 block w-full border border-gray-250 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs py-2 px-3 transition-all outline-none"
                                    v-model="clientObservaciones"
                                    placeholder="Detalles sobre entregas..."
                                ></textarea>
                            </div>
                        </div>

                        <!-- Columna 2: Información Comercial & Condiciones -->
                        <div class="space-y-3.5">
                            <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-855 pb-2">
                                <span class="w-2 h-2 bg-indigo-600 rounded-full"></span>
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">2. Datos Comerciales & Contacto</h4>
                            </div>

                            <!-- Contacto Nombre y Cargo -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <InputLabel for="contacto" value="Contacto Principal" class="text-xs font-bold" />
                                    <TextInput
                                        id="contacto"
                                        type="text"
                                        class="mt-1 block w-full text-xs h-9 px-3"
                                        v-model="clientContacto"
                                        placeholder="Nombre gestor"
                                    />
                                </div>
                                <div>
                                    <InputLabel for="cargo" value="Cargo / Puesto" class="text-xs font-bold" />
                                    <TextInput
                                        id="cargo"
                                        type="text"
                                        class="mt-1 block w-full text-xs h-9 px-3"
                                        v-model="clientCargo"
                                        placeholder="Ej: Ejecutivo ventas"
                                    />
                                </div>
                            </div>

                            <!-- Teléfono y Correo -->
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <InputLabel for="tel" value="Teléfono Móvil" class="text-xs font-bold" />
                                    <TextInput
                                        id="tel"
                                        type="text"
                                        class="mt-1 block w-full text-xs h-9 px-3"
                                        v-model="form.telefono"
                                        placeholder="987654321"
                                    />
                                </div>
                                <div>
                                    <InputLabel for="correo" value="Correo" class="text-xs font-bold" />
                                    <TextInput
                                        id="correo"
                                        type="email"
                                        class="mt-1 block w-full text-xs h-9 px-3 font-mono"
                                        v-model="form.correo"
                                        placeholder="ventas@proveedor.com"
                                    />
                                </div>
                            </div>

                            <!-- Dirección y Ciudad / Dpto -->
                            <div>
                                <InputLabel for="dir" value="Dirección Fiscal / Almacén" class="text-xs font-bold" />
                                <TextInput
                                    id="dir"
                                    type="text"
                                    class="mt-1 block w-full text-xs h-9 px-3"
                                    v-model="clientDireccion"
                                    placeholder="Av. Principal 123"
                                />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <InputLabel for="ciudad" value="Ciudad" class="text-xs font-bold" />
                                    <TextInput
                                        id="ciudad"
                                        type="text"
                                        class="mt-1 block w-full text-xs h-9 px-3"
                                        v-model="clientCiudad"
                                        placeholder="Ej: Lima"
                                    />
                                </div>
                                <div>
                                    <InputLabel for="dpto" value="Departamento" class="text-xs font-bold" />
                                    <TextInput
                                        id="dpto"
                                        type="text"
                                        class="mt-1 block w-full text-xs h-9 px-3"
                                        v-model="clientDepartamento"
                                        placeholder="Ej: Lima"
                                    />
                                </div>
                            </div>

                            <!-- Condiciones Comerciales (Adicionales) -->
                            <div class="pt-2 border-t border-gray-100 dark:border-gray-800 space-y-3">
                                <h5 class="text-[10px] font-bold text-indigo-500 uppercase tracking-wide">🏷️ Condiciones de Compra</h5>
                                
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <InputLabel for="cond_pago" value="Cond. Pago" class="text-[10px] font-bold text-gray-400" />
                                        <select
                                            id="cond_pago"
                                            class="mt-1 block w-full h-8 border border-gray-200 dark:border-gray-800 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-[11px] px-2 outline-none cursor-pointer"
                                            v-model="clientCondicionPago"
                                        >
                                            <option value="Contado">Contado</option>
                                            <option value="7 días">7 días</option>
                                            <option value="15 días">15 días</option>
                                            <option value="30 días">30 días</option>
                                            <option value="Personalizado">Personalizado</option>
                                        </select>
                                    </div>
                                    <div>
                                        <InputLabel for="entrega" value="Entrega (días)" class="text-[10px] font-bold text-gray-400" />
                                        <input
                                            id="entrega"
                                            type="number"
                                            min="0"
                                            class="mt-1 block w-full h-8 border border-gray-200 dark:border-gray-850 dark:bg-gray-900 dark:text-white rounded-lg font-mono text-[11px] px-2 focus:outline-none"
                                            v-model.number="clientTiempoEntrega"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel for="desc" value="Descuento (%)" class="text-[10px] font-bold text-gray-400" />
                                        <input
                                            id="desc"
                                            type="number"
                                            min="0"
                                            max="100"
                                            class="mt-1 block w-full h-8 border border-gray-200 dark:border-gray-855 dark:bg-gray-900 dark:text-white rounded-lg font-mono text-[11px] px-2 focus:outline-none"
                                            v-model.number="clientDescuentoHabitual"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <InputLabel for="comp_min" value="Compra Mínima (S/.)" class="text-[10px] font-bold text-gray-400" />
                                        <input
                                            id="comp_min"
                                            type="number"
                                            min="0"
                                            class="mt-1 block w-full h-8 border border-gray-200 dark:border-gray-855 dark:bg-gray-900 dark:text-white rounded-lg font-mono text-[11px] px-2 focus:outline-none"
                                            v-model.number="clientCompraMinima"
                                        />
                                    </div>
                                    <div>
                                        <InputLabel for="obs_c" value="Notas Comerciales" class="text-[10px] font-bold text-gray-405" />
                                        <input
                                            id="obs_c"
                                            type="text"
                                            maxlength="50"
                                            class="mt-1 block w-full h-8 border border-gray-200 dark:border-gray-855 dark:bg-gray-900 dark:text-white rounded-lg text-[11px] px-2 focus:outline-none"
                                            v-model="clientObservacionesComerciales"
                                            placeholder="Detalle crédito local..."
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-850">
                        <SecondaryButton @click="closeModal" :disabled="form.processing" class="rounded-xl h-11 px-5">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton :loading="form.processing" class="rounded-xl h-11 px-5">
                            {{ editMode ? 'Guardar Cambios' : 'Registrar Proveedor' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 5, 13, 15, 19 & 20. PANEL LATERAL DE DETALLE (Drawer) -->
        <div v-if="showDrawer && drawerProveedor" class="fixed inset-0 overflow-hidden z-50 transition-opacity duration-300">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/35 backdrop-blur-2xs transition-opacity" @click="showDrawer = false"></div>
            
            <div class="absolute inset-y-0 right-0 pl-10 max-w-full flex">
                <div class="w-screen max-w-lg bg-white dark:bg-gray-900 shadow-2xl flex flex-col h-full transform transition-transform duration-300 animate-slide-in">
                    <!-- Drawer Header -->
                    <div class="p-6 border-b border-gray-150 dark:border-gray-800 flex justify-between items-center bg-gray-50/50 dark:bg-gray-950/20 shrink-0">
                        <div class="flex items-center gap-2.5">
                            <div 
                                class="w-9 h-9 rounded-full flex items-center justify-center font-extrabold text-xs uppercase"
                                :style="getAvatarStyle(drawerProveedor.nombre)"
                            >
                                {{ getAvatarInitials(drawerProveedor.nombre) }}
                            </div>
                            <div class="flex flex-col">
                                <h3 class="text-sm font-bold text-gray-900 dark:text-white truncate max-w-xs">{{ drawerProveedor.nombre }}</h3>
                                <span class="text-[9px] text-gray-400 mt-0.5">Ficha de Abastecimiento & Auditoría</span>
                            </div>
                        </div>
                        <button @click="showDrawer = false" class="text-gray-400 hover:text-gray-650 transition-colors">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Drawer Body -->
                    <div class="flex-1 overflow-y-auto p-6 space-y-6">
                        <!-- 1. Información General y Fiscal -->
                        <div class="space-y-2">
                            <h4 class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Ficha Informativa</h4>
                            <div class="space-y-2 text-xs">
                                <div class="flex justify-between border-b border-gray-50 pb-1">
                                    <span class="text-gray-450">RUC:</span>
                                    <span class="font-bold text-gray-850 dark:text-gray-250 font-mono">{{ drawerProveedor.ruc || '-' }}</span>
                                </div>
                                <div class="flex justify-between border-b border-gray-50 pb-1">
                                    <span class="text-gray-450">Clasificación:</span>
                                    <span class="font-bold text-gray-800 dark:text-gray-300">{{ parseDireccion(drawerProveedor.direccion).tipo }}</span>
                                </div>
                                <div class="flex justify-between border-b border-gray-50 pb-1">
                                    <span class="text-gray-450">Contacto Principal:</span>
                                    <span class="font-bold text-gray-850 dark:text-gray-205">{{ parseDireccion(drawerProveedor.direccion).contacto || '-' }}</span>
                                </div>
                                <div class="flex justify-between border-b border-gray-50 pb-1">
                                    <span class="text-gray-450">Cargo:</span>
                                    <span class="font-medium text-gray-800 dark:text-gray-300">{{ parseDireccion(drawerProveedor.direccion).cargo || '-' }}</span>
                                </div>
                                <div class="flex justify-between border-b border-gray-50 pb-1">
                                    <span class="text-gray-450">Teléfono:</span>
                                    <span class="font-bold text-gray-850 dark:text-gray-200">{{ drawerProveedor.telefono || '-' }}</span>
                                </div>
                                <div class="flex justify-between border-b border-gray-50 pb-1">
                                    <span class="text-gray-450">Dirección Fiscal:</span>
                                    <span class="font-medium text-gray-800 dark:text-gray-300 max-w-xs text-right">{{ parseDireccion(drawerProveedor.direccion).dir || '-' }}</span>
                                </div>
                                <div class="flex justify-between border-b border-gray-50 pb-1">
                                    <span class="text-gray-450">Ubigeo:</span>
                                    <span class="font-medium text-gray-800 dark:text-gray-300">{{ parseDireccion(drawerProveedor.direccion).ciudad || '-' }}, {{ parseDireccion(drawerProveedor.direccion).departamento || '-' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Condiciones Comerciales -->
                        <div class="space-y-2 bg-indigo-50/30 dark:bg-indigo-950/10 p-3 rounded-2xl border border-indigo-100 dark:border-indigo-900/20">
                            <h4 class="text-[10px] uppercase font-bold text-indigo-600 dark:text-indigo-400 tracking-wider">🏷️ Condiciones Comerciales</h4>
                            <div class="grid grid-cols-2 gap-3 text-xs mt-2">
                                <div>
                                    <span class="text-[10px] text-gray-450 uppercase block">Condición de Pago</span>
                                    <span class="font-bold text-gray-800 dark:text-gray-200 block mt-0.5">{{ parseDireccion(drawerProveedor.direccion).condicion_pago }}</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-gray-450 uppercase block">Tiempo Estimado</span>
                                    <span class="font-bold text-gray-800 dark:text-gray-200 block mt-0.5">{{ parseDireccion(drawerProveedor.direccion).tiempo_entrega }} días de entrega</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-gray-450 uppercase block">Descuento Habitual</span>
                                    <span class="font-bold font-mono text-gray-800 dark:text-gray-200 block mt-0.5">{{ parseDireccion(drawerProveedor.direccion).descuento_habitual }}%</span>
                                </div>
                                <div>
                                    <span class="text-[10px] text-gray-450 uppercase block">Compra Mínima</span>
                                    <span class="font-bold font-mono text-gray-800 dark:text-gray-200 block mt-0.5">S/ {{ parseDireccion(drawerProveedor.direccion).compra_minima }}</span>
                                </div>
                                <div class="col-span-2">
                                    <span class="text-[10px] text-gray-450 uppercase block">Notas Comerciales</span>
                                    <span class="text-[11px] text-gray-600 dark:text-gray-400 block mt-0.5 italic">{{ parseDireccion(drawerProveedor.direccion).observaciones_comerciales || 'Sin observaciones' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 3. Resumen Financiero y Ticketera -->
                        <div class="space-y-3">
                            <h4 class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Resumen de Compras</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <div class="p-3 bg-gray-50 dark:bg-gray-950 border border-gray-150 dark:border-gray-850 rounded-xl">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">Total Invertido</span>
                                    <span class="text-base font-bold font-mono text-gray-900 dark:text-white mt-1 block">{{ formatCurrency(getProveedorTotalSpent(drawerProveedor)) }}</span>
                                </div>
                                <div class="p-3 bg-gray-50 dark:bg-gray-950 border border-gray-150 dark:border-gray-850 rounded-xl">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">Ticket Promedio</span>
                                    <span class="text-base font-bold font-mono text-blue-600 dark:text-blue-400 mt-1 block">{{ formatCurrency(getProveedorAverageTicket(drawerProveedor)) }}</span>
                                </div>
                                <div class="p-3 bg-gray-50 dark:bg-gray-950 border border-gray-150 dark:border-gray-850 rounded-xl">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">Monto Máximo Ticket</span>
                                    <span class="text-base font-bold font-mono text-purple-600 dark:text-purple-400 mt-1 block">{{ formatCurrency(getProveedorHighestTicket(drawerProveedor)) }}</span>
                                </div>
                                <div class="p-3 bg-gray-50 dark:bg-gray-950 border border-gray-150 dark:border-gray-850 rounded-xl">
                                    <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider block">Compras Realizadas</span>
                                    <span class="text-base font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-1 block">{{ getProveedorComprasCount(drawerProveedor) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Mini Indicadores de Periodos -->
                        <div class="space-y-3">
                            <h4 class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Desglose de Periodo</h4>
                            <div class="grid grid-cols-3 gap-2">
                                <div class="p-2 border border-gray-100 dark:border-gray-800 rounded-xl text-center">
                                    <span class="text-[8px] text-gray-450 uppercase block">Últimos 30 Días</span>
                                    <span class="font-bold font-mono text-gray-800 dark:text-gray-200 mt-0.5 block">{{ formatCurrency(getSpentInPeriod(drawerProveedor, 30)) }}</span>
                                </div>
                                <div class="p-2 border border-gray-100 dark:border-gray-800 rounded-xl text-center">
                                    <span class="text-[8px] text-gray-450 uppercase block">Últimos 6 Meses</span>
                                    <span class="font-bold font-mono text-gray-800 dark:text-gray-200 mt-0.5 block">{{ formatCurrency(getSpentInPeriod(drawerProveedor, 180)) }}</span>
                                </div>
                                <div class="p-2 border border-gray-100 dark:border-gray-800 rounded-xl text-center">
                                    <span class="text-[8px] text-gray-450 uppercase block">Frecuencia Compra</span>
                                    <span class="font-bold text-gray-800 dark:text-gray-200 mt-0.5 block">{{ getProveedorComprasCount(drawerProveedor) > 0 ? 'Cada ' + Math.max(1, Math.round(90 / getProveedorComprasCount(drawerProveedor))) + ' días' : '-' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- 5. Productos que suministra -->
                        <div class="space-y-3">
                            <h4 class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Catálogo Suministrado</h4>
                            <div v-if="getSupplierProducts(drawerProveedor).length > 0" class="overflow-hidden border border-gray-100 dark:border-gray-800 rounded-xl">
                                <table class="w-full text-[11px] text-left text-gray-500">
                                    <thead class="bg-gray-50 dark:bg-gray-950 font-bold text-gray-650">
                                        <tr>
                                            <th class="p-2.5">Producto</th>
                                            <th class="p-2.5 text-right">Último Costo</th>
                                            <th class="p-2.5 text-center">Cant. Prom.</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50 dark:divide-gray-850">
                                        <tr v-for="p in getSupplierProducts(drawerProveedor)" :key="p.id" class="hover:bg-gray-50/50">
                                            <td class="p-2.5 font-bold text-gray-850 dark:text-gray-250 truncate max-w-[150px]">{{ p.nombre }}</td>
                                            <td class="p-2.5 text-right font-mono font-bold">{{ formatCurrency(p.ultimo_costo) }}</td>
                                            <td class="p-2.5 text-center font-mono">{{ p.cantidad_promedio.toFixed(1) }} und</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="text-xs text-gray-400 italic text-center p-3 border border-dashed border-gray-200 dark:border-gray-800 rounded-xl">
                                Sin registros de productos suministrados.
                            </div>
                        </div>
                    </div>

                    <!-- Drawer Footer Actions -->
                    <div class="p-6 border-t border-gray-150 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-950/20 shrink-0 flex flex-col gap-2">
                        <button 
                            @click="showDrawer = false; startNewPurchaseForProveedor(drawerProveedor)"
                            class="w-full justify-center px-4 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs active:scale-98 flex items-center gap-1.5 cursor-pointer"
                        >
                            <Plus class="w-4 h-4" />
                            Registrar Nueva Compra
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
