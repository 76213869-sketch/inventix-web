<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, useForm, usePage, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import Dropdown from '@/Components/Dropdown.vue';
import InputLabel from '@/Components/InputLabel.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';

// Lucide icon imports - Outline only, standard sizing
import {
    Receipt,
    Search,
    AlertCircle,
    Calendar,
    CreditCard,
    User,
    Eye,
    SquarePen,
    FileText,
    MessageCircle,
    Ban,
    MoreHorizontal,
    X,
    Plus,
    Minus,
    Trash2,
    UserX,
    Copy
} from 'lucide-vue-next';

const props = defineProps({
    ventas: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const showDetailModal = ref(false);
const selectedVenta = ref(null);
const searchQuery = ref('');
const filterPago = ref('');
const isLoading = ref(true);

// Edit Sale Modal state
const showEditModal = ref(false);
const editVenta = ref(null);
const editLoading = ref(false);
const isSavingEdit = ref(false);

const catalogProducts = ref([]);
const catalogCategories = ref([]);
const catalogClientes = ref([]);
const editSearchQuery = ref('');
const editSelectedCategory = ref('');

const editCartItems = ref([]);
const editClienteId = ref('');
const editDescuento = ref(0);
const editMetodoPago = ref('efectivo');
const editFechaVenta = ref('');

// Annul Sale Modal state
const showAnularModal = ref(false);
const anularVentaItem = ref(null);

const anularForm = useForm({
    motivo: '',
});

onMounted(() => {
    setTimeout(() => {
        isLoading.value = false;
    }, 200);
});

const filteredVentas = computed(() => {
    let result = props.ventas;
    
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(v => 
            v.numero_venta.toLowerCase().includes(query) ||
            (v.cliente?.nombre && v.cliente.nombre.toLowerCase().includes(query))
        );
    }
    
    if (filterPago.value) {
        result = result.filter(v => v.metodo_pago === filterPago.value);
    }
    
    return result;
});

const openDetailModal = (venta) => {
    selectedVenta.value = venta;
    showDetailModal.value = true;
};

const closeDetailModal = () => {
    showDetailModal.value = false;
    selectedVenta.value = null;
};

const openAnularModal = (venta) => {
    anularVentaItem.value = venta;
    anularForm.motivo = '';
    showAnularModal.value = true;
};

const setPresetMotivo = (preset) => {
    if (preset === 'Otro') {
        anularForm.motivo = '';
        setTimeout(() => {
            document.getElementById('motivo_anulacion')?.focus();
        }, 100);
    } else {
        anularForm.motivo = preset;
    }
};

const submitAnularVenta = () => {
    if (!anularForm.motivo) {
        alert('Por favor ingrese el motivo de la anulación.');
        return;
    }
    anularForm.delete(route('ventas.destroy', anularVentaItem.value.id), {
        onSuccess: () => {
            showAnularModal.value = false;
            anularVentaItem.value = null;
            if (selectedVenta.value && selectedVenta.value.id === anularVentaItem.value?.id) {
                closeDetailModal();
            }
        }
    });
};

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(val);
};

const formatQuantity = (qty, unit) => {
    const isBulk = ['kilogramo', 'gramo', 'litro', 'mililitro'].includes(String(unit).toLowerCase());
    return isBulk ? Number(qty).toFixed(3) : Number(qty).toFixed(0);
};

const getPagoLabel = (metodo) => {
    switch (metodo) {
        case 'efectivo': return 'Efectivo';
        case 'tarjeta': return 'Tarjeta';
        case 'transferencia': return 'Transferencia';
        case 'yape_plin': return 'Yape / Plin';
        default: return metodo;
    }
};

// WhatsApp dispatch state for History
const showPhoneModal = ref(false);
const completedVenta = ref(null);
const clientName = ref('');
const clientPhone = ref('');
const saveClientInfo = ref(true);
const isSendingWhatsApp = ref(false);

const sanitizePhone = (phone) => {
    let cleaned = String(phone).replace(/\D/g, ''); // Keep only digits
    if (cleaned.length === 9) {
        cleaned = '51' + cleaned; // Peru country code
    }
    return cleaned;
};

const sendReceiptViaWhatsApp = async (name, phone, number) => {
    const companyName = page.props.auth.user?.empresa_nombre || 'nuestra bodega';
    const pdfFilename = number.replace('VEN-', 'VENTA-') + '.pdf';
    const pdfUrl = `${window.location.origin}/comprobantes/${pdfFilename}`;
    const formattedPhone = sanitizePhone(phone);

    // Try sharing as file first (Web Share API, primarily mobile)
    try {
        const response = await fetch(pdfUrl);
        const blob = await response.blob();
        const file = new File([blob], pdfFilename, { type: 'application/pdf' });

        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            await navigator.share({
                files: [file],
                title: `Comprobante ${number}`,
                text: `Comprobante Digital - Gracias por su compra en ${companyName}.`,
            });
            showPhoneModal.value = false;
            completedVenta.value = null;
            return;
        }
    } catch (err) {
        console.warn('Web Share API failed or not supported:', err);
    }

    // Fallback for Desktop (or browsers without File Share): Download PDF + WhatsApp Chat
    const link = document.createElement('a');
    link.href = pdfUrl;
    link.download = pdfFilename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    const message = `Comprobante Digital\n\n` +
                    `Hola, ${name || 'Cliente'}.\n\n` +
                    `Le envío el comprobante digital PDF adjunto de su compra en ${companyName}.\n\n` +
                    `¡Gracias por su preferencia!`;

    const url = `https://api.whatsapp.com/send?phone=${formattedPhone}&text=${encodeURIComponent(message)}`;
    window.open(url, '_blank');
    showPhoneModal.value = false;
    completedVenta.value = null;
};

const reenviarWhatsApp = (venta) => {
    completedVenta.value = venta;
    
    if (venta.cliente?.telefono) {
        sendReceiptViaWhatsApp(
            venta.cliente.nombre,
            venta.cliente.telefono,
            venta.numero_venta
        );
        return;
    }
    
    // Otherwise open modal to request phone number
    clientName.value = venta.cliente?.nombre === 'Público General' ? '' : (venta.cliente?.nombre || '');
    clientPhone.value = '';
    saveClientInfo.value = true;
    showPhoneModal.value = true;
};

const submitWhatsAppForm = async () => {
    if (!clientPhone.value) {
        alert('Por favor, ingrese un número de WhatsApp.');
        return;
    }
    
    isSendingWhatsApp.value = true;
    
    try {
        if (saveClientInfo.value) {
            await axios.post(route('clientes.quickStore'), {
                nombre: clientName.value,
                telefono: clientPhone.value,
                cliente_id: completedVenta.value.cliente_id,
            });
        }
        
        await sendReceiptViaWhatsApp(
            clientName.value || (completedVenta.value.cliente ? completedVenta.value.cliente.nombre : 'Público General'),
            clientPhone.value,
            completedVenta.value.numero_venta
        );
    } catch (error) {
        console.error(error);
        alert('Ocurrió un error al guardar los datos del cliente.');
    } finally {
        isSendingWhatsApp.value = false;
    }
};

// Edit Sale Modal Methods
const openEditModal = (venta) => {
    editVenta.value = venta;
    editCartItems.value = [];
    editSearchQuery.value = '';
    editSelectedCategory.value = '';
    editLoading.value = true;
    showEditModal.value = true;

    axios.get(route('ventas.edit-data', venta.id))
        .then(response => {
            catalogProducts.value = response.data.productos;
            catalogCategories.value = response.data.categorias;
            catalogClientes.value = response.data.clientes;

            editClienteId.value = venta.cliente_id || '';
            editDescuento.value = Number(venta.descuento) || 0;
            editMetodoPago.value = venta.metodo_pago;
            editFechaVenta.value = venta.fecha_venta ? new Date(venta.fecha_venta).toISOString().substring(0, 10) : new Date().toISOString().substring(0, 10);

            // Populate cart items
            editCartItems.value = venta.detalles.map(d => {
                const catalogProd = response.data.productos.find(p => p.id === d.producto_id);
                const originalQty = Number(d.cantidad);
                const stockActual = catalogProd ? Number(catalogProd.stock_actual) : 0;
                const stockDisponible = catalogProd && catalogProd.tipo_producto !== 'receta'
                    ? stockActual + originalQty
                    : stockActual;

                return {
                    producto_id: d.producto_id,
                    nombre: d.producto?.nombre || 'Producto',
                    imagen_url: catalogProd?.imagen_url || null,
                    unidad_medida: d.producto?.unidad_medida || 'unidad',
                    tipo_venta: d.producto?.tipo_venta || 'unidad',
                    tipo_producto: d.producto?.tipo_producto || 'estandar',
                    precio_unitario: Number(d.precio_unitario),
                    cantidad: originalQty,
                    descuento: Number(d.descuento),
                    stock_disponible: stockDisponible
                };
            });
        })
        .catch(err => {
            console.error(err);
            alert('Error al cargar datos del catálogo.');
            showEditModal.value = false;
        })
        .finally(() => {
            editLoading.value = false;
        });
};

const editAddToCart = (prod) => {
    const existing = editCartItems.value.find(item => item.producto_id === prod.id);
    if (existing) {
        editIncreaseQty(existing);
        return;
    }

    const defaultQty = prod.tipo_venta === 'peso' || prod.tipo_venta === 'volumen' ? 0.1 : 1;
    
    // Check if new product has stock
    if (prod.tipo_producto !== 'receta' && prod.stock_actual < defaultQty) {
        alert('Stock insuficiente de este producto.');
        return;
    }

    editCartItems.value.push({
        producto_id: prod.id,
        nombre: prod.nombre,
        imagen_url: prod.imagen_url,
        unidad_medida: prod.unidad_medida,
        tipo_venta: prod.tipo_venta,
        tipo_producto: prod.tipo_producto,
        precio_unitario: Number(prod.precio_venta),
        cantidad: defaultQty,
        descuento: 0,
        stock_disponible: Number(prod.stock_actual)
    });
};

const editRemoveFromCart = (index) => {
    editCartItems.value.splice(index, 1);
};

const editIncreaseQty = (item) => {
    const step = item.tipo_venta === 'peso' || item.tipo_venta === 'volumen' ? 0.1 : 1;
    const newQty = item.cantidad + step;
    
    if (item.tipo_producto !== 'receta' && newQty > item.stock_disponible) {
        alert(`Stock máximo alcanzado (${item.stock_disponible} disponibles).`);
        return;
    }
    item.cantidad = Number(newQty.toFixed(3));
};

const editDecreaseQty = (item) => {
    const step = item.tipo_venta === 'peso' || item.tipo_venta === 'volumen' ? 0.1 : 1;
    const newQty = item.cantidad - step;
    if (newQty <= 0) {
        return;
    }
    item.cantidad = Number(newQty.toFixed(3));
};

const editCartSubtotal = computed(() => {
    return editCartItems.value.reduce((sum, item) => sum + (item.cantidad * item.precio_unitario), 0);
});

const editCartTotal = computed(() => {
    const total = editCartSubtotal.value - editDescuento.value;
    return total > 0 ? total : 0;
});

const filteredCatalogProducts = computed(() => {
    let result = catalogProducts.value;

    if (editSelectedCategory.value) {
        result = result.filter(p => p.categoria_id === editSelectedCategory.value);
    }

    if (editSearchQuery.value) {
        const query = editSearchQuery.value.toLowerCase();
        result = result.filter(p => 
            p.nombre.toLowerCase().includes(query) || 
            (p.codigo_barras && p.codigo_barras.toLowerCase().includes(query))
        );
    }

    return result;
});

const submitEditVenta = () => {
    if (editCartItems.value.length === 0) {
        alert('El carrito no puede estar vacío.');
        return;
    }

    isSavingEdit.value = true;

    router.put(route('ventas.update', editVenta.value.id), {
        cliente_id: editClienteId.value || null,
        fecha_venta: editFechaVenta.value,
        descuento: editDescuento.value,
        metodo_pago: editMetodoPago.value,
        items: editCartItems.value.map(item => ({
            producto_id: item.producto_id,
            cantidad: item.cantidad,
            precio_unitario: item.precio_unitario,
            descuento: item.descuento
        }))
    }, {
        onSuccess: () => {
            showEditModal.value = false;
            editVenta.value = null;
            isSavingEdit.value = false;
        },
        onError: (errors) => {
            isSavingEdit.value = false;
            let msg = 'Ocurrió un error al guardar los cambios.';
            if (errors.items) {
                msg = Array.isArray(errors.items) ? errors.items[0] : errors.items;
            }
            alert(msg);
        }
    });
};
</script>

<template>
    <Head title="Historial de Ventas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 tracking-tight">Historial de Ventas</h2>
                    <p class="text-xs text-gray-500 mt-0.5 font-medium">Registro histórico y comprobantes de facturación emitidos.</p>
                </div>
                <Link :href="route('pos')" class="inline-flex h-10 items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 active:scale-98 transition-all">
                    Registrar Nueva Venta (POS)
                </Link>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Toast Notifications / Alertas Modernas -->
            <div v-if="flashSuccess" class="rounded-xl bg-emerald-50 p-4 text-xs text-emerald-800 border border-emerald-100 flex items-start gap-3 shadow-sm">
                <span class="p-1 bg-white rounded-lg border border-emerald-200 text-emerald-600 font-bold">✓</span>
                <div>
                    <div class="font-bold">Acción Realizada Exitosamente</div>
                    <div class="opacity-95 mt-0.5">{{ flashSuccess }}</div>
                </div>
            </div>

            <div v-if="flashError" class="rounded-xl bg-red-50 p-4 text-xs text-red-800 border border-red-100 flex items-start gap-3 shadow-sm">
                <AlertCircle class="w-4.5 h-4.5 text-red-600 mt-0.5 shrink-0" />
                <div>
                    <div class="font-bold">Error en la Operación</div>
                    <div class="opacity-95 mt-0.5">{{ flashError }}</div>
                </div>
            </div>

            <!-- SKELETON LOADING STATE -->
            <div v-if="isLoading" class="bg-white rounded-2xl border border-gray-200/80 p-6 space-y-6 shadow-sm animate-pulse">
                <div class="h-10 bg-gray-100 rounded-xl w-1/4"></div>
                <div class="space-y-3">
                    <div v-for="i in 5" :key="i" class="h-14 bg-gray-50 rounded-xl border border-gray-100/50"></div>
                </div>
            </div>

            <!-- EMPTY STATE (No items in database) -->
            <div v-else-if="ventas.length === 0" class="bg-white rounded-2xl border border-gray-200/80 p-12 shadow-sm flex flex-col items-center text-center max-w-lg mx-auto mt-8">
                <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-5">
                    <Receipt class="w-7 h-7" />
                </div>
                <h3 class="text-sm font-bold text-gray-900">No existen ventas registradas</h3>
                <p class="text-xs text-gray-500 mt-1.5 mb-6 max-w-xs">Comienza a realizar ventas desde la caja registradora (POS) para ver la facturación aquí.</p>
                <Link :href="route('pos')" class="inline-flex h-10 items-center justify-center rounded-xl bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 active:scale-98 transition-all">
                    Abrir Caja Registradora
                </Link>
            </div>

            <!-- MAIN LIST / TABLE -->
            <div v-else class="bg-white rounded-2xl border border-gray-200/80 overflow-hidden shadow-sm">
                <div class="p-6">
                    
                    <!-- Header Filters -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 w-full max-w-xl">
                            <!-- Search -->
                            <div class="relative w-full">
                                <span class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none text-gray-400">
                                    <Search class="w-4 h-4" />
                                </span>
                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    class="block w-full ps-10 p-2.5 h-10 text-xs text-gray-900 border border-gray-200 rounded-xl bg-gray-55/50 hover:bg-white focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-150"
                                    placeholder="Buscar por n° de venta o cliente..."
                                />
                            </div>

                            <!-- Pago Filter -->
                            <select
                                v-model="filterPago"
                                class="h-10 border border-gray-200 bg-white text-gray-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 rounded-xl text-xs px-3 transition-colors outline-none cursor-pointer"
                            >
                                <option value="">Todos los Métodos de Pago</option>
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">Transferencia</option>
                                <option value="yape_plin">Yape / Plin</option>
                            </select>
                        </div>
                    </div>

                    <!-- Table (SaaS Premium Style) -->
                    <div class="relative overflow-x-auto rounded-xl border border-gray-150 max-h-[60vh] overflow-y-auto shadow-2xs">
                        <table class="w-full text-xs text-left text-gray-500 border-collapse">
                            <thead class="text-[10px] text-gray-400 uppercase tracking-wider font-bold bg-gray-50/90 sticky top-0 backdrop-blur-md z-10 border-b border-gray-150">
                                <tr>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500 bg-gray-50/90">N° Venta</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500 bg-gray-50/90">Cliente</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500 bg-gray-50/90">Vendedor</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500 bg-gray-50/90">Fecha</th>
                                    <th scope="col" class="px-6 py-4 text-right font-bold text-gray-500 bg-gray-50/90">Total</th>
                                    <th scope="col" class="px-6 py-4 text-center font-bold text-gray-500 bg-gray-50/90">Método Pago</th>
                                    <th scope="col" class="px-6 py-4 text-center font-bold text-gray-500 bg-gray-50/90">Estado</th>
                                    <th scope="col" class="px-6 py-4 text-center font-bold text-gray-500 bg-gray-50/90">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                <tr 
                                    v-for="venta in filteredVentas" 
                                    :key="venta.id"
                                    class="hover:bg-gray-50/50 transition-colors duration-150"
                                >
                                    <th scope="row" class="px-6 py-4 font-mono font-medium text-gray-900 whitespace-nowrap">
                                        {{ venta.numero_venta }}
                                    </th>
                                    <td class="px-6 py-4 font-semibold text-gray-900">
                                        {{ venta.cliente?.nombre || 'Público General' }}
                                    </td>
                                    <td class="px-6 py-4 text-gray-500">
                                        {{ venta.usuario?.nombre || 'Sistema' }}
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 font-mono">
                                        {{ new Date(venta.fecha_venta).toLocaleDateString('es-PE') }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-bold text-gray-900 font-mono">
                                        {{ formatCurrency(venta.total) }}
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span class="inline-flex items-center gap-1 font-semibold text-gray-700 bg-gray-50 px-2.5 py-1 rounded-lg border border-gray-100 font-mono text-[10px]">
                                            {{ getPagoLabel(venta.metodo_pago) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <span 
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                                            :class="venta.estado === 'completada' 
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-100' 
                                                : 'bg-red-50 text-red-700 border-red-100'"
                                        >
                                            {{ venta.estado === 'completada' ? 'Completada' : 'Anulada' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-center">
                                        <Dropdown align="right" width="48">
                                            <template #trigger>
                                                <button class="inline-flex items-center justify-center p-2 text-gray-400 rounded-xl hover:bg-gray-100 hover:text-gray-600 focus:outline-none transition-all duration-150 cursor-pointer">
                                                    <MoreHorizontal class="w-4.5 h-4.5" />
                                                </button>
                                            </template>
                                            <template #content>
                                                <button
                                                    @click="openDetailModal(venta)"
                                                    class="flex items-center w-full px-4 py-2 text-left text-xs text-gray-700 hover:bg-gray-50 transition-colors duration-150 cursor-pointer"
                                                >
                                                    <Eye class="w-4.5 h-4.5 mr-2 text-gray-450" />
                                                    Ver detalle
                                                </button>
                                                <button
                                                    v-if="venta.estado === 'completada'"
                                                    @click="openEditModal(venta)"
                                                    class="flex items-center w-full px-4 py-2 text-left text-xs text-gray-700 hover:bg-gray-50 transition-colors duration-150 cursor-pointer"
                                                >
                                                    <SquarePen class="w-4.5 h-4.5 mr-2 text-gray-450" />
                                                    Modificar venta
                                                </button>
                                                <a
                                                    :href="'/comprobantes/' + venta.numero_venta.replace('VEN-', 'VENTA-') + '.pdf'"
                                                    target="_blank"
                                                    class="flex items-center w-full px-4 py-2 text-left text-xs text-gray-700 hover:bg-gray-50 transition-colors duration-150 cursor-pointer"
                                                >
                                                    <FileText class="w-4.5 h-4.5 mr-2 text-gray-450" />
                                                    Descargar PDF
                                                </a>
                                                <button
                                                    @click="reenviarWhatsApp(venta)"
                                                    class="flex items-center w-full px-4 py-2 text-left text-xs text-gray-700 hover:bg-gray-50 transition-colors duration-150 cursor-pointer"
                                                >
                                                    <MessageCircle class="w-4.5 h-4.5 mr-2 text-gray-455" />
                                                    Enviar por WhatsApp
                                                </button>
                                                <button
                                                    v-if="venta.estado === 'completada'"
                                                    @click="openAnularModal(venta)"
                                                    class="flex items-center w-full px-4 py-2 text-left text-xs text-red-600 hover:bg-red-50/50 transition-colors duration-150 cursor-pointer border-t border-gray-100"
                                                >
                                                    <Ban class="w-4.5 h-4.5 mr-2 text-red-400" />
                                                    Anular venta
                                                </button>
                                            </template>
                                        </Dropdown>
                                    </td>
                                </tr>
                                <tr v-if="filteredVentas.length === 0">
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                                        No se encontraron resultados para la búsqueda.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Ver Detalle / Ticket (Estilo SaaS Premium) -->
        <Modal :show="showDetailModal" @close="closeDetailModal" max-width="md">
            <div class="p-6 text-gray-900" v-if="selectedVenta">
                <!-- Header Ticket -->
                <div class="text-center mb-6">
                    <h3 class="text-sm font-bold text-gray-900 uppercase tracking-widest">
                        Ticket de Venta
                    </h3>
                    <span class="text-xs text-gray-400 font-mono block mt-1">
                        {{ selectedVenta.numero_venta }}
                    </span>
                    <span class="text-[10px] text-gray-400 block font-mono">
                        Emitido: {{ new Date(selectedVenta.fecha_venta).toLocaleString('es-PE') }}
                    </span>
                </div>

                <!-- Info Client/Seller -->
                <div class="space-y-1 text-xs border-y border-dashed border-gray-200 py-3.5 mb-5 font-mono text-gray-600">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Cliente:</span> 
                        <span class="font-bold text-gray-800">{{ selectedVenta.cliente?.nombre || 'Público General' }}</span>
                    </div>
                    <div v-if="selectedVenta.cliente?.documento_numero" class="flex justify-between">
                        <span class="text-gray-400">{{ selectedVenta.cliente?.documento_tipo }}:</span> 
                        <span class="font-bold text-gray-800">{{ selectedVenta.cliente?.documento_numero }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Atendido por:</span> 
                        <span class="font-bold text-gray-800">{{ selectedVenta.usuario?.nombre || 'Sistema' }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Medio de Pago:</span> 
                        <span class="font-bold text-gray-800">{{ getPagoLabel(selectedVenta.metodo_pago) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Estado:</span> 
                        <span class="font-bold uppercase" :class="selectedVenta.estado === 'completada' ? 'text-emerald-600' : 'text-red-600'">
                            {{ selectedVenta.estado === 'completada' ? 'Completada' : 'Anulada' }}
                        </span>
                    </div>
                </div>

                <!-- items -->
                <div class="space-y-3 mb-6 font-mono text-xs">
                    <div class="flex justify-between border-b border-gray-100 pb-2 font-bold uppercase text-gray-400 text-[10px]">
                        <span>Detalle</span>
                        <div class="flex space-x-6">
                            <span>Cant.</span>
                            <span>Total</span>
                        </div>
                    </div>
                    <div 
                        v-for="det in selectedVenta.detalles" 
                        :key="det.id"
                        class="flex justify-between text-gray-700"
                    >
                        <div class="flex flex-col">
                            <span class="font-semibold text-gray-900">{{ det.producto?.nombre }}</span>
                            <span class="text-[10px] text-gray-400">{{ formatCurrency(det.precio_unitario) }} c/u</span>
                        </div>
                        <div class="flex space-x-8 items-start">
                            <span class="font-bold text-gray-900">{{ formatQuantity(det.cantidad, det.producto?.unidad_medida) }} {{ det.producto?.unidad_medida !== 'unidad' ? det.producto?.unidad_medida : '' }}</span>
                            <span class="text-gray-900">{{ formatCurrency(det.subtotal) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Totales -->
                <div class="space-y-1 text-right text-xs font-mono border-t border-dashed border-gray-200 pt-3 text-gray-600">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Subtotal:</span>
                        <span>{{ formatCurrency(selectedVenta.subtotal) }}</span>
                    </div>
                    <div v-if="Number(selectedVenta.descuento) > 0" class="flex justify-between text-red-600 font-semibold">
                        <span>Descuento:</span>
                        <span>- {{ formatCurrency(selectedVenta.descuento) }}</span>
                    </div>
                    <div class="text-sm font-bold text-gray-900 mt-2 border-t border-gray-100 pt-2 flex justify-between">
                        <span class="text-gray-900 font-bold uppercase">TOTAL:</span>
                        <span class="text-blue-600 font-extrabold text-base">{{ formatCurrency(selectedVenta.total) }}</span>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-8 flex flex-col gap-2.5 pt-4 border-t border-gray-100">
                    <div class="grid grid-cols-2 gap-2" v-if="selectedVenta.estado === 'completada'">
                        <a
                            :href="'/comprobantes/' + selectedVenta.numero_venta.replace('VEN-', 'VENTA-') + '.pdf'"
                            target="_blank"
                            class="inline-flex justify-center items-center h-10 bg-gray-55 hover:bg-gray-100 border border-gray-200 rounded-xl font-bold text-xs text-gray-700 transition-all active:scale-98 cursor-pointer text-center"
                        >
                            <FileText class="w-4 h-4 mr-1.5 text-gray-400" />
                            Ver PDF
                        </a>
                        <button
                            type="button"
                            @click="reenviarWhatsApp(selectedVenta)"
                            class="inline-flex justify-center items-center h-10 bg-emerald-50/50 hover:bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl font-bold text-xs transition-all active:scale-98 cursor-pointer"
                        >
                            <MessageCircle class="w-4 h-4 mr-1.5 text-emerald-500" />
                            WhatsApp
                        </button>
                    </div>

                    <button
                        v-if="selectedVenta.estado === 'completada'"
                        @click="openAnularModal(selectedVenta)"
                        style="background-color: #fee2e2; color: #dc2626; border-color: #fecaca;"
                        class="w-full inline-flex justify-center items-center h-10 bg-red-50 hover:bg-red-100 text-red-650 border border-red-200 rounded-xl font-bold text-xs transition-all active:scale-98 cursor-pointer"
                    >
                        <Ban class="w-4 h-4 mr-1.5" />
                        Anular esta Venta
                    </button>
                    <div v-else class="text-center p-3 rounded-xl bg-red-50 text-red-700 font-bold text-xs border border-red-100">
                        Anulada
                    </div>
                    <SecondaryButton @click="closeDetailModal" class="w-full justify-center rounded-xl h-10 text-xs">
                        Cerrar Ticket
                    </SecondaryButton>
                </div>
            </div>
        </Modal>

        <!-- Modal Anular Venta (Moderno con Motivo) -->
        <Modal :show="showAnularModal" @close="showAnularModal = false" max-width="md">
            <div class="p-6 text-gray-900">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-4">
                    <h3 class="text-base font-bold text-gray-900">Anular venta</h3>
                    <button type="button" @click="showAnularModal = false" class="text-gray-400 hover:text-gray-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <div class="mb-4">
                    <div class="p-3 bg-amber-50 border border-amber-100 rounded-xl text-xs text-amber-800 flex gap-2">
                        <AlertCircle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold">Advertencia:</span> Esta acción es irreversible. Se revertirá el stock de los productos vendidos y se registrará un movimiento de devolución.
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submitAnularVenta" class="space-y-4">
                    <div>
                        <InputLabel for="motivo_anulacion" value="Motivo de la anulación" class="text-xs font-bold text-gray-700 mb-2.5 block" />
                        
                        <!-- BOTONOTES DE RESPUESTAS PREESTABLECIDAS -->
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <!-- Cliente desistió -->
                            <button
                                type="button"
                                @click="setPresetMotivo('Cliente desistió')"
                                class="flex flex-col items-center justify-center p-3 border rounded-xl transition-all duration-150 text-center gap-2 cursor-pointer group select-none h-20"
                                :class="anularForm.motivo === 'Cliente desistió'
                                    ? 'bg-blue-50 border-blue-500 text-blue-700 ring-2 ring-blue-500/20'
                                    : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50/80 hover:border-gray-300'"
                            >
                                <UserX class="w-5 h-5 shrink-0" :class="anularForm.motivo === 'Cliente desistió' ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500'" />
                                <span class="text-xs font-bold">Cliente desistió</span>
                            </button>

                            <!-- Error del cajero -->
                            <button
                                type="button"
                                @click="setPresetMotivo('Error del cajero')"
                                class="flex flex-col items-center justify-center p-3 border rounded-xl transition-all duration-150 text-center gap-2 cursor-pointer group select-none h-20"
                                :class="anularForm.motivo === 'Error del cajero'
                                    ? 'bg-blue-50 border-blue-500 text-blue-700 ring-2 ring-blue-500/20'
                                    : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50/80 hover:border-gray-300'"
                            >
                                <AlertCircle class="w-5 h-5 shrink-0" :class="anularForm.motivo === 'Error del cajero' ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500'" />
                                <span class="text-xs font-bold">Error del cajero</span>
                            </button>

                            <!-- Venta duplicada -->
                            <button
                                type="button"
                                @click="setPresetMotivo('Venta duplicada')"
                                class="flex flex-col items-center justify-center p-3 border rounded-xl transition-all duration-150 text-center gap-2 cursor-pointer group select-none h-20"
                                :class="anularForm.motivo === 'Venta duplicada'
                                    ? 'bg-blue-50 border-blue-500 text-blue-700 ring-2 ring-blue-500/20'
                                    : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50/80 hover:border-gray-300'"
                            >
                                <Copy class="w-5 h-5 shrink-0" :class="anularForm.motivo === 'Venta duplicada' ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500'" />
                                <span class="text-xs font-bold">Venta duplicada</span>
                            </button>

                            <!-- Otro -->
                            <button
                                type="button"
                                @click="setPresetMotivo('Otro')"
                                class="flex flex-col items-center justify-center p-3 border rounded-xl transition-all duration-150 text-center gap-2 cursor-pointer group select-none h-20"
                                :class="anularForm.motivo === 'Otro' || (!['Cliente desistió', 'Error del cajero', 'Venta duplicada'].includes(anularForm.motivo) && anularForm.motivo !== '')
                                    ? 'bg-blue-50 border-blue-500 text-blue-700 ring-2 ring-blue-500/20'
                                    : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50/80 hover:border-gray-300'"
                            >
                                <MoreHorizontal class="w-5 h-5 shrink-0" :class="anularForm.motivo === 'Otro' || (!['Cliente desistió', 'Error del cajero', 'Venta duplicada'].includes(anularForm.motivo) && anularForm.motivo !== '') ? 'text-blue-600' : 'text-gray-400 group-hover:text-gray-500'" />
                                <span class="text-xs font-bold">Otro</span>
                            </button>
                        </div>

                        <textarea
                            id="motivo_anulacion"
                            v-model="anularForm.motivo"
                            class="mt-1.5 block w-full border border-gray-200 rounded-xl shadow-2xs text-xs p-3 h-24 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 bg-gray-50/50 hover:bg-white focus:bg-white resize-none transition-all outline-none"
                            placeholder="Ingrese detalladamente por qué se anula esta venta..."
                            required
                        ></textarea>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button
                            type="button"
                            @click="showAnularModal = false"
                            class="flex-1 py-3 bg-white text-gray-700 border border-gray-200 rounded-xl font-bold text-xs hover:bg-gray-50 active:scale-95 transition-all"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="anularForm.processing"
                            style="background-color: #dc2626; color: #ffffff;"
                            class="flex-1 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer"
                        >
                            <span>Confirmar anulación</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Modal para Solicitar Teléfono si falta al reenviar por WhatsApp -->
        <Modal :show="showPhoneModal" @close="showPhoneModal = false" max-width="md">
            <div class="p-6 text-gray-900">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 mb-4">
                    <h3 class="text-base font-bold text-gray-900">Enviar por WhatsApp</h3>
                    <button type="button" @click="showPhoneModal = false" class="text-gray-400 hover:text-gray-600">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitWhatsAppForm" class="space-y-4">
                    <div>
                        <InputLabel for="hist_client_name" value="Nombre del Cliente (Opcional)" class="text-xs" />
                        <input
                            id="hist_client_name"
                            type="text"
                            v-model="clientName"
                            class="mt-1 block w-full border border-gray-200 rounded-xl shadow-2xs text-xs p-3 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 bg-gray-50/50 hover:bg-white focus:bg-white transition-all outline-none"
                            placeholder="Escribe el nombre del cliente..."
                        />
                    </div>

                    <div>
                        <InputLabel for="hist_client_phone" value="Número de WhatsApp (Requerido)" class="text-xs font-bold" />
                        <input
                            id="hist_client_phone"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            v-model="clientPhone"
                            class="mt-1.5 block w-full border border-gray-200 rounded-xl shadow-2xs text-xs p-3 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 bg-gray-50/50 hover:bg-white focus:bg-white transition-all outline-none font-mono"
                            placeholder="987654321"
                            required
                        />
                    </div>

                    <div class="flex items-center gap-2 pt-1" v-if="completedVenta && completedVenta.cliente_id">
                        <input
                            id="hist_save_client"
                            type="checkbox"
                            v-model="saveClientInfo"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500/20 cursor-pointer"
                        />
                        <label for="hist_save_client" class="text-xs text-gray-550 select-none cursor-pointer">
                            Guardar teléfono en la ficha del cliente
                        </label>
                    </div>

                    <div class="flex gap-3 pt-3">
                        <button
                            type="button"
                            @click="showPhoneModal = false"
                            class="flex-1 py-3 bg-white text-gray-700 border border-gray-200 rounded-xl font-bold text-xs hover:bg-gray-50 active:scale-95 transition-all"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="isSendingWhatsApp"
                            class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center gap-1.5 cursor-pointer"
                        >
                            <span v-if="isSendingWhatsApp">Guardando...</span>
                            <span v-else>Confirmar y Enviar</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Modal POS Modificar Venta (Estilo SaaS Premium 2026) -->
        <Modal :show="showEditModal" @close="showEditModal = false" max-width="6xl">
            <div class="flex flex-col h-[85vh] text-gray-900 bg-white">
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                    <div>
                        <h3 class="text-base font-bold text-gray-900">Modificar venta</h3>
                        <p class="text-xs text-gray-400 mt-0.5" v-if="editVenta">Editando comprobante {{ editVenta.numero_venta }}</p>
                    </div>
                    <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Loading State -->
                <div v-if="editLoading" class="flex-1 flex flex-col items-center justify-center space-y-3">
                    <div class="w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin"></div>
                    <p class="text-xs text-gray-400">Cargando catálogo y datos de venta...</p>
                </div>

                <!-- Main Columns -->
                <div v-else class="flex-1 flex overflow-hidden">
                    <!-- Left Column: Cart (Width 5/12) -->
                    <div class="w-5/12 border-r border-gray-100 flex flex-col bg-gray-50/50">
                        <!-- Cart Client & Date Controls -->
                        <div class="p-4 bg-white border-b border-gray-100 space-y-3 shrink-0">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <InputLabel for="edit_cliente" value="Cliente" class="text-[10px] font-bold uppercase text-gray-400" />
                                    <select id="edit_cliente" v-model="editClienteId" class="mt-1 block w-full border border-gray-200 rounded-xl text-xs h-10 px-3 bg-gray-50 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all outline-none cursor-pointer">
                                        <option value="">Público General</option>
                                        <option v-for="c in catalogClientes" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel for="edit_fecha" value="Fecha de Venta" class="text-[10px] font-bold uppercase text-gray-400" />
                                    <input type="date" id="edit_fecha" v-model="editFechaVenta" class="mt-1 block w-full border border-gray-200 rounded-xl text-xs h-10 px-3 bg-gray-50 focus:bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all outline-none font-mono" />
                                </div>
                            </div>
                        </div>

                        <!-- Cart Item List -->
                        <div class="flex-1 overflow-y-auto p-4 space-y-2.5">
                            <div v-if="editCartItems.length === 0" class="flex flex-col items-center justify-center h-full text-center p-6 text-gray-450">
                                <Receipt class="w-8 h-8 mb-2 opacity-35" />
                                <p class="text-xs font-semibold font-sans">El carrito está vacío</p>
                                <p class="text-[10px] opacity-75 mt-0.5">Agrega productos desde el catálogo de la derecha.</p>
                            </div>
                            <div v-else v-for="(item, idx) in editCartItems" :key="item.producto_id" class="flex items-center justify-between p-3 bg-white border border-gray-100 rounded-xl shadow-2xs hover:border-gray-200 transition-colors gap-3">
                                <!-- Cart Item Image Container -->
                                <div class="w-[50px] h-[50px] bg-white rounded-lg border border-gray-200 flex items-center justify-center overflow-hidden shrink-0 shadow-3xs">
                                    <img 
                                        v-if="item.imagen_url" 
                                        :src="item.imagen_url" 
                                        class="w-full h-full object-contain bg-white p-0.5" 
                                        :alt="item.nombre"
                                    />
                                    <div v-else class="text-gray-400 flex items-center justify-center w-full h-full font-bold text-xs uppercase select-none bg-gray-50">
                                        {{ item.nombre.substring(0, 2) }}
                                    </div>
                                </div>

                                <!-- Item details -->
                                <div class="flex-1 min-w-0 pr-1">
                                    <h4 class="text-xs font-bold text-gray-900 truncate">{{ item.nombre }}</h4>
                                    <div class="flex items-center gap-1.5 mt-1 text-[10px] text-gray-450 font-mono">
                                        <span>S/ {{ item.precio_unitario.toFixed(2) }} c/u</span>
                                        <span>•</span>
                                        <span class="text-emerald-600 font-semibold" v-if="item.tipo_producto !== 'receta'">Disp: {{ item.stock_disponible }} {{ item.unidad_medida }}</span>
                                    </div>
                                </div>
                                
                                <!-- Quantity Controls -->
                                <div class="flex items-center gap-2 shrink-0">
                                    <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden bg-gray-50 h-8">
                                        <button type="button" @click="editDecreaseQty(item)" class="px-2.5 h-full text-gray-500 hover:bg-gray-100 active:bg-gray-200 transition-colors cursor-pointer">
                                            <Minus class="w-3 h-3" />
                                        </button>
                                        <input
                                            type="number"
                                            v-model.number="item.cantidad"
                                            :step="item.tipo_venta === 'peso' || item.tipo_venta === 'volumen' ? '0.001' : '1'"
                                            min="0.001"
                                            class="w-14 text-center text-xs font-bold bg-transparent border-0 focus:ring-0 p-0 font-mono outline-none"
                                        />
                                        <button type="button" @click="editIncreaseQty(item)" class="px-2.5 h-full text-gray-500 hover:bg-gray-100 active:bg-gray-200 transition-colors cursor-pointer">
                                            <Plus class="w-3 h-3" />
                                        </button>
                                    </div>

                                    <div class="w-20 text-right font-bold text-xs text-gray-900 font-mono">
                                        S/ {{ ((item.cantidad * item.precio_unitario) - item.descuento).toFixed(2) }}
                                    </div>

                                    <button type="button" @click="editRemoveFromCart(idx)" class="p-1.5 text-gray-300 hover:text-red-500 transition-colors rounded-lg hover:bg-red-55 cursor-pointer">
                                        <Trash2 class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Cart Summary & Action -->
                        <div class="p-4 bg-white border-t border-gray-100 space-y-4 shrink-0">
                            <div class="space-y-1.5 text-xs text-gray-500">
                                <div class="flex justify-between">
                                    <span>Subtotal:</span>
                                    <span class="font-semibold text-gray-800 font-mono">S/ {{ editCartSubtotal.toFixed(2) }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Descuento general:</span>
                                    <div class="flex items-center gap-1">
                                        <span class="text-gray-400 font-mono">S/</span>
                                        <input type="number" v-model.number="editDescuento" min="0" :max="editCartSubtotal" class="w-20 text-right text-xs font-semibold border border-gray-200 rounded-lg p-1 h-7 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 outline-none font-mono bg-gray-50" />
                                    </div>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span>Método de Pago:</span>
                                    <select v-model="editMetodoPago" class="border border-gray-200 rounded-lg p-1 h-7 text-xs bg-white focus:ring-1 focus:ring-blue-500 outline-none cursor-pointer">
                                        <option value="efectivo">Efectivo</option>
                                        <option value="tarjeta">Tarjeta</option>
                                        <option value="transferencia">Transferencia</option>
                                        <option value="yape_plin">Yape / Plin</option>
                                    </select>
                                </div>
                                <div class="flex justify-between text-sm font-bold text-gray-900 pt-3 border-t border-gray-100">
                                    <span class="uppercase">Total modificado:</span>
                                    <span class="text-blue-600 font-extrabold font-mono text-base">S/ {{ editCartTotal.toFixed(2) }}</span>
                                </div>
                            </div>

                            <div class="flex gap-3">
                                <button type="button" @click="showEditModal = false" class="flex-1 h-11 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 rounded-xl font-semibold text-xs active:scale-98 transition-all cursor-pointer">
                                    Cancelar
                                </button>
                                <button type="button" @click="submitEditVenta" :disabled="isSavingEdit || editCartItems.length === 0" class="flex-1 h-11 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-semibold text-xs shadow-sm hover:shadow active:scale-98 transition-all disabled:opacity-50 flex items-center justify-center gap-2 cursor-pointer">
                                    <span v-if="isSavingEdit">Guardando...</span>
                                    <span v-else>Guardar Cambios</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Catalog (Width 7/12) -->
                    <div class="w-7/12 flex flex-col bg-white">
                        <!-- Search and Categories -->
                        <div class="p-4 border-b border-gray-100 space-y-3 shrink-0">
                            <!-- Search Input -->
                            <div class="relative w-full">
                                <span class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none text-gray-400">
                                    <Search class="w-4 h-4" />
                                </span>
                                <input
                                    v-model="editSearchQuery"
                                    type="search"
                                    class="block w-full ps-10 p-2.5 h-10 text-xs text-gray-900 border border-gray-200 rounded-xl bg-gray-50/50 hover:bg-white focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all outline-none"
                                    placeholder="Buscar producto por nombre o código..."
                                />
                            </div>

                            <!-- Categories Filter -->
                            <div class="flex gap-1.5 overflow-x-auto pb-1 max-w-full no-scrollbar">
                                <button
                                    type="button"
                                    @click="editSelectedCategory = ''"
                                    class="px-3.5 py-2 text-[10px] font-bold rounded-lg border transition-all shrink-0 cursor-pointer"
                                    :class="editSelectedCategory === ''
                                        ? 'bg-blue-600 text-white border-blue-600 shadow-2xs'
                                        : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                                >
                                    Todos
                                </button>
                                <button
                                    v-for="cat in catalogCategories"
                                    :key="cat.id"
                                    type="button"
                                    @click="editSelectedCategory = cat.id"
                                    class="px-3.5 py-2 text-[10px] font-bold rounded-lg border transition-all shrink-0 cursor-pointer"
                                    :class="editSelectedCategory === cat.id
                                        ? 'bg-blue-600 text-white border-blue-600 shadow-2xs'
                                        : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                                >
                                    {{ cat.nombre }}
                                </button>
                            </div>
                        </div>

                        <!-- Product Grid (Premium POS Card Style) -->
                        <div class="flex-1 overflow-y-auto p-4 animate-fade-in">
                            <div v-if="filteredCatalogProducts.length === 0" class="flex flex-col items-center justify-center h-full text-center p-6 text-gray-455">
                                <AlertCircle class="w-8 h-8 mb-2 opacity-30" />
                                <p class="text-xs font-semibold font-sans">No se encontraron productos</p>
                                <p class="text-[10px] opacity-75 mt-0.5 font-sans">Prueba con otro término de búsqueda o categoría.</p>
                            </div>
                            <div v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <button
                                    v-for="prod in filteredCatalogProducts"
                                    :key="prod.id"
                                    type="button"
                                    @click="editAddToCart(prod)"
                                    class="flex flex-col h-[145px] bg-white border border-gray-200 rounded-xl overflow-hidden cursor-pointer transition-all duration-150 active:scale-95 shadow-sm hover:shadow hover:border-blue-400 group relative text-left"
                                >
                                    <!-- Soft Green Bulk Badge -->
                                    <span v-if="prod.tipo_venta === 'peso' || prod.tipo_venta === 'volumen'" class="absolute top-1.5 right-1.5 px-1.5 py-0.5 bg-emerald-50 text-emerald-700 border border-emerald-100 rounded text-[9px] font-bold flex items-center gap-0.5 shadow-2xs select-none z-10">
                                        A granel
                                    </span>

                                    <!-- Area superior (60% de altura): Imagen o Placeholder -->
                                    <div class="relative h-[85px] w-full bg-white border-b border-gray-100 overflow-hidden flex items-center justify-center">
                                        <img 
                                            v-if="prod.imagen_url" 
                                            :src="prod.imagen_url" 
                                            :alt="prod.nombre" 
                                            class="w-full h-full object-contain bg-white group-hover:scale-105 transition-transform duration-300 p-1"
                                        />
                                        <div v-else class="flex flex-col items-center justify-center text-gray-300 gap-0.5 w-full h-full select-none bg-gray-50/50">
                                            <svg class="w-6 h-6 opacity-40 group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"></path>
                                            </svg>
                                            <span class="text-[8px] font-semibold tracking-wider uppercase opacity-30">Inventex</span>
                                        </div>
                                    </div>

                                    <!-- Area inferior (40% de altura): Información -->
                                    <div class="h-[60px] p-2 flex flex-col justify-between w-full">
                                        <div>
                                            <h4 class="text-[11px] font-bold text-gray-800 line-clamp-1 leading-tight mb-0.5" :title="prod.nombre">
                                                {{ prod.nombre }}
                                            </h4>
                                            <span class="text-[9px] text-gray-400 font-mono block">
                                                {{ prod.codigo_barras || '-' }}
                                            </span>
                                        </div>
                                        <div class="flex items-end justify-between mt-auto">
                                            <span class="text-xs font-extrabold text-blue-600 font-mono">
                                                S/ {{ Number(prod.precio_venta).toFixed(2) }}
                                                <span class="text-[9px] font-medium text-gray-405">/ {{ prod.unidad_medida }}</span>
                                            </span>
                                            <span
                                                class="text-[9px] font-bold px-1 py-0.5 rounded font-mono"
                                                :class="prod.tipo_producto === 'receta'
                                                    ? 'bg-indigo-50 text-indigo-700'
                                                    : Number(prod.stock_actual) > 0
                                                        ? 'bg-emerald-50 text-emerald-700'
                                                        : 'bg-red-50 text-red-700'"
                                            >
                                                {{ prod.tipo_producto === 'receta' ? 'Comp' : 'Stock: ' + Number(prod.stock_actual).toFixed(prod.tipo_venta === 'peso' ? 3 : 0) }}
                                            </span>
                                        </div>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
