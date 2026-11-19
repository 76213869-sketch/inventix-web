<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Head, useForm, usePage, Link, router } from '@inertiajs/vue3';
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
    Package, Plus, Search, AlertCircle, Trash2, X, FolderTree, ArrowLeftRight, 
    Sparkles, TrendingUp, TrendingDown, Scale, MoreVertical, Activity, Clock, 
    ArrowUpRight, ArrowDownRight, Eye, Layers, Settings, CheckCircle2, ShoppingBag, 
    AlertTriangle, BadgePercent, ShieldAlert, HeartHandshake, Info, History, Edit3
} from 'lucide-vue-next';

const props = defineProps({
    productos: {
        type: Array,
        required: true,
    },
    categorias: {
        type: Array,
        required: true,
    },
    insumos: {
        type: Array,
        required: true,
    },
});

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const showModal = ref(false);
const editMode = ref(false);
const activeEditTab = ref('info'); // info, inventario, imagen, historial
const searchQuery = ref('');
const filterCategoria = ref('');
const filterTipo = ref('');
const isLoading = ref(true);

// Dashboard interactive filter state
const selectedFilterStat = ref('todos'); // todos, activos, bajo, agotados, granel

// Quick View and Stock Adjust States
const showQuickView = ref(false);
const quickViewProduct = ref(null);

const showAdjustModal = ref(false);
const adjustProduct = ref(null);
const adjustForm = useForm({
    tipo: 'entrada', // entrada, salida, ajuste
    cantidad: 0,
    motivo: 'ajuste_inventario', // compra, venta, devolucion, merma, ajuste_inventario
    observaciones: '',
});

onMounted(() => {
    setTimeout(() => {
        isLoading.value = false;
    }, 200);
});

// Computed Dashboard Metrics
const totalProductsCount = computed(() => props.productos.length);
const activeProductsCount = computed(() => props.productos.filter(p => p.estado === 'activo').length);
const lowStockCount = computed(() => props.productos.filter(p => p.estado === 'activo' && Number(p.stock_actual) > 0 && Number(p.stock_actual) <= Number(p.stock_minimo)).length);
const outOfStockCount = computed(() => props.productos.filter(p => p.estado === 'activo' && Number(p.stock_actual) <= 0).length);
const bulkProductsCount = computed(() => props.productos.filter(p => ['peso', 'volumen'].includes(p.tipo_venta)).length);

// List of potential parents (products of type 'estandar' or 'insumo')
const parentProducts = computed(() => {
    return props.productos.filter(p => p.tipo_producto === 'estandar' && !p.parent_id);
});

const metadataList = ref([]);
const recipeInsumosList = ref([]);
const fileInput = ref(null);
const imagenPreview = ref(null);

// Temp inputs for adding ingredients to the recipe
const selectedInsumoId = ref('');
const insumoCantidad = ref(1);

const form = useForm({
    id: null,
    nombre: '',
    categoria_id: '',
    parent_id: '',
    descripcion: '',
    codigo_barras: '',
    tipo_producto: 'estandar',
    tipo_venta: 'unidad',
    unidad_medida: 'unidad',
    precio_compra: 0,
    precio_venta: 0,
    stock_minimo: 5,
    stock_actual: 0,
    estado: 'activo',
    metadatos: null,
    receta_insumos: [],
    imagen: null,
    imagen_tipo: 'archivo',
    imagen_url: '',
    eliminar_imagen: false,
});

const unitSuffix = computed(() => {
    const unit = form.unidad_medida || 'unidad';
    const map = {
        unidad: 'und',
        kilogramo: 'kg',
        gramo: 'g',
        litro: 'L',
        mililitro: 'ml',
        paquete: 'paq',
        caja: 'caja',
        docena: 'doc'
    };
    return map[unit.toLowerCase()] || unit;
});

const urlError = computed(() => {
    if (!form.imagen_url) return '';
    if (!form.imagen_url.startsWith('http://') && !form.imagen_url.startsWith('https://')) {
        return 'La URL debe comenzar con http:// o https://';
    }
    const extensions = ['.jpg', '.jpeg', '.png', '.webp', '.gif'];
    const urlLower = form.imagen_url.toLowerCase();
    const hasValidExtension = extensions.some(ext => urlLower.includes(ext));
    if (!hasValidExtension) {
        return 'La URL debe apuntar a una imagen válida (jpg, jpeg, png, webp)';
    }
    return '';
});

const resolvedImagenPreview = computed(() => {
    if (form.imagen_tipo === 'url') {
        if (form.imagen_url && !urlError.value) {
            return form.imagen_url;
        }
        return null;
    }
    return imagenPreview.value;
});

// Intelligent Search & Reactive Dashboard Filtering
const filteredProductos = computed(() => {
    let result = props.productos;
    
    // 1. Search Query Filters
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(p => 
            p.nombre.toLowerCase().includes(query) ||
            String(p.id).includes(query) ||
            (p.codigo_barras && p.codigo_barras.toLowerCase().includes(query)) ||
            (p.categoria?.nombre && p.categoria.nombre.toLowerCase().includes(query)) ||
            (p.unidad_medida && p.unidad_medida.toLowerCase().includes(query)) ||
            (p.tipo_venta && p.tipo_venta.toLowerCase().includes(query))
        );
    }
    
    // 2. Select Dropdown Filters
    if (filterCategoria.value) {
        result = result.filter(p => String(p.categoria_id) === String(filterCategoria.value));
    }
    
    if (filterTipo.value) {
        result = result.filter(p => p.tipo_producto === filterTipo.value);
    }

    // 3. Clickable Dashboard Card Filters
    if (selectedFilterStat.value === 'activos') {
        result = result.filter(p => p.estado === 'activo');
    } else if (selectedFilterStat.value === 'bajo') {
        result = result.filter(p => p.estado === 'activo' && Number(p.stock_actual) > 0 && Number(p.stock_actual) <= Number(p.stock_minimo));
    } else if (selectedFilterStat.value === 'agotados') {
        result = result.filter(p => p.estado === 'activo' && Number(p.stock_actual) <= 0);
    } else if (selectedFilterStat.value === 'granel') {
        result = result.filter(p => ['peso', 'volumen'].includes(p.tipo_venta));
    }
    
    return result;
});

const openCreateModal = () => {
    form.clearErrors();
    form.reset();
    
    form.id = null;
    form.nombre = '';
    form.categoria_id = '';
    form.parent_id = '';
    form.descripcion = '';
    form.codigo_barras = '';
    form.tipo_producto = 'estandar';
    form.tipo_venta = 'unidad';
    form.unidad_medida = 'unidad';
    form.precio_compra = 0;
    form.precio_venta = 0;
    form.stock_minimo = 5;
    form.stock_actual = 0;
    form.estado = 'activo';
    form.metadatos = null;
    form.receta_insumos = [];
    form.imagen = null;
    form.imagen_tipo = 'archivo';
    form.imagen_url = '';
    form.eliminar_imagen = false;

    metadataList.value = [];
    recipeInsumosList.value = [];
    selectedInsumoId.value = '';
    insumoCantidad.value = 1;
    imagenPreview.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    editMode.value = false;
    showModal.value = true;
};

const openEditModal = (producto) => {
    form.clearErrors();
    form.reset();

    form.id = producto.id;
    form.nombre = producto.nombre;
    form.categoria_id = producto.categoria_id || '';
    form.parent_id = producto.parent_id || '';
    form.descripcion = producto.descripcion || '';
    form.codigo_barras = producto.codigo_barras || '';
    form.tipo_producto = producto.tipo_producto;
    form.tipo_venta = producto.tipo_venta || 'unidad';
    form.unidad_medida = producto.unidad_medida;
    form.precio_compra = Number(producto.precio_compra);
    form.precio_venta = Number(producto.precio_venta);
    form.stock_minimo = Number(producto.stock_minimo);
    form.stock_actual = Number(producto.stock_actual);
    form.estado = producto.estado;
    form.imagen = null;
    form.eliminar_imagen = false;

    const tipoImg = producto.metadatos?.imagen_tipo || 'archivo';
    form.imagen_tipo = tipoImg;
    if (tipoImg === 'url') {
        form.imagen_url = producto.metadatos?.imagen || '';
    } else {
        form.imagen_url = '';
    }

    metadataList.value = [];
    if (producto.metadatos && typeof producto.metadatos === 'object') {
        Object.entries(producto.metadatos).forEach(([key, value]) => {
            if (key !== 'imagen' && key !== 'imagen_tipo') {
                metadataList.value.push({ key, value: String(value) });
            }
        });
    }

    recipeInsumosList.value = [];
    if (producto.tipo_producto === 'receta' && producto.recetas) {
        producto.recetas.forEach(receta => {
            const insumoDetail = receta.producto_insumo || receta.productoInsumo;
            recipeInsumosList.value.push({
                producto_insumo_id: receta.producto_insumo_id,
                nombre: insumoDetail?.nombre || 'Insumo',
                unidad_medida: insumoDetail?.unidad_medida || 'unidad',
                cantidad: Number(receta.cantidad)
            });
        });
    }

    selectedInsumoId.value = '';
    insumoCantidad.value = 1;
    imagenPreview.value = producto.imagen_url || null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    editMode.value = true;
    activeEditTab.value = 'info';
    showModal.value = true;
};

watch(() => form.tipo_venta, (newVal) => {
    if (newVal === 'peso') {
        form.unidad_medida = 'kilogramo';
    } else if (newVal === 'volumen') {
        form.unidad_medida = 'litro';
    } else if (newVal === 'unidad') {
        form.unidad_medida = 'unidad';
    }
});

const closeModal = () => {
    showModal.value = false;
    imagenPreview.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
    form.reset();
};

const addMetadataRow = () => {
    metadataList.value.push({ key: '', value: '' });
};

const removeMetadataRow = (index) => {
    metadataList.value.splice(index, 1);
};

const compressImage = (file, maxWidth = 800, maxHeight = 800, quality = 0.8) => {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.readAsDataURL(file);
        reader.onload = (event) => {
            const img = new Image();
            img.src = event.target.result;
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;
                
                if (width > height) {
                    if (width > maxWidth) {
                        height = Math.round((height * maxWidth) / width);
                        width = maxWidth;
                    }
                } else {
                    if (height > maxHeight) {
                        width = Math.round((width * maxHeight) / height);
                        height = maxHeight;
                    }
                }
                
                canvas.width = width;
                canvas.height = height;
                
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                canvas.toBlob((blob) => {
                    if (!blob) {
                        reject(new Error('Canvas toBlob failed'));
                        return;
                    }
                    const compressedFile = new File([blob], file.name.substring(0, file.name.lastIndexOf('.')) + '.webp', {
                        type: 'image/webp',
                        lastModified: Date.now()
                    });
                    resolve(compressedFile);
                }, 'image/webp', quality);
            };
            img.onerror = (err) => reject(err);
        };
        reader.onerror = (err) => reject(err);
    });
};

const handleImageFile = async (file) => {
    if (!file) return;
    
    const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    if (!validTypes.includes(file.type)) {
        alert('Formato de imagen no admitido. Por favor, selecciona un archivo JPG, PNG o WEBP.');
        return;
    }
    
    if (file.size > 5 * 1024 * 1024) {
        alert('La imagen es demasiado grande. Selecciona un archivo de menos de 5MB.');
        return;
    }
    
    try {
        const optimizedFile = await compressImage(file);
        form.imagen = optimizedFile;
        form.eliminar_imagen = false;
        imagenPreview.value = URL.createObjectURL(optimizedFile);
    } catch (e) {
        console.error('Error optimizing image:', e);
        form.imagen = file;
        form.eliminar_imagen = false;
        imagenPreview.value = URL.createObjectURL(file);
    }
};

const onImageSelect = (e) => {
    const file = e.target.files[0];
    handleImageFile(file);
};

const removeUploadedImage = () => {
    form.imagen = null;
    imagenPreview.value = null;
    form.eliminar_imagen = true;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = () => {
    const metaObj = {};
    metadataList.value.forEach(item => {
        if (item.key.trim()) {
            metaObj[item.key.trim()] = item.value.trim();
        }
    });
    form.metadatos = Object.keys(metaObj).length > 0 ? metaObj : null;
    
    form.receta_insumos = form.tipo_producto === 'receta' 
        ? recipeInsumosList.value.map(ri => ({
            producto_insumo_id: ri.producto_insumo_id,
            cantidad: ri.cantidad
          }))
        : [];

    if (editMode.value) {
        form.transform((data) => ({
            ...data,
            _method: 'PUT'
        })).post(route('productos.update', form.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('productos.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteProducto = (producto) => {
    if (confirm(`¿Estás seguro de que deseas eliminar permanentemente el producto "${producto.nombre}"?`)) {
        form.delete(route('productos.destroy', producto.id), {
            onSuccess: () => {
                showQuickView.value = false;
            }
        });
    }
};

const handleDuplicate = (producto) => {
    if (confirm(`¿Deseas duplicar el producto "${producto.nombre}"? Se creará una copia con stock 0 y sin código de barras.`)) {
        router.post(route('productos.duplicar', producto.id), {}, {
            onSuccess: () => {
                showQuickView.value = false;
            }
        });
    }
};

const openAdjustModal = (producto) => {
    adjustProduct.value = producto;
    adjustForm.reset();
    adjustForm.tipo = 'entrada';
    adjustForm.cantidad = 0;
    adjustForm.motivo = 'ajuste_inventario';
    adjustForm.observaciones = '';
    showAdjustModal.value = true;
};

const submitAdjust = () => {
    adjustForm.post(route('productos.ajustarStock', adjustProduct.value.id), {
        onSuccess: () => {
            showAdjustModal.value = false;
            if (quickViewProduct.value && quickViewProduct.value.id === adjustProduct.value.id) {
                const updated = props.productos.find(p => p.id === adjustProduct.value.id);
                if (updated) quickViewProduct.value = updated;
            }
        }
    });
};

const openQuickView = (producto) => {
    quickViewProduct.value = producto;
    showQuickView.value = true;
};

const handleToggleStatus = (producto) => {
    const newStatus = producto.estado === 'activo' ? 'inactivo' : 'activo';
    router.put(route('productos.update', producto.id), {
        ...producto,
        estado: newStatus,
        precio_compra: Number(producto.precio_compra),
        precio_venta: Number(producto.precio_venta),
        stock_minimo: Number(producto.stock_minimo),
        stock_actual: Number(producto.stock_actual)
    }, {
        onSuccess: () => {
            if (quickViewProduct.value && quickViewProduct.value.id === producto.id) {
                const updated = props.productos.find(p => p.id === producto.id);
                if (updated) quickViewProduct.value = updated;
            }
        }
    });
};

// Formats number to currency S/.
const formatCurrency = (val) => {
    return new Intl.NumberFormat('es-PE', { style: 'currency', currency: 'PEN' }).format(val);
};

const getStockStatus = (producto) => {
    const stock = Number(producto.stock_actual);
    const min = Number(producto.stock_minimo);
    if (stock <= 0) return 'agotado';
    if (stock <= min) return 'bajo';
    return 'disponible';
};

const getStockStatusLabel = (producto) => {
    const status = getStockStatus(producto);
    if (status === 'agotado') return 'Agotado';
    if (status === 'bajo') return 'Stock Bajo';
    return 'Disponible';
};

const getStockProgress = (producto) => {
    const stock = Number(producto.stock_actual);
    const min = Number(producto.stock_minimo);
    if (stock <= 0) return 0;
    if (min <= 0) return 100;
    if (stock <= min) {
        return (stock / min) * 50; // Progress is 0-50% below min
    } else {
        const extra = ((stock - min) / min) * 50;
        return Math.min(100, 50 + extra); // Progress scales up to 100% above min
    }
};

const formatStockText = (producto) => {
    const isBulk = ['peso', 'volumen'].includes(producto.tipo_venta);
    const suffix = isBulk ? (producto.unidad_medida === 'litro' ? 'L' : 'kg') : 'unidades';
    const qty = isBulk ? Number(producto.stock_actual).toFixed(3) : Number(producto.stock_actual).toFixed(0);
    return `${qty} ${suffix}`;
};

const formatMinStockText = (producto) => {
    const isBulk = ['peso', 'volumen'].includes(producto.tipo_venta);
    const suffix = isBulk ? (producto.unidad_medida === 'litro' ? 'L' : 'kg') : 'unidades';
    const qty = isBulk ? Number(producto.stock_minimo).toFixed(3) : Number(producto.stock_minimo).toFixed(0);
    return `Stock mín: ${qty} ${suffix}`;
};

const getTypeBadgeClass = (tipo) => {
    switch (tipo) {
        case 'estandar': return 'bg-blue-50 text-blue-700 border-blue-100 dark:bg-blue-950/20 dark:text-blue-400 dark:border-blue-900/30';
        case 'variante': return 'bg-purple-50 text-purple-700 border-purple-100 dark:bg-purple-950/20 dark:text-purple-400 dark:border-purple-900/30';
        case 'insumo': return 'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/30';
        case 'receta': return 'bg-pink-50 text-pink-700 border-pink-100 dark:bg-pink-950/20 dark:text-pink-400 dark:border-pink-900/30';
        default: return 'bg-gray-50 text-gray-700 border-gray-100';
    }
};

const getTypeLabel = (tipo) => {
    switch (tipo) {
        case 'estandar': return 'Estándar';
        case 'variante': return 'Variante';
        case 'insumo': return 'Insumo';
        case 'receta': return 'Combo / Receta';
        default: return tipo;
    }
};
</script>

<template>
    <Head title="Productos" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">Catálogo de Productos</h2>
                    <p class="text-xs text-gray-500 mt-0.5 font-medium">Controla existencias, precios de venta, costos e insumos.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="route('categorias.index')">
                        <SecondaryButton class="h-9 rounded-xl">
                            <FolderTree class="w-4 h-4 me-1.5" />
                            Categorías
                        </SecondaryButton>
                    </Link>
                    <Link :href="route('kardex.index')">
                        <SecondaryButton class="h-9 rounded-xl">
                            <ArrowLeftRight class="w-4 h-4 me-1.5" />
                            Auditoría Kardex
                        </SecondaryButton>
                    </Link>
                    <PrimaryButton v-if="productos.length > 0" @click="openCreateModal" class="h-9 rounded-xl">
                        <Plus class="w-4 h-4 me-1.5" />
                        Nuevo Producto
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Toast Notifications -->
            <div v-if="flashSuccess" class="rounded-xl bg-emerald-50 dark:bg-emerald-950/20 p-4 text-xs text-emerald-800 dark:text-emerald-450 border border-emerald-100 dark:border-emerald-900/30 flex items-start gap-3 shadow-3xs">
                <span class="p-1 bg-white dark:bg-gray-900 rounded-lg border border-emerald-200 dark:border-emerald-800 text-emerald-600">✓</span>
                <div>
                    <div class="font-bold">Acción Realizada Exitosamente</div>
                    <div class="opacity-95 mt-0.5">{{ flashSuccess }}</div>
                </div>
            </div>

            <div v-if="flashError" class="rounded-xl bg-red-50 dark:bg-red-950/20 p-4 text-xs text-red-800 dark:text-red-450 border border-red-100 dark:border-red-900/30 flex items-start gap-3 shadow-3xs">
                <AlertCircle class="w-4.5 h-4.5 text-red-600 mt-0.5 shrink-0" />
                <div>
                    <div class="font-bold">Error en la Operación</div>
                    <div class="opacity-95 mt-0.5">{{ flashError }}</div>
                </div>
            </div>

            <!-- SKELETON LOADING STATE -->
            <div v-if="isLoading" class="grid grid-cols-2 md:grid-cols-5 gap-4 animate-pulse mb-6">
                <div v-for="i in 5" :key="i" class="h-20 bg-gray-100 dark:bg-gray-800 rounded-2xl"></div>
            </div>

            <!-- 4. TARJETAS RESUMEN INTERACTIVAS Y CLICKABLES -->
            <div v-else-if="productos.length > 0" class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <!-- Productos Totales -->
                <button 
                    @click="selectedFilterStat = 'todos'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex items-center justify-between outline-none cursor-pointer"
                    :class="selectedFilterStat === 'todos' ? 'border-blue-500 ring-2 ring-blue-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <div>
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Productos Totales</span>
                        <h4 class="text-xl font-bold font-mono text-gray-900 dark:text-white mt-1">{{ totalProductsCount }}</h4>
                        <p class="text-[9px] text-gray-450 mt-1">Catálogo completo registrado</p>
                    </div>
                    <div class="w-8 h-8 rounded-xl bg-gray-50 dark:bg-gray-950 text-gray-500 flex items-center justify-center shrink-0">
                        <Package class="w-4.5 h-4.5" />
                    </div>
                </button>

                <!-- Productos Activos -->
                <button 
                    @click="selectedFilterStat = 'activos'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex items-center justify-between outline-none cursor-pointer"
                    :class="selectedFilterStat === 'activos' ? 'border-emerald-500 ring-2 ring-emerald-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <div>
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500">Activos</span>
                        <h4 class="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-450 mt-1">{{ activeProductsCount }}</h4>
                        <p class="text-[9px] text-gray-450 mt-1">Habilitados para el POS</p>
                    </div>
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-950/20 text-emerald-650 flex items-center justify-center shrink-0">
                        <CheckCircle2 class="w-4.5 h-4.5" />
                    </div>
                </button>

                <!-- Stock Bajo -->
                <button 
                    @click="selectedFilterStat = 'bajo'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex items-center justify-between outline-none cursor-pointer"
                    :class="selectedFilterStat === 'bajo' ? 'border-amber-500 ring-2 ring-amber-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <div>
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-555">Stock Bajo</span>
                        <h4 class="text-xl font-bold font-mono text-amber-600 dark:text-amber-450 mt-1">{{ lowStockCount }}</h4>
                        <p class="text-[9px] text-gray-450 mt-1">Bajo el mínimo establecido</p>
                    </div>
                    <div class="w-8 h-8 rounded-xl bg-amber-50 dark:bg-amber-950/20 text-amber-600 flex items-center justify-center shrink-0">
                        <AlertTriangle class="w-4.5 h-4.5" />
                    </div>
                </button>

                <!-- Productos Agotados -->
                <button 
                    @click="selectedFilterStat = 'agotados'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex items-center justify-between outline-none cursor-pointer"
                    :class="selectedFilterStat === 'agotados' ? 'border-red-500 ring-2 ring-red-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <div>
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-555">Agotados</span>
                        <h4 class="text-xl font-bold font-mono text-red-650 dark:text-red-405 mt-1">{{ outOfStockCount }}</h4>
                        <p class="text-[9px] text-gray-450 mt-1">Existencia nula en almacén</p>
                    </div>
                    <div class="w-8 h-8 rounded-xl bg-red-50 dark:bg-red-950/20 text-red-650 flex items-center justify-center shrink-0">
                        <ShieldAlert class="w-4.5 h-4.5" />
                    </div>
                </button>

                <!-- A Granel -->
                <button 
                    @click="selectedFilterStat = 'granel'"
                    class="text-left bg-white dark:bg-gray-900 border rounded-2xl p-4 shadow-3xs transition-all duration-200 hover:shadow-md hover:-translate-y-0.5 flex items-center justify-between outline-none cursor-pointer"
                    :class="selectedFilterStat === 'granel' ? 'border-teal-500 ring-2 ring-teal-500/10' : 'border-gray-250 dark:border-gray-800'"
                >
                    <div>
                        <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-555">A Granel</span>
                        <h4 class="text-xl font-bold font-mono text-teal-600 dark:text-teal-450 mt-1">{{ bulkProductsCount }}</h4>
                        <p class="text-[9px] text-gray-450 mt-1">Vendidos por peso/volumen</p>
                    </div>
                    <div class="w-8 h-8 rounded-xl bg-teal-50 dark:bg-teal-950/20 text-teal-600 flex items-center justify-center shrink-0">
                        <Scale class="w-4.5 h-4.5" />
                    </div>
                </button>
            </div>

            <!-- EMPTY STATE -->
            <div v-if="productos.length === 0" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-3xl p-12 shadow-3xs flex flex-col items-center text-center max-w-lg mx-auto mt-8">
                <div class="w-16 h-16 bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 rounded-2xl flex items-center justify-center mb-6">
                    <Package class="w-8 h-8" />
                </div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">No existen productos registrados</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2 mb-6 max-w-xs leading-relaxed">Registra tus mercaderías, materias primas o recetas compuestas para habilitar las ventas en POS.</p>
                <PrimaryButton @click="openCreateModal" class="rounded-xl">
                    <Plus class="w-4 h-4 me-1.5" />
                    Registrar primer producto
                </PrimaryButton>
            </div>

            <!-- MAIN LIST / TABLE -->
            <div v-else class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-800 overflow-hidden shadow-3xs">
                <div class="p-6">
                    
                    <!-- 5. Filtros Compactos (Mismo alto h-10) -->
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
                                    class="block w-full ps-10 p-2.5 h-10 text-xs text-gray-900 dark:text-white border border-gray-250 dark:border-gray-800 rounded-xl bg-gray-50/50 dark:bg-gray-850 hover:bg-white focus:bg-white focus:ring-2 focus:ring-blue-500/25 focus:border-blue-500 transition-all duration-150 outline-none"
                                    placeholder="Buscar por nombre, código, barra, categoría..."
                                />
                            </div>

                            <!-- Categoria Filter -->
                            <select
                                v-model="filterCategoria"
                                class="h-10 border border-gray-250 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl text-xs px-3 transition-all outline-none cursor-pointer"
                            >
                                <option value="">Todas las Categorías</option>
                                <option v-for="cat in categorias" :key="cat.id" :value="cat.id">
                                    {{ cat.nombre }}
                                </option>
                            </select>

                            <!-- Tipo Filter -->
                            <select
                                v-model="filterTipo"
                                class="h-10 border border-gray-250 dark:border-gray-800 bg-white dark:bg-gray-900 text-gray-700 dark:text-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl text-xs px-3 transition-all outline-none cursor-pointer"
                            >
                                <option value="">Todos los Tipos</option>
                                <option value="estandar">Producto Normal</option>
                                <option value="variante">Variante</option>
                                <option value="insumo">Insumo</option>
                                <option value="receta">Servicio / Receta</option>
                            </select>
                        </div>
                        <div v-if="selectedFilterStat !== 'todos'" class="flex items-center gap-1.5">
                            <span class="text-[10px] bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-400 px-2.5 py-1 rounded-lg border border-blue-100 dark:border-blue-900/30 font-bold uppercase">
                                Filtro: {{ selectedFilterStat }}
                            </span>
                            <button @click="selectedFilterStat = 'todos'" class="text-[10px] text-gray-400 hover:text-gray-600 font-bold hover:underline">Limpiar</button>
                        </div>
                    </div>

                    <!-- 1 & 6. Tabla Profesional -->
                    <div class="relative overflow-x-auto rounded-xl border border-gray-150 dark:border-gray-800">
                        <table class="w-full text-xs text-left text-gray-500 dark:text-gray-400 border-collapse">
                            <thead class="text-[10px] text-gray-400 uppercase tracking-wider font-bold bg-gray-50/70 dark:bg-gray-950 sticky top-0 z-10 border-b border-gray-150 dark:border-gray-800">
                                <tr>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Producto</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Código</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Categoría</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-right text-gray-500">P. Compra</th>
                                    <th scope="col" class="px-6 py-4 text-right font-bold text-gray-500">P. Venta</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-left text-gray-500 w-44">Stock disponible</th>
                                    <th scope="col" class="px-6 py-4 text-right font-bold text-gray-500 w-16">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                                <tr 
                                    v-for="producto in filteredProductos" 
                                    :key="producto.id"
                                    class="transition-colors duration-150"
                                    :class="producto.estado === 'inactivo'
                                        ? 'bg-gray-50/40 dark:bg-gray-950/10 hover:bg-gray-50/70 dark:hover:bg-gray-900/60'
                                        : 'hover:bg-gray-50/50 dark:hover:bg-gray-950/40'"
                                >
                                    <!-- Thumbnail & Name -->
                                    <th scope="row" class="px-6 py-5 font-semibold text-gray-900 dark:text-white cursor-pointer transition-opacity" :class="producto.estado === 'inactivo' ? 'opacity-50' : ''" @click="openQuickView(producto)">
                                        <div class="flex items-center gap-3">
                                            <!-- 2. Imagen del producto (Aumentada ligeramente) -->
                                            <div class="w-12 h-12 bg-gray-50 dark:bg-gray-950 border border-gray-205 dark:border-gray-800 rounded-xl overflow-hidden shrink-0 flex items-center justify-center relative shadow-3xs">
                                                <img 
                                                    v-if="producto.imagen_url" 
                                                    :src="producto.imagen_url" 
                                                    class="w-full h-full object-cover" 
                                                    alt="Thumbnail" 
                                                />
                                                <div v-else class="text-gray-400">
                                                    <Package class="w-5 h-5 stroke-1.5" />
                                                </div>
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-xs font-extrabold text-gray-900 dark:text-white hover:text-blue-650 transition-colors">
                                                    {{ producto.nombre }}
                                                    <span v-if="producto.parent" class="ms-1 text-[10px] text-gray-400 dark:text-gray-500 font-normal">
                                                        ({{ producto.parent.nombre }})
                                                    </span>
                                                </span>
                                                <div class="flex flex-wrap gap-1.5 mt-1 items-center">
                                                    <span 
                                                        class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[8px] font-bold border tracking-wide uppercase"
                                                        :class="getTypeBadgeClass(producto.tipo_producto)"
                                                    >
                                                        {{ getTypeLabel(producto.tipo_producto) }}
                                                    </span>
                                                    <span class="text-[9px] text-gray-450 dark:text-gray-500 font-bold uppercase">
                                                        {{ producto.unidad_medida }}
                                                    </span>
                                                    <span 
                                                        v-if="producto.estado === 'inactivo'"
                                                        class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[8px] font-bold border tracking-wide uppercase bg-red-50 text-red-700 border-red-150 dark:bg-red-950/20 dark:text-red-400 dark:border-red-900/30"
                                                    >
                                                        Inactivo
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </th>
                                    <!-- Barcode / ID -->
                                    <td class="px-6 py-5 font-mono text-[10px] text-gray-655 dark:text-gray-400 transition-opacity" :class="producto.estado === 'inactivo' ? 'opacity-50' : ''">
                                        {{ producto.codigo_barras || producto.id }}
                                    </td>
                                    <!-- Category -->
                                    <td class="px-6 py-5 text-gray-655 dark:text-gray-300 font-bold transition-opacity" :class="producto.estado === 'inactivo' ? 'opacity-50' : ''">
                                        {{ producto.categoria?.nombre || '-' }}
                                    </td>
                                    <!-- Cost Price -->
                                    <td class="px-6 py-5 text-right text-gray-600 dark:text-gray-405 font-mono transition-opacity" :class="producto.estado === 'inactivo' ? 'opacity-50' : ''">
                                        {{ formatCurrency(producto.precio_compra) }}
                                    </td>
                                    <!-- Sale Price -->
                                    <td class="px-6 py-5 text-right font-extrabold text-gray-900 dark:text-white font-mono transition-opacity" :class="producto.estado === 'inactivo' ? 'opacity-50' : ''">
                                        {{ formatCurrency(producto.precio_venta) }}
                                    </td>
                                    
                                    <!-- 3. Stock Visual y Barra de Progreso -->
                                    <td class="px-6 py-5 transition-opacity" :class="producto.estado === 'inactivo' ? 'opacity-50' : ''">
                                        <div class="flex flex-col space-y-1.5 w-36">
                                            <div class="flex justify-between items-baseline">
                                                <span 
                                                    class="font-extrabold text-xs font-mono"
                                                    :class="{
                                                        'text-red-600 dark:text-red-400': getStockStatus(producto) === 'agotado',
                                                        'text-amber-600 dark:text-amber-450': getStockStatus(producto) === 'bajo',
                                                        'text-emerald-650 dark:text-emerald-400': getStockStatus(producto) === 'disponible',
                                                    }"
                                                >
                                                    {{ formatStockText(producto) }}
                                                </span>
                                                <span class="text-[8px] font-extrabold uppercase tracking-wider px-1.5 py-0.2 rounded"
                                                    :class="{
                                                        'bg-red-50 text-red-700 dark:bg-red-950/20 dark:text-red-405': getStockStatus(producto) === 'agotado',
                                                        'bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-405': getStockStatus(producto) === 'bajo',
                                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-405': getStockStatus(producto) === 'disponible',
                                                    }"
                                                >
                                                    {{ getStockStatusLabel(producto) }}
                                                </span>
                                            </div>
                                            
                                            <!-- Barra visual de disponibilidad -->
                                            <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5 overflow-hidden">
                                                <div 
                                                    class="h-full rounded-full transition-all duration-300"
                                                    :class="{
                                                        'bg-red-500': getStockStatus(producto) === 'agotado',
                                                        'bg-amber-500': getStockStatus(producto) === 'bajo',
                                                        'bg-emerald-500': getStockStatus(producto) === 'disponible',
                                                    }"
                                                    :style="{ width: `${getStockProgress(producto)}%` }"
                                                ></div>
                                            </div>
                                            
                                            <span class="text-[9px] text-gray-400 dark:text-gray-500 font-bold">
                                                {{ formatMinStockText(producto) }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- 1. Acciones por Producto (Menú contextual de tres puntos fijo) -->
                                    <td class="px-6 py-5 text-right whitespace-nowrap">
                                        <Dropdown align="right" width="48">
                                            <template #trigger>
                                                <button class="inline-flex items-center justify-center p-2 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-850 transition-colors cursor-pointer border border-gray-100 dark:border-gray-800">
                                                    <MoreVertical class="w-4 h-4" />
                                                </button>
                                            </template>
                                            <template #content>
                                                <div class="py-1 text-gray-700 dark:text-gray-300">
                                                    <button @click="openEditModal(producto)" class="flex w-full px-4 py-2 text-left text-xs hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer items-center gap-2">
                                                        <Edit3 class="w-3.5 h-3.5 text-gray-400" />
                                                        Editar producto
                                                    </button>
                                                    <button @click="openAdjustModal(producto)" class="flex w-full px-4 py-2 text-left text-xs hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer items-center gap-2">
                                                        <Scale class="w-3.5 h-3.5 text-gray-400" />
                                                        Ajustar stock
                                                    </button>
                                                    <button @click="handleDuplicate(producto)" class="flex w-full px-4 py-2 text-left text-xs hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer items-center gap-2">
                                                        <Layers class="w-3.5 h-3.5 text-gray-400" />
                                                        Duplicar producto
                                                    </button>
                                                    <button @click="handleToggleStatus(producto)" class="flex w-full px-4 py-2 text-left text-xs hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer items-center gap-2">
                                                        <CheckCircle2 class="w-3.5 h-3.5 text-gray-400" />
                                                        {{ producto.estado === 'activo' ? 'Desactivar producto' : 'Activar producto' }}
                                                    </button>
                                                    
                                                    <!-- Divisor visual para destructivo -->
                                                    <div class="border-t border-gray-100 dark:border-gray-800 my-1"></div>
                                                    
                                                    <button @click="deleteProducto(producto)" class="flex w-full px-4 py-2 text-left text-xs text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/20 font-bold transition-colors cursor-pointer items-center gap-2">
                                                        <Trash2 class="w-3.5 h-3.5 text-red-500" />
                                                        Eliminar producto
                                                    </button>
                                                </div>
                                            </template>
                                        </Dropdown>
                                    </td>
                                </tr>
                                <tr v-if="filteredProductos.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-450 dark:text-gray-655 font-medium">
                                        No se encontraron resultados para la búsqueda.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 8. MODAL NUEVO PRODUCTO (Bloques Claros Diferenciados) -->
        <Modal :show="showModal && !editMode" @close="closeModal" max-width="3xl">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-850 mb-5">
                    <div class="flex items-center gap-2">
                        <Package class="w-5 h-5 text-blue-600" />
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Registrar Nuevo Producto</h3>
                    </div>
                    <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-650 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- BLOQUE 1: Información General -->
                    <div class="bg-gray-50/50 dark:bg-gray-950/10 p-5 border border-gray-150 dark:border-gray-800 rounded-2xl space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-850 pb-2">
                            <span class="w-2 h-2 bg-blue-600 rounded-full"></span>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Información General</h4>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-3">
                                <div>
                                    <InputLabel for="nombre" value="Nombre comercial del producto" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                    <TextInput id="nombre" type="text" class="mt-1.5 block w-full text-xs" v-model="form.nombre" placeholder="Ej. Coca Cola Personal 500ml" required />
                                    <InputError class="mt-1" :message="form.errors.nombre" />
                                </div>
                                <div>
                                    <InputLabel for="categoria_id" value="Categoría de inventario" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                    <select id="categoria_id" class="mt-1.5 block w-full h-10 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 transition-all outline-none cursor-pointer" v-model="form.categoria_id">
                                        <option value="">Selecciona Categoría...</option>
                                        <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombre }}</option>
                                    </select>
                                    <InputError class="mt-1" :message="form.errors.categoria_id" />
                                </div>
                            </div>
                            <div>
                                <InputLabel for="descripcion" value="Notas / Descripción descriptiva" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <textarea id="descripcion" rows="4" class="mt-1.5 block w-full border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs p-3 transition-all outline-none resize-none" v-model="form.descripcion" placeholder="Detalles de presentación o marcas..."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- BLOQUE 2: Venta y Precios -->
                    <div class="bg-gray-50/50 dark:bg-gray-950/10 p-5 border border-gray-150 dark:border-gray-800 rounded-2xl space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-855 pb-2">
                            <span class="w-2 h-2 bg-emerald-600 rounded-full"></span>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Venta y Precios</h4>
                        </div>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <InputLabel for="precio_compra" value="Precio Compra (S/.)" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput id="precio_compra" type="number" step="0.01" class="mt-1.5 block w-full text-xs" v-model="form.precio_compra" required />
                                <InputError class="mt-1" :message="form.errors.precio_compra" />
                            </div>
                            <div>
                                <InputLabel for="precio_venta" value="Precio Venta (S/.)" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput id="precio_venta" type="number" step="0.01" class="mt-1.5 block w-full text-xs" v-model="form.precio_venta" required />
                                <InputError class="mt-1" :message="form.errors.precio_venta" />
                            </div>
                            <div>
                                <InputLabel for="tipo_venta" value="Tipo de Venta" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <select id="tipo_venta" class="mt-1.5 block w-full h-10 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer" v-model="form.tipo_venta" required>
                                    <option value="unidad">Por Unidad</option>
                                    <option value="peso">Por Peso (Granel)</option>
                                    <option value="volumen">Por Volumen</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="unidad_medida" value="Unidad de Medida" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <select id="unidad_medida" class="mt-1.5 block w-full h-10 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer" v-model="form.unidad_medida" required>
                                    <option value="unidad">Unidades</option>
                                    <option value="kilogramo">Kilogramos</option>
                                    <option value="gramo">Gramos</option>
                                    <option value="litro">Litros</option>
                                    <option value="mililitro">Mililitros</option>
                                    <option value="paquete">Paquetes</option>
                                    <option value="caja">Cajas</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- BLOQUE 3: Inventario y Control -->
                    <div class="bg-gray-50/50 dark:bg-gray-950/10 p-5 border border-gray-150 dark:border-gray-800 rounded-2xl space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-855 pb-2">
                            <span class="w-2 h-2 bg-indigo-600 rounded-full"></span>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Inventario y Control</h4>
                        </div>
                        
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <InputLabel for="stock_actual" value="Stock Inicial" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput id="stock_actual" type="number" step="0.001" class="mt-1.5 block w-full text-xs" v-model="form.stock_actual" required />
                            </div>
                            <div>
                                <InputLabel for="stock_minimo" value="Stock Mínimo" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput id="stock_minimo" type="number" step="0.001" class="mt-1.5 block w-full text-xs" v-model="form.stock_minimo" required />
                            </div>
                            <div>
                                <InputLabel for="codigo_barras" value="Código de Barras" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput id="codigo_barras" type="text" class="mt-1.5 block w-full text-xs font-mono" v-model="form.codigo_barras" placeholder="Ej. 775..." />
                            </div>
                            <div>
                                <InputLabel for="tipo_producto" value="Tipo Producto" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <select id="tipo_producto" class="mt-1.5 block w-full h-10 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer" v-model="form.tipo_producto" required>
                                    <option value="estandar">Normal (Estándar)</option>
                                    <option value="insumo">Insumo / Materia prima</option>
                                    <option value="receta">Combo / Receta</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- BLOQUE 4: Imagen del Producto -->
                    <div class="bg-gray-50/50 dark:bg-gray-950/10 p-5 border border-gray-150 dark:border-gray-800 rounded-2xl space-y-4">
                        <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-855 pb-2">
                            <span class="w-2 h-2 bg-purple-650 rounded-full"></span>
                            <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider">Ficha de Imagen</h4>
                        </div>
                        
                        <div class="flex gap-4">
                            <div class="w-28 h-28 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl flex items-center justify-center overflow-hidden shrink-0 relative shadow-3xs">
                                <img v-if="resolvedImagenPreview" :src="resolvedImagenPreview" class="w-full h-full object-cover" />
                                <div v-else class="text-gray-400 flex flex-col items-center">
                                    <Package class="w-7 h-7 stroke-1.5 opacity-40" />
                                    <span class="text-[8px] mt-1 font-extrabold uppercase">Sin Imagen</span>
                                </div>
                            </div>
                            <div class="flex-1 space-y-3">
                                <div class="flex border-b border-gray-150 dark:border-gray-800 text-[10px] font-bold gap-4">
                                    <button type="button" @click="form.imagen_tipo = 'archivo'" :class="form.imagen_tipo === 'archivo' ? 'border-b-2 border-blue-600 text-blue-600 pb-2' : 'text-gray-400 pb-2'">Subir Archivo</button>
                                    <button type="button" @click="form.imagen_tipo = 'url'" :class="form.imagen_tipo === 'url' ? 'border-b-2 border-blue-600 text-blue-600 pb-2' : 'text-gray-400 pb-2'">Usar URL</button>
                                </div>
                                <div v-if="form.imagen_tipo === 'archivo'" class="space-y-2">
                                    <input type="file" ref="fileInput" accept="image/*" class="hidden" @change="onImageSelect" />
                                    <SecondaryButton type="button" @click="$refs.fileInput.click()" class="w-full text-xs h-9 justify-center rounded-xl">Seleccionar Archivo Local</SecondaryButton>
                                    <button v-if="imagenPreview" type="button" @click="removeUploadedImage" class="text-[10px] text-red-600 font-bold block mx-auto hover:underline">Eliminar imagen</button>
                                </div>
                                <div v-else>
                                    <TextInput type="url" class="w-full text-xs" v-model="form.imagen_url" placeholder="https://ejemplo.com/imagen.jpg" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-855">
                        <SecondaryButton @click="closeModal" class="rounded-xl h-11 px-6">Cancelar</SecondaryButton>
                        <PrimaryButton type="submit" class="rounded-xl h-11 px-6">Guardar Producto</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 9. MODAL EDITAR PRODUCTO (Pestañas Horizontales) -->
        <Modal :show="showModal && editMode" @close="closeModal" max-width="3xl">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-850 mb-5">
                    <div class="flex items-center gap-2">
                        <Settings class="w-5 h-5 text-blue-650" />
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Ficha de Edición: <span class="text-blue-600">{{ form.nombre }}</span></h3>
                    </div>
                    <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-650 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <!-- Tab Headers -->
                <div class="flex border-b border-gray-150 dark:border-gray-850 text-xs font-bold gap-6 mb-6">
                    <button type="button" @click="activeEditTab = 'info'" :class="activeEditTab === 'info' ? 'border-b-2 border-blue-600 text-blue-600 pb-3' : 'text-gray-400 pb-3 hover:text-gray-600'">
                        <Info class="w-4 h-4 inline mr-1.5" /> Información
                    </button>
                    <button type="button" @click="activeEditTab = 'inventario'" :class="activeEditTab === 'inventario' ? 'border-b-2 border-blue-600 text-blue-600 pb-3' : 'text-gray-400 pb-3 hover:text-gray-600'">
                        <Layers class="w-4 h-4 inline mr-1.5" /> Inventario
                    </button>
                    <button type="button" @click="activeEditTab = 'imagen'" :class="activeEditTab === 'imagen' ? 'border-b-2 border-blue-600 text-blue-600 pb-3' : 'text-gray-400 pb-3 hover:text-gray-600'">
                        <Package class="w-4 h-4 inline mr-1.5" /> Imagen
                    </button>
                    <button type="button" @click="activeEditTab = 'historial'" :class="activeEditTab === 'historial' ? 'border-b-2 border-blue-600 text-blue-600 pb-3' : 'text-gray-400 pb-3 hover:text-gray-600'">
                        <History class="w-4 h-4 inline mr-1.5" /> Historial
                    </button>
                </div>

                <form @submit.prevent="submit" class="space-y-6">
                    <!-- TAB 1: Información -->
                    <div v-if="activeEditTab === 'info'" class="space-y-4">
                        <div>
                            <InputLabel for="edit_nombre" value="Nombre comercial del producto" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                            <TextInput id="edit_nombre" type="text" class="mt-1.5 block w-full text-xs" v-model="form.nombre" required />
                            <InputError class="mt-1.5" :message="form.errors.nombre" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="edit_categoria_id" value="Categoría" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <select id="edit_categoria_id" class="mt-1.5 block w-full h-10 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer" v-model="form.categoria_id">
                                    <option value="">Selecciona Categoría...</option>
                                    <option v-for="cat in categorias" :key="cat.id" :value="cat.id">{{ cat.nombre }}</option>
                                </select>
                            </div>
                            <div>
                                <InputLabel for="edit_tipo_venta" value="Tipo de Venta" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <select id="edit_tipo_venta" class="mt-1.5 block w-full h-10 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer" v-model="form.tipo_venta" required>
                                    <option value="unidad">Por Unidad</option>
                                    <option value="peso">Por Peso (Granel)</option>
                                    <option value="volumen">Por Volumen</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <InputLabel for="edit_descripcion" value="Notas / Descripción" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                            <textarea id="edit_descripcion" rows="3" class="mt-1.5 block w-full border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs p-3 transition-all outline-none resize-none" v-model="form.descripcion" placeholder="Detalles..."></textarea>
                        </div>
                    </div>

                    <!-- TAB 2: Inventario -->
                    <div v-if="activeEditTab === 'inventario'" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="edit_precio_compra" value="Precio Compra (S/.)" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput id="edit_precio_compra" type="number" step="0.01" class="mt-1.5 block w-full text-xs" v-model="form.precio_compra" required />
                            </div>
                            <div>
                                <InputLabel for="edit_precio_venta" value="Precio Venta (S/.)" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput id="edit_precio_venta" type="number" step="0.01" class="mt-1.5 block w-full text-xs" v-model="form.precio_venta" required />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel value="Stock Actual (Arqueo formal se realiza en Ajustar Stock)" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput type="number" step="0.001" class="mt-1.5 block w-full text-xs bg-gray-50 dark:bg-gray-950" v-model="form.stock_actual" />
                            </div>
                            <div>
                                <InputLabel for="edit_stock_minimo" value="Stock Mínimo" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput id="edit_stock_minimo" type="number" step="0.001" class="mt-1.5 block w-full text-xs" v-model="form.stock_minimo" required />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <InputLabel for="edit_codigo_barras" value="Código de Barras" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <TextInput id="edit_codigo_barras" type="text" class="mt-1.5 block w-full text-xs font-mono" v-model="form.codigo_barras" />
                            </div>
                            <div>
                                <InputLabel for="edit_estado" value="Estado" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                                <select id="edit_estado" class="mt-1.5 block w-full h-10 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer" v-model="form.estado" required>
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: Imagen -->
                    <div v-if="activeEditTab === 'imagen'" class="space-y-4">
                        <div class="flex flex-col sm:flex-row gap-5 items-start sm:items-center">
                            <div class="w-36 h-36 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-2xl flex items-center justify-center overflow-hidden shrink-0 relative shadow-3xs">
                                <img v-if="resolvedImagenPreview" :src="resolvedImagenPreview" class="w-full h-full object-cover" />
                                <div v-else class="text-gray-400 flex flex-col items-center">
                                    <Package class="w-10 h-10 stroke-1.5 opacity-40" />
                                    <span class="text-[9px] mt-1 font-bold">SIN IMAGEN</span>
                                </div>
                            </div>
                            <div class="flex-1 space-y-3 w-full">
                                <div class="flex border-b border-gray-100 dark:border-gray-800 text-xs font-bold gap-4">
                                    <button type="button" @click="form.imagen_tipo = 'archivo'" :class="form.imagen_tipo === 'archivo' ? 'border-b-2 border-blue-600 text-blue-600 pb-2' : 'text-gray-400 pb-2'">Subir Archivo</button>
                                    <button type="button" @click="form.imagen_tipo = 'url'" :class="form.imagen_tipo === 'url' ? 'border-b-2 border-blue-600 text-blue-600 pb-2' : 'text-gray-400 pb-2'">Usar URL</button>
                                </div>
                                <div v-if="form.imagen_tipo === 'archivo'">
                                    <input type="file" ref="fileInput" accept="image/*" class="hidden" @change="onImageSelect" />
                                    <SecondaryButton type="button" @click="$refs.fileInput.click()" class="w-full justify-center rounded-xl h-10">Subir Imagen</SecondaryButton>
                                    <button v-if="imagenPreview" type="button" @click="removeUploadedImage" class="text-xs text-red-600 font-bold block mt-2 hover:underline">Eliminar imagen actual</button>
                                </div>
                                <div v-else>
                                    <TextInput type="url" class="w-full text-xs" v-model="form.imagen_url" placeholder="https://ejemplo.com/imagen.jpg" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: Historial -->
                    <div v-if="activeEditTab === 'historial'" class="space-y-4">
                        <div class="bg-gray-50/50 dark:bg-gray-950/10 p-5 border border-gray-150 dark:border-gray-800 rounded-2xl space-y-3 text-xs font-mono">
                            <div class="flex justify-between border-b border-gray-100 dark:border-gray-850 pb-2">
                                <span class="text-gray-400">Fecha de Creación:</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">
                                    {{ quickViewProduct?.created_at ? new Date(quickViewProduct.created_at).toLocaleString('es-PE') : 'No registrado' }}
                                </span>
                            </div>
                            <div class="flex justify-between border-b border-gray-100 dark:border-gray-850 pb-2">
                                <span class="text-gray-400">Última Modificación:</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">
                                    {{ quickViewProduct?.updated_at ? new Date(quickViewProduct.updated_at).toLocaleString('es-PE') : 'No registrado' }}
                                </span>
                            </div>
                            <div class="flex justify-between border-b border-gray-100 dark:border-gray-850 pb-2">
                                <span class="text-gray-400">Última Compra:</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">No registrada</span>
                            </div>
                            <div class="flex justify-between border-b border-gray-100 dark:border-gray-850 pb-2">
                                <span class="text-gray-400">Última Venta:</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">No registrada</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Proveedor Principal:</span>
                                <span class="font-bold text-gray-800 dark:text-gray-200">Proveedor General</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-855">
                        <SecondaryButton @click="closeModal" class="rounded-xl h-11 px-6">Cancelar</SecondaryButton>
                        <PrimaryButton type="submit" class="rounded-xl h-11 px-6">Guardar Cambios</PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 10. AJUSTE RÁPIDO DE STOCK EXCLUSIVO -->
        <Modal :show="showAdjustModal" @close="showAdjustModal = false" max-width="md">
            <div class="p-6 text-gray-900 dark:text-gray-100" v-if="adjustProduct">
                <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-850 mb-5">
                    <div class="flex items-center gap-2">
                        <Scale class="w-5 h-5 text-amber-600" />
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">Ajuste de Stock: <span class="text-amber-600">{{ adjustProduct.nombre }}</span></h3>
                    </div>
                    <button type="button" @click="showAdjustModal = false" class="text-gray-400 hover:text-gray-655 transition-colors">
                        <X class="w-5 h-5" />
                    </button>
                </div>

                <form @submit.prevent="submitAdjust" class="space-y-4">
                    <div class="p-4 bg-gray-50 dark:bg-gray-950 border border-gray-150 dark:border-gray-850 rounded-2xl text-xs font-mono flex justify-between items-center mb-4">
                        <span class="text-gray-400">Stock Actual registrado:</span>
                        <span class="font-extrabold text-sm text-gray-850 dark:text-white">{{ formatStockText(adjustProduct) }}</span>
                    </div>

                    <div>
                        <InputLabel for="adjust_tipo" value="Operación de Ajuste" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                        <select id="adjust_tipo" class="mt-1.5 block w-full h-10 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer" v-model="adjustForm.tipo" required>
                            <option value="entrada">Entrada de Inventario (Sumar)</option>
                            <option value="salida">Salida / Merma (Restar)</option>
                            <option value="ajuste">Ajuste Físico de Inventario (Establecer stock real)</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="adjust_cantidad" value="Cantidad" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                            <TextInput id="adjust_cantidad" type="number" step="0.001" class="mt-1.5 block w-full text-xs font-mono font-bold" v-model="adjustForm.cantidad" required />
                        </div>
                        <div>
                            <InputLabel for="adjust_motivo" value="Motivo" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                            <select id="adjust_motivo" class="mt-1.5 block w-full h-10 border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs px-3 outline-none cursor-pointer" v-model="adjustForm.motivo" required>
                                <option value="ajuste_inventario">Ajuste de Inventario</option>
                                <option value="compra">Compra</option>
                                <option value="devolucion">Devolución</option>
                                <option value="merma">Merma / Pérdida</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <InputLabel for="adjust_observaciones" value="Observaciones / Bitácora" class="text-xs font-bold text-gray-700 dark:text-gray-300" />
                        <textarea id="adjust_observaciones" rows="2" class="mt-1.5 block w-full border border-gray-200 dark:border-gray-800 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/25 rounded-xl bg-white dark:bg-gray-900 text-gray-900 dark:text-white text-xs p-3 transition-all outline-none resize-none" v-model="adjustForm.observaciones" placeholder="Ej. Conteo físico de fin de mes, producto roto..."></textarea>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-850 mt-4">
                        <SecondaryButton @click="showAdjustModal = false" class="rounded-xl h-11 px-5">Cancelar</SecondaryButton>
                        <button type="submit" :disabled="adjustForm.processing" class="bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs rounded-xl h-11 px-5 cursor-pointer shadow-3xs transition-all active:scale-95">Aplicar Ajuste</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- PANEL LATERAL / VISTA RÁPIDA (SLIDE-OVER DRAWER) -->
        <div v-if="showQuickView && quickViewProduct" class="fixed inset-0 overflow-hidden z-50">
            <!-- Overlay background -->
            <div class="absolute inset-0 bg-gray-550/30 backdrop-blur-xs transition-opacity" @click="showQuickView = false"></div>
            
            <div class="absolute inset-y-0 right-0 pl-10 max-w-full flex">
                <div class="w-screen max-w-md bg-white dark:bg-gray-900 shadow-xl border-l border-gray-200 dark:border-gray-800 flex flex-col justify-between p-6">
                    
                    <!-- Header -->
                    <div class="flex items-center justify-between pb-4 border-b border-gray-100 dark:border-gray-850">
                        <div class="flex items-center gap-2">
                            <Eye class="w-5 h-5 text-blue-600" />
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">Vista Rápida del Producto</h3>
                        </div>
                        <button type="button" @click="showQuickView = false" class="text-gray-400 hover:text-gray-655 transition-colors">
                            <X class="w-5 h-5" />
                        </button>
                    </div>

                    <!-- Body Content -->
                    <div class="flex-1 overflow-y-auto py-5 space-y-6 min-h-0 pr-1">
                        <!-- Product Large Image -->
                        <div class="w-full h-48 bg-gray-50 dark:bg-gray-950 border border-gray-200 dark:border-gray-800 rounded-3xl overflow-hidden relative shadow-3xs flex items-center justify-center">
                            <img v-if="quickViewProduct.imagen_url" :src="quickViewProduct.imagen_url" class="w-full h-full object-contain p-2" />
                            <div v-else class="text-gray-450 dark:text-gray-600 flex flex-col items-center">
                                <Package class="w-12 h-12 stroke-1" />
                                <span class="text-[9px] mt-1.5 font-bold uppercase tracking-wider">Sin Imagen de Producto</span>
                            </div>
                        </div>

                        <!-- Basic Information -->
                        <div>
                            <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-555 font-mono">Código #{{ quickViewProduct.id }}</span>
                            <h2 class="text-lg font-bold text-gray-900 dark:text-white mt-1">{{ quickViewProduct.nombre }}</h2>
                            <p class="text-xs text-gray-555 dark:text-gray-400 mt-1.5 leading-relaxed">{{ quickViewProduct.descripcion || 'Sin descripción detallada.' }}</p>
                        </div>

                        <!-- Status and Type Badges -->
                        <div class="flex flex-wrap gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border" :class="getTypeBadgeClass(quickViewProduct.tipo_producto)">
                                {{ getTypeLabel(quickViewProduct.tipo_producto) }}
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border bg-gray-50 text-gray-650 border-gray-150 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700">
                                Categoría: {{ quickViewProduct.categoria?.nombre || 'General' }}
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border"
                                :class="quickViewProduct.estado === 'activo' ? 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-450' : 'bg-gray-50 text-gray-600 border-gray-150'"
                            >
                                {{ quickViewProduct.estado === 'activo' ? 'Activo' : 'Inactivo' }}
                            </span>
                        </div>

                        <!-- Financials / Ganancia -->
                        <div class="bg-gray-50/50 dark:bg-gray-955/20 p-4 border border-gray-150 dark:border-gray-800 rounded-2xl space-y-2.5 text-xs">
                            <div class="flex justify-between font-mono">
                                <span class="text-gray-400">Precio Compra (Costo):</span>
                                <span class="font-semibold text-gray-850 dark:text-gray-200">{{ formatCurrency(quickViewProduct.precio_compra) }}</span>
                            </div>
                            <div class="flex justify-between font-mono">
                                <span class="text-gray-400">Precio Venta (Público):</span>
                                <span class="font-bold text-gray-900 dark:text-white">{{ formatCurrency(quickViewProduct.precio_venta) }}</span>
                            </div>
                            <div class="flex justify-between border-t border-dashed border-gray-250 dark:border-gray-800 pt-2 font-mono">
                                <span class="text-gray-400">Ganancia Estimada:</span>
                                <span class="font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ formatCurrency(Number(quickViewProduct.precio_venta) - Number(quickViewProduct.precio_compra)) }}
                                    <span class="text-[10px] font-normal text-emerald-500 dark:text-emerald-555 ms-1">
                                        ({{ Number(quickViewProduct.precio_compra) > 0 ? (((Number(quickViewProduct.precio_venta) - Number(quickViewProduct.precio_compra)) / Number(quickViewProduct.precio_compra)) * 100).toFixed(0) : '0' }}%)
                                    </span>
                                </span>
                            </div>
                        </div>

                        <!-- Stock Info -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-gray-50/50 dark:bg-gray-955/20 p-4 border border-gray-150 dark:border-gray-800 rounded-2xl text-center">
                                <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500 block mb-1">Stock Disponible</span>
                                <span class="text-base font-extrabold font-mono" :class="getStockStatus(quickViewProduct) === 'agotado' ? 'text-red-600' : (getStockStatus(quickViewProduct) === 'bajo' ? 'text-amber-600' : 'text-emerald-600')">
                                    {{ formatStockText(quickViewProduct) }}
                                </span>
                            </div>
                            <div class="bg-gray-50/50 dark:bg-gray-955/20 p-4 border border-gray-150 dark:border-gray-800 rounded-2xl text-center">
                                <span class="text-[9px] font-extrabold uppercase tracking-wider text-gray-400 dark:text-gray-500 block mb-1">Stock Mínimo</span>
                                <span class="text-base font-extrabold font-mono text-gray-700 dark:text-gray-300">
                                    {{ formatMinStockText(quickViewProduct) }}
                                </span>
                            </div>
                        </div>

                        <!-- Metadata Auditoría -->
                        <div class="space-y-2 text-xs font-mono border-t border-gray-100 dark:border-gray-855 pt-4">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Creado el:</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ new Date(quickViewProduct.created_at).toLocaleDateString('es-PE') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Código barras:</span>
                                <span class="text-gray-700 dark:text-gray-300">{{ quickViewProduct.codigo_barras || 'No asignado' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Venta por:</span>
                                <span class="text-gray-700 dark:text-gray-300 capitalize">{{ quickViewProduct.tipo_venta }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="border-t border-gray-100 dark:border-gray-855 pt-4 space-y-3 shrink-0">
                        <div class="flex gap-2">
                            <button @click="openEditModal(quickViewProduct); showQuickView = false" class="flex-1 inline-flex h-10 items-center justify-center rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs transition-all active:scale-98 cursor-pointer shadow-3xs">
                                Editar Producto
                            </button>
                            <button @click="openAdjustModal(quickViewProduct)" class="flex-1 inline-flex h-10 items-center justify-center rounded-xl bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/20 dark:hover:bg-amber-950/30 text-amber-700 dark:text-amber-450 border border-amber-200 dark:border-amber-900/40 font-bold text-xs transition-all active:scale-98 cursor-pointer">
                                Ajustar Stock
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <button @click="deleteProducto(quickViewProduct)" class="flex-1 inline-flex h-10 items-center justify-center rounded-xl bg-red-50 hover:bg-red-150 dark:bg-red-950/25 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900/30 transition-all cursor-pointer font-bold text-xs">
                                Eliminar Ficha
                            </button>
                            <button @click="showQuickView = false" class="flex-1 inline-flex h-10 items-center justify-center rounded-xl bg-gray-50 hover:bg-gray-100 dark:bg-gray-850 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-750 font-bold text-xs transition-all cursor-pointer">
                                Cerrar Vista
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>
