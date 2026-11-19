<script setup>
import { computed, onMounted, ref } from 'vue';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import axios from 'axios';
import { 
    TrendingUp, TrendingDown, DollarSign, Archive, ShoppingCart, 
    Users, Truck, ArrowLeftRight, Clock, AlertTriangle, CheckCircle2,
    Calendar, CreditCard, Activity, BarChart3, ChevronRight, HelpCircle,
    Sparkles, Send, Trash2, Copy, Check, Loader, User, Zap
} from 'lucide-vue-next';

const props = defineProps({
    panel: { type: String, required: true },
    kpis: { type: Object, required: true },
    productosBajoStock: { type: Array, required: true },
    actividadesRecientes: { type: Array, required: true },
    alerts: { type: Array, required: true },
    
    // Panel 'control'
    resumenCards: { type: Object, required: false },

    // Panel 'ejecutivo'
    chartVentasCompras: { type: Array, required: false },

    // Panel 'ventas'
    chartVentas30: { type: Array, required: false },
    chartVentasHora: { type: Array, required: false },
    chartVentasMetodoPago: { type: Array, required: false },
    chartProductosMasVendidos: { type: Array, required: false },
    rankingVendedores: { type: Array, required: false },
    ticketPromedio: { type: Number, required: false },
    chartVentasCategoria: { type: Array, required: false },
    clientesFrecuentes: { type: Array, required: false },

    // Panel 'compras'
    chartComprasMensual: { type: Array, required: false },
    comprasMes: { type: [Number, String], required: false },
    montoInvertidoTotal: { type: [Number, String], required: false },
    proveedoresPrincipales: { type: Array, required: false },
    ultimasCompras: { type: Array, required: false },
    comprasPendientes: { type: Array, required: false },

    // Panel 'inventario'
    productosAgotados: { type: Array, required: false },
    rotacion: { type: Object, required: false },
    valorizacionCosto: { type: Number, required: false },
    valorizacionVenta: { type: Number, required: false },
    movimientosRecientes: { type: Array, required: false },

    // Panel 'financiero'
    chartFinanciero: { type: Array, required: false },
    balanceCaja: { type: [Number, String], required: false },
    totalIngresos: { type: [Number, String], required: false },
    totalEgresos: { type: [Number, String], required: false },
    utilidadTotal: { type: [Number, String], required: false },
    margenRentabilidad: { type: Number, required: false },

    // Panel 'clientes'
    clientesNuevos: { type: Array, required: false },
    clientesVIP: { type: Array, required: false },
    clientesInactivos: { type: Array, required: false },
    chartRegistros: { type: Array, required: false },

    // Panel 'proveedores'
    rankingProveedores: { type: Array, required: false },
    comprasPorEstado: { type: Object, required: false },

    // Panel 'ia'
    prediccionVentas: { type: Object, required: false },
    recomendacionesCompra: { type: Array, required: false },
    productosPocoRentables: { type: Array, required: false },
    productosEstrella: { type: Array, required: false },
    clientesRiesgoAbandono: { type: Array, required: false },
    consejosInteligentes: { type: Array, required: false }
});

const page = usePage();
const userName = computed(() => page.props.auth.user?.name || page.props.auth.user?.nombre || 'Administrador');
const isLoading = ref(true);

onMounted(() => {
    setTimeout(() => {
        isLoading.value = false;
    }, 200);
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(Number(val) || 0);
};

const formatSoldQuantity = (item) => {
    const qty = Number(item.cantidad) || 0;
    if (item.tipo_venta === 'peso') {
        const unit = item.unidad_medida === 'kilogramo' ? 'kg' : (item.unidad_medida === 'gramo' ? 'g' : (item.unidad_medida || 'kg'));
        return `${qty.toFixed(3)} ${unit}`;
    } else if (item.tipo_venta === 'volumen') {
        const unit = item.unidad_medida === 'litro' ? 'L' : (item.unidad_medida === 'mililitro' ? 'ml' : (item.unidad_medida || 'L'));
        return `${qty.toFixed(3)} ${unit}`;
    } else {
        return `${Math.round(qty)} und`;
    }
};

const relativeTime = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    if (diffMins < 1) return 'Hace un momento';
    if (diffMins < 60) return `Hace ${diffMins} min`;
    const diffHrs = Math.floor(diffMins / 60);
    if (diffHrs < 24) return `Hace ${diffHrs} hrs`;
    const diffDays = Math.floor(diffHrs / 24);
    return `Hace ${diffDays} día${diffDays > 1 ? 's' : ''}`;
};

// Least sold products (Bottom 5 from chartProductosMasVendidos)
const productosMenosVendidos = computed(() => {
    if (!props.chartProductosMasVendidos) return [];
    return [...props.chartProductosMasVendidos]
        .sort((a, b) => Number(a.cantidad) - Number(b.cantidad))
        .slice(0, 5);
});

// -----------------------------------------------------------------
// Módulo Asistente IA
// -----------------------------------------------------------------
const chatInput = ref('');
const chatMessages = ref([]);
const isChatLoading = ref(false);
const chatContainer = ref(null);
const copiedIndex = ref(null);
const activeAction = ref(null);

const suggestions = [
    '¿Cómo estuvieron mis ventas hoy?',
    '¿Qué productos debo comprar?',
    '¿Qué productos tienen poco stock?',
    '¿Qué productos no se venden?',
    '¿Cuánto dinero tengo en caja?',
    '¿Quién compra más?',
    '¿Cuál es mi producto más rentable?',
    '¿Qué proveedor utilizo más?'
];

const scrollToBottom = () => {
    if (chatContainer.value) {
        setTimeout(() => {
            chatContainer.value.scrollTop = chatContainer.value.scrollHeight;
        }, 50);
    }
};

const sendMessage = async (customMessage = null) => {
    const text = customMessage || chatInput.value.trim();
    if (!text || isChatLoading.value) return;

    chatMessages.value.push({
        role: 'user',
        content: { text: text }
    });

    chatInput.value = '';
    isChatLoading.value = true;
    scrollToBottom();

    try {
        const historyPayload = chatMessages.value.slice(0, -1).map(msg => ({
            role: msg.role,
            content: msg.content.text
        }));

        const response = await axios.post(route('ai.chat'), {
            message: text,
            history: historyPayload
        });

        if (response.data) {
            chatMessages.value.push({
                role: 'assistant',
                content: response.data
            });

            // Trigger confirmation for returned system actions
            if (response.data.actions && response.data.actions.length > 0) {
                activeAction.value = response.data.actions[0];
            }
        }
    } catch (e) {
        chatMessages.value.push({
            role: 'assistant',
            content: {
                text: 'Hubo un error de conexión al servidor del asistente de IA. Inténtalo nuevamente por favor.',
                visuals: [],
                actions: []
            }
        });
    } finally {
        isChatLoading.value = false;
        scrollToBottom();
    }
};

const analyzeBusiness = async () => {
    if (isChatLoading.value) return;

    chatMessages.value.push({
        role: 'user',
        content: { text: 'Analizar mi negocio' }
    });

    isChatLoading.value = true;
    scrollToBottom();

    try {
        const response = await axios.post(route('ai.analyze'));
        if (response.data) {
            chatMessages.value.push({
                role: 'assistant',
                content: response.data
            });
        }
    } catch (e) {
        chatMessages.value.push({
            role: 'assistant',
            content: {
                text: 'Hubo un error al compilar el informe ejecutivo. Por favor, vuelve a intentarlo.',
                visuals: [],
                actions: []
            }
        });
    } finally {
        isChatLoading.value = false;
        scrollToBottom();
    }
};

const clearConversation = () => {
    chatMessages.value = [];
};

const copyToClipboard = (text, idx) => {
    navigator.clipboard.writeText(text);
    copiedIndex.value = idx;
    setTimeout(() => {
        copiedIndex.value = null;
    }, 2000);
};

const regenerateResponse = (idx) => {
    if (idx === 0) return;
    let lastUserMessage = '';
    for (let i = idx - 1; i >= 0; i--) {
        if (chatMessages.value[i].role === 'user') {
            lastUserMessage = chatMessages.value[i].content.text;
            break;
        }
    }

    if (lastUserMessage) {
        chatMessages.value = chatMessages.value.slice(0, idx);
        sendMessage(lastUserMessage);
    }
};

const executeSystemAction = (action) => {
    if (!action) return;
    
    const type = action.type;
    activeAction.value = null; // dismiss dialog

    switch (type) {
        case 'abrir_producto':
            router.visit(route('productos.index'));
            break;
        case 'ir_a_clientes':
            router.visit(route('clientes.index'));
            break;
        case 'mostrar_reporte_financiero':
            router.visit(route('dashboard', { panel: 'financiero' }));
            break;
        case 'crear_compra':
            router.visit(route('compras.index'));
            break;
        case 'ir_a_proveedores':
            router.visit(route('proveedores.index'));
            break;
    }
};

const parseMarkdown = (text) => {
    if (!text) return '';
    let html = text;

    // Bold: **text**
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    
    // Bold/Italic: ***text***
    html = html.replace(/\*\*\*(.*?)\*\*\*/g, '<strong><em>$1</em></strong>');
    
    // Italic: *text* or _text_
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    
    // Inline code: `code`
    html = html.replace(/`(.*?)`/g, '<code class="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-800 rounded font-mono text-xs text-rose-600 dark:text-rose-400">$1</code>');
    
    // Line breaks
    html = html.replace(/\n/g, '<br>');
    
    // Bullet lists: - item or * item
    let lines = html.split('<br>');
    let inList = false;
    for (let i = 0; i < lines.length; i++) {
        let line = lines[i].trim();
        if (line.startsWith('- ') || line.startsWith('* ')) {
            let content = line.substring(2);
            if (!inList) {
                lines[i] = '<ul class="list-disc pl-5 my-2 space-y-1 text-gray-700 dark:text-gray-300">' + `<li>${content}</li>`;
                inList = true;
            } else {
                lines[i] = `<li>${content}</li>`;
            }
        } else {
            if (inList) {
                lines[i - 1] = lines[i - 1] + '</ul>';
                inList = false;
            }
        }
    }
    if (inList) {
        lines[lines.length - 1] = lines[lines.length - 1] + '</ul>';
    }
    html = lines.join('<br>');
    
    // Simple table parser
    lines = html.split('<br>');
    let inTable = false;
    for (let i = 0; i < lines.length; i++) {
        let line = lines[i].trim();
        if (line.startsWith('|') && line.endsWith('|')) {
            if (line.match(/^\|[\s-:\\|]+\|$/)) {
                lines[i] = '';
                continue;
            }
            
            let cells = line.split('|').slice(1, -1).map(c => c.trim());
            let cellTag = !inTable ? 'th' : 'td';
            let cellClass = !inTable 
                ? 'px-3 py-2 bg-gray-50 dark:bg-gray-850 font-bold border-b border-gray-200 dark:border-gray-800' 
                : 'px-3 py-2 border-b border-gray-200 dark:border-gray-850';
            let rowHtml = '<tr>' + cells.map(c => `<${cellTag} class="${cellClass}">${c}</${cellTag}>`).join('') + '</tr>';
            
            if (!inTable) {
                lines[i] = '<div class="overflow-x-auto my-3"><table class="w-full text-xs text-left border-collapse border border-gray-200 dark:border-gray-800 rounded-lg">' + `<thead>${rowHtml}</thead><tbody>`;
                inTable = true;
            } else {
                lines[i] = rowHtml;
            }
        } else {
            if (inTable) {
                lines[i - 1] = lines[i - 1] + '</tbody></table></div>';
                inTable = false;
            }
        }
    }
    if (inTable) {
        lines[lines.length - 1] = lines[lines.length - 1] + '</tbody></table></div>';
    }
    html = lines.join('<br>').replace(/<br><br>/g, '<p class="my-2.5"></p>').replace(/<br>/g, ' ');
    
    return html;
};

// -----------------------------------------------------------------
// SVG chart helpers
// -----------------------------------------------------------------

// Short currency format helper for Y axis
const formatShortCurrency = (val) => {
    const num = Number(val) || 0;
    if (num >= 1000000) return `S/ ${(num / 1000000).toFixed(1)}M`;
    if (num >= 1000) return `S/ ${(num / 1000).toFixed(1)}K`;
    return `S/ ${Math.round(num)}`;
};

// Smooth Bezier path generator (Cubic splines)
const getBezierPath = (points) => {
    if (!points || points.length === 0) return '';
    let path = `M ${points[0].x} ${points[0].y}`;
    for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i];
        const p1 = points[i + 1];
        // Control points at 1/3 and 2/3 distance
        const cpX1 = p0.x + (p1.x - p0.x) / 3;
        const cpY1 = p0.y;
        const cpX2 = p0.x + 2 * (p1.x - p0.x) / 3;
        const cpY2 = p1.y;
        path += ` C ${cpX1} ${cpY1}, ${cpX2} ${cpY2}, ${p1.x} ${p1.y}`;
    }
    return path;
};

const getBezierAreaPath = (points, baselineY = 140) => {
    const linePath = getBezierPath(points);
    if (!linePath) return '';
    const first = points[0];
    const last = points[points.length - 1];
    return `${linePath} L ${last.x} ${baselineY} L ${first.x} ${baselineY} Z`;
};

// Ejecutivo: Ventas vs Compras (Grouped Bars)
const maxChart1Val = computed(() => {
    if (!props.chartVentasCompras || props.chartVentasCompras.length === 0) return 100;
    const vals = props.chartVentasCompras.flatMap(d => [d.ventas, d.compras]);
    return Math.max(...vals, 100) * 1.1;
});

// Ventas: 30 Days Line Chart
const maxVentas30Val = computed(() => {
    if (!props.chartVentas30 || props.chartVentas30.length === 0) return 100;
    const vals = props.chartVentas30.map(d => d.value);
    return Math.max(...vals, 100) * 1.1;
});

const ventas30Points = computed(() => {
    if (!props.chartVentas30 || props.chartVentas30.length === 0) return [];
    const width = 500;
    const xStart = 20; // started from 20 since labels are outside
    const xSpacing = (width - xStart - 20) / (props.chartVentas30.length - 1 || 1);
    return props.chartVentas30.map((item, idx) => {
        const x = xStart + idx * xSpacing;
        const y = 140 - (item.value / maxVentas30Val.value) * 105;
        return { ...item, x, y };
    });
});

const ventas30Path = computed(() => getBezierPath(ventas30Points.value));
const ventas30AreaPath = computed(() => getBezierAreaPath(ventas30Points.value, 140));

// Financiero: monthly balance line chart
const maxFinancieroVal = computed(() => {
    if (!props.chartFinanciero || props.chartFinanciero.length === 0) return 100;
    const vals = props.chartFinanciero.flatMap(d => [d.ingresos, d.egresos, d.utilidad]);
    return Math.max(...vals, 100) * 1.1;
});

const financieroPoints = computed(() => {
    if (!props.chartFinanciero || props.chartFinanciero.length === 0) return { ingresos: [], egresos: [], utilidad: [] };
    const xStart = 30; // started from 30 since labels are outside
    const xSpacing = 105; // increased spacing for better distribution
    
    const ingresos = [];
    const egresos = [];
    const utilidad = [];

    props.chartFinanciero.forEach((item, idx) => {
        const x = xStart + idx * xSpacing;
        ingresos.push({ label: item.label, value: item.ingresos, x, y: 140 - (item.ingresos / maxFinancieroVal.value) * 110 });
        egresos.push({ label: item.label, value: item.egresos, x, y: 140 - (item.egresos / maxFinancieroVal.value) * 110 });
        utilidad.push({ label: item.label, value: item.utilidad, x, y: 140 - (item.utilidad / maxFinancieroVal.value) * 110 });
    });

    return { ingresos, egresos, utilidad };
});

const makePath = (pts) => getBezierPath(pts);

// Payment Method list
const metodosPagoSuma = computed(() => {
    if (!props.chartVentasMetodoPago) return [];
    const total = props.chartVentasMetodoPago.reduce((sum, item) => sum + item.total, 0) || 1;
    return props.chartVentasMetodoPago.map(item => ({
        ...item,
        percent: ((item.total / total) * 100).toFixed(1)
    })).sort((a, b) => b.total - a.total);
});
</script>

<template>
    <Head title="Reportes del Negocio" />

    <AuthenticatedLayout>
        <!-- Header -->
        <template #header v-if="panel !== 'ia'">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight flex items-center gap-2">
                        <span v-if="panel === 'control'">Hola, {{ userName }}.</span>
                        <span v-else-if="panel === 'ejecutivo'" class="flex items-center gap-2"><BarChart3 class="w-5.5 h-5.5 text-blue-600 shrink-0" /> Dashboard Ejecutivo</span>
                        <span v-else-if="panel === 'ventas'" class="flex items-center gap-2"><ShoppingCart class="w-5.5 h-5.5 text-emerald-600 shrink-0" /> Reporte de Ventas</span>
                        <span v-else-if="panel === 'inventario'" class="flex items-center gap-2"><Archive class="w-5.5 h-5.5 text-indigo-600 shrink-0" /> Reporte de Inventario</span>
                        <span v-else-if="panel === 'financiero'" class="flex items-center gap-2"><DollarSign class="w-5.5 h-5.5 text-teal-600 shrink-0" /> Reporte Financiero</span>
                        <span v-else-if="panel === 'compras'" class="flex items-center gap-2"><Calendar class="w-5.5 h-5.5 text-amber-600 shrink-0" /> Reporte de Compras</span>
                        <span v-else-if="panel === 'clientes'" class="flex items-center gap-2"><Users class="w-5.5 h-5.5 text-sky-600 shrink-0" /> Reporte de Clientes</span>
                        <span v-else-if="panel === 'proveedores'" class="flex items-center gap-2"><Truck class="w-5.5 h-5.5 text-rose-600 shrink-0" /> Reporte de Proveedores</span>
                    </h2>
                    <p class="text-xs text-gray-500 mt-0.5 font-medium">
                        <span v-if="panel === 'control'">Centro de control y reportes de la bodega.</span>
                        <span v-else>Estadísticas sencillas y respuestas clave para la toma de decisiones.</span>
                    </p>
                </div>
                <div v-if="panel !== 'control'">
                    <Link 
                        :href="route('dashboard', { panel: 'control' })" 
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-gray-500 bg-white dark:bg-gray-900 border border-gray-205 dark:border-gray-800 hover:bg-gray-50 rounded-xl shadow-3xs transition-colors cursor-pointer"
                    >
                        <span>← Volver al Centro de Control</span>
                    </Link>
                </div>
            </div>
        </template>

        <!-- Loading State -->
        <div v-if="isLoading" class="grid grid-cols-2 md:grid-cols-4 gap-5">
            <div v-for="i in 4" :key="i" class="h-24 bg-gray-100 dark:bg-gray-800 rounded-2xl animate-pulse"></div>
        </div>

        <div v-else :class="panel === 'ia' ? 'h-full flex flex-col flex-1 min-h-0' : ''">
            <!-- ======================================================= -->
            <!-- 1. HUB CENTRAL DE CONTROL                               -->
            <!-- ======================================================= -->
            <div v-if="panel === 'control'" class="space-y-6">
                <!-- Grid de Accesos Rápidos a Reportes -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <!-- Ejecutivo -->
                    <Link :href="route('dashboard', { panel: 'ejecutivo' })" class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs hover:shadow-md hover:border-blue-600/40 hover:-translate-y-0.5 transition-all flex flex-col justify-between h-40 group cursor-pointer text-left">
                        <div class="flex items-center justify-between">
                            <span class="p-2.5 bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 rounded-xl group-hover:bg-blue-600 group-hover:text-white transition-colors duration-200">
                                <BarChart3 class="w-5 h-5" />
                            </span>
                            <ChevronRight class="w-4 h-4 text-gray-400" />
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white">Dashboard Ejecutivo</h4>
                            <p class="text-[10px] text-gray-450 mt-1 leading-snug">Ventas netas, utilidades, balance de caja diario y productos críticos.</p>
                        </div>
                    </Link>

                    <!-- Ventas -->
                    <Link :href="route('dashboard', { panel: 'ventas' })" class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs hover:shadow-md hover:border-blue-600/40 hover:-translate-y-0.5 transition-all flex flex-col justify-between h-40 group cursor-pointer text-left">
                        <div class="flex items-center justify-between">
                            <span class="p-2.5 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-600 dark:text-emerald-400 rounded-xl group-hover:bg-emerald-600 group-hover:text-white transition-colors duration-200">
                                <ShoppingCart class="w-5 h-5" />
                            </span>
                            <ChevronRight class="w-4 h-4 text-gray-400" />
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white">Reporte de Ventas</h4>
                            <p class="text-[10px] text-gray-450 mt-1 leading-snug">Productos más vendidos, facturación diaria y canales de pago.</p>
                        </div>
                    </Link>

                    <!-- Inventario -->
                    <Link :href="route('dashboard', { panel: 'inventario' })" class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs hover:shadow-md hover:border-blue-600/40 hover:-translate-y-0.5 transition-all flex flex-col justify-between h-40 group cursor-pointer text-left">
                        <div class="flex items-center justify-between">
                            <span class="p-2.5 bg-indigo-50 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 rounded-xl group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-200">
                                <Archive class="w-5 h-5" />
                            </span>
                            <ChevronRight class="w-4 h-4 text-gray-400" />
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white">Reporte de Inventario</h4>
                            <p class="text-[10px] text-gray-450 mt-1 leading-snug">Valorización de almacén, productos sin movimiento y stock cero.</p>
                        </div>
                    </Link>

                    <!-- Financiero -->
                    <Link :href="route('dashboard', { panel: 'financiero' })" class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs hover:shadow-md hover:border-blue-600/40 hover:-translate-y-0.5 transition-all flex flex-col justify-between h-40 group cursor-pointer text-left">
                        <div class="flex items-center justify-between">
                            <span class="p-2.5 bg-teal-50 dark:bg-teal-950/30 text-teal-600 dark:text-teal-400 rounded-xl group-hover:bg-teal-600 group-hover:text-white transition-colors duration-200">
                                <DollarSign class="w-5 h-5" />
                            </span>
                            <ChevronRight class="w-4 h-4 text-gray-400" />
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white">Reporte Financiero</h4>
                            <p class="text-[10px] text-gray-450 mt-1 leading-snug">Ingresos del mes, egresos, margen neto y evolución de flujo.</p>
                        </div>
                    </Link>

                    <!-- Compras -->
                    <Link :href="route('dashboard', { panel: 'compras' })" class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs hover:shadow-md hover:border-blue-600/40 hover:-translate-y-0.5 transition-all flex flex-col justify-between h-40 group cursor-pointer text-left">
                        <div class="flex items-center justify-between">
                            <span class="p-2.5 bg-amber-50 dark:bg-amber-950/30 text-amber-600 dark:text-amber-400 rounded-xl group-hover:bg-amber-600 group-hover:text-white transition-colors duration-200">
                                <Calendar class="w-5 h-5" />
                            </span>
                            <ChevronRight class="w-4 h-4 text-gray-400" />
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white">Reporte de Compras</h4>
                            <p class="text-[10px] text-gray-450 mt-1 leading-snug">Total invertido en insumos, últimas órdenes y folios de compras.</p>
                        </div>
                    </Link>

                    <!-- Clientes / Proveedores -->
                    <Link :href="route('dashboard', { panel: 'clientes' })" class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs hover:shadow-md hover:border-blue-600/40 hover:-translate-y-0.5 transition-all flex flex-col justify-between h-40 group cursor-pointer text-left">
                        <div class="flex items-center justify-between">
                            <span class="p-2.5 bg-sky-50 dark:bg-sky-950/30 text-sky-600 dark:text-sky-400 rounded-xl group-hover:bg-sky-600 group-hover:text-white transition-colors duration-200">
                                <Users class="w-5 h-5" />
                            </span>
                            <ChevronRight class="w-4 h-4 text-gray-400" />
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white">Clientes & Proveedores</h4>
                            <p class="text-[10px] text-gray-450 mt-1 leading-snug">Mejores clientes, deudores de crédito, inactivos y ranking de gasto comercial.</p>
                        </div>
                    </Link>
                </div>

                <!-- Alertas de Control Operativo -->
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-855 p-6 shadow-3xs">
                    <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800 flex items-center gap-2">
                        <AlertTriangle class="w-4.5 h-4.5 text-amber-500" />
                        Alertas Operativas del Negocio
                    </h3>
                    <div v-if="alerts.length === 0" class="text-xs text-gray-450 text-center py-6">
                        No hay anomalías o stock crítico por alertar hoy. ¡Buen trabajo!
                    </div>
                    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-3 mt-4">
                        <div 
                            v-for="(alert, idx) in alerts" 
                            :key="idx" 
                            class="p-4 rounded-xl border text-xs flex items-start gap-3 shadow-2xs"
                            :class="{
                                'bg-rose-50 border-rose-100 text-rose-800 dark:bg-rose-950/20 dark:border-rose-900/30 dark:text-rose-400': alert.type === 'critical',
                                'bg-amber-50 border-amber-100 text-amber-800 dark:bg-amber-950/20 dark:border-amber-900/30 dark:text-amber-400': alert.type === 'warning',
                                'bg-emerald-50 border-emerald-100 text-emerald-800 dark:bg-emerald-950/20 dark:border-emerald-900/30 dark:text-emerald-400': alert.type === 'success',
                                'bg-blue-50 border-blue-100 text-blue-800 dark:bg-blue-950/20 dark:border-blue-900/30 dark:text-blue-400': alert.type === 'info'
                            }"
                        >
                            <span class="text-sm select-none">💡</span>
                            <div>
                                <div class="font-extrabold uppercase text-[10px] tracking-wider mb-0.5">{{ alert.title }}</div>
                                <div class="opacity-95">{{ alert.message }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- 2. DASHBOARD EJECUTIVO                                  -->
            <!-- ======================================================= -->
            <div v-else-if="panel === 'ejecutivo'" class="space-y-6">
                <!-- Header de preguntas respondidas -->
                <div class="bg-blue-50/50 dark:bg-blue-950/10 border border-blue-100/60 dark:border-blue-900/20 p-4 rounded-2xl flex items-center gap-3">
                    <span class="text-base select-none">📌</span>
                    <p class="text-xs text-blue-900 dark:text-blue-300 font-medium">
                        Este reporte responde: <strong>¿Cuánto vendí hoy?</strong>, <strong>¿Cuánto gané hoy?</strong>, <strong>¿Cómo está la caja?</strong> y <strong>¿Tengo stock crítico por reponer?</strong>.
                    </p>
                </div>

                <!-- KPIs -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Ventas de Hoy -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 p-5 rounded-2xl shadow-3xs flex flex-col justify-between">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450">Ventas de Hoy</span>
                        <h4 class="text-xl font-bold font-mono text-gray-900 dark:text-white mt-1">
                            {{ formatCurrency(kpis.ventas?.value) }}
                        </h4>
                        <!-- Comparativa con ayer -->
                        <div class="mt-2.5 flex items-center gap-1.5 text-[9px] font-mono">
                            <span 
                                class="inline-flex items-center px-1 rounded font-bold"
                                :class="kpis.ventas.trend === 'up' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                            >
                                {{ kpis.ventas.trend === 'up' ? '↑' : '↓' }} {{ kpis.ventas.pct }}%
                            </span>
                            <span class="text-gray-450">vs ayer</span>
                        </div>
                    </div>

                    <!-- Utilidad de Hoy -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 p-5 rounded-2xl shadow-3xs flex flex-col justify-between">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450">Ganancia de Hoy</span>
                        <h4 class="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-450 mt-1">
                            {{ formatCurrency(kpis.utilidad?.value) }}
                        </h4>
                        <div class="mt-2.5 flex items-center gap-1.5 text-[9px] font-mono">
                            <span 
                                class="inline-flex items-center px-1 rounded font-bold"
                                :class="kpis.utilidad.trend === 'up' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'"
                            >
                                {{ kpis.utilidad.trend === 'up' ? '↑' : '↓' }} {{ kpis.utilidad.pct }}%
                            </span>
                            <span class="text-gray-450">vs ayer</span>
                        </div>
                    </div>

                    <!-- Caja General -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 p-5 rounded-2xl shadow-3xs flex flex-col justify-between">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450">Saldo en Caja</span>
                        <h4 class="text-xl font-bold font-mono text-gray-900 dark:text-white mt-1">
                            {{ formatCurrency(kpis.caja?.value) }}
                        </h4>
                        <span class="text-[9px] text-gray-400 mt-2.5 block font-medium">Efectivo líquido estimado</span>
                    </div>

                    <!-- Stock Mínimo -->
                    <div class="bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 p-5 rounded-2xl shadow-3xs flex flex-col justify-between">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450">Stock Crítico</span>
                        <h4 class="text-xl font-bold font-mono mt-1" :class="Number(kpis.bajo_stock?.value) > 0 ? 'text-red-500' : 'text-gray-900 dark:text-white'">
                            {{ kpis.bajo_stock?.value }} ítems
                        </h4>
                        <span class="text-[9px] text-gray-400 mt-2.5 block font-medium">Productos por reponer</span>
                    </div>
                </div>

                <!-- Comparativa Gráfica Sencilla -->
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-855 p-6 shadow-3xs space-y-4">
                    <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800">
                        Balance Semanal: Ventas vs Compras
                    </h3>
                    <div class="relative h-56 w-full pt-4 flex">
                        <!-- Y-Axis labels (Crisp HTML, never stretched) -->
                        <div class="w-12 h-[140px] mt-[10px] flex flex-col justify-between text-[9px] font-mono font-bold text-gray-400 text-right pr-2 select-none z-10">
                            <span>{{ formatShortCurrency(maxChart1Val) }}</span>
                            <span>{{ formatShortCurrency(maxChart1Val / 2) }}</span>
                            <span>S/ 0</span>
                        </div>
                        
                        <!-- SVG graphic area -->
                        <div class="flex-1 h-[160px] relative">
                            <svg class="w-full h-[140px]" viewBox="0 0 590 140" preserveAspectRatio="none">
                                <!-- Grid lines -->
                                <line x1="0" y1="0" x2="590" y2="0" stroke="#f3f4f6" stroke-width="1" stroke-dasharray="4 4" class="dark:stroke-gray-800" />
                                <line x1="0" y1="70" x2="590" y2="70" stroke="#f3f4f6" stroke-width="1" stroke-dasharray="4 4" class="dark:stroke-gray-800" />
                                <line x1="0" y1="140" x2="590" y2="140" stroke="#e5e7eb" stroke-width="1" class="dark:stroke-gray-800" />

                                <g v-for="(item, idx) in chartVentasCompras" :key="idx">
                                    <!-- Ventas (Blue) -->
                                    <rect 
                                        :x="20 + idx * 80" 
                                        :y="140 - (item.ventas / maxChart1Val) * 120" 
                                        width="14" 
                                        :height="Math.max((item.ventas / maxChart1Val) * 120, 2)" 
                                        rx="3" 
                                        fill="#3b82f6"
                                        class="transition-all duration-300"
                                    />
                                    <!-- Compras (Gray) -->
                                    <rect 
                                        :x="36 + idx * 80" 
                                        :y="140 - (item.compras / maxChart1Val) * 120" 
                                        width="14" 
                                        :height="Math.max((item.compras / maxChart1Val) * 120, 2)" 
                                        rx="3" 
                                        fill="#cbd5e1"
                                        class="dark:fill-gray-700 transition-all duration-300"
                                    />
                                </g>
                            </svg>
                            
                            <!-- X-Axis labels (Crisp HTML, absolutely positioned, never stretched) -->
                            <div class="absolute left-0 right-0 top-[148px] h-6 text-[10px] font-bold text-gray-400 select-none">
                                <span 
                                    v-for="(item, idx) in chartVentasCompras" 
                                    :key="idx" 
                                    class="absolute w-16 text-center"
                                    :style="{ left: `calc(${((35 + idx * 80) / 590) * 100}% - 2rem)` }"
                                >
                                    {{ item.label }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-4 justify-center text-[10px] font-bold">
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-blue-500"></span> Ventas</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-gray-300 dark:bg-gray-600"></span> Compras (Logística)</span>
                    </div>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- 3. REPORTE DE VENTAS                                    -->
            <!-- ======================================================= -->
            <div v-else-if="panel === 'ventas'" class="space-y-6">
                <!-- Header de preguntas respondidas -->
                <div class="bg-blue-50/50 dark:bg-blue-950/10 border border-blue-100/60 dark:border-blue-900/20 p-4 rounded-2xl flex items-center gap-3">
                    <span class="text-base select-none">📌</span>
                    <p class="text-xs text-blue-900 dark:text-blue-300 font-medium">
                        Este reporte responde: <strong>¿Qué vendí más y qué vendí menos?</strong>, <strong>¿Cuál es la venta diaria?</strong>, <strong>¿Quién vende más?</strong> y <strong>¿Qué método de pago prefieren?</strong>.
                    </p>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Line chart 30 days -->
                    <div class="lg:col-span-2 bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-800 p-6 space-y-4 shadow-3xs">
                        <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800">
                            Historial Diario de Facturación (Últimos 30 días)
                        </h3>
                        <div class="relative h-48 w-full pt-2 flex">
                            <!-- Y-Axis labels (Crisp HTML, never stretched) -->
                            <div class="w-12 h-[140px] flex flex-col justify-between text-[9px] font-mono font-bold text-gray-400 text-right pr-2 select-none z-10">
                                <span>{{ formatShortCurrency(maxVentas30Val) }}</span>
                                <span>{{ formatShortCurrency(maxVentas30Val / 2) }}</span>
                                <span>S/ 0</span>
                            </div>
                            
                            <!-- SVG graphic area -->
                            <div class="flex-1 h-[160px] relative">
                                <svg class="w-full h-[140px]" viewBox="0 0 500 140" preserveAspectRatio="none">
                                    <defs>
                                        <linearGradient id="salesGrad30" x1="0" y1="0" x2="0" y2="1">
                                            <stop offset="0%" stop-color="#3b82f6" stop-opacity="0.15" />
                                            <stop offset="100%" stop-color="#3b82f6" stop-opacity="0" />
                                        </linearGradient>
                                    </defs>
                                    <!-- Grid lines -->
                                    <line x1="0" y1="0" x2="500" y2="0" stroke="#f3f4f6" stroke-width="1" stroke-dasharray="4 4" class="dark:stroke-gray-800" />
                                    <line x1="0" y1="70" x2="500" y2="70" stroke="#f3f4f6" stroke-width="1" stroke-dasharray="4 4" class="dark:stroke-gray-800" />
                                    <line x1="0" y1="140" x2="500" y2="140" stroke="#e5e7eb" stroke-width="1" class="dark:stroke-gray-800" />

                                    <!-- Area & Curve -->
                                    <path :d="ventas30AreaPath" fill="url(#salesGrad30)" />
                                    <path :d="ventas30Path" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    
                                    <g v-for="(pt, idx) in ventas30Points" :key="idx">
                                        <g v-if="idx % 5 === 0 || idx === ventas30Points.length - 1">
                                            <circle :cx="pt.x" :cy="pt.y" r="5" fill="#3b82f6" fill-opacity="0.2" />
                                            <circle :cx="pt.x" :cy="pt.y" r="2.5" fill="#3b82f6" />
                                        </g>
                                    </g>
                                </svg>
                                
                                <!-- X-Axis labels (Crisp HTML, absolutely positioned, never stretched) -->
                                <div class="absolute left-0 right-0 top-[145px] h-6 text-[10px] font-bold text-gray-400 select-none">
                                    <span 
                                        v-for="(pt, idx) in ventas30Points" 
                                        :key="idx" 
                                        class="absolute w-16 text-center"
                                        :style="{ left: `calc(${(pt.x / 500) * 100}% - 2rem)` }"
                                    >
                                        <template v-if="idx % 5 === 0">
                                            {{ pt.label }}
                                        </template>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Métodos de Pago Preferidos -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-800 p-6 space-y-4 shadow-3xs">
                        <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800">
                            ¿Cómo Pagan los Clientes?
                        </h3>
                        <div class="space-y-4 pt-1">
                            <div v-for="(seg, idx) in metodosPagoSuma" :key="idx" class="space-y-1">
                                <div class="flex justify-between text-xs font-bold">
                                    <span class="capitalize flex items-center gap-1.5">
                                        <CreditCard class="w-3.5 h-3.5 text-gray-400" />
                                        {{ seg.label }}
                                    </span>
                                    <span class="font-mono text-gray-900 dark:text-white">{{ seg.percent }}%</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-800 h-2 rounded-full overflow-hidden">
                                    <div 
                                        class="h-full rounded-full" 
                                        :class="idx === 0 ? 'bg-blue-500' : idx === 1 ? 'bg-emerald-500' : 'bg-amber-500'" 
                                        :style="{ width: `${seg.percent}%` }"
                                    ></div>
                                </div>
                                <span class="text-[9px] text-gray-450 block font-mono">Facturado: {{ formatCurrency(seg.total) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Top Vendidos -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-855 p-6 shadow-3xs space-y-4">
                        <h3 class="text-xs font-bold text-emerald-600 uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800">
                            🔥 Productos Más Vendidos
                        </h3>
                        <div class="space-y-2">
                            <div 
                                v-for="(item, i) in chartProductosMasVendidos?.slice(0, 5)" 
                                :key="item.nombre"
                                class="flex items-center justify-between p-2.5 border border-gray-100 dark:border-gray-850 rounded-xl text-xs"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold font-mono text-[10px]">{{ i + 1 }}</span>
                                    <span class="font-bold text-gray-800 dark:text-gray-250 truncate max-w-[150px]">{{ item.nombre }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono font-bold block text-gray-950 dark:text-white">{{ formatSoldQuantity(item) }}</span>
                                    <span class="text-[9px] text-gray-450 font-mono">{{ formatCurrency(item.total) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Menos Vendidos -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-855 p-6 shadow-3xs space-y-4">
                        <h3 class="text-xs font-bold text-rose-600 uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800">
                            ❄️ Productos Menos Vendidos
                        </h3>
                        <div class="space-y-2">
                            <div 
                                v-for="(item, i) in productosMenosVendidos" 
                                :key="item.nombre"
                                class="flex items-center justify-between p-2.5 border border-gray-100 dark:border-gray-855 rounded-xl text-xs"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="w-5 h-5 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center font-bold font-mono text-[10px]">{{ i + 1 }}</span>
                                    <span class="font-bold text-gray-800 dark:text-gray-250 truncate max-w-[150px]">{{ item.nombre }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono font-bold block text-gray-950 dark:text-white">{{ formatSoldQuantity(item) }}</span>
                                    <span class="text-[9px] text-gray-450 font-mono">{{ formatCurrency(item.total) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ranking Cajeros -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-855 p-6 shadow-3xs space-y-4">
                        <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800">
                            👤 Desempeño de Cajeros
                        </h3>
                        <div class="space-y-2">
                            <div 
                                v-for="item in rankingVendedores" 
                                :key="item.nombre"
                                class="flex items-center justify-between p-2.5 border border-gray-100 dark:border-gray-850 rounded-xl text-xs"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="p-1 bg-gray-50 dark:bg-gray-950 rounded-lg">👤</span>
                                    <span class="font-bold text-gray-850 dark:text-gray-250">{{ item.nombre }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono font-bold block text-emerald-600">{{ formatCurrency(item.total) }}</span>
                                    <span class="text-[9px] text-gray-450 font-mono">{{ item.cantidad }} ventas</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- 4. REPORTE DE INVENTARIO                                -->
            <!-- ======================================================= -->
            <div v-else-if="panel === 'inventario'" class="space-y-6">
                <!-- Header de preguntas respondidas -->
                <div class="bg-blue-50/50 dark:bg-blue-950/10 border border-blue-100/60 dark:border-blue-900/20 p-4 rounded-2xl flex items-center gap-3">
                    <span class="text-base select-none">📌</span>
                    <p class="text-xs text-blue-900 dark:text-blue-300 font-medium">
                        Este reporte responde: <strong>¿Cuál es el valor total de mi stock?</strong>, <strong>¿Qué productos debo comprar?</strong> y <strong>¿Qué productos están agotados?</strong>.
                    </p>
                </div>

                <!-- Valorización de inventario -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450">Valor del Stock (Costo Adquisición)</span>
                        <h4 class="text-xl font-bold font-mono text-gray-900 dark:text-white mt-1">
                            {{ formatCurrency(valorizacionCosto) }}
                        </h4>
                        <span class="text-[9px] text-gray-400 mt-2 block font-medium">Dinero invertido en almacén</span>
                    </div>

                    <div class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450">Valor del Stock (Precio de Venta)</span>
                        <h4 class="text-xl font-bold font-mono text-gray-900 dark:text-white mt-1">
                            {{ formatCurrency(valorizacionVenta) }}
                        </h4>
                        <span class="text-[9px] text-gray-400 mt-2 block font-medium">Ingreso potencial estimado</span>
                    </div>

                    <div class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450">Margen Comercial Estimado</span>
                        <h4 class="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-450 mt-1">
                            {{ formatCurrency(valorizacionVenta - valorizacionCosto) }}
                        </h4>
                        <span class="text-[9px] text-gray-400 mt-2 block font-medium">Ganancia si se vende todo el stock</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Productos por reponer (Crítico) -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-855 p-6 shadow-3xs space-y-4">
                        <h3 class="text-xs font-bold text-rose-600 uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800">
                            ⚠️ Productos a Reponer (Stock Crítico)
                        </h3>
                        <div v-if="productosBajoStock.length === 0" class="text-xs text-gray-400 text-center py-8 italic">
                            No hay productos por debajo del stock mínimo.
                        </div>
                        <div v-else class="space-y-2 max-h-96 overflow-y-auto pr-1">
                            <div 
                                v-for="p in productosBajoStock" 
                                :key="p.id"
                                class="flex items-center justify-between p-3 border border-gray-100 dark:border-gray-855 rounded-xl text-xs hover:bg-gray-50/50"
                            >
                                <div class="flex flex-col">
                                    <span class="font-bold text-gray-850 dark:text-gray-250">{{ p.nombre }}</span>
                                    <span class="text-[9px] text-gray-400 mt-0.5">Stock Mínimo: {{ p.stock_minimo }}</span>
                                </div>
                                <span class="font-mono font-bold text-red-500 text-sm">{{ p.stock_actual }} {{ p.unidad_medida }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Agotados -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-855 p-6 shadow-3xs space-y-4">
                        <h3 class="text-xs font-bold text-slate-800 dark:text-gray-200 uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800">
                            🚫 Productos Agotados (Stock Cero)
                        </h3>
                        <div v-if="productosAgotados.length === 0" class="text-xs text-gray-400 text-center py-8 italic">
                            No hay productos completamente agotados.
                        </div>
                        <div v-else class="space-y-2 max-h-96 overflow-y-auto pr-1">
                            <div 
                                v-for="p in productosAgotados" 
                                :key="p.id"
                                class="flex items-center justify-between p-3 border border-gray-100 dark:border-gray-855 rounded-xl text-xs hover:bg-gray-50/50"
                            >
                                <span class="font-bold text-gray-850 dark:text-gray-250">{{ p.nombre }}</span>
                                <span class="font-mono font-bold text-red-500 bg-red-50 px-2 py-0.5 rounded border border-red-100 text-[10px] uppercase">Agotado</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- 5. REPORTE FINANCIERO                                   -->
            <!-- ======================================================= -->
            <div v-else-if="panel === 'financiero'" class="space-y-6">
                <!-- Header de preguntas respondidas -->
                <div class="bg-blue-50/50 dark:bg-blue-950/10 border border-blue-100/60 dark:border-blue-900/20 p-4 rounded-2xl flex items-center gap-3">
                    <span class="text-base select-none">📌</span>
                    <p class="text-xs text-blue-900 dark:text-blue-300 font-medium">
                        Este reporte responde: <strong>¿Cuánto dinero ingresó?</strong>, <strong>¿Cuánto dinero salió en compras?</strong> y <strong>¿Cuál es la utilidad del negocio?</strong>.
                    </p>
                </div>

                <!-- KPIs -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450 block">Ingresos Totales (Ventas)</span>
                        <span class="text-xl font-bold font-mono text-gray-900 dark:text-white mt-1.5 block">{{ formatCurrency(totalIngresos) }}</span>
                    </div>
                    <div class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450 block">Egresos Totales (Compras)</span>
                        <span class="text-xl font-bold font-mono text-red-500 mt-1.5 block">{{ formatCurrency(totalEgresos) }}</span>
                    </div>
                    <div class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450 block">Utilidad Neta</span>
                        <span class="text-xl font-bold font-mono text-emerald-600 mt-1.5 block">{{ formatCurrency(utilidadTotal) }}</span>
                    </div>
                    <div class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450 block">Margen Neto (%)</span>
                        <span class="text-xl font-bold font-mono text-blue-600 mt-1.5 block">{{ Number(margenRentabilidad).toFixed(1) }}%</span>
                    </div>
                </div>

                <!-- Cash flow line chart -->
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-855 p-6 shadow-3xs space-y-4">
                    <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800">
                        Evolución del Flujo de Caja
                    </h3>
                    <div class="relative h-56 w-full pt-4 flex">
                        <!-- Y-Axis labels (Crisp HTML, never stretched) -->
                        <div class="w-12 h-[140px] mt-[10px] flex flex-col justify-between text-[9px] font-mono font-bold text-gray-400 text-right pr-2 select-none z-10">
                            <span>{{ formatShortCurrency(maxFinancieroVal) }}</span>
                            <span>{{ formatShortCurrency(maxFinancieroVal / 2) }}</span>
                            <span>S/ 0</span>
                        </div>
                        
                        <!-- SVG Area -->
                        <div class="flex-1 h-[160px] relative">
                            <svg class="w-full h-[140px]" viewBox="0 0 600 140" preserveAspectRatio="none">
                                <!-- Grid lines -->
                                <line x1="0" y1="0" x2="600" y2="0" stroke="#f3f4f6" stroke-width="1" stroke-dasharray="4 4" class="dark:stroke-gray-800" />
                                <line x1="0" y1="70" x2="600" y2="70" stroke="#f3f4f6" stroke-width="1" stroke-dasharray="4 4" class="dark:stroke-gray-800" />
                                <line x1="0" y1="140" x2="600" y2="140" stroke="#e5e7eb" stroke-width="1" class="dark:stroke-gray-800" />

                                <!-- Curves -->
                                <path :d="makePath(financieroPoints.ingresos)" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <path :d="makePath(financieroPoints.egresos)" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                <path :d="makePath(financieroPoints.utilidad)" fill="none" stroke="#3b82f6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />

                                <!-- Glowing Dots -->
                                <g v-for="(pt, idx) in financieroPoints.ingresos" :key="idx">
                                    <circle :cx="pt.x" :cy="pt.y" r="5" fill="#10B981" fill-opacity="0.2" />
                                    <circle :cx="pt.x" :cy="pt.y" r="2.5" fill="#10B981" />
                                    
                                    <circle :cx="financieroPoints.egresos[idx].x" :cy="financieroPoints.egresos[idx].y" r="5" fill="#ef4444" fill-opacity="0.2" />
                                    <circle :cx="financieroPoints.egresos[idx].x" :cy="financieroPoints.egresos[idx].y" r="2.5" fill="#ef4444" />
                                    
                                    <circle :cx="financieroPoints.utilidad[idx].x" :cy="financieroPoints.utilidad[idx].y" r="5" fill="#3b82f6" fill-opacity="0.2" />
                                    <circle :cx="financieroPoints.utilidad[idx].x" :cy="financieroPoints.utilidad[idx].y" r="2.5" fill="#3b82f6" />
                                </g>
                            </svg>
                            
                            <!-- X-Axis labels (Crisp HTML, absolutely positioned, never stretched) -->
                            <div class="absolute left-0 right-0 top-[148px] h-6 text-[10px] font-bold text-gray-400 select-none">
                                <span 
                                    v-for="(pt, idx) in financieroPoints.ingresos" 
                                    :key="idx" 
                                    class="absolute w-16 text-center"
                                    :style="{ left: `calc(${(pt.x / 600) * 100}% - 2rem)` }"
                                >
                                    {{ pt.label }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-4 justify-center text-[10px] font-bold">
                        <span class="flex items-center gap-1.5"><span class="w-3.5 h-0.5 bg-emerald-500"></span> Ingresos</span>
                        <span class="flex items-center gap-1.5"><span class="w-3.5 h-0.5 bg-red-500"></span> Egresos</span>
                        <span class="flex items-center gap-1.5"><span class="w-3.5 h-0.5 bg-blue-500"></span> Utilidad</span>
                    </div>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- 6. REPORTE DE COMPRAS                                   -->
            <!-- ======================================================= -->
            <div v-else-if="panel === 'compras'" class="space-y-6">
                <!-- Header de preguntas respondidas -->
                <div class="bg-blue-50/50 dark:bg-blue-950/10 border border-blue-100/60 dark:border-blue-900/20 p-4 rounded-2xl flex items-center gap-3">
                    <span class="text-base select-none">📌</span>
                    <p class="text-xs text-blue-900 dark:text-blue-300 font-medium">
                        Este reporte responde: <strong>¿Cuánto invertí en mercadería este mes?</strong>, <strong>¿A qué distribuidor le compro más?</strong> y <strong>¿Cuáles son las últimas compras?</strong>.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450 block">Compras del Mes (Reabastecimientos)</span>
                        <span class="text-xl font-bold font-mono text-gray-900 dark:text-white mt-1.5 block">{{ formatCurrency(comprasMes) }}</span>
                    </div>
                    <div class="p-5 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450 block">Monto Invertido Total (Histórico)</span>
                        <span class="text-xl font-bold font-mono text-gray-900 dark:text-white mt-1.5 block">{{ formatCurrency(montoInvertidoTotal) }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Últimas compras -->
                    <div class="lg:col-span-8 bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-855 p-6 shadow-3xs space-y-4">
                        <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800">
                            Últimas Compras Registradas
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-[#E5E7EB] dark:border-gray-800 text-gray-450 font-bold">
                                        <th class="py-2.5 px-3">Código</th>
                                        <th class="py-2.5 px-3">Proveedor</th>
                                        <th class="py-2.5 px-3 text-right">Monto</th>
                                        <th class="py-2.5 px-3 text-center">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="compra in ultimasCompras" :key="compra.id" class="border-b border-[#E5E7EB]/50 dark:border-gray-800 hover:bg-gray-50/50 transition-colors">
                                        <td class="py-3 px-3 font-bold text-[#3b82f6] font-mono">{{ compra.numero_compra }}</td>
                                        <td class="py-3 px-3 text-gray-850 dark:text-gray-250">{{ compra.proveedor?.nombre || '-' }}</td>
                                        <td class="py-3 px-3 text-right font-mono font-bold text-red-500">{{ formatCurrency(compra.total) }}</td>
                                        <td class="py-3 px-3 text-center text-gray-400 font-mono">{{ new Date(compra.fecha_compra).toLocaleDateString('es-PE') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Proveedores Principales -->
                    <div class="lg:col-span-4 bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-855 p-6 shadow-3xs space-y-4">
                        <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800">
                            Distribución de Compras por Proveedor
                        </h3>
                        <div class="space-y-4">
                            <div v-for="item in proveedoresPrincipales" :key="item.nombre" class="space-y-1">
                                <div class="flex justify-between text-xs font-bold">
                                    <span class="text-gray-850 dark:text-gray-250 truncate max-w-[150px]">{{ item.nombre }}</span>
                                    <span class="font-bold text-gray-900 dark:text-white font-mono">{{ formatCurrency(item.total) }}</span>
                                </div>
                                <div class="w-full bg-gray-100 dark:bg-gray-800 h-1.5 rounded-full overflow-hidden">
                                    <div class="bg-amber-500 h-full rounded-full" :style="{ width: `${(item.total / (proveedoresPrincipales[0]?.total || 1)) * 100}%` }"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- 7. REPORTE DE CLIENTES                                  -->
            <!-- ======================================================= -->
            <div v-else-if="panel === 'clientes'" class="space-y-6">
                <!-- Header de preguntas respondidas -->
                <div class="bg-blue-50/50 dark:bg-blue-950/10 border border-blue-100/60 dark:border-blue-900/20 p-4 rounded-2xl flex items-center gap-3">
                    <span class="text-base select-none">📌</span>
                    <p class="text-xs text-blue-900 dark:text-blue-300 font-medium">
                        Este reporte responde: <strong>¿Quiénes son mis clientes estrella?</strong>, <strong>¿Quién compra con más frecuencia?</strong>, <strong>¿Quiénes dejaron de comprar (inactivos)?</strong>.
                    </p>
                </div>

                <!-- KPIs -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="p-4 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450 block">Clientes VIP (Consumo Alto)</span>
                        <span class="text-xl font-bold font-mono text-gray-900 dark:text-white mt-1 block">{{ clientesVIP?.length || 0 }}</span>
                    </div>
                    <div class="p-4 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450 block">Clientes Nuevos del Mes</span>
                        <span class="text-xl font-bold font-mono text-gray-900 dark:text-white mt-1 block">{{ clientesNuevos?.length || 0 }}</span>
                    </div>
                    <div class="p-4 bg-white dark:bg-gray-900 border border-gray-250 dark:border-gray-800 rounded-2xl shadow-3xs">
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-450 block">Clientes Inactivos (Fuga)</span>
                        <span class="text-xl font-bold font-mono text-red-500 mt-1 block">{{ clientesInactivos?.length || 0 }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <!-- Mejores clientes -->
                    <div class="lg:col-span-8 bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-855 p-6 shadow-3xs space-y-4">
                        <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800">
                            Ranking de Clientes Estrella (VIP)
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-[#E5E7EB] dark:border-gray-800 text-gray-455 font-bold">
                                        <th class="py-2.5 px-3">Cliente</th>
                                        <th class="py-2.5 px-3">Correo</th>
                                        <th class="py-2.5 px-3 text-right">Compras</th>
                                        <th class="py-2.5 px-3 text-right">Total Invertido</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="vip in clientesVIP" :key="vip.nombre" class="border-b border-[#E5E7EB]/50 dark:border-gray-800 hover:bg-gray-50/50 transition-colors">
                                        <td class="py-3.5 px-3 font-bold text-gray-850 dark:text-gray-250">{{ vip.nombre }}</td>
                                        <td class="py-3.5 px-3 text-gray-450 font-mono">{{ vip.email || '-' }}</td>
                                        <td class="py-3.5 px-3 text-right font-mono">{{ vip.cantidad }} vtas.</td>
                                        <td class="py-3.5 px-3 text-right font-mono font-bold text-emerald-600">{{ formatCurrency(vip.total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Inactivos -->
                    <div class="lg:col-span-4 bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-855 p-6 shadow-3xs space-y-4">
                        <h3 class="text-xs font-bold text-rose-500 uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800">
                            ⚠️ Clientes Inactivos (Reactivar)
                        </h3>
                        <div v-if="clientesInactivos?.length === 0" class="text-xs text-gray-400 text-center py-8 italic">
                            No hay clientes inactivos.
                        </div>
                        <div v-else class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                            <div v-for="c in clientesInactivos" :key="c.id" class="flex justify-between items-center text-xs p-3 bg-gray-50 dark:bg-gray-950 border border-gray-100 dark:border-gray-850 rounded-xl shadow-3xs">
                                <div>
                                    <div class="font-bold text-gray-850 dark:text-gray-250 truncate max-w-[130px]">{{ c.nombre }}</div>
                                    <div class="text-[9px] text-gray-450 mt-0.5">{{ c.telefono || 'Sin teléfono' }}</div>
                                </div>
                                <span class="text-[9px] font-mono text-rose-600 bg-rose-50 px-2 py-0.5 rounded border border-rose-100 font-bold uppercase shrink-0">Inactivo</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- 8. REPORTE DE PROVEEDORES                               -->
            <!-- ======================================================= -->
            <div v-else-if="panel === 'proveedores'" class="space-y-6">
                <!-- Header de preguntas respondidas -->
                <div class="bg-blue-50/50 dark:bg-blue-950/10 border border-blue-100/60 dark:border-blue-900/20 p-4 rounded-2xl flex items-center gap-3">
                    <span class="text-base select-none">📌</span>
                    <p class="text-xs text-blue-900 dark:text-blue-300 font-medium">
                        Este reporte responde: <strong>¿A qué distribuidor le compré más?</strong>, <strong>¿Cuántos pedidos les realicé?</strong> y <strong>¿Cuánto dinero gasté con cada uno?</strong>.
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-855 p-6 shadow-3xs space-y-4">
                    <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider pb-3 border-b border-gray-100 dark:border-gray-800">
                        Historial de Gastos y Pedidos por Proveedor
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs text-left border-collapse">
                            <thead>
                                <tr class="border-b border-[#E5E7EB] dark:border-gray-800 text-gray-455 font-bold">
                                    <th class="py-2.5 px-3">Proveedor</th>
                                    <th class="py-2.5 px-3">RUC</th>
                                    <th class="py-2.5 px-3 text-right">Pedidos / Órdenes</th>
                                    <th class="py-2.5 px-3 text-right font-bold text-gray-900 dark:text-white">Total Facturado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="prov in rankingProveedores" :key="prov.nombre" class="border-b border-[#E5E7EB]/50 dark:border-gray-800 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3.5 px-3 font-bold text-gray-850 dark:text-gray-250">{{ prov.nombre }}</td>
                                    <td class="py-3.5 px-3 text-gray-450 font-mono">{{ prov.ruc || '-' }}</td>
                                    <td class="py-3.5 px-3 text-right font-mono">{{ prov.cantidad }} órdenes</td>
                                    <td class="py-3.5 px-3 text-right font-mono font-bold text-emerald-600">{{ formatCurrency(prov.total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ======================================================= -->
            <!-- 9. ASISTENTE IA                                         -->
            <!-- ======================================================= -->
            <div v-else-if="panel === 'ia'" class="flex flex-col flex-1 min-h-0 bg-white dark:bg-gray-900 border-0 md:border border-gray-200 dark:border-gray-800 shadow-none md:shadow-sm overflow-hidden">
                <!-- Welcome/Top Bar of Chat -->
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-lg bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center">
                            <Sparkles class="w-4.5 h-4.5 text-violet-600 dark:text-violet-400" />
                        </div>
                        <div>
                            <h3 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Asistente IA</h3>
                            <span class="text-[9px] text-gray-400 font-medium">Asesor Comercial Inteligente</span>
                        </div>
                    </div>
                    
                    <button 
                        v-if="chatMessages.length > 0"
                        @click="clearConversation" 
                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-gray-200 dark:border-gray-855 hover:bg-gray-50 dark:hover:bg-gray-850 text-xs font-bold text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors cursor-pointer"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        Limpiar Chat
                    </button>
                </div>

                <!-- Chat History / Welcome Screen -->
                <div 
                    ref="chatContainer"
                    class="flex-1 overflow-y-auto p-6 space-y-6"
                >
                    <!-- Welcome Screen -->
                    <div v-if="chatMessages.length === 0" class="max-w-2xl mx-auto py-10 space-y-8">
                        <div class="text-center space-y-3">
                            <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-500 text-white items-center justify-center shadow-lg shadow-violet-500/25">
                                <Sparkles class="w-7 h-7" />
                            </div>
                            <h2 class="text-xl font-extrabold text-gray-900 dark:text-white tracking-tight text-center">Hola, {{ userName }}</h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400 max-w-md mx-auto leading-relaxed text-center">
                                Soy tu asesor de negocio inteligente. Puedo responder dudas sobre tus ventas, el inventario, clientes estrella, compras y darte consejos para mejorar tu bodega.
                            </p>
                        </div>

                        <!-- Analizar mi negocio main CTA -->
                        <div class="p-5 bg-gradient-to-r from-violet-500/5 to-indigo-500/5 dark:from-violet-500/10 dark:to-indigo-500/10 border border-violet-100/60 dark:border-violet-900/30 rounded-2xl flex flex-col md:flex-row items-center justify-between gap-4 shadow-3xs hover:border-violet-300 dark:hover:border-violet-800 transition-all duration-200">
                            <div class="space-y-1 text-center md:text-left">
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white flex items-center gap-1.5 justify-center md:justify-start">
                                    <Zap class="w-4 h-4 text-amber-500 fill-amber-500" />
                                    Auditoría de Negocio Instantánea
                                </h4>
                                <p class="text-[10px] text-gray-500 dark:text-gray-400">
                                    Analiza automáticamente tus inventarios, flujos de caja y ventas para compilar un reporte ejecutivo con fortalezas, problemas y recomendaciones.
                                </p>
                            </div>
                            <button 
                                @click="analyzeBusiness"
                                :disabled="isChatLoading"
                                class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-700 hover:to-indigo-700 text-white text-xs font-extrabold shadow-md shadow-violet-600/15 disabled:opacity-50 transition-all duration-150 cursor-pointer"
                            >
                                <Loader v-if="isChatLoading" class="w-4 h-4 animate-spin" />
                                <Sparkles v-else class="w-4 h-4" />
                                Analizar mi negocio
                            </button>
                        </div>

                        <!-- Quick suggestion grid -->
                        <div class="space-y-3">
                            <h4 class="text-[10px] font-extrabold uppercase tracking-wider text-gray-400">¿Qué te gustaría preguntar?</h4>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <button 
                                    v-for="sug in suggestions" 
                                    :key="sug"
                                    @click="sendMessage(sug)"
                                    class="p-3.5 text-left text-xs text-gray-700 hover:text-gray-950 dark:text-gray-300 dark:hover:text-white bg-gray-50 dark:bg-gray-850 hover:bg-gray-100 dark:hover:bg-gray-800 border border-gray-100 dark:border-gray-800 hover:border-gray-250 dark:hover:border-gray-700 rounded-xl transition-all duration-150 flex items-center justify-between group cursor-pointer"
                                >
                                    <span>{{ sug }}</span>
                                    <ChevronRight class="w-3.5 h-3.5 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-300 transition-colors" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Messages list -->
                    <div v-else class="space-y-6 max-w-4xl mx-auto">
                        <div 
                            v-for="(msg, idx) in chatMessages" 
                            :key="idx" 
                            class="flex gap-4"
                            :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
                        >
                            <!-- Avatar Assistant -->
                            <div v-if="msg.role === 'assistant'" class="w-8 h-8 rounded-lg bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center shrink-0">
                                <Sparkles class="w-4 h-4 text-violet-600 dark:text-violet-400" />
                            </div>

                            <!-- Bubble Content -->
                            <div 
                                class="max-w-[85%] rounded-2xl text-xs md:text-sm shadow-3xs"
                                :class="msg.role === 'user' 
                                    ? 'bg-blue-600 text-white rounded-br-xs px-5 py-3 font-medium leading-relaxed' 
                                    : 'bg-gray-50 dark:bg-gray-850 text-gray-855 dark:text-gray-200 border border-gray-100 dark:border-gray-800 rounded-bl-xs px-5 py-4 space-y-4'"
                            >
                                <div v-if="msg.role === 'user'" class="whitespace-pre-line leading-relaxed">
                                    {{ msg.content.text }}
                                </div>
                                <div v-else class="space-y-4">
                                    <!-- Render parsed markdown text -->
                                    <div 
                                        class="leading-relaxed markdown-content space-y-2.5" 
                                        v-html="parseMarkdown(msg.content.text)"
                                    ></div>

                                    <!-- Render dynamic visual cards -->
                                    <div v-if="msg.content.visuals && msg.content.visuals.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                                        <div 
                                            v-for="v in msg.content.visuals" 
                                            :key="v.id"
                                            class="p-3 bg-white dark:bg-gray-900 border border-gray-150 dark:border-gray-800 rounded-xl flex items-center justify-between gap-3 shadow-3xs"
                                        >
                                            <div class="min-w-0">
                                                <span class="text-[8px] font-extrabold uppercase tracking-wider text-violet-500 block">{{ v.type }}</span>
                                                <span class="font-bold text-gray-900 dark:text-white truncate block text-xs mt-0.5">{{ v.nombre }}</span>
                                                <span class="text-[10px] text-gray-500 dark:text-gray-400 block font-mono mt-0.5">
                                                    <template v-if="v.type === 'producto'">
                                                        Stock: {{ v.stock }} • Precio: {{ formatCurrency(v.precio) }}
                                                    </template>
                                                    <template v-else-if="v.type === 'cliente'">
                                                        Doc: {{ v.dni || v.documento_numero || '-' }}
                                                    </template>
                                                    <template v-else-if="v.type === 'proveedor'">
                                                        RUC: {{ v.ruc || '-' }}
                                                    </template>
                                                    <template v-else-if="v.type === 'venta' || v.type === 'compra'">
                                                        Total: {{ formatCurrency(v.total) }}
                                                    </template>
                                                </span>
                                            </div>
                                            <button 
                                                @click="executeSystemAction({ type: `abrir_${v.type}`, id: v.id, nombre: v.nombre })"
                                                class="flex items-center gap-1 px-2.5 py-1.5 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 text-[10px] font-bold text-gray-700 dark:text-gray-300 rounded-lg border border-gray-200 dark:border-gray-750 transition-colors shrink-0 cursor-pointer"
                                            >
                                                Ver
                                                <ChevronRight class="w-3 h-3" />
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Bottom Assistant controls -->
                                    <div class="flex items-center gap-2 border-t border-gray-100 dark:border-gray-800 pt-2.5 text-[10px] font-bold text-gray-400">
                                        <button 
                                            @click="copyToClipboard(msg.content.text, idx)" 
                                            class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-white transition-colors cursor-pointer"
                                        >
                                            <Check v-if="copiedIndex === idx" class="w-3.5 h-3.5 text-emerald-500" />
                                            <Copy v-else class="w-3.5 h-3.5" />
                                            {{ copiedIndex === idx ? 'Copiado' : 'Copiar' }}
                                        </button>
                                        <span class="text-gray-300 dark:text-gray-800">•</span>
                                        <button 
                                            @click="regenerateResponse(idx)" 
                                            class="flex items-center gap-1 hover:text-gray-700 dark:hover:text-white transition-colors cursor-pointer"
                                        >
                                            <RefreshCw class="w-3.5 h-3.5" />
                                            Regenerar
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Avatar User -->
                            <div v-if="msg.role === 'user'" class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center shrink-0">
                                <User class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                            </div>
                        </div>

                        <!-- Typing Loading animation -->
                        <div v-if="isChatLoading" class="flex gap-4 justify-start">
                            <div class="w-8 h-8 rounded-lg bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center shrink-0">
                                <Sparkles class="w-4 h-4 text-violet-600 dark:text-violet-400 animate-pulse" />
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-850 rounded-2xl rounded-bl-xs border border-gray-100 dark:border-gray-800 px-4.5 py-3.5 shadow-3xs flex items-center gap-1">
                                <span class="w-1.5 h-1.5 rounded-full bg-violet-600 dark:bg-violet-400 animate-bounce" style="animation-delay: 0ms"></span>
                                <span class="w-1.5 h-1.5 rounded-full bg-violet-600 dark:bg-violet-400 animate-bounce" style="animation-delay: 150ms"></span>
                                <span class="w-1.5 h-1.5 rounded-full bg-violet-600 dark:bg-violet-400 animate-bounce" style="animation-delay: 300ms"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat Input Form -->
                <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-900/50 shrink-0">
                    <form @submit.prevent="sendMessage()" class="max-w-4xl mx-auto flex items-center gap-3">
                        <input 
                            v-model="chatInput"
                            type="text" 
                            placeholder="Haz una pregunta sobre tus ventas, stock o clientes..." 
                            class="flex-1 bg-white dark:bg-gray-950 border border-gray-250 dark:border-gray-850 focus:border-violet-500 focus:ring-1 focus:ring-violet-500 rounded-xl px-4 py-2.5 text-xs text-gray-900 dark:text-white transition-all shadow-3xs outline-none"
                            :disabled="isChatLoading"
                        />
                        <button 
                            type="submit"
                            :disabled="!chatInput.trim() || isChatLoading"
                            class="p-2.5 rounded-xl bg-violet-600 hover:bg-violet-700 text-white shadow-md shadow-violet-600/15 disabled:opacity-50 transition-all duration-150 shrink-0 cursor-pointer"
                        >
                            <Send class="w-4 h-4" />
                        </button>
                    </form>
                </div>

                <!-- Visual Action Confirmation Dialog -->
                <div 
                    v-if="activeAction"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-600/30 backdrop-blur-xs"
                >
                    <div class="w-full max-w-sm bg-white dark:bg-gray-900 rounded-2xl border border-gray-250 dark:border-gray-800 p-6 shadow-xl space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-900/30 flex items-center justify-center text-violet-600 dark:text-violet-400">
                                <Zap class="w-5 h-5" />
                            </div>
                            <div>
                                <h4 class="text-xs font-bold text-gray-900 dark:text-white">Acción sugerida</h4>
                                <p class="text-[10px] text-gray-400 font-medium">Navegación del sistema</p>
                            </div>
                        </div>
                        
                        <p class="text-xs text-gray-600 dark:text-gray-300">
                            La IA sugiere ir a la siguiente sección:
                            <strong class="block text-gray-900 dark:text-white mt-1 capitalize">{{ activeAction.type.replace('_', ' ') }}</strong>
                        </p>
                        
                        <div class="flex gap-3 justify-end text-[10px] font-extrabold pt-2">
                            <button 
                                @click="activeAction = null"
                                class="px-4 py-2 rounded-xl border border-gray-255 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-850 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors cursor-pointer"
                            >
                                Cancelar
                            </button>
                            <button 
                                @click="executeSystemAction(activeAction)"
                                class="px-4 py-2 rounded-xl bg-violet-600 hover:bg-violet-700 text-white shadow-md shadow-violet-600/15 transition-all duration-150 cursor-pointer"
                            >
                                Confirmar y Navegar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Clean styles matching Clientes and POS design */
select {
    outline: none;
}
</style>
