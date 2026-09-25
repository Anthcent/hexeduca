<script setup>
import { computed } from 'vue';
import PanelHeader from '@/Components/PanelHeader.vue';

// Step history of a process (e.g. an enrollment), newest step last.
// Each item: { title, description, time, meta?, done?, current? }.
const props = defineProps({
    title: { type: String, default: 'Seguimiento del proceso' },
    items: { type: Array, required: true },
});

const timelineItems = computed(() => props.items.map((item, index) => ({
    value: index,
    title: item.title,
    description: item.meta ? `${item.description} · ${item.meta}` : item.description,
    date: item.time,
    icon: item.done ? 'i-lucide-check' : (item.current ? 'i-lucide-loader-circle' : 'i-lucide-clock-3'),
})));

// Steps up to the current one render as completed.
const currentIndex = computed(() => {
    const current = props.items.findIndex((item) => item.current);

    return current === -1 ? props.items.filter((item) => item.done).length - 1 : current;
});
</script>

<template>
    <UCard class="shadow-card">
        <template #header>
            <PanelHeader kicker="Seguimiento" :title="title">
                <slot name="action" />
            </PanelHeader>
        </template>
        <UTimeline
            :items="timelineItems"
            :default-value="currentIndex"
            color="primary"
            size="sm"
            :ui="{ title: 'text-sm font-semibold', description: 'text-sm text-muted', date: 'text-xs' }"
        />
    </UCard>
</template>
