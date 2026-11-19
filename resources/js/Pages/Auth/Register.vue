<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    empresa_nombre: '',
    empresa_ruc: '',
    nombre: '',
    correo: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Registro de Empresa" />

        <div class="mb-6 text-center">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Únete a INVENTEX</h2>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Crea tu cuenta SaaS y gestiona tu negocio hoy mismo</p>
        </div>

        <form @submit.prevent="submit">
            <!-- Datos de la Empresa -->
            <div class="border-b border-gray-200 dark:border-gray-700 pb-4 mb-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Datos del Negocio</h3>
                
                <div>
                    <InputLabel for="empresa_nombre" value="Nombre del Negocio / Razón Social" />
                    <TextInput
                        id="empresa_nombre"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.empresa_nombre"
                        required
                        autofocus
                        placeholder="Ej. Minimarket Don Pepe"
                    />
                    <InputError class="mt-2" :message="form.errors.empresa_nombre" />
                </div>

                <div class="mt-4">
                    <InputLabel for="empresa_ruc" value="Número de RUC (Perú)" />
                    <TextInput
                        id="empresa_ruc"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.empresa_ruc"
                        required
                        maxlength="11"
                        placeholder="Ej. 20123456789"
                    />
                    <InputError class="mt-2" :message="form.errors.empresa_ruc" />
                </div>
            </div>

            <!-- Datos del Administrador -->
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-3">Datos del Administrador</h3>

                <div>
                    <InputLabel for="nombre" value="Nombre Completo" />
                    <TextInput
                        id="nombre"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.nombre"
                        required
                        placeholder="Ej. José Pérez"
                    />
                    <InputError class="mt-2" :message="form.errors.nombre" />
                </div>

                <div class="mt-4">
                    <InputLabel for="correo" value="Correo Electrónico" />
                    <TextInput
                        id="correo"
                        type="email"
                        class="mt-1 block w-full"
                        v-model="form.correo"
                        required
                        placeholder="ejemplo@correo.com"
                    />
                    <InputError class="mt-2" :message="form.errors.correo" />
                </div>

                <div class="mt-4">
                    <InputLabel for="password" value="Contraseña" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password" />
                </div>

                <div class="mt-4">
                    <InputLabel for="password_confirmation" value="Confirmar Contraseña" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                    />
                    <InputError class="mt-2" :message="form.errors.password_confirmation" />
                </div>
            </div>

            <div class="mt-6 flex items-center justify-between">
                <Link
                    :href="route('login')"
                    class="rounded-md text-sm text-gray-600 underline hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100 dark:focus:ring-offset-gray-800"
                >
                    ¿Ya tienes una cuenta?
                </Link>

                <PrimaryButton
                    class="ms-4"
                    :class="{ 'opacity-25': form.processing }"
                    :disabled="form.processing"
                >
                    Registrar Negocio
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>

