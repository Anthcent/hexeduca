<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import DataToolbar from '@/Components/DataToolbar.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import { tableUi } from '@/Components/tableUi';
import PlanFormModal from '../Components/PlanFormModal.vue';

const props = defineProps({
    plans: { type: Object, required: true },
    filters: { type: Object, required: true },
    counts: { type: Object, required: true },
    planCodes: { type: Array, default: () => [] },
});

const items = computed(() => props.plans.data ?? []);
const total = computed(() => props.plans.total ?? items.value.length);
const archivedView = computed(() => props.filters.status === 'archived');

// Search and status are server-side filters: every change reloads page 1
// with the query string, so the results span all pages.

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? 'active');

const statuses = computed(() => [
    { value: 'active', label: 'Activos', count: props.counts.active },
    { value: 'archived', label: 'Archivados', count: props.counts.archived },
]);

let searchTimer = null;

function reload() {
    const query = { status: status.value };

    if (search.value.trim() !== '') {
        query.search = search.value.trim();
    }

    router.get(route('subjects.index', undefined, false), query, { preserveState: true, preserveScroll: true, replace: true });
}

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(reload, 300);
});

watch(status, reload);

function resetSearch() {
    search.value = '';
}

const columns = [
    { accessorKey: 'code', header: 'Código' },
    { accessorKey: 'name', header: 'Nombre' },
    { accessorKey: 'subjectCount', header: 'Asignaturas', meta: { class: { th: 'w-32', td: 'tabular-nums' } } },
    { accessorKey: 'status', header: 'Estado', meta: { class: { th: 'w-32' } } },
    { id: 'actions', header: '', meta: { class: { th: 'w-16', td: 'text-right' } } },
];

function showPlan(plan) {
    router.visit(route('subjects.plans.show', plan.id, false));
}

const createOpen = ref(false);
</script>

<template>
    <Head title="Planes de estudio" />

    <DashboardLayout active="module:subjects:subjects.index">
        <PageHeader
            eyebrow="Académico"
            title="Planes de estudio"
            description="Planes oficiales de la institución con sus asignaturas por año. Asígnalos a cada periodo desde Asignaciones."
            icon="i-lucide-book-open"
        >
            <template #actions>
                <UButton
                    :to="route('subjects.assignments.index', undefined, false)"
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-list-checks"
                    class="bg-white/10 text-white ring-white/25 hover:bg-white/20"
                >
                    Asignaciones por periodo
                </UButton>
            </template>
            <template #notch>
                <UButton icon="i-lucide-plus" size="lg" @click="createOpen = true">Nuevo plan</UButton>
            </template>
        </PageHeader>

        <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0', footer: 'p-0 sm:px-0' }">
            <template #header>
                <div class="space-y-4">
                    <PanelHeader
                        :kicker="archivedView ? 'Archivados' : 'Catálogo'"
                        :title="total === 1 ? '1 plan' : `${total} planes`"
                    />
                    <DataToolbar
                        v-model:search="search"
                        v-model:status="status"
                        placeholder="Buscar por código, nombre u observación"
                        :statuses="statuses"
                    />
                </div>
            </template>

            <EmptyState
                v-if="items.length === 0 && filters.search"
                icon="i-lucide-search-x"
                title="Sin resultados"
                description="Ningún plan coincide con la búsqueda."
                :actions="[{ label: 'Limpiar búsqueda', color: 'neutral', variant: 'outline', icon: 'i-lucide-rotate-ccw', onClick: resetSearch }]"
            />
            <EmptyState
                v-else-if="items.length === 0 && archivedView"
                icon="i-lucide-archive"
                title="No hay planes archivados"
                description="Los planes que archives aparecerán aquí, con su historial."
            />
            <EmptyState
                v-else-if="items.length === 0"
                icon="i-lucide-book-open"
                title="Todavía no hay planes de estudio"
                description="Crea el primero y agrégale sus asignaturas por año."
                :actions="[{ label: 'Nuevo plan', icon: 'i-lucide-plus', onClick: () => (createOpen = true) }]"
            />

            <template v-else>
                <UTable :data="items" :columns="columns" :ui="tableUi" class="hidden md:block">
                    <template #code-cell="{ row }">
                        <div class="min-w-0 max-w-xs">
                            <p class="font-semibold tabular-nums text-highlighted">{{ row.original.code }}</p>
                            <p v-if="row.original.observation" class="truncate text-xs text-muted" :title="row.original.observation">
                                {{ row.original.observation }}
                            </p>
                        </div>
                    </template>
                    <template #name-cell="{ row }">
                        <span class="block max-w-md truncate" :title="row.original.name">{{ row.original.name }}</span>
                    </template>
                    <template #status-cell="{ row }">
                        <UBadge v-if="row.original.status === 'archived'" color="neutral" variant="subtle" icon="i-lucide-archive">Archivado</UBadge>
                        <UBadge v-else color="success" variant="subtle">Activo</UBadge>
                    </template>
                    <template #actions-cell="{ row }">
                        <UTooltip text="Ver plan">
                            <UButton
                                :to="route('subjects.plans.show', row.original.id, false)"
                                color="neutral"
                                variant="ghost"
                                icon="i-lucide-chevron-right"
                                :aria-label="`Ver el plan ${row.original.code}`"
                            />
                        </UTooltip>
                    </template>
                </UTable>

                <ul class="divide-y divide-default md:hidden">
                    <li v-for="plan in items" :key="plan.id">
                        <button type="button" class="flex w-full items-center gap-3 px-4 py-3.5 text-left" @click="showPlan(plan)">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                                <UIcon name="i-lucide-book-open" class="size-4" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-highlighted">
                                    {{ plan.code }}<template v-if="plan.observation"> · {{ plan.observation }}</template>
                                </span>
                                <span class="block truncate text-xs text-muted">{{ plan.name }} · {{ plan.subjectCount }} asignaturas</span>
                            </span>
                            <UIcon name="i-lucide-chevron-right" class="size-4 shrink-0 text-muted" />
                        </button>
                    </li>
                </ul>
            </template>

            <template v-if="plans.last_page > 1" #footer>
                <PaginationBar :paginator="plans" noun="planes" />
            </template>
        </UCard>

        <PlanFormModal v-model:open="createOpen" :plan-codes="planCodes" />
    </DashboardLayout>
</template>
