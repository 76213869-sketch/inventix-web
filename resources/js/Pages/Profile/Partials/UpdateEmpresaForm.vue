<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
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
});

const form = useForm({
    nombre: props.empresa?.nombre || '',
    ruc: props.empresa?.ruc || '',
    direccion: props.empresa?.direccion || '',
    telefono: props.empresa?.telefono || '',
    correo: props.empresa?.correo || '',
    logo: null,
});

const logoPreview = ref(props.logoUrl);

const handleLogoChange = (e) => {
    const file = e.target.files[0];
    if (file) {
        form.logo = file;
        logoPreview.value = URL.createObjectURL(file);
    }
};

const submit = () => {
    // We send via POST (multipart/form-data) because PUT/PATCH file upload is not supported out of the box by PHP
    form.post(route('profile.updateEmpresa'), {
        preserveScroll: true,
        onSuccess: () => {
            // Optional: reset file field
            form.logo = null;
        },
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Configuración de la Empresa
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Actualice los datos comerciales y el logotipo de su negocio para los comprobantes digitales.
            </p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Nombre de la Empresa -->
                <div>
                    <InputLabel for="empresa_nombre" value="Nombre de la Empresa / Bodega" />
                    <TextInput
                        id="empresa_nombre"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.nombre"
                        required
                        autocomplete="organization"
                    />
                    <InputError class="mt-2" :message="form.errors.nombre" />
                </div>

                <!-- RUC -->
                <div>
                    <InputLabel for="empresa_ruc" value="RUC (Opcional)" />
                    <TextInput
                        id="empresa_ruc"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.ruc"
                        autocomplete="off"
                    />
                    <InputError class="mt-2" :message="form.errors.ruc" />
                </div>

                <!-- Dirección -->
                <div class="md:col-span-2">
                    <InputLabel for="empresa_direccion" value="Dirección Comercial" />
                    <TextInput
                        id="empresa_direccion"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.direccion"
                    />
                    <InputError class="mt-2" :message="form.errors.direccion" />
                </div>

                <!-- Teléfono -->
                <div>
                    <InputLabel for="empresa_telefono" value="Teléfono de Contacto" />
                    <TextInput
                        id="empresa_telefono"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.telefono"
                        autocomplete="tel"
                    />
                    <InputError class="mt-2" :message="form.errors.telefono" />
                </div>

                <!-- Correo Electrónico -->
                <div>
                    <InputLabel for="empresa_correo" value="Correo Electrónico" />
                    <TextInput
                        id="empresa_correo"
                        type="email"
                        class="mt-1 block w-full"
                        v-model="form.correo"
                        autocomplete="email"
                    />
                    <InputError class="mt-2" :message="form.errors.correo" />
                </div>
            </div>

            <!-- Logotipo de la Empresa -->
            <div class="border-t border-gray-100 dark:border-gray-700 pt-6">
                <InputLabel value="Logotipo del Negocio" />
                
                <div class="mt-2 flex items-center gap-6">
                    <!-- Preview Container -->
                    <div class="w-24 h-24 rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 flex items-center justify-center overflow-hidden shrink-0">
                        <img 
                            v-if="logoPreview" 
                            :src="logoPreview" 
                            alt="Logo preview" 
                            class="w-full h-full object-contain p-1"
                        />
                        <div v-else class="text-gray-400 dark:text-gray-500 text-center text-[10px] uppercase font-semibold">
                            Sin Logo
                        </div>
                    </div>

                    <!-- Input / Upload Controls -->
                    <div class="space-y-1">
                        <input
                            type="file"
                            id="empresa_logo"
                            ref="logoInput"
                            class="hidden"
                            accept="image/jpeg,image/png,image/jpg,image/webp"
                            @change="handleLogoChange"
                        />
                        <button
                            type="button"
                            @click="$refs.logoInput.click()"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-semibold text-gray-700 dark:text-gray-300 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none transition-colors"
                        >
                            Seleccionar Logotipo
                        </button>
                        <p class="text-[10px] text-gray-400 dark:text-gray-500">
                            Formatos soportados: JPG, PNG, WEBP. Máximo 2MB.
                        </p>
                    </div>
                </div>
                <InputError class="mt-2" :message="form.errors.logo" />
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Guardar Cambios</PrimaryButton>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="text-sm text-gray-600 dark:text-gray-400"
                    >
                        Guardado.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>
