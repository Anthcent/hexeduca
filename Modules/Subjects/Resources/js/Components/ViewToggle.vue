<script setup>
// Segmented switch between two or three views of the same data.
defineProps({
    // [{ value, label, icon }]
    options: { type: Array, required: true },
    label: { type: String, default: 'Vista' },
});

const view = defineModel({ type: String, required: true });
</script>

<template>
    <UFieldGroup :aria-label="label" role="group">
        <UButton
            v-for="option in options"
            :key="option.value"
            size="sm"
            :color="view === option.value ? 'primary' : 'neutral'"
            :variant="view === option.value ? 'soft' : 'outline'"
            :icon="option.icon"
            :aria-pressed="view === option.value"
            @click="view = option.value"
        >
            <span class="hidden sm:inline">{{ option.label }}</span>
        </UButton>
    </UFieldGroup>
</template>
