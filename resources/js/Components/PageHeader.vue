<script setup>
// The one page header recipe: eyebrow, title, description, actions, divider.
defineProps({
    eyebrow: { type: String, default: null },
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <header class="mb-6 border-b border-default pb-5">
        <div v-if="$slots.leading" class="mb-3">
            <slot name="leading" />
        </div>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p v-if="eyebrow" class="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-[.12em] text-muted">
                    <span class="h-[3px] w-4 shrink-0 rounded-full bg-amber" aria-hidden="true" />
                    {{ eyebrow }}
                </p>
                <h1 class="text-2xl font-bold tracking-tight text-highlighted text-pretty sm:text-[1.75rem] sm:leading-tight">
                    {{ title }}
                </h1>
                <p v-if="description || $slots.description" class="mt-1.5 max-w-[65ch] text-sm text-muted text-pretty">
                    <slot name="description">{{ description }}</slot>
                </p>
            </div>

            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 max-sm:[&>*]:flex-1 max-sm:[&>*]:justify-center sm:shrink-0">
                <slot name="actions" />
            </div>
        </div>
    </header>
</template>
