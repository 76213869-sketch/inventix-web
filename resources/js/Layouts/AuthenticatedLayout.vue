<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';

// Lucide icon imports
import {
    LayoutDashboard,
    Sparkles,
    Receipt,
    ShoppingBag,
    Calculator,
    Package,
    FolderTree,
    ArrowLeftRight,
    Boxes,
    Users,
    Truck,
    UserCheck,
    TrendingUp,
    Coins,
    Building2,
    CreditCard,
    ShieldCheck,
    Settings,
    User,
    HelpCircle,
    Bell,
    Search,
    Plus,
    ChevronDown,
    LogOut,
    Menu,
    X,
    Briefcase,
    Clock,
    FileText
} from 'lucide-vue-next';

const { props } = usePage();
const user = props.auth.user;

const isMobileSidebarOpen = ref(false);
const openGroups = ref({
    general: true,
    operaciones: true,
    inventario: true,
    personas: true,
    reportes: true,
    configuracion: true
});

const toggleGroup = (group) => {
    openGroups.value[group] = !openGroups.value[group];
};

const isActive = (routeName, panelParam = null) => {
    const pageRoute = route();
    if (panelParam) {
        return pageRoute.current(routeName) && pageRoute.params?.panel === panelParam;
    }
    if (routeName === 'dashboard') {
        return pageRoute.current('dashboard') && (!pageRoute.params?.panel || pageRoute.params?.panel === 'control');
    }
    return pageRoute.current(routeName) || pageRoute.current(routeName + '.*');
};

// Check if current view is POS
const isPOS = computed(() => {
    try {
        return route().current('pos');
    } catch (e) {
        return false;
    }
});

const mainClasses = computed(() => {
    if (isPOS.value) return 'p-4 overflow-hidden flex flex-col';
    try {
        const pageRoute = route();
        if (pageRoute.current('dashboard') && pageRoute.params?.panel === 'ia') {
            return 'p-0 overflow-hidden flex flex-col';
        }
    } catch (e) {}
    return 'p-6 overflow-y-auto';
});

const contentClasses = computed(() => {
    if (isPOS.value) return 'space-y-3 flex flex-col flex-1 min-h-0';
    try {
        const pageRoute = route();
        if (pageRoute.current('dashboard') && pageRoute.params?.panel === 'ia') {
            return 'space-y-0 flex flex-col flex-1 min-h-0';
        }
    } catch (e) {}
    return 'space-y-6';
});

// Dynamic label and session state for the active caja in POS mode
const cajaAbierta = computed(() => usePage().props.caja_abierta);

const cajaLabel = computed(() => {
    if (cajaAbierta.value) {
        return `Caja #${String(cajaAbierta.value.id).padStart(3, '0')}`;
    }
    return 'Caja Cerrada';
});

const isSuperAdminGlobal = computed(() => {
    try {
        return usePage().props.auth.user?.rol === 'super_admin' && !usePage().props.impersonated_empresa;
    } catch (e) {
        return false;
    }
});

const isSaaSTabActive = (tabName) => {
    try {
        const pageRoute = route();
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('tab') || 'dashboard';
        return pageRoute.current('saas.index') && activeTab === tabName;
    } catch (e) {
        return false;
    }
};

// Dynamic time tracking for POS bar
const currentTime = ref(new Date());
let timeInterval = null;

onMounted(() => {
    timeInterval = setInterval(() => {
        currentTime.value = new Date();
    }, 10000);
});

onUnmounted(() => {
    if (timeInterval) clearInterval(timeInterval);
});

const formattedDateTime = computed(() => {
    const d = currentTime.value;
    const dateStr = d.toLocaleDateString('es-PE', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
    const timeStr = d.toLocaleTimeString('es-PE', {
        hour: '2-digit',
        minute: '2-digit',
        hour12: true
    }).toUpperCase();
    return `${dateStr} • ${timeStr}`;
});
</script>

<template>
    <div class="min-h-screen bg-[#F6F8FB] font-sans antialiased text-gray-900 flex p-0 md:p-4 gap-0 md:gap-4 overflow-hidden h-screen">
        
        <!-- ========================================== -->
        <!-- 1. DESKTOP SIDEBAR (Floating UI)           -->
        <!-- ========================================== -->
        <aside class="w-64 bg-white border border-gray-200/80 rounded-2xl flex flex-col shrink-0 hidden lg:flex shadow-xs h-full overflow-hidden select-none">
            <!-- Header Brand & New Logo -->
            <div class="h-16 flex items-center px-6 border-b border-gray-100 shrink-0">
                <Link :href="isSuperAdminGlobal ? route('saas.index') : route('pos')" class="flex items-center group">
                    <img src="/logo-inventex.png" class="h-12 w-auto object-contain" alt="INVENTEX" />
                </Link>
            </div>

            <!-- Navigation Lists for Super Admin Global -->
            <div v-if="isSuperAdminGlobal" class="flex-1 overflow-y-auto py-5 px-3 space-y-1">
                
                <!-- Dashboard Global -->
                <Link :href="route('saas.index', { tab: 'dashboard' })" :class="isSaaSTabActive('dashboard') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isSaaSTabActive('dashboard')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                    <LayoutDashboard class="w-4 h-4 shrink-0 text-indigo-600/80" />
                    <span>Dashboard Global</span>
                </Link>

                <!-- Empresas -->
                <Link :href="route('saas.index', { tab: 'empresas' })" :class="isSaaSTabActive('empresas') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isSaaSTabActive('empresas')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                    <Building2 class="w-4 h-4 shrink-0 text-amber-600/80" />
                    <span>Empresas</span>
                </Link>

                <!-- Usuarios Globales -->
                <Link :href="route('saas.index', { tab: 'usuarios' })" :class="isSaaSTabActive('usuarios') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isSaaSTabActive('usuarios')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                    <Users class="w-4 h-4 shrink-0 text-blue-600/80" />
                    <span>Usuarios Globales</span>
                </Link>

                <!-- Planes y Licencias -->
                <Link :href="route('saas.index', { tab: 'planes' })" :class="isSaaSTabActive('planes') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isSaaSTabActive('planes')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                    <CreditCard class="w-4 h-4 shrink-0 text-emerald-600/80" />
                    <span>Planes y Licencias</span>
                </Link>

                <!-- Facturación -->
                <Link :href="route('saas.index', { tab: 'facturacion' })" :class="isSaaSTabActive('facturacion') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isSaaSTabActive('facturacion')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                    <Receipt class="w-4 h-4 shrink-0 text-rose-600/80" />
                    <span>Facturación</span>
                </Link>

                <!-- Monitoreo -->
                <Link :href="route('saas.index', { tab: 'monitoreo' })" :class="isSaaSTabActive('monitoreo') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isSaaSTabActive('monitoreo')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                    <ShieldCheck class="w-4 h-4 shrink-0 text-teal-600/80" />
                    <span>Monitoreo</span>
                </Link>

                <!-- Logs -->
                <Link :href="route('saas.index', { tab: 'logs' })" :class="isSaaSTabActive('logs') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isSaaSTabActive('logs')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                    <FileText class="w-4 h-4 shrink-0 text-cyan-600/80" />
                    <span>Logs</span>
                </Link>

                <!-- Backups -->
                <Link :href="route('saas.index', { tab: 'backups' })" :class="isSaaSTabActive('backups') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isSaaSTabActive('backups')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                    <FolderTree class="w-4 h-4 shrink-0 text-indigo-600/80" />
                    <span>Backups</span>
                </Link>

                <!-- Configuración Global -->
                <Link :href="route('saas.index', { tab: 'configuracion' })" :class="isSaaSTabActive('configuracion') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isSaaSTabActive('configuracion')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                    <Settings class="w-4 h-4 shrink-0 text-slate-600/80" />
                    <span>Configuración Global</span>
                </Link>
            </div>

            <!-- Navigation Lists for Empresa/Tenant -->
            <div v-else class="flex-1 overflow-y-auto py-5 px-3 space-y-1">
                
                <!-- Caja (POS) -->
                <Link :href="route('pos')" :class="isActive('pos') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isActive('pos')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                    <Calculator class="w-4 h-4 shrink-0 text-blue-600/80" />
                    <span>Caja (POS)</span>
                </Link>

                <!-- Caja Diaria -->
                <Link :href="route('cajas.index')" :class="isActive('cajas') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isActive('cajas')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                    <Coins class="w-4 h-4 shrink-0 text-emerald-600/80" />
                    <span>Caja Diaria</span>
                </Link>

                <!-- Productos -->
                <Link :href="route('productos.index')" :class="isActive('productos') || isActive('categorias') || isActive('kardex') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isActive('productos') || isActive('categorias') || isActive('kardex')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                    <Package class="w-4 h-4 shrink-0 text-amber-600/80" />
                    <span>Productos</span>
                </Link>

                <!-- Compras -->
                <Link :href="route('compras.index')" :class="isActive('compras') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isActive('compras')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                    <ShoppingBag class="w-4 h-4 shrink-0 text-emerald-600/80" />
                    <span>Compras</span>
                </Link>

                <!-- Clientes -->
                <Link :href="route('clientes.index')" :class="isActive('clientes') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isActive('clientes')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                    <Users class="w-4 h-4 shrink-0 text-indigo-600/80" />
                    <span>Clientes</span>
                </Link>

                <!-- Proveedores -->
                <Link :href="route('proveedores.index')" :class="isActive('proveedores') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isActive('proveedores')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                    <Truck class="w-4 h-4 shrink-0 text-teal-600/80" />
                    <span>Proveedores</span>
                </Link>

                <!-- Reportes -->
                <Link :href="route('dashboard')" :class="isActive('dashboard') && route().params?.panel !== 'ia' ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isActive('dashboard') && route().params?.panel !== 'ia'" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                    <LayoutDashboard class="w-4 h-4 shrink-0 text-rose-600/80" />
                    <span>Reportes</span>
                </Link>

                <!-- Inteligencia Artificial -->
                <Link :href="route('dashboard', { panel: 'ia' })" :class="isActive('dashboard', 'ia') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isActive('dashboard', 'ia')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                    <Sparkles class="w-4 h-4 shrink-0 text-violet-500" />
                    <span>Asistente IA</span>
                </Link>

            </div>

            <!-- Sidebar Footer (Configuración & Cerrar Sesión) -->
            <div class="p-3 border-t border-gray-100 shrink-0 bg-gray-50/50 flex flex-col gap-1.5">
                <!-- Configuración Global for Super Admin -->
                <Link v-if="isSuperAdminGlobal" :href="route('saas.index', { tab: 'configuracion' })" :class="isSaaSTabActive('configuracion') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isSaaSTabActive('configuracion')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                    <Settings class="w-4 h-4 shrink-0 text-slate-600/80" />
                    <span>Configuración Global</span>
                </Link>
                <!-- Configuración for Regular User -->
                <Link v-else :href="route('profile.edit')" :class="isActive('profile.edit') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                    <span v-if="isActive('profile.edit')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                    <Settings class="w-4 h-4 shrink-0 text-slate-600/80" />
                    <span>Configuración</span>
                </Link>
                <!-- Cerrar Sesión -->
                <Link :href="route('logout')" method="post" as="button" class="w-full flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs font-bold text-red-600 hover:bg-red-50 hover:text-red-700 rounded-xl transition-all duration-150 cursor-pointer text-left">
                    <LogOut class="w-4 h-4 text-red-500 shrink-0" />
                    <span>Cerrar Sesión</span>
                </Link>
            </div>
        </aside>

        <!-- ========================================== -->
        <!-- 2. MOBILE DRAWER SIDEBAR                   -->
        <!-- ========================================== -->
        <div v-show="isMobileSidebarOpen" class="fixed inset-0 z-50 flex lg:hidden">
            <!-- Backdrop -->
            <div @click="isMobileSidebarOpen = false" class="fixed inset-0 bg-gray-600/30 backdrop-blur-xs transition-opacity duration-300"></div>
                <!-- Drawer Body -->
            <aside class="relative flex flex-col w-64 bg-white border-r border-gray-200 min-h-screen z-10 animate-slide-in">
                <div class="h-16 flex items-center justify-between px-6 border-b border-gray-100 shrink-0">
                    <div class="flex items-center">
                        <img src="/logo-inventex.png" class="h-12 w-auto object-contain" alt="INVENTEX" />
                    </div>
                    <button @click="isMobileSidebarOpen = false" class="p-1.5 rounded-xl text-gray-400 hover:bg-gray-100 cursor-pointer">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Navigation List for Super Admin -->
                <div v-if="isSuperAdminGlobal" class="flex-1 overflow-y-auto py-5 px-3 space-y-1">
                    <!-- Dashboard Global -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('saas.index', { tab: 'dashboard' })" :class="isSaaSTabActive('dashboard') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isSaaSTabActive('dashboard')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                        <LayoutDashboard class="w-4 h-4 shrink-0 text-indigo-600/80" />
                        <span>Dashboard Global</span>
                    </Link>

                    <!-- Empresas -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('saas.index', { tab: 'empresas' })" :class="isSaaSTabActive('empresas') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isSaaSTabActive('empresas')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                        <Building2 class="w-4 h-4 shrink-0 text-amber-600/80" />
                        <span>Empresas</span>
                    </Link>

                    <!-- Usuarios Globales -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('saas.index', { tab: 'usuarios' })" :class="isSaaSTabActive('usuarios') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isSaaSTabActive('usuarios')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                        <Users class="w-4 h-4 shrink-0 text-blue-600/80" />
                        <span>Usuarios Globales</span>
                    </Link>

                    <!-- Planes y Licencias -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('saas.index', { tab: 'planes' })" :class="isSaaSTabActive('planes') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isSaaSTabActive('planes')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                        <CreditCard class="w-4 h-4 shrink-0 text-emerald-600/80" />
                        <span>Planes y Licencias</span>
                    </Link>

                    <!-- Facturación -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('saas.index', { tab: 'facturacion' })" :class="isSaaSTabActive('facturacion') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isSaaSTabActive('facturacion')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                        <Receipt class="w-4 h-4 shrink-0 text-rose-600/80" />
                        <span>Facturación</span>
                    </Link>

                    <!-- Monitoreo -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('saas.index', { tab: 'monitoreo' })" :class="isSaaSTabActive('monitoreo') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isSaaSTabActive('monitoreo')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                        <ShieldCheck class="w-4 h-4 shrink-0 text-teal-600/80" />
                        <span>Monitoreo</span>
                    </Link>

                    <!-- Logs -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('saas.index', { tab: 'logs' })" :class="isSaaSTabActive('logs') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isSaaSTabActive('logs')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                        <FileText class="w-4 h-4 shrink-0 text-cyan-600/80" />
                        <span>Logs</span>
                    </Link>

                    <!-- Backups -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('saas.index', { tab: 'backups' })" :class="isSaaSTabActive('backups') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isSaaSTabActive('backups')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                        <FolderTree class="w-4 h-4 shrink-0 text-indigo-600/80" />
                        <span>Backups</span>
                    </Link>

                    <!-- Configuración Global -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('saas.index', { tab: 'configuracion' })" :class="isSaaSTabActive('configuracion') ? 'bg-indigo-50/50 text-indigo-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isSaaSTabActive('configuracion')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-indigo-600 rounded-r-md"></span>
                        <Settings class="w-4 h-4 shrink-0 text-slate-650/80" />
                        <span>Configuración Global</span>
                    </Link>
                </div>

                <!-- Navigation List for Tenant -->
                <div v-else class="flex-1 overflow-y-auto py-5 px-3 space-y-1">
                    
                    <!-- Caja (POS) -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('pos')" :class="isActive('pos') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isActive('pos')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                        <Calculator class="w-4 h-4 shrink-0 text-blue-600/80" />
                        <span>Caja (POS)</span>
                    </Link>

                    <!-- Caja Diaria -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('cajas.index')" :class="isActive('cajas') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isActive('cajas')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                        <Coins class="w-4 h-4 shrink-0 text-emerald-600/80" />
                        <span>Caja Diaria</span>
                    </Link>

                    <!-- Productos -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('productos.index')" :class="isActive('productos') || isActive('categorias') || isActive('kardex') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isActive('productos') || isActive('categorias') || isActive('kardex')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                        <Package class="w-4 h-4 shrink-0 text-amber-600/80" />
                        <span>Productos</span>
                    </Link>

                    <!-- Compras -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('compras.index')" :class="isActive('compras') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isActive('compras')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                        <ShoppingBag class="w-4 h-4 shrink-0 text-emerald-600/80" />
                        <span>Compras</span>
                    </Link>

                    <!-- Clientes -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('clientes.index')" :class="isActive('clientes') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isActive('clientes')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                        <Users class="w-4 h-4 shrink-0 text-indigo-600/80" />
                        <span>Clientes</span>
                    </Link>

                    <!-- Proveedores -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('proveedores.index')" :class="isActive('proveedores') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isActive('proveedores')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                        <Truck class="w-4 h-4 shrink-0 text-teal-600/80" />
                        <span>Proveedores</span>
                    </Link>

                    <!-- Reportes -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('dashboard')" :class="isActive('dashboard') && route().params?.panel !== 'ia' ? 'bg-blue-50/50 text-blue-600 font-semibold border-l-2 border-blue-600 pl-2.5' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isActive('dashboard') && route().params?.panel !== 'ia'" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                        <LayoutDashboard class="w-4 h-4 shrink-0 text-rose-600/80" />
                        <span>Reportes</span>
                    </Link>

                    <!-- Inteligencia Artificial -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('dashboard', { panel: 'ia' })" :class="isActive('dashboard', 'ia') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isActive('dashboard', 'ia')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                        <Sparkles class="w-4 h-4 shrink-0 text-violet-500" />
                        <span>Asistente IA</span>
                    </Link>

                    <!-- Configuración -->
                    <Link @click="isMobileSidebarOpen = false" :href="route('profile.edit')" :class="isActive('profile.edit') ? 'bg-blue-50/50 text-blue-600 font-semibold' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900'" class="relative flex items-center gap-2.5 pl-3 pr-3 py-2 text-xs rounded-xl transition-all duration-150">
                        <span v-if="isActive('profile.edit')" class="absolute left-0 top-1.5 bottom-1.5 w-[3px] bg-blue-600 rounded-r-md"></span>
                        <Settings class="w-4 h-4 shrink-0 text-slate-650/80" />
                        <span>Configuración</span>
                    </Link>

                </div>

                <div class="p-4 border-t border-gray-100 shrink-0 bg-gray-50/50">
                    <div class="text-xs font-bold text-gray-900">{{ user.name }}</div>
                    <Link :href="route('logout')" method="post" as="button" class="mt-2.5 w-full text-center px-4 py-2 text-xs text-red-600 bg-red-50 hover:bg-red-100 rounded-xl font-bold transition-colors cursor-pointer">Cerrar Sesión</Link>
                </div>
            </aside>
        </div>
 
        <!-- ========================================== -->
        <!-- 3. MAIN CONTENT CONTAINER (Floating UI)    -->
        <!-- ========================================== -->
        <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
            <!-- Impersonation Banner for Super Admin -->
            <div v-if="$page.props.impersonated_empresa" class="bg-indigo-600 text-white px-6 py-2.5 flex items-center justify-between text-xs font-semibold shadow-md border-b border-indigo-700 select-none shrink-0 z-50 rounded-2xl mb-2 mt-2 mx-4">
                <div class="flex items-center gap-2">
                    <span class="bg-white/20 px-2 py-0.5 rounded-md text-[10px] tracking-wider uppercase font-bold text-indigo-100">Modo Super Admin</span>
                    <span>Administrando empresa: <strong class="text-amber-300 font-extrabold uppercase">{{ $page.props.impersonated_empresa.nombre }}</strong> <span class="opacity-60 text-[10px] font-mono">(RUC: {{ $page.props.impersonated_empresa.ruc }})</span></span>
                </div>
                <div class="flex items-center gap-3">
                    <Link :href="route('saas.stopImpersonating')" method="post" :data="{ redirect_to: 'empresas' }" as="button" class="text-indigo-100 hover:text-amber-200 transition-colors underline cursor-pointer">
                        [ Cambiar empresa ]
                    </Link>
                    <Link :href="route('saas.stopImpersonating')" method="post" as="button" class="text-indigo-100 hover:text-amber-200 transition-colors underline cursor-pointer font-bold">
                        [ Volver al Panel Global ]
                    </Link>
                </div>
            </div>
                    <!-- Topbar (White, rounded-2xl, border-b or bordered, high padding) -->
            <header 
                class="h-16 bg-white border border-gray-200/80 rounded-2xl flex items-center justify-between px-6 shrink-0 z-40 shadow-2xs"
                :class="isPOS ? 'mb-0 md:mb-2' : 'mb-0 md:mb-4'"
            >
                          <!-- 1. POS MODE TOPBAR -->
                <template v-if="isPOS">
                    <!-- Left side: Brand Logo INVENTEX + Caja ID badge -->
                    <div class="flex items-center gap-4">
                        <!-- Mobile Sidebar Toggle -->
                        <button 
                            @click="isMobileSidebarOpen = true" 
                            class="p-2 -ml-2 rounded-xl text-gray-500 hover:text-gray-900 hover:bg-gray-100 lg:hidden cursor-pointer"
                        >
                            <Menu class="w-5 h-5" />
                        </button>

                        <!-- Brand Logo -->
                        <div class="flex items-center lg:hidden">
                            <img src="/logo-inventex.png" class="h-12 w-auto object-contain" alt="INVENTEX" />
                        </div>

                        <!-- Caja Badge -->
                        <span 
                            class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider font-mono px-2.5 py-1 rounded-lg border transition-all"
                            :class="cajaAbierta ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/20 border-emerald-250/50' : 'text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-950/20 border-red-250/50'"
                        >
                            <span 
                                class="w-1.5 h-1.5 rounded-full animate-pulse"
                                :class="cajaAbierta ? 'bg-emerald-500' : 'bg-red-500'"
                            ></span>
                            {{ cajaLabel }}
                        </span>
                    </div>

                    <!-- Center: Current Date & Time -->
                    <div class="hidden md:flex items-center justify-center gap-1.5 text-xs text-gray-650 dark:text-gray-400 font-mono bg-gray-50 dark:bg-gray-800/40 px-3 py-1.5 rounded-xl border border-gray-200/40 shadow-xs">
                        <Clock class="w-3.5 h-3.5 text-gray-400" />
                        <span>{{ formattedDateTime }}</span>
                    </div>

                    <!-- Right side: Avatar & Dropdown menu -->
                    <div class="flex items-center gap-3">
                        <!-- User Profile Dropdown -->
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2.5 p-1 border border-transparent text-xs font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none cursor-pointer"
                                >
                                    <span class="w-10 h-10 flex items-center justify-center bg-blue-50 text-blue-600 rounded-xl text-sm font-bold font-mono border border-blue-100 overflow-hidden shadow-2xs">
                                        <img v-if="user.avatar_url" :src="user.avatar_url" class="w-full h-full object-cover" />
                                        <img v-else-if="user.empresa_logo_url" :src="user.empresa_logo_url" class="w-full h-full object-cover" />
                                        <template v-else>
                                            {{ user.name ? user.name.substring(0, 2).toUpperCase() : 'US' }}
                                        </template>
                                    </span>
                                    <span class="hidden md:inline max-w-[120px] truncate text-left text-sm font-semibold text-gray-800">{{ user.name }}</span>
                                    <ChevronDown class="w-4 h-4 text-gray-400 hidden md:inline" />
                                </button>
                            </template>

                            <template #content>
                                <div class="px-4 py-2.5 border-b border-gray-100">
                                    <p class="text-xs font-bold text-gray-900">{{ user.name }}</p>
                                    <p class="text-[10px] text-gray-400 truncate mt-0.5 font-mono">{{ user.email }}</p>
                                </div>
                                <DropdownLink :href="route('profile.edit', { tab: 'perfil' })">
                                    <span class="flex items-center gap-2">
                                        <User class="w-4 h-4 text-gray-500" />
                                        <span>Mi Perfil</span>
                                    </span>
                                </DropdownLink>
                                <DropdownLink :href="route('profile.edit')">
                                    <span class="flex items-center gap-2">
                                        <Settings class="w-4 h-4 text-gray-500" />
                                        <span>Configuración</span>
                                    </span>
                                </DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">
                                    <span class="flex items-center gap-2 text-red-600 hover:text-red-700">
                                        <LogOut class="w-4 h-4 text-red-500" />
                                        <span>Cerrar Sesión</span>
                                    </span>
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </template>

                <!-- 2. REGULAR MODE TOPBAR -->
                <template v-else>
                    <!-- Left side: Mobile Toggle Burger & Business Name -->
                    <div class="flex items-center gap-4">
                        <button 
                            @click="isMobileSidebarOpen = true" 
                            class="p-2 -ml-2 rounded-xl text-gray-500 hover:text-gray-900 hover:bg-gray-100 lg:hidden cursor-pointer"
                        >
                            <Menu class="w-5 h-5" />
                        </button>

                        <!-- Caja Badge (Tenant) vs SaaS Global Badge (Super Admin) -->
                        <span 
                            v-if="!isSuperAdminGlobal"
                            class="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider font-mono px-2.5 py-1 rounded-lg border transition-all"
                            :class="cajaAbierta ? 'text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/20 border-emerald-250/50' : 'text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-950/20 border-red-250/50'"
                        >
                            <span 
                                class="w-1.5 h-1.5 rounded-full animate-pulse"
                                :class="cajaAbierta ? 'bg-emerald-500' : 'bg-red-500'"
                            ></span>
                            {{ cajaLabel }}
                        </span>
                        <span 
                            v-else
                            class="inline-flex items-center gap-2 text-[10px] font-bold uppercase tracking-wider font-mono px-3 py-1 rounded-xl border border-indigo-200 bg-indigo-50 text-indigo-700"
                        >
                            <span class="w-2 h-2 rounded-full bg-indigo-600 animate-pulse"></span>
                            INVENTEX • Plataforma Global SaaS
                        </span>
                    </div>

                    <!-- Center: Current Date & Time -->
                    <div class="hidden md:flex items-center justify-center gap-1.5 text-xs text-gray-650 dark:text-gray-400 font-mono bg-gray-50 dark:bg-gray-800/40 px-3 py-1.5 rounded-xl border border-gray-200/40 shadow-xs">
                        <Clock class="w-3.5 h-3.5 text-gray-400" />
                        <span>{{ formattedDateTime }}</span>
                    </div>

                    <!-- Right side: Avatar & Dropdown menu -->
                    <div class="flex items-center gap-3">
                        <!-- User Profile Dropdown -->
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-2.5 p-1 border border-transparent text-xs font-bold rounded-xl text-gray-700 bg-white hover:bg-gray-50 transition-all focus:outline-none cursor-pointer"
                                >
                                    <span class="w-10 h-10 flex items-center justify-center bg-blue-50 text-blue-600 rounded-xl text-sm font-bold font-mono border border-blue-100 overflow-hidden shadow-2xs">
                                        <img v-if="user.avatar_url" :src="user.avatar_url" class="w-full h-full object-cover" />
                                        <img v-else-if="user.empresa_logo_url" :src="user.empresa_logo_url" class="w-full h-full object-cover" />
                                        <template v-else>
                                            {{ user.name ? user.name.substring(0, 2).toUpperCase() : 'US' }}
                                        </template>
                                    </span>
                                    <span class="hidden md:inline max-w-[120px] truncate text-left text-sm font-semibold text-gray-800">{{ user.name }}</span>
                                    <ChevronDown class="w-4 h-4 text-gray-400 hidden md:inline" />
                                </button>
                            </template>

                            <template #content>
                                <div class="px-4 py-2.5 border-b border-gray-100">
                                    <p class="text-xs font-bold text-gray-900">{{ user.name }}</p>
                                    <p class="text-[10px] text-gray-400 truncate mt-0.5 font-mono">{{ user.email }}</p>
                                </div>
                                <template v-if="isSuperAdminGlobal">
                                    <DropdownLink :href="route('saas.index', { tab: 'dashboard' })">
                                        <span class="flex items-center gap-2">
                                            <LayoutDashboard class="w-4 h-4 text-indigo-600" />
                                            <span>Dashboard Global</span>
                                        </span>
                                    </DropdownLink>
                                    <DropdownLink :href="route('saas.index', { tab: 'configuracion' })">
                                        <span class="flex items-center gap-2">
                                            <Settings class="w-4 h-4 text-gray-500" />
                                            <span>Configuración Global</span>
                                        </span>
                                    </DropdownLink>
                                </template>
                                <template v-else>
                                    <DropdownLink :href="route('profile.edit', { tab: 'perfil' })">
                                        <span class="flex items-center gap-2">
                                            <User class="w-4 h-4 text-gray-500" />
                                            <span>Mi Perfil</span>
                                        </span>
                                    </DropdownLink>
                                    <DropdownLink :href="route('profile.edit')">
                                        <span class="flex items-center gap-2">
                                            <Settings class="w-4 h-4 text-gray-500" />
                                            <span>Configuración</span>
                                        </span>
                                    </DropdownLink>
                                </template>
                                <DropdownLink :href="route('logout')" method="post" as="button">
                                    <span class="flex items-center gap-2 text-red-600 hover:text-red-700">
                                        <LogOut class="w-4 h-4 text-red-500" />
                                        <span>Cerrar Sesión</span>
                                    </span>
                                </DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </template>
            </header>

            <!-- Page Main Content Wrap (Scrollable) -->
            <main 
                class="flex-1 bg-white dark:bg-gray-800 border border-gray-200/80 dark:border-gray-700 rounded-2xl shadow-2xs"
                :class="mainClasses"
            >
                <div 
                    class="max-w-7xl mx-auto w-full"
                    :class="contentClasses"
                >
                    <!-- Heading Area if page header slot is provided -->
                    <div v-if="$slots.header" :class="isPOS ? 'pb-2 border-b border-gray-150 dark:border-gray-700 shrink-0' : 'pb-3 border-b border-gray-100'">
                        <slot name="header" />
                    </div>

                    <!-- Page Body -->
                    <slot />
                </div>
            </main>
        </div>

    </div>
</template>

<style>
/* Subtle entry animation for mobile sidebar */
@keyframes slideIn {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(0);
    }
}
.animate-slide-in {
    animation: slideIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
