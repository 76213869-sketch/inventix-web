<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    disabled: {
        type: Boolean,
        default: false,
    }
});
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        class="inline-flex h-10 items-center justify-center rounded-xl border border-transparent bg-blue-600 px-4 text-sm font-semibold text-white shadow-xs transition-all duration-150 ease-in-out hover:bg-blue-700 hover:shadow-sm active:scale-98 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-1 disabled:opacity-50 disabled:pointer-events-none gap-2 select-none"
    >
        <!-- Loading spinner -->
        <svg v-if="loading" class="animate-spin -ml-1 mr-1 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <slot />
    </button>
</template>
