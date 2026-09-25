<script setup>
// Calm empty state for panels and lists: tinted icon tile, one-line title,
// muted hint and optional actions (UButton props).
defineProps({
    icon: { type: String, default: 'i-lucide-inbox' },
    title: { type: String, required: true },
    description: { type: String, default: null },
    actions: { type: Array, default: () => [] },
});
</script>

<template>
    <UEmpty
        :icon="icon"
        :title="title"
        :description="description ?? undefined"
        :actions="actions"
        variant="naked"
        size="lg"
        class="py-12"
        :ui="{ avatar: 'size-12 rounded-lg bg-primary/10 text-primary', title: 'font-semibold' }"
    />
</template>
