<script setup>
// Inline teacher select: picking a teacher emits its id, clearing emits null.
// The parent saves right away; there is no separate submit.
defineProps({
    teachers: { type: Array, required: true },
    modelValue: { type: Number, default: null },
    placeholder: { type: String, default: 'Sin asignar' },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    label: { type: String, required: true },
});

const emit = defineEmits(['change']);
</script>

<template>
    <USelectMenu
        :model-value="modelValue"
        :items="teachers"
        value-key="id"
        label-key="name"
        description-key="email"
        :placeholder="placeholder"
        :search-input="{ placeholder: 'Buscar docente' }"
        :disabled="disabled"
        :loading="loading"
        :aria-label="label"
        :color="modelValue ? 'primary' : 'neutral'"
        :variant="modelValue ? 'soft' : 'outline'"
        size="sm"
        class="w-full"
        :clear="!disabled && modelValue !== null"
        @update:model-value="(value) => value !== modelValue && emit('change', value ?? null)"
        @clear="emit('change', null)"
    />
</template>
