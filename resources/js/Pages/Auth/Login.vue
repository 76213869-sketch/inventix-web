<script setup>
import { ref, nextTick } from 'vue';
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    correo: '',
    password: '',
    remember: false,
});

const showPassword = ref(false);
const passwordInput = ref(null);

const localErrors = ref({
    correo: '',
    password: '',
});

const togglePasswordVisibility = () => {
    if (!passwordInput.value) return;

    // Save cursor position
    const start = passwordInput.value.selectionStart;
    const end = passwordInput.value.selectionEnd;

    showPassword.value = !showPassword.value;

    // Restore focus and cursor selection range on next tick
    nextTick(() => {
        passwordInput.value.focus();
        passwordInput.value.setSelectionRange(start, end);
    });
};

const validate = () => {
    let valid = true;
    localErrors.value.correo = '';
    localErrors.value.password = '';
    
    // Clear previous errors from Laravel form
    form.clearErrors();

    if (!form.correo) {
        localErrors.value.correo = 'El correo electrónico es requerido.';
        valid = false;
    } else {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(form.correo)) {
            localErrors.value.correo = 'El formato del correo electrónico no es válido.';
            valid = false;
        }
    }

    if (!form.password) {
        localErrors.value.password = 'La contraseña es requerida.';
        valid = false;
    }

    return valid;
};

const submit = () => {
    if (!validate()) return;

    form.post(route('login'), {
        onFinish: () => {
            // Keep email, only clear the password!
            form.password = '';
            // Autofocus on the password field after finishing
            nextTick(() => {
                passwordInput.value?.focus();
            });
        },
        onError: () => {
            // Focus on the password field on error
            nextTick(() => {
                passwordInput.value?.focus();
            });
        }
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Iniciar Sesión" />

        <div class="mb-6 text-center">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white tracking-tight">Ingresa a INVENTEX</h2>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 font-medium">Gestiona. Vende. Crece.</p>
        </div>

        <div v-if="status" class="mb-4 text-xs font-bold text-emerald-600 bg-emerald-50 dark:bg-emerald-950/20 p-3 rounded-xl border border-emerald-100 dark:border-emerald-900/30">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <!-- Correo -->
            <div>
                <InputLabel for="correo" value="Correo Electrónico" class="text-xs font-bold" />

                <input
                    id="correo"
                    type="email"
                    class="mt-1.5 block w-full rounded-xl border bg-white dark:bg-gray-900 text-gray-950 dark:text-white text-sm py-2.5 px-3 shadow-3xs transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500/25"
                    :class="localErrors.correo || form.errors.correo 
                        ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20' 
                        : 'border-gray-200 dark:border-gray-800 focus:border-blue-500'"
                    v-model="form.correo"
                    autofocus
                    autocomplete="username"
                    placeholder="ejemplo@correo.com"
                />

                <span v-if="localErrors.correo" class="text-[11px] text-red-600 font-bold mt-1.5 block animate-fade-in">
                    {{ localErrors.correo }}
                </span>
                <InputError class="mt-1.5" :message="form.errors.correo" />
            </div>

            <!-- Contraseña -->
            <div>
                <InputLabel for="password" value="Contraseña" class="text-xs font-bold" />

                <div class="relative mt-1.5">
                    <input
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        ref="passwordInput"
                        class="block w-full rounded-xl border bg-white dark:bg-gray-900 text-gray-950 dark:text-white text-sm py-2.5 px-3 pr-10 shadow-3xs transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500/25"
                        :class="localErrors.password || form.errors.password 
                            ? 'border-red-500 focus:border-red-500 focus:ring-red-500/20' 
                            : 'border-gray-200 dark:border-gray-800 focus:border-blue-500'"
                        v-model="form.password"
                        autocomplete="current-password"
                        placeholder="••••••••"
                    />
                    <button
                        type="button"
                        @click.prevent="togglePasswordVisibility"
                        class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none cursor-pointer"
                        tabindex="-1"
                    >
                        <EyeOff v-if="showPassword" class="w-4 h-4" />
                        <Eye v-else class="w-4 h-4" />
                    </button>
                </div>

                <span v-if="localErrors.password" class="text-[11px] text-red-600 font-bold mt-1.5 block animate-fade-in">
                    {{ localErrors.password }}
                </span>
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between pt-1">
                <!-- Recordar Sesión -->
                <label class="flex items-center select-none cursor-pointer">
                    <Checkbox name="remember" v-model:checked="form.remember" />
                    <span class="ms-2 text-xs text-gray-500 dark:text-gray-400 font-medium">Recordar mi sesión</span>
                </label>

                <!-- Recuperar Contraseña -->
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-xs text-gray-550 dark:text-gray-400 hover:text-blue-650 hover:underline font-bold transition-colors focus:outline-none"
                >
                    ¿Olvidaste tu contraseña?
                </Link>
            </div>

            <!-- Botón Ingresar -->
            <div class="pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full h-10 px-4 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition-all duration-150 active:scale-98 shadow-xs disabled:opacity-50 select-none cursor-pointer flex items-center justify-center"
                >
                    <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ form.processing ? 'Ingresando...' : 'Ingresar' }}</span>
                </button>
            </div>
        </form>
    </GuestLayout>
</template>

<style>
/* CSS Keyframes for smooth error fadeIn transitions */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
