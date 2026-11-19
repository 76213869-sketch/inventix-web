<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Head, useForm, usePage, Link } from '@inertiajs/vue3';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Receipt, ShoppingCart, ArrowRight, Scale, Banknote, X, User, History } from 'lucide-vue-next';

const props = defineProps({
    categorias: {
        type: Array,
        required: true,
    },
    clientes: {
        type: Array,
        required: true,
    },
    productos: {
        type: Array,
        required: true,
    },
    caja_abierta: {
        type: Object,
        default: null,
    },
});

const activeCaja = ref(props.caja_abierta);
const showAperturaModal = ref(false);
watch(() => props.caja_abierta, (newVal) => {
    activeCaja.value = newVal;
    if (newVal) {
        showAperturaModal.value = false;
    }
});

const aperturaForm = useForm({
    monto_inicial: 100,
    observacion: '',
});

const submitApertura = () => {
    aperturaForm.post(route('cajas.abrir'), {
        onSuccess: () => {
            aperturaForm.reset();
            showAperturaModal.value = false;
        }
    });
};

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const searchQuery = ref('');
const selectedCategoriaId = ref('');

// Register Form using Inertia useForm
const form = useForm({
    cliente_id: '',
    fecha_venta: new Date().toISOString().substr(0, 10),
    descuento: 0,
    metodo_pago: 'efectivo',
    items: [], // { producto_id: X, nombre: Y, cantidad: Z, precio_unitario: W, descuento: V, stock_disponible: U, unidad_medida: T, imagen_url: S }
    observaciones: '',
    notas_internas: '',
    cash_paid: 0,
    vuelto: 0,
});

// Cash change calculator helpers
const cashPaid = ref(0);
const cashChange = computed(() => {
    if (cashPaid.value <= 0) return 0;
    const diff = cashPaid.value - cartTotal.value;
    return diff > 0 ? diff : 0;
});

// Discount input visibility helper
const showDiscountInput = ref(false);
const clearDiscount = () => {
    form.descuento = 0;
    showDiscountInput.value = false;
};

// Digital receipts and success modal state
const showSuccessModal = ref(false);
const completedVenta = ref(null);

// Bulk product sales state
const showBulkModal = ref(false);
const bulkProduct = ref(null);
const bulkTab = ref('peso'); // 'peso' or 'monto'
const bulkQuantity = ref('');
const bulkAmount = ref('');
const bulkQtyInputRef = ref(null);
const bulkAmtInputRef = ref(null);

const onBulkQuantityInput = () => {
    if (bulkProduct.value) {
        const qty = parseFloat(bulkQuantity.value) || 0;
        bulkAmount.value = (qty * Number(bulkProduct.value.precio_venta)).toFixed(2);
    }
};

const onBulkAmountInput = () => {
    if (bulkProduct.value) {
        const amt = parseFloat(bulkAmount.value) || 0;
        bulkQuantity.value = (amt / Number(bulkProduct.value.precio_venta)).toFixed(3);
    }
};

const selectBulkQuantityPreset = (qty) => {
    bulkQuantity.value = qty;
    onBulkQuantityInput();
};

const selectBulkAmountPreset = (amt) => {
    bulkAmount.value = amt;
    onBulkAmountInput();
};

const handleOpenBulkModal = (producto, existingCartItem = null) => {
    bulkProduct.value = producto;
    bulkTab.value = 'peso';
    if (existingCartItem) {
        bulkQuantity.value = existingCartItem.cantidad;
        bulkAmount.value = (existingCartItem.cantidad * existingCartItem.precio_unitario).toFixed(2);
    } else {
        bulkQuantity.value = '';
        bulkAmount.value = '';
    }
    showBulkModal.value = true;
};

const handleProductSelection = (producto) => {
    if (!activeCaja.value) {
        showAperturaModal.value = true;
        return;
    }
    if (producto.tipo_venta === 'peso' || producto.tipo_venta === 'volumen') {
        const existing = form.items.find(item => String(item.producto_id) === String(producto.id));
        handleOpenBulkModal(producto, existing);
    } else {
        addToCart(producto);
    }
};

const submitBulkModal = () => {
    if (!bulkProduct.value) return;
    const qty = parseFloat(bulkQuantity.value) || 0;
    if (qty <= 0) {
        alert('Por favor, ingrese una cantidad o monto válido.');
        return;
    }
    
    if (bulkProduct.value.tipo_producto !== 'receta' && qty > Number(bulkProduct.value.stock_actual)) {
        alert(`Stock máximo alcanzado (${bulkProduct.value.stock_actual} disponibles).`);
        return;
    }

    const existing = form.items.find(item => String(item.producto_id) === String(bulkProduct.value.id));
    if (existing) {
        existing.cantidad = qty;
    } else {
        form.items.push({
            producto_id: bulkProduct.value.id,
            nombre: bulkProduct.value.nombre,
            unidad_medida: bulkProduct.value.unidad_medida,
            cantidad: qty,
            precio_unitario: Number(bulkProduct.value.precio_venta),
            descuento: 0,
            stock_disponible: Number(bulkProduct.value.stock_actual),
            tipo_producto: bulkProduct.value.tipo_producto,
            imagen_url: bulkProduct.value.imagen_url,
            tipo_venta: bulkProduct.value.tipo_venta,
        });
    }

    showBulkModal.value = false;
    bulkProduct.value = null;
    
    setTimeout(() => {
        searchInputRef.value?.focus();
    }, 50);
};

watch(showBulkModal, (newVal) => {
    if (newVal) {
        setTimeout(() => {
            if (bulkTab.value === 'peso') {
                bulkQtyInputRef.value?.focus();
                bulkQtyInputRef.value?.select();
            } else {
                bulkAmtInputRef.value?.focus();
                bulkAmtInputRef.value?.select();
            }
        }, 100);
    }
});

watch(bulkTab, (newVal) => {
    setTimeout(() => {
        if (newVal === 'peso') {
            bulkQtyInputRef.value?.focus();
            bulkQtyInputRef.value?.select();
        } else {
            bulkAmtInputRef.value?.focus();
            bulkAmtInputRef.value?.select();
        }
    }, 50);
});

// Floating toast state
const toastMessage = ref('');
const toastType = ref('success');
const showToast = ref(false);
let toastTimeout = null;

const triggerToast = (msg, type = 'success') => {
    if (toastTimeout) {
        clearTimeout(toastTimeout);
    }
    toastMessage.value = msg;
    toastType.value = type;
    showToast.value = true;
    
    toastTimeout = setTimeout(() => {
        showToast.value = false;
    }, 4000);
};

const closeToast = () => {
    showToast.value = false;
    if (page.props.flash) {
        page.props.flash.success = null;
        page.props.flash.error = null;
    }
};

// Watch for flash messages and errors
watch(() => page.props.flash?.success, (newVal) => {
    if (newVal && !showSuccessModal.value) {
        triggerToast(newVal, 'success');
    }
}, { immediate: true });

watch(() => page.props.flash?.error, (newVal) => {
    if (newVal) {
        triggerToast(newVal, 'error');
    }
}, { immediate: true });

watch(() => form.errors.items, (newVal) => {
    if (newVal) {
        triggerToast(newVal, 'error');
    }
});

// WhatsApp dispatch state
const showPhoneRequest = ref(false);
const clientName = ref('');
const clientPhone = ref('');
const saveClientInfo = ref(true);
const isSendingWhatsApp = ref(false);

// Watch for flash success and completed sale object
watch(() => page.props.flash?.created_venta, (newVal) => {
    if (newVal) {
        completedVenta.value = newVal;
        showSuccessModal.value = true;
        
        // Reset phone request form
        showPhoneRequest.value = false;
        clientName.value = newVal.cliente_nombre === 'Público General' ? '' : (newVal.cliente_nombre || '');
        clientPhone.value = newVal.cliente_telefono || '';
        saveClientInfo.value = true;
    }
}, { immediate: true });

// Clear flash messages when search query changes or when items are added to cart
watch(searchQuery, (newVal) => {
    if (newVal && page.props.flash) {
        page.props.flash.success = null;
        page.props.flash.error = null;
    }
});

watch(() => form.items.length, (newVal) => {
    if (newVal > 0 && page.props.flash) {
        page.props.flash.success = null;
        page.props.flash.error = null;
    }
});

// Close success modal and reset
const closeSuccessModal = () => {
    showSuccessModal.value = false;
    completedVenta.value = null;
    showPhoneRequest.value = false;
    
    // Trigger toast after modal is closed!
    if (page.props.flash?.success) {
        triggerToast(page.props.flash.success, 'success');
    }
    
    // Clear flash data in memory to prevent reopening
    if (page.props.flash) {
        page.props.flash.created_venta = null;
        page.props.flash.error = null;
    }
    
    // Refocus search bar
    setTimeout(() => {
        searchInputRef.value?.focus();
    }, 50);
};

// WhatsApp links helpers
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

    const message = `🧾 Comprobante Digital\n\n` +
                    `Hola, ${name || 'Cliente'}. 👋\n\n` +
                    `Le envío el comprobante digital PDF adjunto de su compra en ${companyName}.\n\n` +
                    `¡Gracias por su preferencia!`;

    const url = `https://api.whatsapp.com/send?phone=${formattedPhone}&text=${encodeURIComponent(message)}`;
    window.open(url, '_blank');
};

const handleWhatsAppClick = () => {
    if (!completedVenta.value) return;
    
    // If client already has phone, just open WhatsApp link
    if (completedVenta.value.cliente_telefono) {
        sendReceiptViaWhatsApp(
            completedVenta.value.cliente_nombre,
            completedVenta.value.cliente_telefono,
            completedVenta.value.numero_venta
        );
        return;
    }
    
    // Otherwise show phone request sub-step
    showPhoneRequest.value = true;
};

const submitWhatsAppForm = async () => {
    if (!clientPhone.value) {
        alert('Por favor, ingrese un número de WhatsApp.');
        return;
    }
    
    isSendingWhatsApp.value = true;
    
    try {
        if (saveClientInfo.value) {
            // Save/update client phone number via quick store endpoint
            const response = await axios.post(route('clientes.quickStore'), {
                nombre: clientName.value,
                telefono: clientPhone.value,
                cliente_id: completedVenta.value.cliente_id,
            });
            
            if (response.data?.success) {
                // If it was Público General, update completedVenta local name for the template
                if (!completedVenta.value.cliente_id && response.data.cliente) {
                    completedVenta.value.cliente_nombre = response.data.cliente.nombre;
                    completedVenta.value.cliente_telefono = response.data.cliente.telefono;
                }
            }
        }
        
        await sendReceiptViaWhatsApp(
            clientName.value || completedVenta.value.cliente_nombre,
            clientPhone.value,
            completedVenta.value.numero_venta
        );
        showPhoneRequest.value = false;
    } catch (error) {
        console.error(error);
        alert('Ocurrió un error al guardar los datos del cliente.');
    } finally {
        isSendingWhatsApp.value = false;
    }
};

// Filter products for the POS catalogue grid
const filteredProductos = computed(() => {
    let result = props.productos;

    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(
            (p) =>
                p.nombre.toLowerCase().includes(query) ||
                (p.codigo_barras && p.codigo_barras.toLowerCase().includes(query)) ||
                (p.categoria && p.categoria.nombre && p.categoria.nombre.toLowerCase().includes(query)) ||
                (p.unidad_medida && p.unidad_medida.toLowerCase().includes(query))
        );
    }

    if (selectedCategoriaId.value) {
        result = result.filter(
            (p) => String(p.categoria_id) === String(selectedCategoriaId.value)
        );
    }

    return result;
});

// Add a product to the cashier cart
const addToCart = (producto) => {
    if (Number(producto.stock_actual) <= 0 && producto.tipo_producto !== 'receta') {
        alert('Este producto no cuenta con stock disponible.');
        return;
    }

    const existing = form.items.find((item) => String(item.producto_id) === String(producto.id));

    if (existing) {
        // Validation: standard product cannot exceed current stock
        if (producto.tipo_producto !== 'receta' && existing.cantidad >= Number(producto.stock_actual)) {
            alert(`Stock máximo alcanzado (${producto.stock_actual} disponibles).`);
            return;
        }
        existing.cantidad += 1;
    } else {
        form.items.push({
            producto_id: producto.id,
            nombre: producto.nombre,
            unidad_medida: producto.unidad_medida,
            cantidad: 1,
            precio_unitario: Number(producto.precio_venta),
            descuento: 0,
            stock_disponible: Number(producto.stock_actual),
            tipo_producto: producto.tipo_producto,
            imagen_url: producto.imagen_url,
        });
    }
};

const incrementQty = (item) => {
    if (item.tipo_producto !== 'receta' && item.cantidad >= item.stock_disponible) {
        alert(`Stock máximo alcanzado (${item.stock_disponible} disponibles).`);
        return;
    }
    item.cantidad += 1;
};

const decrementQty = (item) => {
    if (item.cantidad <= 1) {
        removeFromCart(item);
    } else {
        item.cantidad -= 1;
    }
};

const removeFromCart = (item) => {
    const idx = form.items.indexOf(item);
    if (idx !== -1) {
        form.items.splice(idx, 1);
    }
};

// Calculations
const cartSubtotal = computed(() => {
    return form.items.reduce((sum, item) => sum + (item.cantidad * item.precio_unitario), 0);
});

const cartItemsDescuento = computed(() => {
    return form.items.reduce((sum, item) => sum + Number(item.descuento), 0);
});

const cartTotal = computed(() => {
    const tot = cartSubtotal.value - cartItemsDescuento.value - Number(form.descuento);
    return tot > 0 ? tot : 0;
});

const isCobrarDisabled = computed(() => {
    if (!activeCaja.value) return true;
    if (form.items.length === 0 || form.processing) return true;
    if (form.metodo_pago === 'efectivo') {
        return Number(cashPaid.value) < Number(cartTotal.value);
    }
    return false;
});

const searchInputRef = ref(null);

const handleSearchEnter = () => {
    if (!searchQuery.value) return;
    const query = searchQuery.value.trim().toLowerCase();
    
    // 1. Try to find exact barcode match first
    let matched = props.productos.find(p => p.codigo_barras && p.codigo_barras.toLowerCase() === query);
    
    // 2. If no exact barcode, check if there's exactly one filtered item in the catalogue
    if (!matched && filteredProductos.value.length === 1) {
        matched = filteredProductos.value[0];
    }
    
    if (matched) {
        handleProductSelection(matched);
        searchQuery.value = '';
    }
};

const submitVenta = () => {
    if (!activeCaja.value) {
        showAperturaModal.value = true;
        return;
    }
    if (isCobrarDisabled.value) return;
    form.cash_paid = cashPaid.value;
    form.vuelto = cashChange.value;
    form.post(route('ventas.store'), {
        onSuccess: () => {
            form.cliente_id = '';
            form.fecha_venta = new Date().toISOString().substr(0, 10);
            form.descuento = 0;
            form.metodo_pago = 'efectivo';
            form.items = [];
            form.observaciones = '';
            form.notas_internas = '';
            form.cash_paid = 0;
            form.vuelto = 0;
            cashPaid.value = 0;
            showDiscountInput.value = false;
            // Focus search input immediately
            setTimeout(() => {
                searchInputRef.value?.focus();
            }, 50);
        },
    });
};

const handleGlobalKeydown = (e) => {
    // Escape and Enter behavior inside bulk modal
    if (showBulkModal.value) {
        if (e.key === 'Escape') {
            e.preventDefault();
            showBulkModal.value = false;
            bulkProduct.value = null;
            setTimeout(() => {
                searchInputRef.value?.focus();
            }, 50);
            return;
        }
        if (e.key === 'Enter') {
            e.preventDefault();
            submitBulkModal();
            return;
        }
    }

    // If success modal is open (and we're not asking for phone), any key closes it
    if (showSuccessModal.value && !showPhoneRequest.value) {
        // Ignore modifier keys
        if (['Shift', 'Control', 'Alt', 'Meta'].includes(e.key)) {
            return;
        }
        
        closeSuccessModal();
        
        // If it's a single printable character, set search query to it to start typing
        if (e.key.length === 1) {
            searchQuery.value = e.key;
            e.preventDefault();
        }
        return;
    }

    // Ctrl + Enter or F12 to submit sale
    if ((e.ctrlKey && e.key === 'Enter') || e.key === 'F12') {
        e.preventDefault();
        if (!isCobrarDisabled.value) {
            submitVenta();
        }
    }
    // F2 or ESC to focus search
    if (e.key === 'F2' || e.key === 'Escape') {
        e.preventDefault();
        searchInputRef.value?.focus();
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
    // Focus search on mount if caja is open
    if (activeCaja.value) {
        setTimeout(() => {
            searchInputRef.value?.focus();
        }, 100);
    }

    // Check query params for client parameter
    const params = new URLSearchParams(window.location.search);
    const clientParam = params.get('cliente_id') || params.get('cliente');
    if (clientParam) {
        const exists = props.clientes.some(c => String(c.id) === String(clientParam));
        if (exists) {
            form.cliente_id = Number(clientParam);
        }
    }
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});

const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(val);
};

const getUnitShortName = (unit) => {
    if (!unit) return 'unidad';
    const u = unit.toLowerCase();
    if (u === 'kilogramo') return 'kg';
    if (u === 'litro') return 'L';
    return u;
};
</script>

<template>
    <Head title="Punto de Venta" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">
                        Punto de Venta (POS)
                    </h2>
                </div>
                <div>
                    <Link :href="route('ventas.index')">
                        <button class="inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500/20 transition-all cursor-pointer shadow-3xs">
                            <History class="w-4 h-4 text-gray-500 dark:text-gray-400" />
                            <span>Historial de Ventas</span>
                        </button>
                    </Link>
                </div>
            </div>
        </template>

        <!-- Banner Caja Cerrada -->
        <div v-if="!activeCaja" class="mb-4 p-4 bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-900/50 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-3 shadow-3xs animate-fade-in shrink-0">
            <div class="flex items-center gap-3">
                <span class="p-2 bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300 rounded-xl">
                    <Banknote class="w-5 h-5 animate-pulse" />
                </span>
                <div>
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white">La caja registradora se encuentra cerrada</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Debes abrir la caja para poder agregar productos y registrar ventas.</p>
                </div>
            </div>
            <button 
                @click="showAperturaModal = true"
                class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl shadow-xs transition-all active:scale-95 whitespace-nowrap cursor-pointer"
            >
                Abrir Caja Registradora
            </button>
        </div>

        <!-- Grid principal split 12 cols -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 flex-1 min-h-0 items-stretch">
                    
                    <!-- PANEL IZQUIERDO (7 cols): Catálogo de Productos -->
                    <div class="lg:col-span-7 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 flex flex-col h-full overflow-hidden">
                        
                        <!-- Filtros superior -->
                        <div class="flex flex-col sm:flex-row gap-3 shrink-0 mb-3">
                            <div class="relative flex-1">
                                <span class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none text-gray-400">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.637 10.637z"></path>
                                    </svg>
                                </span>
                                <input
                                    ref="searchInputRef"
                                    v-model="searchQuery"
                                    type="text"
                                    class="block w-full ps-10 pe-12 p-2 text-sm text-gray-900 border border-gray-300 rounded-lg bg-gray-50 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-indigo-500 dark:focus:border-indigo-500"
                                    placeholder="Escribe nombre o escanea código..."
                                    @keydown.enter="handleSearchEnter"
                                />
                                <div class="absolute inset-y-0 end-0 flex items-center pe-3 pointer-events-none">
                                    <kbd class="hidden sm:inline-flex items-center px-1.5 py-0.5 text-[9px] font-bold text-gray-400 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-md font-mono" title="Presiona F2 para enfocar">F2</kbd>
                                </div>
                            </div>

                            <select
                                v-model="selectedCategoriaId"
                                class="border border-gray-300 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-lg text-sm p-2 w-full sm:w-48"
                            >
                                <option value="">Todas Categorías</option>
                                <option v-for="cat in categorias" :key="cat.id" :value="cat.id">
                                    {{ cat.nombre }}
                                </option>
                            </select>
                        </div>

                        <!-- Grilla de productos scrollable -->
                        <div class="flex-1 overflow-y-auto pr-1 min-h-0">
                            <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                                <div 
                                    v-for="prod in filteredProductos" 
                                    :key="prod.id"
                                    @click="handleProductSelection(prod)"
                                    class="flex flex-col h-[172px] bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden cursor-pointer transition-all duration-150 active:scale-95 shadow-sm hover:shadow hover:border-blue-300 dark:hover:border-blue-800 group relative"
                                >
                                    <!-- Soft Green Bulk / Blue Unit Badge -->
                                    <span v-if="prod.tipo_venta === 'peso' || prod.tipo_venta === 'volumen'" class="absolute top-1.5 right-1.5 px-1.5 py-0.5 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-100 dark:border-emerald-900/30 rounded text-[9px] font-bold flex items-center shadow-2xs select-none z-10">
                                        A Granel
                                    </span>
                                    <span v-else-if="prod.tipo_venta === 'unidad'" class="absolute top-1.5 right-1.5 px-1.5 py-0.5 bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-400 border border-blue-100 dark:border-blue-900/30 rounded text-[9px] font-bold flex items-center shadow-2xs select-none z-10">
                                        Unidad
                                    </span>

                                    <!-- Área superior (Imagen o Placeholder) -->
                                    <div class="relative h-[82px] w-full bg-white dark:bg-gray-950 border-b border-gray-100 dark:border-gray-800 overflow-hidden flex items-center justify-center shrink-0">
                                        <img 
                                            v-if="prod.imagen_url" 
                                            :src="prod.imagen_url" 
                                            :alt="prod.nombre" 
                                            class="w-full h-full object-contain bg-white dark:bg-gray-950 group-hover:scale-105 transition-transform duration-300 p-1"
                                        />
                                        <div v-else class="flex flex-col items-center justify-center text-gray-300 dark:text-gray-650 gap-0.5 w-full h-full select-none">
                                            <svg class="w-6 h-6 opacity-40 group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"></path>
                                            </svg>
                                            <span class="text-[8px] font-semibold tracking-wider uppercase opacity-30">Inventex</span>
                                        </div>
                                    </div>

                                    <!-- Área inferior: Información con altura garantizada -->
                                    <div class="p-2 flex flex-col justify-between flex-1 bg-white dark:bg-gray-900 overflow-hidden">
                                        <!-- Nombre del producto (máx 2 líneas, ellipsis) -->
                                        <div class="min-h-[28px] flex items-start">
                                            <span class="text-[11px] font-semibold text-gray-800 dark:text-white line-clamp-2 leading-tight" :title="prod.nombre">
                                                {{ prod.nombre }}
                                            </span>
                                        </div>

                                        <!-- Grid de 2 columnas para la parte inferior -->
                                        <div class="grid grid-cols-2 gap-2 items-end mt-1.5 pt-0.5 border-t border-gray-50 dark:border-gray-800">
                                            <!-- Columna izquierda: Stock y Unidad de medida -->
                                            <div class="flex flex-col text-[10px] text-gray-500 dark:text-gray-400 leading-tight overflow-hidden">
                                                <span class="truncate">
                                                    Stock: 
                                                    <span 
                                                        :class="Number(prod.stock_actual) <= Number(prod.stock_minimo) && prod.tipo_producto !== 'receta' 
                                                            ? 'text-red-500 font-bold' 
                                                            : 'text-gray-700 dark:text-gray-300 font-semibold'"
                                                    >
                                                        {{ prod.tipo_producto === 'receta' ? '∞' : (prod.tipo_venta === 'peso' || prod.tipo_venta === 'volumen' ? Number(prod.stock_actual).toFixed(3) : Number(prod.stock_actual).toFixed(0)) }}
                                                    </span>
                                                </span>
                                                <span class="capitalize truncate text-gray-400 dark:text-gray-550 mt-0.5 text-[9px] font-medium">
                                                    {{ prod.unidad_medida }}
                                                </span>
                                            </div>

                                            <!-- Columna derecha: Precio grande y Unidad de cobro pequeña -->
                                            <div class="flex flex-col items-end leading-none text-right">
                                                <!-- Precio grande: Color azul del sistema, peso semibold, siempre alineado abajo a la derecha -->
                                                <span class="text-[11px] sm:text-xs font-semibold text-blue-600 dark:text-blue-400 font-mono tracking-tight">
                                                    {{ formatCurrency(prod.precio_venta) }}
                                                </span>
                                                <!-- Unidad de cobro pequeña: debajo del precio, en gris claro -->
                                                <span class="text-[9px] text-gray-400 dark:text-gray-500 mt-0.5 font-mono">
                                                    /{{ getUnitShortName(prod.unidad_medida) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div 
                                    v-if="filteredProductos.length === 0" 
                                    class="col-span-full py-16 text-center text-gray-400"
                                >
                                    Ningún producto disponible coincide con la búsqueda.
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- PANEL DERECHO (5 cols): Checkout & Carrito (Reconstruido desde Cero) -->
                    <div class="lg:col-span-5 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-3 flex flex-col h-full overflow-hidden gap-2">
                        
                        <!-- 1. CLIENTE -->
                        <div class="flex items-center justify-between gap-3 shrink-0 pb-2 border-b border-gray-150 dark:border-gray-700">
                            <div class="flex items-center gap-1.5">
                                <User class="w-3.5 h-3.5 text-gray-500 dark:text-gray-400" />
                                <label class="text-[10px] font-extrabold uppercase tracking-wider text-gray-500 dark:text-gray-400">Cliente</label>
                            </div>
                            <div class="flex-1 max-w-[200px]">
                                <select
                                    id="cliente"
                                    v-model="form.cliente_id"
                                    class="block w-full border-gray-200 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-xl text-xs py-1 px-2.5 font-semibold focus:outline-none transition-all duration-150 bg-gray-50/50"
                                >
                                    <option value="">Público General</option>
                                    <option v-for="c in clientes" :key="c.id" :value="c.id">
                                        {{ c.nombre }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- 2. PRODUCTOS DEL CARRITO (Con scroll únicamente en esta sección, maximizado de espacio) -->
                        <div class="flex-1 min-h-0 overflow-y-auto pt-1.5 pb-2.5 space-y-1.5 pr-1" style="scrollbar-width: thin;">
                            <div 
                                v-for="item in form.items" 
                                :key="item.producto_id"
                                class="bg-white dark:bg-gray-900 p-1.5 rounded-xl border border-gray-200 dark:border-gray-700 flex items-center justify-between gap-2 shadow-2xs hover:border-gray-300 dark:hover:border-gray-600 transition-all duration-150"
                            >
                                <!-- Imagen del producto (40x40) -->
                                <div class="w-[40px] h-[40px] bg-white dark:bg-gray-950 rounded-lg border border-gray-200 dark:border-gray-800 flex items-center justify-center overflow-hidden shrink-0">
                                    <img 
                                        v-if="item.imagen_url" 
                                        :src="item.imagen_url" 
                                        class="w-full h-full object-contain bg-white p-0.5" 
                                        :alt="item.nombre"
                                    />
                                    <div v-else class="text-gray-400 dark:text-gray-600 flex items-center justify-center w-full h-full font-bold text-xs uppercase select-none bg-gray-100 dark:bg-gray-800">
                                        {{ item.nombre.substring(0, 2) }}
                                    </div>
                                </div>

                                <!-- Información (Nombre y Precio unitario) -->
                                <div class="flex-1 min-w-0">
                                    <span class="font-bold text-gray-850 dark:text-gray-200 block truncate text-xs leading-snug" :title="item.nombre">
                                        {{ item.nombre }}
                                    </span>
                                    <span class="text-gray-400 dark:text-gray-500 font-mono text-[11px] block mt-0.5">
                                        {{ formatCurrency(item.precio_unitario) }} / {{ getUnitShortName(item.unidad_medida) }}
                                    </span>
                                </div>
                                
                                <!-- Botones grandes de cantidad (-) Cantidad (+) o badge de granel -->
                                <div class="flex items-center shrink-0">
                                    <div v-if="item.tipo_venta === 'peso' || item.tipo_venta === 'volumen'" 
                                         @click="handleOpenBulkModal(productos.find(p => p.id === item.producto_id), item)"
                                         class="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/20 dark:hover:bg-amber-950/30 text-amber-700 dark:text-amber-450 rounded-lg border border-amber-200 dark:border-amber-900/40 font-mono font-bold text-xs select-none cursor-pointer flex items-center gap-1 shadow-2xs transition-colors"
                                         title="Haga clic para editar peso / monto"
                                    >
                                        ⚖️ {{ Number(item.cantidad).toFixed(3) }} {{ item.unidad_medida }}
                                    </div>
                                    <div v-else class="flex items-center border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden bg-white dark:bg-gray-950 shadow-2xs">
                                        <button 
                                            type="button" 
                                            @click="decrementQty(item)"
                                            class="px-2 py-0.5 text-xs text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 active:scale-90 transition-all select-none cursor-pointer"
                                        >-</button>
                                        <span class="px-1.5 font-mono font-bold text-xs text-gray-800 dark:text-gray-250 select-none min-w-[14px] text-center">{{ item.cantidad }}</span>
                                        <button 
                                            type="button" 
                                            @click="incrementQty(item)"
                                            class="px-2 py-0.5 text-xs text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 active:scale-90 transition-all select-none cursor-pointer"
                                        >+</button>
                                    </div>
                                </div>

                                <!-- Total del producto alineado a la derecha -->
                                <div class="w-16 text-right shrink-0">
                                    <span class="font-extrabold text-gray-800 dark:text-white font-mono text-xs block">
                                        {{ formatCurrency(item.cantidad * item.precio_unitario) }}
                                    </span>
                                </div>

                                <!-- Botón eliminar -->
                                <button 
                                    type="button" 
                                    @click="removeFromCart(item)"
                                    class="text-gray-400 hover:text-red-600 dark:hover:text-red-400 p-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-950/20 active:scale-90 transition-all cursor-pointer shrink-0"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.247 2.077H8.084a2.25 2.25 0 0 1-2.247-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"></path>
                                    </svg>
                                </button>
                            </div>
                            
                            <div 
                                v-if="form.items.length === 0" 
                                class="h-full min-h-[160px] flex flex-col items-center justify-center text-gray-400 dark:text-gray-500 italic text-center py-10 gap-2 select-none"
                            >
                                <svg class="w-8 h-8 opacity-20 text-gray-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 10.5V6a3.75 3.75 0 1 0-7.5 0v4.5m11.356-1.993 1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 0 1-1.12-1.243l1.264-12A1.125 1.125 0 0 1 5.513 7.5h12.974c.576 0 1.059.435 1.119 1.007ZM8.625 10.5a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm7.5 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"></path>
                                </svg>
                                <span class="text-xs">El carrito está vacío.<br>Haz clic en los productos para agregarlos.</span>
                            </div>
                        </div>

                        <!-- 3. RESUMEN (Subtotal, Descuento, Total) -->
                        <div class="border-t border-gray-150 dark:border-gray-700 pt-2 space-y-1 shrink-0">
                            <!-- Subtotal -->
                            <div class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                                <span>Subtotal</span>
                                <span class="font-mono font-medium">S/ {{ Number(cartSubtotal).toFixed(2) }}</span>
                            </div>
                            
                            <!-- Descuento (Solo si existe o si se activa el input) -->
                            <div v-if="form.descuento > 0 || showDiscountInput" class="flex justify-between items-center text-xs text-gray-500 dark:text-gray-400">
                                <span class="flex items-center gap-1">
                                    <span>🏷️ Descuento</span>
                                    <button 
                                        type="button" 
                                        @click="clearDiscount" 
                                        class="text-red-500 hover:text-red-700 text-[10px] bg-red-50 dark:bg-red-950/20 px-1 rounded hover:scale-105 transition-all"
                                        title="Eliminar Descuento"
                                    >✕</button>
                                </span>
                                <div class="flex items-center gap-1 font-mono">
                                    <span>-S/</span>
                                    <input 
                                        type="number" 
                                        v-model.number="form.descuento" 
                                        class="w-14 border border-gray-200 dark:border-gray-700 dark:bg-gray-900 rounded-md font-mono text-[11px] text-right p-0.5 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                        min="0"
                                        step="0.10"
                                        placeholder="0.00"
                                    />
                                </div>
                            </div>
                            <div v-else class="flex justify-end">
                                <button 
                                    type="button" 
                                    @click="showDiscountInput = true" 
                                    class="text-[10px] text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 font-bold hover:underline transition-colors cursor-pointer flex items-center gap-1"
                                >
                                    ✨ + Agregar Descuento
                                </button>
                            </div>
                            
                            <!-- TOTAL Destacado -->
                            <div class="flex justify-between items-baseline pt-0.5">
                                <span class="text-xs font-bold text-gray-500 dark:text-gray-400">TOTAL</span>
                                <span class="text-xl font-extrabold font-mono text-gray-900 dark:text-white tracking-tight">S/ {{ Number(cartTotal).toFixed(2) }}</span>
                            </div>
                        </div>

                        <!-- 4. MÉTODO DE PAGO -->
                        <div class="shrink-0 pt-1.5 border-t border-gray-150 dark:border-gray-700">
                            <label class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500 block mb-1">Método de Pago</label>
                            <div class="flex justify-between gap-2">
                                <!-- Efectivo -->
                                <button
                                    type="button"
                                    @click="form.metodo_pago = 'efectivo'"
                                    class="flex flex-col items-center justify-center w-[120px] h-[48px] rounded-xl border transition-all duration-150 text-center cursor-pointer active:scale-95 gap-0.5 focus:outline-none select-none shadow-2xs hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-900"
                                    :class="form.metodo_pago === 'efectivo' 
                                        ? 'border-blue-500 bg-blue-50/20 text-blue-900 dark:bg-blue-950/10 dark:text-blue-300 font-bold shadow-sm ring-1 ring-blue-500' 
                                        : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400'"
                                >
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-600 dark:text-emerald-400">
                                        <rect x="2" y="6" width="20" height="12" rx="2" />
                                        <circle cx="12" cy="12" r="2" />
                                        <path d="M6 12h.01M18 12h.01" />
                                    </svg>
                                    <span class="text-[9px] font-extrabold uppercase tracking-wider">Efectivo</span>
                                </button>

                                <!-- Yape / Plin -->
                                <button
                                    type="button"
                                    @click="form.metodo_pago = 'yape_plin'"
                                    class="flex flex-col items-center justify-center w-[120px] h-[48px] rounded-xl border transition-all duration-150 text-center cursor-pointer active:scale-95 gap-0.5 focus:outline-none select-none shadow-2xs hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-900"
                                    :class="form.metodo_pago === 'yape_plin' 
                                        ? 'border-blue-500 bg-blue-50/20 text-blue-900 dark:bg-blue-950/10 dark:text-blue-300 font-bold shadow-sm ring-1 ring-blue-500' 
                                        : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400'"
                                >
                                    <div class="flex items-center justify-center gap-1.5 mb-1 select-none">
                                        <!-- Yape Official Brand Badge -->
                                        <div class="relative w-[18px] h-[18px] rounded-[5px] bg-[#742E8A] flex items-center justify-center font-black text-[11px] font-sans text-white leading-none shadow-3xs">
                                            <span class="relative -top-[1.5px] -left-[0.5px]">y</span>
                                            <span class="absolute top-[2.5px] right-[2.5px] w-[3px] h-[3px] rounded-full bg-[#00CCD6]"></span>
                                        </div>
                                        <!-- Plin Official Brand Badge -->
                                        <div class="w-[18px] h-[18px] rounded-full bg-[#00CCD6] flex items-center justify-center font-black text-[11px] font-sans text-white leading-none shadow-3xs">
                                            <span class="relative -top-[1.5px]">p</span>
                                        </div>
                                    </div>
                                    <span class="text-[9px] font-extrabold uppercase tracking-wider">Yape / Plin</span>
                                </button>

                                <!-- Tarjeta -->
                                <button
                                    type="button"
                                    @click="form.metodo_pago = 'tarjeta'"
                                    class="flex flex-col items-center justify-center w-[120px] h-[48px] rounded-xl border transition-all duration-150 text-center cursor-pointer active:scale-95 gap-0.5 focus:outline-none select-none shadow-2xs hover:border-gray-300 dark:hover:border-gray-600 bg-white dark:bg-gray-900"
                                    :class="form.metodo_pago === 'tarjeta' 
                                        ? 'border-blue-500 bg-blue-50/20 text-blue-900 dark:bg-blue-950/10 dark:text-blue-300 font-bold shadow-sm ring-1 ring-blue-500' 
                                        : 'border-gray-200 dark:border-gray-700 text-gray-500 dark:text-gray-400'"
                                >
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-blue-600 dark:text-blue-400">
                                        <rect x="2" y="5" width="20" height="14" rx="2" />
                                        <line x1="2" y1="10" x2="22" y2="10" />
                                    </svg>
                                    <span class="text-[9px] font-extrabold uppercase tracking-wider">Tarjeta</span>
                                </button>
                            </div>
                        </div>

                        <!-- 5. PAGO RECIBIDO Y VUELTO (Solo Efectivo) -->
                        <div v-if="form.metodo_pago === 'efectivo'" class="shrink-0 space-y-1.5">
                            <div class="grid grid-cols-2 gap-3">
                                <!-- Pago Recibido -->
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 block">Pago Recibido</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 start-0 flex items-center ps-3 text-gray-450 font-mono font-bold text-xs select-none">S/</span>
                                        <input 
                                            type="number" 
                                            v-model.number="cashPaid" 
                                            class="block w-full border border-gray-200 dark:border-gray-750 dark:bg-gray-900 dark:text-white rounded-xl shadow-2xs font-mono font-bold text-xs py-1 px-2.5 ps-7 focus:outline-none transition-colors duration-155"
                                            :class="Number(cashPaid) < Number(cartTotal) 
                                                ? 'border-red-300 bg-red-50/20 text-red-950 dark:text-red-300 dark:bg-red-950/10 focus:ring-1 focus:ring-red-300' 
                                                : 'focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500'"
                                            step="0.10"
                                            placeholder="0.00"
                                        />
                                    </div>
                                </div>

                                <!-- Vuelto -->
                                <div class="space-y-1">
                                    <label class="text-[10px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 block">
                                        {{ Number(cashPaid) < Number(cartTotal) ? 'Falta Pagar' : 'Vuelto' }}
                                    </label>
                                    <div 
                                        class="rounded-xl border py-1 px-3 flex items-center justify-between shadow-2xs transition-colors duration-150 h-[30px]"
                                        :class="Number(cashPaid) < Number(cartTotal)
                                            ? 'bg-red-50/50 border-red-200 dark:bg-red-950/10 dark:border-red-900/40 text-red-700 dark:text-red-400'
                                            : 'bg-green-50/50 border-green-200 dark:bg-green-950/10 dark:border-green-900/40 text-green-700 dark:text-green-400'"
                                    >
                                        <span class="font-extrabold font-mono text-xs leading-none">
                                            S/ {{ Number(Number(cashPaid) < Number(cartTotal) ? cartTotal - cashPaid : cashChange).toFixed(2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Botones rápidos en una sola fila (6 columnas) -->
                            <div class="grid grid-cols-6 gap-1">
                                <button 
                                    v-for="amount in [5, 10, 20, 50, 100]" 
                                    :key="amount"
                                    type="button" 
                                    @click="cashPaid = amount"
                                    class="py-1 text-[10px] font-bold border rounded-lg active:scale-95 transition-all font-mono cursor-pointer select-none text-center shadow-2xs"
                                    :class="Number(cashPaid) === amount 
                                        ? 'border-blue-500 bg-blue-50/50 text-blue-900 dark:bg-blue-950/20 dark:text-blue-200 font-bold shadow-2xs' 
                                        : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700 text-gray-655 dark:text-gray-350 hover:bg-gray-50'"
                                >
                                    S/{{ amount }}
                                </button>
                                <button 
                                    type="button" 
                                    @click="cashPaid = cartTotal"
                                    class="py-1 text-[10px] font-bold border rounded-lg active:scale-95 transition-all cursor-pointer select-none text-center shadow-2xs"
                                    :class="Number(cashPaid) === Number(cartTotal) 
                                        ? 'border-blue-500 bg-blue-50/50 text-blue-900 dark:bg-blue-950/20 dark:text-blue-200 font-bold shadow-2xs' 
                                        : 'bg-indigo-50 dark:bg-indigo-950/40 border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100'"
                                >
                                    Exacto
                                </button>
                            </div>
                        </div>

                        <!-- 7. BOTÓN COBRAR (Fijo) -->
                        <div class="shrink-0">
                            <button
                                type="button"
                                @click="submitVenta"
                                :disabled="isCobrarDisabled"
                                class="w-full py-2.5 px-4 bg-[#4B56E6] hover:bg-[#3C47C8] disabled:opacity-30 disabled:hover:bg-[#4B56E6] text-white rounded-xl font-bold transition-all duration-150 active:scale-[0.98] flex items-center justify-between cursor-pointer shadow-sm select-none"
                            >
                                <span class="flex items-center gap-2 text-xs uppercase tracking-wider font-extrabold">
                                    <ShoppingCart class="w-4 h-4" />
                                    <span>COBRAR</span>
                                </span>
                                <span class="text-base font-black font-mono">S/ {{ Number(cartTotal).toFixed(2) }}</span>
                            </button>
                        </div>

                    </div>

                </div>
        <!-- Modal de Éxito de Venta y Envío por WhatsApp -->
        <Modal :show="showSuccessModal" @close="closeSuccessModal" max-width="md">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <!-- Step 1: Venta Registrada & WhatsApp/Finalizar Buttons -->
                <div v-if="!showPhoneRequest" class="text-center space-y-6 py-4">
                    <div class="w-16 h-16 bg-emerald-50 dark:bg-emerald-950/30 text-emerald-500 rounded-full flex items-center justify-center mx-auto border border-emerald-100 dark:border-emerald-800">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"></path>
                        </svg>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-extrabold text-gray-900 dark:text-white">
                            ✅ Venta registrada correctamente.
                        </h3>
                        <p v-if="completedVenta" class="text-xs text-gray-400 font-mono mt-1">
                            Comprobante: {{ completedVenta.numero_venta }} • Total: S/ {{ Number(completedVenta.total).toFixed(2) }}
                        </p>
                    </div>

                    <div class="flex flex-col gap-3 pt-2">
                        <!-- Enviar por WhatsApp -->
                        <button
                            type="button"
                            @click="handleWhatsAppClick"
                            class="w-full py-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold shadow-md hover:shadow-lg transition-all duration-150 active:scale-[0.98] flex items-center justify-center gap-2 cursor-pointer select-none text-sm"
                        >
                            <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.513 2.262 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.713-1.458L0 24zm6.59-4.846c1.6.95 3.16 1.455 4.77 1.456 5.485 0 9.948-4.614 9.951-10.282.002-2.747-1.061-5.328-2.996-7.27C16.38 1.107 13.8 0 11.97 0c-5.49 0-9.955 4.606-9.958 10.279-.002 1.944.505 3.843 1.47 5.514L2.43 20.89l5.217-1.365zM18.268 14.63c-.34-.17-2.015-.993-2.327-1.107-.313-.115-.54-.17-.768.17-.227.34-.879 1.107-1.077 1.332-.197.225-.395.253-.735.084-.34-.17-1.436-.53-2.735-1.687-1.01-.902-1.69-2.016-1.888-2.356-.198-.34-.02-.523.15-.693.152-.152.34-.397.51-.595.17-.198.227-.34.34-.567.113-.227.056-.425-.028-.595-.085-.17-.768-1.85-.1052-2.535-.276-.665-.554-.576-.768-.588-.198-.01-.424-.013-.652-.013-.227 0-.595.085-.907.425-.312.34-1.192 1.166-1.192 2.84 0 1.674 1.218 3.29 1.388 3.518.17.227 2.398 3.662 5.807 5.132.812.35 1.446.56 1.94.717.816.26 1.56.223 2.148.135.656-.098 2.015-.823 2.298-1.587.283-.765.283-1.417.198-1.587-.085-.17-.312-.27-.653-.44z"/>
                            </svg>
                            <span>Enviar comprobante por WhatsApp</span>
                        </button>

                        <!-- Finalizar -->
                        <button
                            type="button"
                            @click="closeSuccessModal"
                            class="w-full py-3.5 bg-gray-100 hover:bg-gray-250 dark:bg-gray-700 dark:hover:bg-gray-655 text-gray-700 dark:text-gray-300 rounded-xl font-bold transition-all active:scale-[0.98] cursor-pointer select-none text-sm border border-gray-200 dark:border-gray-600"
                        >
                            Finalizar
                        </button>
                    </div>
                </div>

                <!-- Step 2: Request Phone Form (If missing) -->
                <div v-else class="space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800">
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Enviar por WhatsApp</h3>
                        <button 
                            type="button"
                            @click="showPhoneRequest = false" 
                            class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <form @submit.prevent="submitWhatsAppForm" class="space-y-4">
                        <div>
                            <InputLabel for="modal_client_name" value="Nombre del Cliente (Opcional)" class="text-xs" />
                            <input
                                id="modal_client_name"
                                type="text"
                                v-model="clientName"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white rounded-xl shadow-sm text-sm p-3 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Escribe el nombre del cliente..."
                            />
                        </div>

                        <div>
                            <InputLabel for="modal_client_phone" value="Número de WhatsApp (Requerido)" class="text-xs font-bold" />
                            <input
                                id="modal_client_phone"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                v-model="clientPhone"
                                class="mt-1 block w-full border border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-white rounded-xl shadow-sm text-sm p-3 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 font-mono"
                                placeholder="987654321"
                                required
                            />
                        </div>

                        <div class="flex items-center gap-2 pt-1">
                            <input
                                id="modal_save_client"
                                type="checkbox"
                                v-model="saveClientInfo"
                                class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 focus:ring-indigo-500"
                            />
                            <label for="modal_save_client" class="text-xs text-gray-500 dark:text-gray-400 select-none cursor-pointer">
                                Guardar cliente para futuras compras
                            </label>
                        </div>

                        <div class="flex gap-3 pt-3">
                            <button
                                type="button"
                                @click="showPhoneRequest = false"
                                class="flex-1 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700 rounded-xl font-bold text-xs hover:bg-gray-50 dark:hover:bg-gray-700 active:scale-95 transition-all"
                            >
                                Volver
                            </button>
                            <button
                                type="submit"
                                :disabled="isSendingWhatsApp"
                                class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center gap-1.5"
                            >
                                <svg v-if="!isSendingWhatsApp" class="w-4 h-4 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946C.06 5.348 5.397.01 12.008.01c3.202.001 6.212 1.246 8.477 3.513 2.262 2.268 3.507 5.28 3.505 8.484-.004 6.657-5.34 11.997-11.953 11.997-2.005-.001-3.973-.502-5.713-1.458L0 24zm6.59-4.846c1.6.95 3.16 1.455 4.77 1.456 5.485 0 9.948-4.614 9.951-10.282.002-2.747-1.061-5.328-2.996-7.27C16.38 1.107 13.8 0 11.97 0c-5.49 0-9.955 4.606-9.958 10.279-.002 1.944.505 3.843 1.47 5.514L2.43 20.89l5.217-1.365zM18.268 14.63c-.34-.17-2.015-.993-2.327-1.107-.313-.115-.54-.17-.768.17-.227.34-.879 1.107-1.077 1.332-.197.225-.395.253-.735.084-.34-.17-1.436-.53-2.735-1.687-1.01-.902-1.69-2.016-1.888-2.356-.198-.34-.02-.523.15-.693.152-.152.34-.397.51-.595.17-.198.227-.34.34-.567.113-.227.056-.425-.028-.595-.085-.17-.768-1.85-.1052-2.535-.276-.665-.554-.576-.768-.588-.198-.01-.424-.013-.652-.013-.227 0-.595.085-.907.425-.312.34-1.192 1.166-1.192 2.84 0 1.674 1.218 3.29 1.388 3.518.17.227 2.398 3.662 5.807 5.132.812.35 1.446.56 1.94.717.816.26 1.56.223 2.148.135.656-.098 2.015-.823 2.298-1.587.283-.765.283-1.417.198-1.587-.085-.17-.312-.27-.653-.44z"/>
                                </svg>
                                <span v-if="isSendingWhatsApp">Guardando...</span>
                                <span v-else>Confirmar y Enviar</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Modal>

        <!-- Modal de Venta a Granel (Peso/Volumen y Monto) -->
        <Modal :show="showBulkModal" @close="showBulkModal = false" max-width="md">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-center justify-between pb-3.5 border-b border-gray-150 dark:border-gray-800 mb-4">
                    <h3 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider">
                        Venta a Granel
                    </h3>
                    <button 
                        type="button"
                        @click="showBulkModal = false" 
                        class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-350 cursor-pointer transition-colors"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div v-if="bulkProduct" class="space-y-5">
                    <!-- Información del Producto Rediseñada -->
                    <div class="flex flex-col gap-3">
                        <!-- Nombre e Imagen -->
                        <div class="flex items-center gap-3.5">
                            <div class="w-12 h-12 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shrink-0 flex items-center justify-center shadow-2xs">
                                <img 
                                    v-if="bulkProduct.imagen_url" 
                                    :src="bulkProduct.imagen_url" 
                                    class="w-full h-full object-contain p-1" 
                                    :alt="bulkProduct.nombre"
                                />
                                <span v-else class="text-sm font-black text-gray-400 dark:text-gray-500 uppercase select-none">
                                    {{ bulkProduct.nombre.substring(0, 2) }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-extrabold text-base text-gray-900 dark:text-white truncate leading-tight">
                                    {{ bulkProduct.nombre }}
                                </h4>
                                <span class="text-[10px] text-gray-400 dark:text-gray-500 font-medium uppercase tracking-wider">Venta a granel</span>
                            </div>
                        </div>

                        <!-- Dos Tarjetas: Precio y Stock -->
                        <div class="grid grid-cols-2 gap-2.5">
                            <div class="bg-gray-50 dark:bg-gray-900/40 py-2.5 px-3 rounded-xl border border-gray-200/60 dark:border-gray-800/80 flex flex-col justify-between">
                                <span class="text-[9px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Precio</span>
                                <span class="text-xs font-extrabold text-gray-800 dark:text-gray-200 mt-0.5 font-mono">
                                    S/ {{ Number(bulkProduct.precio_venta).toFixed(2) }} / {{ getUnitShortName(bulkProduct.unidad_medida) }}
                                </span>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-900/40 py-2.5 px-3 rounded-xl border border-gray-200/60 dark:border-gray-800/80 flex flex-col justify-between">
                                <span class="text-[9px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Stock disponible</span>
                                <span class="text-xs font-extrabold text-gray-800 dark:text-gray-200 mt-0.5 font-mono">
                                    {{ Number(bulkProduct.stock_actual).toFixed(3) }} {{ getUnitShortName(bulkProduct.unidad_medida) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Selector de modo de venta (Botones tipo tarjeta) -->
                    <div class="grid grid-cols-2 gap-2.5 pt-0.5">
                        <button
                            type="button"
                            @click="bulkTab = 'peso'"
                            class="flex flex-col items-center justify-center py-1.5 rounded-xl border-2 transition-all duration-200 font-bold text-[11px] gap-0.5 select-none cursor-pointer active:scale-98"
                            :class="bulkTab === 'peso' 
                                ? 'bg-[#4B56E6] border-[#4B56E6] text-white shadow-xs' 
                                : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-850'"
                        >
                            <Scale class="w-4 h-4 transition-transform duration-200" :class="bulkTab === 'peso' ? 'text-white' : 'text-gray-400 dark:text-gray-500'" />
                            <span>Por {{ bulkProduct.tipo_venta === 'volumen' ? 'Volumen' : 'Peso' }}</span>
                        </button>
                        <button
                            type="button"
                            @click="bulkTab = 'monto'"
                            class="flex flex-col items-center justify-center py-1.5 rounded-xl border-2 transition-all duration-200 font-bold text-[11px] gap-0.5 select-none cursor-pointer active:scale-98"
                            :class="bulkTab === 'monto' 
                                ? 'bg-[#4B56E6] border-[#4B56E6] text-white shadow-xs' 
                                : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-500 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-850'"
                        >
                            <Banknote class="w-4 h-4 transition-transform duration-200" :class="bulkTab === 'monto' ? 'text-white' : 'text-gray-400 dark:text-gray-500'" />
                            <span>Por Monto</span>
                        </button>
                    </div>

                    <!-- Contenido de las Pestañas (Condicional con Transición) -->
                    <div class="relative overflow-hidden min-h-[290px] pt-1">
                        <transition name="tab-fade" mode="out-in">
                            <!-- TAB: PESO / VOLUMEN -->
                            <div v-if="bulkTab === 'peso'" key="peso" class="space-y-4 pt-1 transition-all duration-200">
                                <div class="space-y-1.5">
                                    <InputLabel for="bulk_qty" value="Cantidad" class="text-xs font-bold" />
                                    <div class="relative">
                                        <input 
                                            id="bulk_qty"
                                            type="number" 
                                            v-model="bulkQuantity" 
                                            @input="onBulkQuantityInput"
                                            @keydown.enter.prevent="submitBulkModal"
                                            step="0.001"
                                            min="0.001"
                                            class="block w-full border border-gray-200 dark:border-gray-700 dark:bg-gray-900 rounded-xl shadow-2xs font-mono font-bold text-base py-2 ps-3 pe-14 focus:outline-none focus:ring-1 focus:ring-[#4B56E6] focus:border-[#4B56E6]"
                                            placeholder="0.000"
                                            required
                                            ref="bulkQtyInputRef"
                                        />
                                        <span class="absolute inset-y-0 end-[30px] flex items-center text-xs font-bold text-gray-400 select-none pointer-events-none">
                                            {{ getUnitShortName(bulkProduct.unidad_medida) }}
                                        </span>
                                    </div>
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 block mt-1">
                                        Ingrese la cantidad en {{ 
                                            bulkProduct.unidad_medida === 'kilogramo' ? 'kilogramos' : 
                                            (bulkProduct.unidad_medida === 'litro' ? 'litros' : 
                                            (bulkProduct.unidad_medida === 'gramo' ? 'gramos' : 
                                            (bulkProduct.unidad_medida === 'mililitro' ? 'mililitros' : bulkProduct.unidad_medida))) 
                                        }}.
                                    </span>
                                </div>

                                <!-- Presets de peso/volumen -->
                                <div class="grid grid-cols-6 gap-1.5">
                                    <button 
                                        v-for="preset in [0.25, 0.5, 0.75, 1, 2, 5]" 
                                        :key="preset"
                                        type="button" 
                                        @click="selectBulkQuantityPreset(preset)"
                                        class="py-2 border rounded-xl active:scale-95 transition-all duration-150 font-mono font-bold text-[10px] text-center select-none shadow-2xs cursor-pointer transform hover:scale-[1.02] ease-out"
                                        :class="Number(bulkQuantity) === preset 
                                            ? 'border-[#4B56E6] bg-[#4B56E6] text-white scale-102 shadow-sm font-extrabold' 
                                            : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-850'"
                                    >
                                        {{ preset < 1 ? preset.toFixed(2) : preset }} {{ getUnitShortName(bulkProduct.unidad_medida) }}
                                    </button>
                                </div>

                                <!-- Tarjeta de Resumen -->
                                <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-2xl border border-gray-150 dark:border-gray-800 space-y-3">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-gray-400 dark:text-gray-500 font-bold uppercase tracking-wider">Precio</span>
                                        <span class="font-extrabold text-gray-800 dark:text-gray-200 font-mono">
                                            S/ {{ Number(bulkProduct.precio_venta).toFixed(2) }} / {{ getUnitShortName(bulkProduct.unidad_medida) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-gray-400 dark:text-gray-500 font-bold uppercase tracking-wider">Cantidad</span>
                                        <span class="font-extrabold text-gray-800 dark:text-gray-200 font-mono">
                                            {{ Number(bulkQuantity || 0).toFixed(3) }} {{ getUnitShortName(bulkProduct.unidad_medida) }}
                                        </span>
                                    </div>
                                    <div class="border-t border-dashed border-gray-200 dark:border-gray-800 pt-3 flex justify-between items-center">
                                        <span class="text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">TOTAL</span>
                                        <span class="text-2xl font-black font-mono text-[#4B56E6] dark:text-[#5C67F2] transition-all duration-300 transform scale-100 hover:scale-105">
                                            S/ {{ Number(bulkAmount || 0).toFixed(2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- TAB: MONTO -->
                            <div v-else-if="bulkTab === 'monto'" key="monto" class="space-y-4 pt-1 transition-all duration-200">
                                <div class="space-y-1.5">
                                    <InputLabel for="bulk_amt" value="Monto" class="text-xs font-bold" />
                                    <div class="relative">
                                        <span class="absolute inset-y-0 start-0 flex items-center ps-4 text-gray-405 font-mono font-bold text-sm select-none">S/</span>
                                        <input 
                                            id="bulk_amt"
                                            type="number" 
                                            v-model="bulkAmount" 
                                            @input="onBulkAmountInput"
                                            @keydown.enter.prevent="submitBulkModal"
                                            step="0.10"
                                            min="0.10"
                                            class="block w-full border border-gray-200 dark:border-gray-700 dark:bg-gray-900 rounded-xl shadow-2xs font-mono font-bold text-base py-2 ps-10 pe-8 focus:outline-none focus:ring-1 focus:ring-[#4B56E6] focus:border-[#4B56E6]"
                                            placeholder="0.00"
                                            required
                                            ref="bulkAmtInputRef"
                                        />
                                    </div>
                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 block mt-1">
                                        Ingrese el monto correspondiente de venta.
                                    </span>
                                </div>

                                <!-- Presets de monto -->
                                <div class="grid grid-cols-6 gap-1.5">
                                    <button 
                                        v-for="preset in [1, 2, 3, 5, 10, 20]" 
                                        :key="preset"
                                        type="button" 
                                        @click="selectBulkAmountPreset(preset)"
                                        class="py-2 border rounded-xl active:scale-95 transition-all duration-150 font-mono font-bold text-[10px] text-center select-none shadow-2xs cursor-pointer transform hover:scale-[1.02] ease-out"
                                        :class="Number(bulkAmount) === preset 
                                            ? 'border-[#4B56E6] bg-[#4B56E6] text-white scale-102 shadow-sm font-extrabold' 
                                            : 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-855'"
                                    >
                                        S/{{ preset }}
                                    </button>
                                </div>

                                <!-- Tarjeta de Resumen -->
                                <div class="bg-gray-50 dark:bg-gray-900/50 p-4 rounded-2xl border border-gray-150 dark:border-gray-800 space-y-3">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-gray-400 dark:text-gray-500 font-bold uppercase tracking-wider">Precio</span>
                                        <span class="font-extrabold text-gray-800 dark:text-gray-200 font-mono">
                                            S/ {{ Number(bulkProduct.precio_venta).toFixed(2) }} / {{ getUnitShortName(bulkProduct.unidad_medida) }}
                                        </span>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-gray-400 dark:text-gray-500 font-bold uppercase tracking-wider">Cantidad</span>
                                        <span class="font-extrabold text-gray-800 dark:text-gray-200 font-mono">
                                            {{ Number(bulkQuantity || 0).toFixed(3) }} {{ getUnitShortName(bulkProduct.unidad_medida) }}
                                        </span>
                                    </div>
                                    <div class="border-t border-dashed border-gray-200 dark:border-gray-800 pt-3 flex justify-between items-center">
                                        <span class="text-xs font-black text-gray-500 dark:text-gray-400 uppercase tracking-wider">TOTAL</span>
                                        <span class="text-2xl font-black font-mono text-[#4B56E6] dark:text-[#5C67F2] transition-all duration-300 transform scale-100 hover:scale-105">
                                            S/ {{ Number(bulkAmount || 0).toFixed(2) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </transition>
                    </div>

                    <!-- Botones de Acción -->
                    <div class="flex gap-3 pt-3 border-t border-gray-150 dark:border-gray-800">
                        <button
                            type="button"
                            @click="showBulkModal = false"
                            class="flex-1 py-3 bg-white dark:bg-gray-850 text-gray-700 dark:text-gray-350 border border-gray-200 dark:border-gray-700 rounded-2xl font-bold text-xs hover:bg-gray-50 dark:hover:bg-gray-750 active:scale-98 transition-all duration-150"
                        >
                            Cancelar
                        </button>
                        <button
                            type="button"
                            @click="submitBulkModal"
                            class="flex-1 py-3 bg-[#4B56E6] hover:bg-[#3C47C8] text-white rounded-2xl font-bold text-xs active:scale-98 transition-all duration-150 flex items-center justify-center gap-1.5 cursor-pointer shadow-sm"
                        >
                            Agregar al carrito
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Floating Toast Notification -->
        <div class="fixed top-6 right-6 z-[100] pointer-events-none flex flex-col gap-2 max-w-sm w-full">
            <transition name="toast-slide">
                <div 
                    v-if="showToast" 
                    class="pointer-events-auto flex items-center gap-3 p-4 rounded-2xl border shadow-lg backdrop-blur-md transition-all duration-300"
                    :class="toastType === 'success' 
                        ? 'bg-emerald-50/95 dark:bg-emerald-950/95 border-emerald-200 dark:border-emerald-900/50 text-emerald-800 dark:text-emerald-300 shadow-emerald-100/10 dark:shadow-none' 
                        : 'bg-red-50/95 dark:bg-red-950/95 border-red-200 dark:border-red-900/50 text-red-800 dark:text-red-350 shadow-red-100/10 dark:shadow-none'"
                >
                    <!-- Icon -->
                    <div class="shrink-0">
                        <svg v-if="toastType === 'success'" class="w-5 h-5 text-emerald-600 dark:text-emerald-450" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <svg v-else class="w-5 h-5 text-red-600 dark:text-red-450" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"></path>
                        </svg>
                    </div>

                    <!-- Text -->
                    <div class="flex-1 text-xs font-bold leading-snug">
                        {{ toastMessage }}
                    </div>

                    <!-- Close Button -->
                    <button 
                        type="button" 
                        @click="closeToast" 
                        class="text-gray-400 hover:text-gray-650 dark:hover:text-gray-200 transition-colors p-1 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </transition>
        </div>

        <!-- Modal Apertura de Caja -->
        <Modal :show="showAperturaModal" @close="showAperturaModal = false" max-width="md">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-center justify-between pb-3 border-b border-gray-100 dark:border-gray-800 mb-4">
                    <div class="flex items-center gap-3">
                        <span class="p-2 bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 rounded-xl">
                            <Banknote class="w-5 h-5" />
                        </span>
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">Apertura de Caja</h3>
                            <p class="text-xs text-gray-400 dark:text-gray-550 mt-0.5">Monto inicial para iniciar el turno de ventas.</p>
                        </div>
                    </div>
                    <button 
                        type="button"
                        @click="showAperturaModal = false"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors cursor-pointer"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitApertura" class="space-y-4">
                    <div>
                        <InputLabel for="monto_inicial" value="Monto Inicial en Efectivo (S/.)" class="text-xs font-bold" />
                        <div class="relative mt-1.5 rounded-xl shadow-2xs">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-xs font-bold text-gray-400">S/</span>
                            </div>
                            <input
                                id="monto_inicial"
                                type="number"
                                step="0.01"
                                min="0"
                                v-model="aperturaForm.monto_inicial"
                                class="block w-full pl-8 pr-3 py-3 border border-gray-200 dark:border-gray-700 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 bg-gray-55/50 dark:bg-gray-800 hover:bg-white dark:hover:bg-gray-750 focus:bg-white dark:focus:bg-gray-750 transition-all font-mono"
                                placeholder="0.00"
                                required
                            />
                        </div>
                        <InputError class="mt-1" :message="aperturaForm.errors.monto_inicial" />
                    </div>

                    <div>
                        <InputLabel for="observacion_apertura" value="Observación (Opcional)" class="text-xs font-bold" />
                        <textarea
                            id="observacion_apertura"
                            v-model="aperturaForm.observacion"
                            class="mt-1.5 block w-full border border-gray-200 dark:border-gray-700 rounded-xl shadow-2xs text-xs p-3 h-20 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 bg-gray-55/50 dark:bg-gray-800 hover:bg-white dark:hover:bg-gray-750 focus:bg-white dark:focus:bg-gray-750 resize-none transition-all outline-none"
                            placeholder="Ej. Cambio para el día, billetes sencillos..."
                        ></textarea>
                        <InputError class="mt-1" :message="aperturaForm.errors.observacion" />
                    </div>

                    <div class="pt-2 flex gap-3">
                        <button
                            type="button"
                            @click="showAperturaModal = false"
                            class="flex-1 py-3 border border-gray-250 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-750 dark:text-gray-250 rounded-xl font-bold text-xs active:scale-95 transition-all cursor-pointer text-center"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="aperturaForm.processing"
                            style="background-color: #2563eb; color: #ffffff;"
                            class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all disabled:opacity-50 flex items-center justify-center gap-1.5 shadow-2xs cursor-pointer"
                        >
                            <span>Abrir Caja</span>
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.tab-fade-enter-active,
.tab-fade-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.tab-fade-enter-from {
    opacity: 0;
    transform: translateX(12px);
}
.tab-fade-leave-to {
    opacity: 0;
    transform: translateX(-12px);
}
.animate-fadeIn {
    animation: fadeIn 0.2s ease forwards;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* Toast slide-in/out transition */
.toast-slide-enter-active,
.toast-slide-leave-active {
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}
.toast-slide-enter-from {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
}
.toast-slide-leave-to {
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
}
</style>
