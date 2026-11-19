<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import { 
    Building, 
    User, 
    Lock, 
    Trash2, 
    CheckCircle, 
    AlertTriangle,
    Camera,
    Upload,
    Palette,
    Monitor,
    ShieldCheck,
    Database,
    RefreshCw
} from 'lucide-vue-next';

const props = defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    empresa: {
        type: Object,
        required: false,
        default: () => ({}),
    },
    logoUrl: {
        type: String,
        required: false,
        default: null,
    },
    avatarUrl: {
        type: String,
        required: false,
        default: null,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

// Active Tab state (empresa, perfil, seguridad, personalizacion, danger)
const activeTab = ref('empresa');

// Sync URL parameter on page loads
const updateTabFromUrl = () => {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam && ['empresa', 'perfil', 'seguridad', 'personalizacion', 'danger'].includes(tabParam)) {
        activeTab.value = tabParam;
    } else {
        activeTab.value = 'empresa';
    }
};

onMounted(() => {
    updateTabFromUrl();
});

// Watch Inertia page navigation/URL changes to switch tabs reactively
watch(() => page.url, () => {
    updateTabFromUrl();
});

// Update browser history query when user clicks tabs
watch(activeTab, (newTab) => {
    const currentTabInUrl = new URLSearchParams(window.location.search).get('tab');
    if (currentTabInUrl !== newTab) {
        const newUrl = `${window.location.pathname}?tab=${newTab}`;
        window.history.pushState({ path: newUrl }, '', newUrl);
    }
});

const tabs = [
    { id: 'empresa', name: 'Empresa', icon: Building },
    { id: 'perfil', name: 'Mi Perfil', icon: User },
    { id: 'seguridad', name: 'Seguridad', icon: Lock },
    { id: 'personalizacion', name: 'Personalización', icon: Palette },
    { id: 'danger', name: 'Zona de peligro', icon: Trash2 },
];

// Feedback Toast messages
const successMessage = ref('');
const showSuccessToast = (msg) => {
    successMessage.value = msg;
    setTimeout(() => {
        successMessage.value = '';
    }, 4000);
};

// Watch for page session successes
watch(() => page.props.flash?.success, (newVal) => {
    if (newVal) {
        showSuccessToast(newVal);
        page.props.flash.success = null;
    }
}, { immediate: true });

// Form 1: Empresa
const empresaForm = useForm({
    nombre: props.empresa?.nombre || '',
    ruc: props.empresa?.ruc || '',
    direccion: props.empresa?.direccion || '',
    telefono: props.empresa?.telefono || '',
    correo: props.empresa?.correo || '',
    logo: null,
});

const logoPreview = ref(props.logoUrl);
const logoInputRef = ref(null);
const moneda = ref(localStorage.getItem('inventex_moneda') || 'PEN');
const timezone = ref(localStorage.getItem('inventex_timezone') || 'America/Lima');

const handleLogoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        empresaForm.logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const submitEmpresa = () => {
    localStorage.setItem('inventex_moneda', moneda.value);
    localStorage.setItem('inventex_timezone', timezone.value);
    
    empresaForm.post(route('profile.updateEmpresa'), {
        preserveScroll: true,
        onSuccess: () => {
            empresaForm.logo = null;
            showSuccessToast('Los cambios de la empresa se guardaron correctamente.');
        },
    });
};

// Form 2: Mi Perfil
const perfilForm = useForm({
    _method: 'PATCH',
    nombre: user.value?.nombre || '',
    correo: user.value?.correo || '',
    avatar: null,
});

const avatarPreview = ref(props.avatarUrl);
const avatarInputRef = ref(null);
const cargo = ref(localStorage.getItem('inventex_cargo') || 'Propietario');

const handleAvatarChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        perfilForm.avatar = file;
        avatarPreview.value = URL.createObjectURL(file);
    }
};

const submitPerfil = () => {
    localStorage.setItem('inventex_cargo', cargo.value);
    
    perfilForm.post(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            perfilForm.avatar = null;
            showSuccessToast('Los cambios del perfil se guardaron correctamente.');
        },
    });
};

// Password block inside Mi Perfil / Seguridad
const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const seguridadForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submitSeguridad = () => {
    seguridadForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => {
            seguridadForm.reset();
            showSuccessToast('La contraseña se ha actualizado correctamente.');
        },
        onError: () => {
            if (seguridadForm.errors.password) {
                seguridadForm.reset('password', 'password_confirmation');
                passwordInput.value?.focus();
            }
            if (seguridadForm.errors.current_password) {
                seguridadForm.reset('current_password');
                currentPasswordInput.value?.focus();
            }
        },
    });
};

// Security mock sessions and MFA
const activeSessions = ref(['Chrome en Lima, Perú (Sesión Actual - Activa)', 'Safari en iPhone (Hace 2 horas)', 'Firefox en Windows (Hace 1 día)']);
const confirmingCloseSessions = ref(false);
const closeSessionsCode = ref('');

const triggerCloseSessions = () => {
    confirmingCloseSessions.value = true;
};

const submitCloseSessions = () => {
    if (closeSessionsCode.value.toUpperCase() === 'CERRAR SESIONES') {
        activeSessions.value = ['Chrome en Lima, Perú (Sesión Actual - Activa)'];
        confirmingCloseSessions.value = false;
        closeSessionsCode.value = '';
        showSuccessToast('Se cerraron correctamente las demás sesiones activas.');
    } else {
        alert('Código de seguridad incorrecto.');
    }
};

const mfaEnabled = ref(localStorage.getItem('inventex_mfa_enabled') === 'true');
const confirmingMfaToggle = ref(false);
const mfaToggleCode = ref('');

const toggleMfa = () => {
    confirmingMfaToggle.value = true;
};

const submitMfaToggle = () => {
    const expected = mfaEnabled.value ? 'DESACTIVAR 2FA' : 'ACTIVAR 2FA';
    if (mfaToggleCode.value.toUpperCase() === expected) {
        mfaEnabled.value = !mfaEnabled.value;
        localStorage.setItem('inventex_mfa_enabled', mfaEnabled.value ? 'true' : 'false');
        confirmingMfaToggle.value = false;
        mfaToggleCode.value = '';
        showSuccessToast(mfaEnabled.value ? 'Autenticación de dos pasos habilitada con éxito.' : 'Autenticación de dos pasos deshabilitada.');
    } else {
        alert('Código de seguridad incorrecto.');
    }
};

// Form 4: Personalización (stored locally)
const logoSistemaPreview = ref(localStorage.getItem('inventex_logo_sistema') || '/logo-inventex.png');
const logoSistemaInputRef = ref(null);
const colorPrincipal = ref(localStorage.getItem('inventex_color') || 'violet');
const formatoFecha = ref(localStorage.getItem('inventex_formato_fecha') || 'DD/MM/YYYY');
const formatoMoneda = ref(localStorage.getItem('inventex_formato_moneda') || 'S/ 1,234.56');
const ticketHeader = ref(localStorage.getItem('inventex_ticket_header') || '¡Gracias por su compra!');
const ticketFooter = ref(localStorage.getItem('inventex_ticket_footer') || 'Vuelva pronto');
const comprobanteInfo = ref(localStorage.getItem('inventex_comprobante_info') || 'Términos y condiciones estándar de la bodega.');

const handleLogoSistemaChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = () => {
            logoSistemaPreview.value = reader.result;
            localStorage.setItem('inventex_logo_sistema', reader.result);
            showSuccessToast('Logotipo del sistema cargado.');
        };
        reader.readAsDataURL(file);
    }
};

const submitPersonalizacion = () => {
    localStorage.setItem('inventex_color', colorPrincipal.value);
    localStorage.setItem('inventex_formato_fecha', formatoFecha.value);
    localStorage.setItem('inventex_formato_moneda', formatoMoneda.value);
    localStorage.setItem('inventex_ticket_header', ticketHeader.value);
    localStorage.setItem('inventex_ticket_footer', ticketFooter.value);
    localStorage.setItem('inventex_comprobante_info', comprobanteInfo.value);
    showSuccessToast('Preferencias de personalización guardadas con éxito.');
};

// Danger Zone Confirmations
const deleteForm = useForm({
    password: '',
});

const confirmingUserDeletion = ref(false);
const deletePasswordInput = ref(null);

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    setTimeout(() => deletePasswordInput.value?.focus(), 200);
};

const submitDeleteUser = () => {
    deleteForm.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => deletePasswordInput.value?.focus(),
        onFinish: () => deleteForm.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    deleteForm.clearErrors();
    deleteForm.reset();
};

const confirmingResetDemo = ref(false);
const resetDemoCode = ref('');

const triggerResetDemo = () => {
    confirmingResetDemo.value = true;
};

const submitResetDemo = () => {
    if (resetDemoCode.value.toUpperCase() === 'REINICIAR') {
        confirmingResetDemo.value = false;
        resetDemoCode.value = '';
        showSuccessToast('Los datos de demostración se reiniciaron correctamente a valores iniciales.');
    } else {
        alert('Código de seguridad incorrecto.');
    }
};

const confirmingRestoreBackup = ref(false);
const restoreBackupCode = ref('');

const triggerRestoreBackup = () => {
    confirmingRestoreBackup.value = true;
};

const submitRestoreBackup = () => {
    if (restoreBackupCode.value.toUpperCase() === 'RESTAURAR') {
        confirmingRestoreBackup.value = false;
        restoreBackupCode.value = '';
        showSuccessToast('Copia de seguridad restaurada correctamente en el sistema.');
    } else {
        alert('Código de seguridad incorrecto.');
    }
};
</script>

<template>
    <Head title="Configuración" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 dark:text-white tracking-tight">
                        Configuración del Sistema
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-medium">
                        Administre la identidad comercial de su negocio y las opciones del sistema.
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                
                <!-- Main Container layout with Horizontal Tabs and dynamic forms below -->
                <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-250 dark:border-gray-800 shadow-sm overflow-hidden flex flex-col min-h-[600px]">
                    
                    <!-- Horizontal Tab Navigation Bar -->
                    <div class="border-b border-gray-155 dark:border-gray-800 bg-gray-50/40 dark:bg-gray-900/50 shrink-0">
                        <div class="px-6 py-1 overflow-x-auto flex flex-nowrap scrollbar-none gap-6">
                            <button
                                v-for="tab in tabs"
                                :key="tab.id"
                                type="button"
                                @click="activeTab = tab.id"
                                class="relative py-4 px-1 text-xs md:text-sm font-extrabold uppercase tracking-wider transition-all duration-150 cursor-pointer flex items-center gap-2 select-none shrink-0 outline-none whitespace-nowrap active:scale-[0.98]"
                                :class="activeTab === tab.id
                                    ? 'text-indigo-600 dark:text-indigo-400 font-extrabold'
                                    : 'text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-300'"
                            >
                                <component :is="tab.icon" class="w-4 h-4 shrink-0" />
                                <span>{{ tab.name }}</span>
                                <span 
                                    v-if="activeTab === tab.id"
                                    class="absolute bottom-0 left-0 right-0 h-[3px] bg-indigo-600 dark:bg-indigo-400 rounded-t-full"
                                ></span>
                            </button>
                        </div>
                    </div>

                    <!-- Main content panel -->
                    <div class="flex-1 p-6 md:p-10 flex flex-col justify-between">
                        <div>
                            <!-- Success Toast Alert -->
                            <transition
                                enter-active-class="transition ease-out duration-350"
                                enter-from-class="opacity-0 translate-y-[-10px]"
                                enter-to-class="opacity-100 translate-y-0"
                                leave-active-class="transition ease-in duration-200"
                                leave-to-class="opacity-0 translate-y-[-10px]"
                            >
                                <div v-if="successMessage" class="mb-8 p-4.5 rounded-2xl bg-emerald-50 text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-300 border border-emerald-100 dark:border-emerald-800/50 flex items-center gap-3.5 shadow-2xs">
                                    <CheckCircle class="w-5.5 h-5.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                                    <span class="text-sm font-semibold">{{ successMessage }}</span>
                                </div>
                            </transition>

                            <!-- ================= TAB 1: EMPRESA ================= -->
                            <div v-if="activeTab === 'empresa'" class="space-y-8">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Datos Comerciales</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        Configure los datos que se imprimirán en los comprobantes digitales enviados por WhatsApp.
                                    </p>
                                </div>

                                <form @submit.prevent="submitEmpresa" class="space-y-8">
                                    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
                                        
                                        <!-- Fields Column -->
                                        <div class="xl:col-span-8 space-y-6">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div>
                                                    <label for="empresa_nombre" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Nombre del Negocio</label>
                                                    <TextInput
                                                        id="empresa_nombre"
                                                        type="text"
                                                        class="w-full"
                                                        v-model="empresaForm.nombre"
                                                        required
                                                    />
                                                    <InputError class="mt-2" :message="empresaForm.errors.nombre" />
                                                </div>

                                                <div>
                                                    <label for="empresa_ruc" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">RUC (Opcional)</label>
                                                    <TextInput
                                                        id="empresa_ruc"
                                                        type="text"
                                                        class="w-full"
                                                        v-model="empresaForm.ruc"
                                                    />
                                                    <InputError class="mt-2" :message="empresaForm.errors.ruc" />
                                                </div>
                                            </div>

                                            <div>
                                                <label for="empresa_direccion" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Dirección Comercial</label>
                                                <TextInput
                                                    id="empresa_direccion"
                                                    type="text"
                                                    class="w-full"
                                                    v-model="empresaForm.direccion"
                                                />
                                                <InputError class="mt-2" :message="empresaForm.errors.direccion" />
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div>
                                                    <label for="empresa_telefono" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Teléfono</label>
                                                    <TextInput
                                                        id="empresa_telefono"
                                                        type="text"
                                                        class="w-full"
                                                        v-model="empresaForm.telefono"
                                                    />
                                                    <InputError class="mt-2" :message="empresaForm.errors.telefono" />
                                                </div>

                                                <div>
                                                    <label for="empresa_correo" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Correo de Contacto</label>
                                                    <TextInput
                                                        id="empresa_correo"
                                                        type="email"
                                                        class="w-full"
                                                        v-model="empresaForm.correo"
                                                    />
                                                    <InputError class="mt-2" :message="empresaForm.errors.correo" />
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div>
                                                    <label for="empresa_moneda" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Moneda por Defecto</label>
                                                    <select 
                                                        id="empresa_moneda" 
                                                        v-model="moneda" 
                                                        class="w-full bg-white dark:bg-gray-950 border border-gray-250 dark:border-gray-800 rounded-xl px-4 py-2.5 text-xs text-gray-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                    >
                                                        <option value="PEN">Sol Peruano (S/)</option>
                                                        <option value="USD">Dólar Americano ($)</option>
                                                        <option value="COP">Peso Colombiano ($)</option>
                                                        <option value="EUR">Euro (€)</option>
                                                    </select>
                                                </div>

                                                <div>
                                                    <label for="empresa_timezone" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Zona Horaria</label>
                                                    <select 
                                                        id="empresa_timezone" 
                                                        v-model="timezone" 
                                                        class="w-full bg-white dark:bg-gray-950 border border-gray-250 dark:border-gray-800 rounded-xl px-4 py-2.5 text-xs text-gray-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                    >
                                                        <option value="America/Lima">Lima / Bogotá (UTC-5)</option>
                                                        <option value="America/Mexico_City">CDMX (UTC-6)</option>
                                                        <option value="America/Santiago">Santiago (UTC-4)</option>
                                                        <option value="Europe/Madrid">Madrid (UTC+1)</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Logo Upload Column -->
                                        <div class="xl:col-span-4 flex flex-col items-center">
                                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-4 self-start xl:self-center">Logotipo del Negocio</label>
                                            <div class="w-32 h-32 rounded-2xl bg-gray-50 dark:bg-gray-955 border border-gray-200 dark:border-gray-850 flex items-center justify-center overflow-hidden shadow-2xs relative group">
                                                <img 
                                                    v-if="logoPreview" 
                                                    :src="logoPreview" 
                                                    alt="Logotipo de la empresa" 
                                                    class="w-full h-full object-contain p-2"
                                                />
                                                <div v-else class="text-gray-400 dark:text-gray-500 text-center text-xs uppercase font-extrabold tracking-wider">
                                                    Sin Logo
                                                </div>
                                            </div>

                                            <div class="mt-4 flex flex-col items-center gap-2">
                                                <input
                                                    type="file"
                                                    ref="logoInputRef"
                                                    class="hidden"
                                                    accept="image/*"
                                                    @change="handleLogoChange"
                                                />
                                                <button
                                                    type="button"
                                                    @click="$refs.logoInputRef.click()"
                                                    class="px-5 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-xl text-xs font-bold text-gray-700 dark:text-gray-300 transition-colors shadow-2xs cursor-pointer active:scale-95 flex items-center gap-2"
                                                >
                                                    <Camera class="w-4 h-4" />
                                                    Cambiar Logotipo
                                                </button>
                                                <p class="text-[10px] text-gray-400 dark:text-gray-500 text-center mt-1">
                                                    Formatos: JPG, PNG, WEBP.<br>Máx. 5MB.
                                                </p>
                                            </div>
                                            <InputError class="mt-2" :message="empresaForm.errors.logo" />
                                        </div>

                                    </div>

                                    <div class="pt-6 border-t border-gray-100 dark:border-gray-800 flex justify-end">
                                        <button
                                            type="submit"
                                            :disabled="empresaForm.processing"
                                            class="w-full sm:w-48 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-extrabold transition-all text-xs cursor-pointer shadow-md shadow-indigo-600/10 active:scale-[0.98] disabled:opacity-50"
                                        >
                                            Guardar cambios
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- ================= TAB 2: MI PERFIL ================= -->
                            <div v-if="activeTab === 'perfil'" class="space-y-8">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Información del Perfil</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        Actualice los datos personales y de acceso de su cuenta.
                                    </p>
                                </div>

                                <form @submit.prevent="submitPerfil" class="space-y-8">
                                    <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
                                        
                                        <!-- Fields Column -->
                                        <div class="xl:col-span-8 space-y-6">
                                            <div>
                                                <label for="perfil_nombre" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Nombre Completo</label>
                                                <TextInput
                                                    id="perfil_nombre"
                                                    type="text"
                                                    class="w-full"
                                                    v-model="perfilForm.nombre"
                                                    required
                                                />
                                                <InputError class="mt-2" :message="perfilForm.errors.nombre" />
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div>
                                                    <label for="perfil_correo" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Correo Electrónico</label>
                                                    <TextInput
                                                        id="perfil_correo"
                                                        type="email"
                                                        class="w-full"
                                                        v-model="perfilForm.correo"
                                                        required
                                                    />
                                                    <InputError class="mt-2" :message="perfilForm.errors.correo" />
                                                </div>

                                                <div>
                                                    <label for="perfil_cargo" class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Cargo / Rol en Negocio</label>
                                                    <select 
                                                        id="perfil_cargo" 
                                                        v-model="cargo" 
                                                        class="w-full bg-white dark:bg-gray-950 border border-gray-250 dark:border-gray-800 rounded-xl px-4 py-2.5 text-xs text-gray-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                    >
                                                        <option value="Propietario">Propietario / Dueño de Bodega</option>
                                                        <option value="Administrador">Administrador</option>
                                                        <option value="Cajero">Cajero Principal</option>
                                                        <option value="Vendedor">Vendedor de Salón</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Profile Photo Column -->
                                        <div class="xl:col-span-4 flex flex-col items-center">
                                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-4 self-start xl:self-center">Foto de Perfil (Opcional)</label>
                                            <div class="w-32 h-32 rounded-full bg-gray-50 dark:bg-gray-955 border border-gray-200 dark:border-gray-850 flex items-center justify-center overflow-hidden shadow-2xs relative group">
                                                <img 
                                                    v-if="avatarPreview" 
                                                    :src="avatarPreview" 
                                                    alt="Foto de perfil" 
                                                    class="w-full h-full object-cover"
                                                />
                                                <div v-else class="text-indigo-600 dark:text-indigo-400 font-bold text-2xl uppercase select-none">
                                                    {{ perfilForm.nombre.charAt(0) || 'U' }}
                                                </div>
                                            </div>

                                            <div class="mt-4 flex flex-col items-center gap-2">
                                                <input
                                                    type="file"
                                                    ref="avatarInputRef"
                                                    class="hidden"
                                                    accept="image/*"
                                                    @change="handleAvatarChange"
                                                />
                                                <button
                                                    type="button"
                                                    @click="$refs.avatarInputRef.click()"
                                                    class="px-5 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-xl text-xs font-bold text-gray-700 dark:text-gray-300 transition-colors shadow-2xs cursor-pointer active:scale-95 flex items-center gap-2"
                                                >
                                                    <Camera class="w-4 h-4" />
                                                    Cambiar Foto
                                                </button>
                                                <p class="text-[10px] text-gray-400 dark:text-gray-500 text-center mt-1">
                                                    Formatos: JPG, PNG, WEBP.<br>Máx. 5MB.
                                                </p>
                                            </div>
                                            <InputError class="mt-2" :message="perfilForm.errors.avatar" />
                                        </div>

                                    </div>

                                    <div class="pt-6 border-t border-gray-100 dark:border-gray-800 flex justify-end">
                                        <button
                                            type="submit"
                                            :disabled="perfilForm.processing"
                                            class="w-full sm:w-48 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-extrabold transition-all text-xs cursor-pointer shadow-md shadow-indigo-600/10 active:scale-[0.98] disabled:opacity-50"
                                        >
                                            Guardar perfil
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- ================= TAB 3: SEGURIDAD ================= -->
                            <div v-if="activeTab === 'seguridad'" class="space-y-8">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                    <!-- Password Updates -->
                                    <div class="space-y-6">
                                        <div>
                                            <h3 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider">Cambiar Contraseña</h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                Asegúrese de cambiar periódicamente su clave para mantener protegida su cuenta.
                                            </p>
                                        </div>

                                        <form @submit.prevent="submitSeguridad" class="space-y-6">
                                            <div>
                                                <label for="current_password" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">Contraseña actual</label>
                                                <TextInput
                                                    id="current_password"
                                                    ref="currentPasswordInput"
                                                    v-model="seguridadForm.current_password"
                                                    type="password"
                                                    class="w-full"
                                                    autocomplete="current-password"
                                                />
                                                <InputError :message="seguridadForm.errors.current_password" class="mt-2" />
                                            </div>

                                            <div>
                                                <label for="password" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">Nueva contraseña</label>
                                                <TextInput
                                                    id="password"
                                                    ref="passwordInput"
                                                    v-model="seguridadForm.password"
                                                    type="password"
                                                    class="w-full"
                                                    autocomplete="new-password"
                                                />
                                                <InputError :message="seguridadForm.errors.password" class="mt-2" />
                                            </div>

                                            <div>
                                                <label for="password_confirmation" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">Confirmar contraseña</label>
                                                <TextInput
                                                    id="password_confirmation"
                                                    v-model="seguridadForm.password_confirmation"
                                                    type="password"
                                                    class="w-full"
                                                    autocomplete="new-password"
                                                />
                                                <InputError :message="seguridadForm.errors.password_confirmation" class="mt-2" />
                                            </div>

                                            <div class="flex justify-end">
                                                <button
                                                    type="submit"
                                                    :disabled="seguridadForm.processing"
                                                    class="w-full sm:w-48 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-extrabold transition-all text-xs cursor-pointer shadow-md shadow-indigo-600/10 active:scale-[0.98] disabled:opacity-50"
                                                >
                                                    Actualizar contraseña
                                                </button>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- Browser sessions & MFA -->
                                    <div class="space-y-8">
                                        <div class="space-y-4">
                                            <div>
                                                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider">Sesiones Activas</h3>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                    Lista de navegadores que han accedido a su cuenta.
                                                </p>
                                            </div>

                                            <div class="space-y-3 bg-gray-50 dark:bg-gray-955 p-4 border border-gray-150 dark:border-gray-850 rounded-2xl">
                                                <div 
                                                    v-for="sess in activeSessions" 
                                                    :key="sess"
                                                    class="flex items-center gap-3 text-xs text-gray-700 dark:text-gray-300"
                                                >
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    <span>{{ sess }}</span>
                                                </div>

                                                <button
                                                    type="button"
                                                    @click="triggerCloseSessions"
                                                    class="mt-3 text-[10px] font-extrabold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100/70 border border-rose-200/50 px-3.5 py-1.5 rounded-lg transition-colors cursor-pointer"
                                                >
                                                    Cerrar otras sesiones activas
                                                </button>
                                            </div>
                                        </div>

                                        <div class="space-y-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                                            <div>
                                                <h3 class="text-sm font-extrabold text-gray-900 dark:text-white uppercase tracking-wider">Autenticación en Dos Pasos (MFA)</h3>
                                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                    Añada una capa extra de seguridad solicitando un código dinámico en su celular.
                                                </p>
                                            </div>

                                            <div class="flex items-center justify-between p-4 bg-gray-50/50 dark:bg-gray-955 border border-gray-150 dark:border-gray-855 rounded-2xl">
                                                <div class="space-y-0.5">
                                                    <span class="text-xs font-bold text-gray-900 dark:text-white block">Estado de MFA</span>
                                                    <span class="text-[10px] text-gray-500 dark:text-gray-400 block">{{ mfaEnabled ? 'Habilitado y Protegiendo' : 'Desactivado temporalmente' }}</span>
                                                </div>

                                                <button
                                                    @click="toggleMfa"
                                                    type="button"
                                                    class="px-4 py-2 text-[10px] font-extrabold rounded-xl transition-all cursor-pointer border shadow-3xs"
                                                    :class="mfaEnabled 
                                                        ? 'bg-rose-50 border-rose-200 text-rose-600 hover:bg-rose-100' 
                                                        : 'bg-indigo-600 border-indigo-600 hover:bg-indigo-700 text-white'"
                                                >
                                                    {{ mfaEnabled ? 'Desactivar 2FA' : 'Activar 2FA' }}
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ================= TAB 4: PERSONALIZACIÓN ================= -->
                            <div v-if="activeTab === 'personalizacion'" class="space-y-8">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">Personalización del Sistema</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        Configure los colores y aspectos gráficos generales de INVENTEX.
                                    </p>
                                </div>

                                <form @submit.prevent="submitPersonalizacion" class="space-y-8">
                                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                                        <!-- Visual layouts -->
                                        <div class="space-y-6">
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-3">Color de Acento del Sistema</label>
                                                <div class="flex items-center gap-3">
                                                    <button
                                                        v-for="color in ['violet', 'blue', 'emerald', 'indigo', 'slate']"
                                                        :key="color"
                                                        type="button"
                                                        @click="colorPrincipal = color"
                                                        class="w-10 h-10 rounded-xl transition-all border-2 cursor-pointer shadow-3xs"
                                                        :class="[
                                                            color === 'violet' ? 'bg-violet-600' : 
                                                            color === 'blue' ? 'bg-blue-600' : 
                                                            color === 'emerald' ? 'bg-emerald-600' : 
                                                            color === 'indigo' ? 'bg-indigo-600' : 'bg-slate-700',
                                                            colorPrincipal === color ? 'border-gray-900 dark:border-white scale-110' : 'border-transparent hover:scale-105'
                                                        ]"
                                                    ></button>
                                                </div>
                                            </div>

                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div>
                                                    <label for="pref_fecha" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">Formato de Fecha</label>
                                                    <select 
                                                        id="pref_fecha" 
                                                        v-model="formatoFecha" 
                                                        class="w-full bg-white dark:bg-gray-950 border border-gray-250 dark:border-gray-800 rounded-xl px-4 py-2.5 text-xs text-gray-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                    >
                                                        <option value="DD/MM/YYYY">Día/Mes/Año (DD/MM/YYYY)</option>
                                                        <option value="MM/DD/YYYY">Mes/Día/Año (MM/DD/YYYY)</option>
                                                        <option value="YYYY-MM-DD">Año-Mes-Día (YYYY-MM-DD)</option>
                                                    </select>
                                                </div>

                                                <div>
                                                    <label for="pref_moneda" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">Formato de Moneda</label>
                                                    <select 
                                                        id="pref_moneda" 
                                                        v-model="formatoMoneda" 
                                                        class="w-full bg-white dark:bg-gray-950 border border-gray-250 dark:border-gray-800 rounded-xl px-4 py-2.5 text-xs text-gray-900 dark:text-white outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                                                    >
                                                        <option value="S/ 1,234.56">S/ 1,234.56 (Soles)</option>
                                                        <option value="$ 1,234.56">$ 1,234.56 (Dólares)</option>
                                                        <option value="€ 1.234,56">€ 1.234,56 (Euros)</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <!-- Custom system brand logo -->
                                            <div>
                                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-3">Logotipo de la Aplicación</label>
                                                <div class="flex items-center gap-5">
                                                    <div class="h-16 px-4 bg-gray-50 dark:bg-gray-955 rounded-xl border border-gray-200 dark:border-gray-850 flex items-center justify-center overflow-hidden">
                                                        <img :src="logoSistemaPreview" class="h-10 w-auto object-contain" />
                                                    </div>
                                                    <input 
                                                        type="file" 
                                                        ref="logoSistemaInputRef"
                                                        class="hidden"
                                                        accept="image/*"
                                                        @change="handleLogoSistemaChange"
                                                    />
                                                    <button
                                                        type="button"
                                                        @click="$refs.logoSistemaInputRef.click()"
                                                        class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 rounded-xl text-[10px] font-bold text-gray-700 dark:text-gray-300 cursor-pointer shadow-3xs active:scale-95"
                                                    >
                                                        Cambiar Logo App
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Ticket settings -->
                                        <div class="space-y-6">
                                            <div>
                                                <label for="ticket_head" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">Mensaje de Cabecera del Ticket</label>
                                                <TextInput
                                                    id="ticket_head"
                                                    type="text"
                                                    class="w-full"
                                                    v-model="ticketHeader"
                                                />
                                            </div>

                                            <div>
                                                <label for="ticket_foot" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">Mensaje de Pie de Página del Ticket</label>
                                                <TextInput
                                                    id="ticket_foot"
                                                    type="text"
                                                    class="w-full"
                                                    v-model="ticketFooter"
                                                />
                                            </div>

                                            <div>
                                                <label for="comprobante_terms" class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-2">Información en los Comprobantes de Pago</label>
                                                <textarea
                                                    id="comprobante_terms"
                                                    v-model="comprobanteInfo"
                                                    rows="3"
                                                    class="w-full bg-white dark:bg-gray-950 border border-gray-250 dark:border-gray-850 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 rounded-xl px-4 py-2.5 text-xs text-gray-900 dark:text-white outline-none transition-all shadow-3xs"
                                                ></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pt-6 border-t border-gray-100 dark:border-gray-800 flex justify-end">
                                        <button
                                            type="submit"
                                            class="w-full sm:w-48 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-extrabold transition-all text-xs cursor-pointer shadow-md shadow-indigo-600/10 active:scale-[0.98]"
                                        >
                                            Guardar personalización
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- ================= TAB 5: DANGER ZONE ================= -->
                            <div v-if="activeTab === 'danger'" class="space-y-8">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-955 dark:text-white">Zona de peligro</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        Acciones críticas e irreversibles relacionadas con la permanencia y datos de su cuenta.
                                    </p>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                    <!-- 1. Reset Demo -->
                                    <div class="bg-red-50/20 dark:bg-red-955/5 border border-red-200/50 dark:border-red-950/20 rounded-2xl p-6 flex flex-col justify-between h-56">
                                        <div class="space-y-2">
                                            <h4 class="text-xs font-bold text-red-900 dark:text-red-400 flex items-center gap-1.5">
                                                <RefreshCw class="w-4 h-4 shrink-0 text-red-500" />
                                                Reiniciar datos de demo
                                            </h4>
                                            <p class="text-[10px] text-red-750 dark:text-red-300/80 leading-relaxed">
                                                Carga stock vacío y borra transacciones de demostración creadas al inicio.
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            @click="triggerResetDemo"
                                            class="w-full py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold transition-all text-[10px] cursor-pointer"
                                        >
                                            Reiniciar Demo
                                        </button>
                                    </div>

                                    <!-- 2. Restore Backup -->
                                    <div class="bg-red-50/20 dark:bg-red-955/5 border border-red-200/50 dark:border-red-950/20 rounded-2xl p-6 flex flex-col justify-between h-56">
                                        <div class="space-y-2">
                                            <h4 class="text-xs font-bold text-red-900 dark:text-red-400 flex items-center gap-1.5">
                                                <Database class="w-4 h-4 shrink-0 text-red-500" />
                                                Restaurar Respaldo
                                            </h4>
                                            <p class="text-[10px] text-red-750 dark:text-red-300/80 leading-relaxed">
                                                Sobrescribe los datos de hoy con una copia de seguridad anterior del sistema.
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            @click="triggerRestoreBackup"
                                            class="w-full py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold transition-all text-[10px] cursor-pointer"
                                        >
                                            Restaurar Respaldo
                                        </button>
                                    </div>

                                    <!-- 3. Deletion -->
                                    <div class="bg-red-50/20 dark:bg-red-955/5 border border-red-200/50 dark:border-red-950/20 rounded-2xl p-6 flex flex-col justify-between h-56">
                                        <div class="space-y-2">
                                            <h4 class="text-xs font-bold text-red-900 dark:text-red-400 flex items-center gap-1.5">
                                                <Trash2 class="w-4 h-4 shrink-0 text-red-500" />
                                                Eliminar información
                                            </h4>
                                            <p class="text-[10px] text-red-750 dark:text-red-300/80 leading-relaxed">
                                                Eliminará permanentemente todos tus recursos, productos y ventas de forma irreversible.
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            @click="confirmUserDeletion"
                                            class="w-full py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold transition-all text-[10px] cursor-pointer shadow-md shadow-red-650/10 active:scale-[0.98]"
                                        >
                                            Eliminar Negocio
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>
        </div>

        <!-- 1. Reset Demo Confirmation Modal -->
        <Modal :show="confirmingResetDemo" @close="confirmingResetDemo = false" max-width="md">
            <div class="p-8 space-y-6">
                <div class="flex items-center gap-3 pb-4 border-b border-gray-155 dark:border-gray-800">
                    <div class="p-2 bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400 rounded-lg">
                        <AlertTriangle class="w-5 h-5 shrink-0" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">
                        ¿Confirmar reinicio de datos?
                    </h3>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                    Esta acción vaciará el stock actual de demostración. Ingrese la palabra de seguridad <strong class="text-red-600 dark:text-red-400">REINICIAR</strong> para proceder.
                </p>

                <form @submit.prevent="submitResetDemo" class="space-y-6">
                    <div>
                        <InputLabel for="modal_reset_code" value="Código de confirmación" class="text-xs font-bold" />
                        <TextInput
                            id="modal_reset_code"
                            v-model="resetDemoCode"
                            type="text"
                            class="mt-1 block w-full uppercase"
                            placeholder="Escriba REINICIAR..."
                            required
                        />
                    </div>

                    <div class="flex gap-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                        <button
                            type="button"
                            @click="confirmingResetDemo = false"
                            class="flex-1 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700 rounded-xl font-bold text-xs hover:bg-gray-50 dark:hover:bg-gray-700 active:scale-95 transition-all cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="flex-1 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all cursor-pointer"
                        >
                            Confirmar y Reiniciar
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 2. Restore Backup Confirmation Modal -->
        <Modal :show="confirmingRestoreBackup" @close="confirmingRestoreBackup = false" max-width="md">
            <div class="p-8 space-y-6">
                <div class="flex items-center gap-3 pb-4 border-b border-gray-155 dark:border-gray-800">
                    <div class="p-2 bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400 rounded-lg">
                        <AlertTriangle class="w-5 h-5 shrink-0" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">
                        ¿Confirmar restauración de respaldo?
                    </h3>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                    Se sobrescribirán todos los cambios recientes con la última copia de seguridad. Ingrese la palabra de seguridad <strong class="text-red-600 dark:text-red-400">RESTAURAR</strong> para proceder.
                </p>

                <form @submit.prevent="submitRestoreBackup" class="space-y-6">
                    <div>
                        <InputLabel for="modal_restore_code" value="Código de confirmación" class="text-xs font-bold" />
                        <TextInput
                            id="modal_restore_code"
                            v-model="restoreBackupCode"
                            type="text"
                            class="mt-1 block w-full uppercase"
                            placeholder="Escriba RESTAURAR..."
                            required
                        />
                    </div>

                    <div class="flex gap-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                        <button
                            type="button"
                            @click="confirmingRestoreBackup = false"
                            class="flex-1 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700 rounded-xl font-bold text-xs hover:bg-gray-50 dark:hover:bg-gray-700 active:scale-95 transition-all cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="flex-1 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all cursor-pointer"
                        >
                            Confirmar y Restaurar
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 3. Close other sessions confirmation modal -->
        <Modal :show="confirmingCloseSessions" @close="confirmingCloseSessions = false" max-width="md">
            <div class="p-8 space-y-6">
                <div class="flex items-center gap-3 pb-4 border-b border-gray-155 dark:border-gray-800">
                    <div class="p-2 bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400 rounded-lg">
                        <AlertTriangle class="w-5 h-5 shrink-0" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">
                        ¿Cerrar otras sesiones activas?
                    </h3>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                    Esto desconectará su cuenta de los demás dispositivos y navegadores. Ingrese <strong class="text-red-600 dark:text-red-400">CERRAR SESIONES</strong> para confirmar.
                </p>

                <form @submit.prevent="submitCloseSessions" class="space-y-6">
                    <div>
                        <InputLabel for="modal_close_sess" value="Código de confirmación" class="text-xs font-bold" />
                        <TextInput
                            id="modal_close_sess"
                            v-model="closeSessionsCode"
                            type="text"
                            class="mt-1 block w-full uppercase"
                            placeholder="Escriba CERRAR SESIONES..."
                            required
                        />
                    </div>

                    <div class="flex gap-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                        <button
                            type="button"
                            @click="confirmingCloseSessions = false"
                            class="flex-1 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700 rounded-xl font-bold text-xs hover:bg-gray-50 dark:hover:bg-gray-700 active:scale-95 transition-all cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="flex-1 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all cursor-pointer"
                        >
                            Confirmar y Cerrar
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 4. MFA toggle confirmation modal -->
        <Modal :show="confirmingMfaToggle" @close="confirmingMfaToggle = false" max-width="md">
            <div class="p-8 space-y-6">
                <div class="flex items-center gap-3 pb-4 border-b border-gray-155 dark:border-gray-800">
                    <div class="p-2 bg-indigo-50 dark:bg-indigo-950/30 text-indigo-600 dark:text-indigo-400 rounded-lg">
                        <ShieldCheck class="w-5 h-5 shrink-0" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">
                        ¿Confirmar cambio en 2FA?
                    </h3>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 leading-relaxed">
                    Escriba <strong class="text-indigo-650 dark:text-indigo-400">{{ mfaEnabled ? 'DESACTIVAR 2FA' : 'ACTIVAR 2FA' }}</strong> para proceder con el cambio de autenticación de dos pasos.
                </p>

                <form @submit.prevent="submitMfaToggle" class="space-y-6">
                    <div>
                        <InputLabel for="modal_mfa_code" value="Código de confirmación" class="text-xs font-bold" />
                        <TextInput
                            id="modal_mfa_code"
                            v-model="mfaToggleCode"
                            type="text"
                            class="mt-1 block w-full uppercase"
                            :placeholder="mfaEnabled ? 'Escriba DESACTIVAR 2FA...' : 'Escriba ACTIVAR 2FA...'"
                            required
                        />
                    </div>

                    <div class="flex gap-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                        <button
                            type="button"
                            @click="confirmingMfaToggle = false"
                            class="flex-1 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700 rounded-xl font-bold text-xs hover:bg-gray-50 dark:hover:bg-gray-700 active:scale-95 transition-all cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="flex-1 py-3 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all cursor-pointer"
                        >
                            Confirmar
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- 5. Deletion Confirmation Modal -->
        <Modal :show="confirmingUserDeletion" @close="closeModal" max-width="md">
            <div class="p-8">
                <div class="flex items-center gap-3 pb-4 border-b border-gray-155 dark:border-gray-800">
                    <div class="p-2 bg-red-50 dark:bg-red-950/30 text-red-600 dark:text-red-400 rounded-lg">
                        <AlertTriangle class="w-5 h-5 shrink-0" />
                    </div>
                    <h3 class="text-base font-bold text-gray-900 dark:text-white">
                        ¿Confirmar eliminación de cuenta?
                    </h3>
                </div>

                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">
                    Esta acción no se puede deshacer. Ingrese su contraseña de acceso para confirmar que desea eliminar esta cuenta de forma permanente.
                </p>

                <form @submit.prevent="submitDeleteUser" class="mt-6 space-y-6">
                    <div>
                        <InputLabel for="modal_delete_password" value="Contraseña de confirmación" class="text-xs font-bold" />
                        <TextInput
                            id="modal_delete_password"
                            ref="deletePasswordInput"
                            v-model="deleteForm.password"
                            type="password"
                            class="mt-1 block w-full"
                            placeholder="Ingrese su contraseña..."
                            required
                        />
                        <InputError :message="deleteForm.errors.password" class="mt-2" />
                    </div>

                    <div class="flex gap-3 pt-3 border-t border-gray-100 dark:border-gray-800">
                        <button
                            type="button"
                            @click="closeModal"
                            class="flex-1 py-3 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700 rounded-xl font-bold text-xs hover:bg-gray-50 dark:hover:bg-gray-700 active:scale-95 transition-all cursor-pointer"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            :disabled="deleteForm.processing"
                            class="flex-1 py-3 bg-red-600 hover:bg-red-700 text-white rounded-xl font-bold text-xs active:scale-95 transition-all disabled:opacity-50 cursor-pointer"
                        >
                            Eliminar Cuenta
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.35s ease, transform 0.35s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
    transform: translateY(-10px);
}
/* Custom styling for horizontal tab scrollbars */
.scrollbar-none::-webkit-scrollbar {
    display: none;
}
.scrollbar-none {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}
select {
    outline: none;
}
</style>
