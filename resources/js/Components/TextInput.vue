<script setup>
import { onMounted, ref } from 'vue';

const model = defineModel({
    type: String,
    required: true,
});

const input = ref(null);

onMounted(() => {
    if (input.value.hasAttribute('autofocus')) {
        input.value.focus();
    }
});

defineExpose({ focus: () => input.value.focus() });
</script>

<template>
    <input
        class="rounded-xl border-gray-200 shadow-sm focus:border-blue-500 focus:ring-blue-500 bg-white text-gray-900 text-sm py-2 px-3 transition-colors"
        v-model="model"
        ref="input"
    />
</template>
