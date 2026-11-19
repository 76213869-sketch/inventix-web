<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Dropdown from '@/Components/Dropdown.vue';

// Lucide icon helper
import { Package, Plus, Search, FolderTree, AlertCircle } from 'lucide-vue-next';

const props = defineProps({
    categorias: {
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
const isLoading = ref(true);

onMounted(() => {
    setTimeout(() => {
        isLoading.value = false;
    }, 200);
});

const form = useForm({
    id: null,
    nombre: '',
    descripcion: '',
    estado: 'activo',
});

const filteredCategorias = computed(() => {
    if (!searchQuery.value) return props.categorias;
    const query = searchQuery.value.toLowerCase();
    return props.categorias.filter(
        (cat) =>
            cat.nombre.toLowerCase().includes(query) ||
            (cat.descripcion && cat.descripcion.toLowerCase().includes(query))
    );
});

const openCreateModal = () => {
    form.clearErrors();
    form.reset({
        id: null,
        nombre: '',
        descripcion: '',
        estado: 'activo',
    });
    editMode.value = false;
    showModal.value = true;
};

const openEditModal = (categoria) => {
    form.clearErrors();
    form.reset({
        id: categoria.id,
        nombre: categoria.nombre,
        descripcion: categoria.descripcion || '',
        estado: categoria.estado,
    });
    editMode.value = true;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
};

const submit = () => {
    if (editMode.value) {
        form.put(route('categorias.update', form.id), {
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('categorias.store'), {
            onSuccess: () => closeModal(),
        });
    }
};

const deleteCategoria = (categoria) => {
    if (confirm(`¿Estás seguro de que deseas eliminar la categoría "${categoria.nombre}"?`)) {
        form.delete(route('categorias.destroy', categoria.id));
    }
};
</script>

<template>
    <Head title="Categorías" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 tracking-tight">Categorías de Productos</h2>
                    <p class="text-xs text-gray-500 mt-0.5 font-medium">Clasificación y grupos de existencias del inventario.</p>
                </div>
                <PrimaryButton v-if="categorias.length > 0" @click="openCreateModal">
                    <Plus class="w-4 h-4 me-1.5" />
                    Nueva Categoría
                </PrimaryButton>
            </div>
        </template>

        <div class="space-y-6">
            <!-- Toast Notifications / Alertas Modernas -->
            <div v-if="flashSuccess" class="rounded-xl bg-emerald-50 p-4 text-xs text-emerald-800 border border-emerald-100 flex items-start gap-3 shadow-2xs">
                <span class="p-1 bg-white rounded-lg border border-emerald-200 text-emerald-600">✓</span>
                <div>
                    <div class="font-bold">Acción Realizada Exitosamente</div>
                    <div class="opacity-95 mt-0.5">{{ flashSuccess }}</div>
                </div>
            </div>

            <div v-if="flashError" class="rounded-xl bg-red-50 p-4 text-xs text-red-800 border border-red-100 flex items-start gap-3 shadow-2xs">
                <AlertCircle class="w-4.5 h-4.5 text-red-600 mt-0.5 shrink-0" />
                <div>
                    <div class="font-bold">Error en la Operación</div>
                    <div class="opacity-95 mt-0.5">{{ flashError }}</div>
                </div>
            </div>

            <!-- SKELETON LOADING STATE -->
            <div v-if="isLoading" class="bg-white rounded-2xl border border-gray-200/80 p-6 space-y-6 shadow-2xs animate-pulse">
                <div class="h-10 bg-gray-100 rounded-xl w-1/4"></div>
                <div class="space-y-3">
                    <div v-for="i in 5" :key="i" class="h-14 bg-gray-50 rounded-xl border border-gray-100/50"></div>
                </div>
            </div>

            <!-- EMPTY STATE (No items in database) -->
            <div v-else-if="categorias.length === 0" class="bg-white rounded-2xl border border-gray-200/80 p-12 shadow-2xs flex flex-col items-center text-center max-w-lg mx-auto mt-8">
                <div class="w-14 h-14 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center mb-5">
                    <FolderTree class="w-7 h-7" />
                </div>
                <h3 class="text-sm font-bold text-gray-900">No existen categorías registradas</h3>
                <p class="text-xs text-gray-500 mt-1.5 mb-6 max-w-xs">Organiza tus productos clasificándolos en categorías para mejorar la gestión y los reportes.</p>
                <PrimaryButton @click="openCreateModal">
                    <Plus class="w-4 h-4 me-1.5" />
                    Crear primera categoría
                </PrimaryButton>
            </div>

            <!-- MAIN LIST / TABLE -->
            <div v-else class="bg-white rounded-2xl border border-gray-200/80 overflow-hidden shadow-2xs">
                <div class="p-6">
                    <!-- Search Header -->
                    <div class="mb-5 relative max-w-md w-full">
                        <span class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none text-gray-400">
                            <Search class="w-4 h-4" />
                        </span>
                        <input
                            v-model="searchQuery"
                            type="search"
                            class="block w-full ps-10 p-2.5 h-10 text-xs text-gray-900 border border-gray-200 rounded-xl bg-gray-50/50 hover:bg-white focus:bg-white focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all duration-150"
                            placeholder="Buscar por nombre o descripción..."
                        />
                    </div>

                    <!-- Table (Airtable Style) -->
                    <div class="relative overflow-x-auto rounded-xl border border-gray-100">
                        <table class="w-full text-xs text-left text-gray-500">
                            <thead class="text-[10px] text-gray-400 uppercase tracking-wider font-bold bg-gray-50/70 border-b border-gray-100">
                                <tr>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Nombre</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-gray-500">Descripción</th>
                                    <th scope="col" class="px-6 py-4 font-bold text-center text-gray-500">Estado</th>
                                    <th scope="col" class="px-6 py-4 text-right font-bold text-gray-500">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50 bg-white">
                                <tr 
                                    v-for="categoria in filteredCategorias" 
                                    :key="categoria.id"
                                    class="hover:bg-gray-50/60 transition-colors duration-150"
                                >
                                    <th scope="row" class="px-6 py-5 font-semibold text-gray-900 whitespace-nowrap">
                                        {{ categoria.nombre }}
                                    </th>
                                    <td class="px-6 py-5 text-gray-500">
                                        {{ categoria.descripcion || 'Sin descripción' }}
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <span 
                                            class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border"
                                            :class="categoria.estado === 'activo' 
                                                ? 'bg-emerald-50 text-emerald-700 border-emerald-100' 
                                                : 'bg-red-50 text-red-700 border-red-100'"
                                        >
                                            {{ categoria.estado === 'activo' ? 'Activo' : 'Inactivo' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-5 text-right whitespace-nowrap">
                                        <Dropdown align="right" width="48">
                                            <template #trigger>
                                                <button class="inline-flex items-center justify-center p-1.5 text-gray-400 rounded-lg hover:bg-gray-100 hover:text-gray-600 focus:outline-none transition-all duration-150 cursor-pointer">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                                                    </svg>
                                                </button>
                                            </template>
                                            <template #content>
                                                <button
                                                    @click="openEditModal(categoria)"
                                                    class="flex w-full px-4 py-2 text-left text-xs text-gray-700 hover:bg-gray-50 transition-colors duration-150 cursor-pointer"
                                                >
                                                    Editar
                                                </button>
                                                <button
                                                    @click="deleteCategoria(categoria)"
                                                    class="flex w-full px-4 py-2 text-left text-xs text-red-600 hover:bg-red-50 transition-colors duration-150 cursor-pointer"
                                                >
                                                    Eliminar
                                                </button>
                                            </template>
                                        </Dropdown>
                                    </td>
                                </tr>
                                <tr v-if="filteredCategorias.length === 0">
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-400">
                                        No se encontraron resultados para la búsqueda.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Formulario -->
        <Modal :show="showModal" @close="closeModal" max-width="md">
            <div class="p-6">
                <h3 class="text-sm font-bold text-gray-900 mb-5 pb-3 border-b border-gray-100">
                    {{ editMode ? 'Editar Categoría' : 'Nueva Categoría' }}
                </h3>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Nombre -->
                    <div>
                        <InputLabel for="nombre" value="Nombre" />
                        <TextInput
                            id="nombre"
                            type="text"
                            class="mt-1 block w-full"
                            v-model="form.nombre"
                            placeholder="Ej. Bebidas, Abarrotes, etc."
                            required
                            autofocus
                        />
                        <InputError class="mt-2" :message="form.errors.nombre" />
                    </div>

                    <!-- Descripción -->
                    <div>
                        <InputLabel for="descripcion" value="Descripción (Opcional)" />
                        <textarea
                            id="descripcion"
                            rows="3"
                            class="mt-1 block w-full border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 rounded-xl bg-white text-gray-900 text-xs py-2.5 px-3.5 transition-all outline-none"
                            placeholder="Breve descripción del tipo de producto..."
                            v-model="form.descripcion"
                        ></textarea>
                        <InputError class="mt-2" :message="form.errors.descripcion" />
                    </div>

                    <!-- Estado -->
                    <div>
                        <InputLabel for="estado" value="Estado" />
                        <select
                            id="estado"
                            class="mt-1 block w-full h-10 border border-gray-200 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 rounded-xl bg-white text-gray-900 text-xs py-2 px-3 transition-all outline-none cursor-pointer"
                            v-model="form.estado"
                            required
                        >
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                        </select>
                        <InputError class="mt-2" :message="form.errors.estado" />
                    </div>

                    <div class="mt-8 flex justify-end space-x-3 pt-4 border-t border-gray-100">
                        <SecondaryButton @click="closeModal" :disabled="form.processing">
                            Cancelar
                        </SecondaryButton>
                        <PrimaryButton :loading="form.processing">
                            {{ editMode ? 'Guardar Cambios' : 'Crear' }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
