<script setup>
// Header recipe for a panel (UCard #header): kicker, title, controls on the right.
defineProps({
    kicker: { type: String, default: null },
    title: { type: String, required: true },
});
</script>

<template>
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <p v-if="kicker" class="text-xs font-bold uppercase tracking-[.12em] text-muted">{{ kicker }}</p>
            <h2 class="mt-0.5 text-base font-bold text-highlighted">{{ title }}</h2>
        </div>
        <div v-if="$slots.default" class="flex flex-wrap items-center gap-2">
            <slot />
        </div>
    </div>
</template>
