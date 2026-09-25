<script setup>
// Short confirmation dialog (delete, deactivate…): title, message, Cancel + confirm.
const open = defineModel('open', { type: Boolean, default: false });

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: null },
    confirmLabel: { type: String, default: 'Eliminar' },
    confirmIcon: { type: String, default: 'i-lucide-trash-2' },
    color: { type: String, default: 'error' },
    loading: { type: Boolean, default: false },
});

defineEmits(['confirm', 'after:leave']);
</script>

<template>
    <UModal
        v-model:open="open"
        :title="title"
        :description="description ?? undefined"
        :dismissible="!loading"
        @after:leave="$emit('after:leave')"
    >
        <template v-if="$slots.default" #body>
            <div class="text-sm text-muted">
                <slot />
            </div>
        </template>
        <template #footer>
            <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <UButton color="neutral" variant="ghost" class="justify-center" :disabled="loading" @click="open = false">
                    Cancelar
                </UButton>
                <UButton :color="color" :icon="confirmIcon" class="justify-center" :loading="loading" @click="$emit('confirm')">
                    {{ confirmLabel }}
                </UButton>
            </div>
        </template>
    </UModal>
</template>
