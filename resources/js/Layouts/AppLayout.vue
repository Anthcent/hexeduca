<script setup>
defineProps({
    title: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 dark:bg-gray-900">
        <nav class="border-b border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            <slot name="nav">
                <div class="mx-auto flex max-w-7xl items-center px-6 py-4">
                    <span class="text-lg font-semibold text-gray-900 dark:text-white">
                        Educativo
                    </span>
                </div>
            </slot>
        </nav>

        <header v-if="title" class="bg-white shadow dark:bg-gray-800">
            <div class="mx-auto max-w-7xl px-6 py-6">
                <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                    {{ title }}
                </h1>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-6 py-8">
            <slot />
        </main>
    </div>
</template>
