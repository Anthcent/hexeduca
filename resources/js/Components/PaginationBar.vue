<script setup>
import { computed } from 'vue';
import { toPath } from '@/Layouts/navigation';

// Card footer for a Laravel LengthAwarePaginator prop: "Mostrando X–Y de Z" plus
// page links (the controller already reads `?page=`).
const props = defineProps({
    paginator: { type: Object, required: true },
    // Plural noun for the range label, e.g. "archivos".
    noun: { type: String, default: 'resultados' },
});

const hasPages = computed(() => (props.paginator.last_page ?? 1) > 1);

function pageUrl(page) {
    const url = new URL(props.paginator.path, window.location.origin);
    url.searchParams.set('page', String(page));

    return toPath(url.toString());
}
</script>

<template>
    <div
        v-if="paginator.total > 0"
        class="flex flex-col items-center gap-3 px-4 py-3 sm:flex-row sm:justify-between sm:px-5"
    >
        <p class="text-sm text-muted tabular-nums">
            Mostrando {{ paginator.from }}–{{ paginator.to }} de {{ paginator.total }} {{ noun }}
        </p>
        <UPagination
            v-if="hasPages"
            :page="paginator.current_page"
            :total="paginator.total"
            :items-per-page="paginator.per_page"
            :sibling-count="1"
            :to="pageUrl"
            size="sm"
        />
    </div>
</template>
