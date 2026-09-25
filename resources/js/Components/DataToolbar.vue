<script setup>
// Toolbar for a list panel: search box, optional "Filtros" popover, and
// status chips. Filtering itself stays in the page (client-side or query params).
defineProps({
    placeholder: { type: String, default: 'Buscar…' },
    // [{ value, label, count? }]; the first one is usually "Todos".
    statuses: { type: Array, default: () => [] },
    // Active filters inside the popover, shown as a count on the button.
    filterCount: { type: Number, default: 0 },
});

const search = defineModel('search', { type: String, default: '' });
const status = defineModel('status', { type: [String, Number, Boolean, null], default: null });

defineEmits(['clear-filters']);
</script>

<template>
    <div class="space-y-3">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <UInput
                v-model="search"
                icon="i-lucide-search"
                :placeholder="placeholder"
                :aria-label="placeholder"
                class="w-full sm:max-w-sm"
                :ui="{ trailing: 'pe-1' }"
            >
                <template v-if="search" #trailing>
                    <UButton
                        color="neutral"
                        variant="link"
                        size="sm"
                        icon="i-lucide-x"
                        aria-label="Limpiar búsqueda"
                        @click="search = ''"
                    />
                </template>
            </UInput>

            <UPopover v-if="$slots.filters" :content="{ align: 'start' }">
                <UButton color="neutral" variant="outline" icon="i-lucide-sliders-horizontal" class="justify-center sm:justify-start">
                    Filtros
                    <UBadge v-if="filterCount > 0" color="primary" variant="solid" size="sm" class="ms-0.5 rounded-full">{{ filterCount }}</UBadge>
                </UButton>
                <template #content>
                    <div class="w-72 space-y-4 p-4">
                        <slot name="filters" />
                        <div v-if="filterCount > 0" class="flex justify-end border-t border-default pt-3">
                            <UButton color="neutral" variant="ghost" size="sm" icon="i-lucide-rotate-ccw" @click="$emit('clear-filters')">
                                Limpiar filtros
                            </UButton>
                        </div>
                    </div>
                </template>
            </UPopover>

            <div v-if="$slots.default" class="flex items-center gap-2 sm:ms-auto">
                <slot />
            </div>
        </div>

        <div v-if="statuses.length > 0" class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por estado">
            <UButton
                v-for="item in statuses"
                :key="String(item.value)"
                size="sm"
                :color="status === item.value ? 'primary' : 'neutral'"
                :variant="status === item.value ? 'solid' : 'outline'"
                class="rounded-full px-3"
                :aria-pressed="status === item.value"
                @click="status = item.value"
            >
                {{ item.label }}
                <span v-if="item.count !== undefined" class="tabular-nums opacity-70">{{ item.count }}</span>
            </UButton>
        </div>
    </div>
</template>
