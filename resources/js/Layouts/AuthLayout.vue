<script setup>
defineProps({
    title: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="flex min-h-screen items-center justify-center bg-canvas px-4 py-10">
        <div class="w-full max-w-[400px]">
            <div class="mb-7 flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-g1 bg-brand text-base font-medium text-white">
                    E
                </div>
                <span class="text-[1.375rem] font-normal text-ink-muted">
                    <strong class="font-medium text-ink">Educativo</strong>
                </span>
            </div>

            <div class="rounded-g2 border border-outline bg-white p-8 shadow-e1">
                <h1 v-if="title" class="mb-1 text-2xl font-semibold text-ink">
                    {{ title }}
                </h1>
                <slot />
            </div>
        </div>
    </div>
</template>
