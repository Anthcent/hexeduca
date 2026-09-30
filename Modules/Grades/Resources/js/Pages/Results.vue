<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    period: { type: Object, required: true },
    offers: { type: Array, default: () => [] },
    offerId: { type: Number, default: null },
    subjects: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    pass: { type: Number, default: 10 },
    maxPending: { type: Number, default: 2 },
});

const selected = ref(props.offerId);
const offerItems = computed(() => props.offers.map((o) => ({ label: o.label, value: o.id })));

watch(selected, (value) => {
    if (value !== props.offerId) {
        router.get(route('grades.results', undefined, false), { period: props.period.id, offer: value }, { preserveScroll: true, replace: true });
    }
});

const OUTCOME = {
    passed: { label: 'Aprobado', color: 'success', variant: 'subtle', icon: 'i-lucide-circle-check' },
    pending: { label: 'Materia pendiente', color: 'warning', variant: 'subtle', icon: 'i-lucide-circle-alert' },
    repeats: { label: 'Repite', color: 'error', variant: 'subtle', icon: 'i-lucide-circle-x' },
    incomplete: { label: 'Sin resultado', color: 'neutral', variant: 'outline', icon: 'i-lucide-circle-dashed' },
};

const counts = computed(() => Object.fromEntries(Object.keys(OUTCOME).map((key) => [key, props.rows.filter((r) => r.outcome === key).length])));

function pad(value) {
    return String(value).padStart(2, '0');
}
</script>

<template>
    <Head title="Resultado del año" />

    <DashboardLayout active="notas">
        <PageHeader
            :eyebrow="`Notas · ${period.name}`"
            title="Resultado del año"
            :description="`Definitivas por asignatura y resultado de cada estudiante. Con 1 a ${maxPending} asignaturas aplazadas se promueve con materia pendiente; con más, repite.`"
            icon="i-lucide-graduation-cap"
        >
            <template #leading>
                <UButton :to="route('grades.index', { period: period.id }, false)" color="neutral" variant="link" icon="i-lucide-arrow-left" class="px-0">
                    Volver a notas
                </UButton>
            </template>
        </PageHeader>

        <EmptyState
            v-if="offers.length === 0"
            icon="i-lucide-layout-grid"
            title="El periodo no tiene secciones"
            description="Cuando haya secciones en el periodo, aquí verás su resultado del año."
        />

        <template v-else>
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
                <USelect v-model="selected" :items="offerItems" icon="i-lucide-layout-grid" class="w-full sm:w-72" aria-label="Sección" />
                <div class="flex flex-wrap gap-1.5 sm:ms-auto">
                    <UBadge v-for="(meta, key) in OUTCOME" :key="key" size="sm" :color="meta.color" :variant="meta.variant" :icon="meta.icon">
                        {{ meta.label }}: <span class="font-semibold tabular-nums">{{ counts[key] }}</span>
                    </UBadge>
                </div>
            </div>

            <EmptyState
                v-if="rows.length === 0"
                icon="i-lucide-users"
                title="La sección no tiene estudiantes inscritos"
                description="Cuando haya estudiantes inscritos, aquí verás su resultado del año."
            />
            <EmptyState
                v-else-if="subjects.length === 0"
                icon="i-lucide-book-open"
                title="La sección no tiene asignaturas"
                description="Asigna un plan de estudios a la sección para calcular el resultado del año."
            />

            <div v-else class="overflow-x-auto rounded-xl border border-default bg-default shadow-card">
                <table class="w-full text-sm">
                    <thead class="border-b border-default bg-elevated/50 text-xs text-muted">
                        <tr>
                            <th scope="col" class="sticky start-0 bg-elevated px-4 py-3 text-left font-semibold">Estudiante</th>
                            <th v-for="subject in subjects" :key="subject.id" scope="col" class="px-2 py-3 text-center font-semibold" :title="subject.name">
                                <ULink :to="route('grades.year', { period: period.id, offer: offerId, subject: subject.id }, false)" class="block max-w-24 truncate hover:text-highlighted">
                                    {{ subject.code ?? subject.name }}
                                </ULink>
                            </th>
                            <th scope="col" class="px-4 py-3 text-left font-semibold text-highlighted">Resultado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-default">
                        <tr v-for="row in rows" :key="row.id">
                            <th scope="row" class="sticky start-0 bg-default px-4 py-2.5 text-left font-medium whitespace-nowrap text-highlighted">{{ row.name }}</th>
                            <td v-for="subject in subjects" :key="subject.id" class="px-2 py-2.5 text-center tabular-nums">
                                <span v-if="row.years[subject.id] === null" class="text-dimmed">—</span>
                                <span v-else :class="row.years[subject.id] < pass ? 'rounded-md bg-error/10 px-1.5 py-0.5 font-semibold text-error' : 'text-highlighted'">
                                    {{ pad(row.years[subject.id]) }}
                                </span>
                            </td>
                            <td class="px-4 py-2.5">
                                <UBadge size="sm" :color="OUTCOME[row.outcome].color" :variant="OUTCOME[row.outcome].variant" :icon="OUTCOME[row.outcome].icon" class="whitespace-nowrap">
                                    {{ OUTCOME[row.outcome].label }}<template v-if="row.outcome === 'pending'"> ({{ row.failed }})</template>
                                </UBadge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </DashboardLayout>
</template>
