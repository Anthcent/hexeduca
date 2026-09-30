<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    period: { type: Object, default: null },
    moments: { type: Array, default: () => [] },
    moment: { type: Object, default: null },
    rows: { type: Array, default: () => [] },
});

function selectMoment(id) {
    router.get(route('grades.monitor', undefined, false), { moment: id }, { preserveScroll: true, replace: true });
}

const STATUS = {
    no_plan: { label: 'Sin plan', color: 'error', variant: 'subtle', icon: 'i-lucide-circle-x' },
    not_started: { label: 'Sin iniciar', color: 'warning', variant: 'subtle', icon: 'i-lucide-circle-dashed' },
    in_progress: { label: 'En progreso', color: 'info', variant: 'subtle', icon: 'i-lucide-loader' },
    complete: { label: 'Completo', color: 'success', variant: 'subtle', icon: 'i-lucide-circle-check' },
};

const onlyPending = ref(false);
const search = ref('');

function plain(value) {
    return value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('es');
}

const visible = computed(() => {
    const term = plain(search.value.trim());

    return props.rows.filter((row) => (!onlyPending.value || row.status !== 'complete')
        && (term === '' || plain(`${row.offerLabel} ${row.subjectName} ${row.teacher ?? ''}`).includes(term)));
});

// Grouped by offer, in the rows' order.
const groups = computed(() => {
    const byOffer = new Map();

    for (const row of visible.value) {
        if (!byOffer.has(row.offerId)) byOffer.set(row.offerId, { offerId: row.offerId, label: row.offerLabel, rows: [] });
        byOffer.get(row.offerId).rows.push(row);
    }

    return [...byOffer.values()];
});

const counts = computed(() => ({
    total: props.rows.length,
    complete: props.rows.filter((r) => r.status === 'complete').length,
    inProgress: props.rows.filter((r) => r.status === 'in_progress').length,
    pending: props.rows.filter((r) => r.status === 'no_plan' || r.status === 'not_started').length,
}));

function formatDateTime(value) {
    return value ? new Date(value).toLocaleString('es', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
}
</script>

<template>
    <Head title="Monitor de carga de notas" />

    <DashboardLayout active="notas">
        <PageHeader
            eyebrow="Notas"
            title="Monitor de carga"
            :description="period ? `Avance de la carga de notas por sección y asignatura · ${period.name}` : 'Avance de la carga de notas por sección y asignatura.'"
            icon="i-lucide-gauge"
        >
            <template #leading>
                <UButton :to="route('grades.index', undefined, false)" color="neutral" variant="link" icon="i-lucide-arrow-left" class="px-0">
                    Volver a notas
                </UButton>
            </template>
        </PageHeader>

        <EmptyState
            v-if="!period || moments.length === 0"
            icon="i-lucide-calendar-x"
            title="No hay momentos en el periodo activo"
            description="Crea los momentos académicos del periodo para seguir la carga de notas."
        />

        <template v-else>
            <div class="mb-5 flex flex-wrap gap-1.5" role="tablist" aria-label="Momento">
                <UButton
                    v-for="item in moments"
                    :key="item.id"
                    role="tab"
                    :aria-selected="moment?.id === item.id"
                    size="sm"
                    class="rounded-full px-3"
                    :color="moment?.id === item.id ? 'primary' : 'neutral'"
                    :variant="moment?.id === item.id ? 'solid' : 'outline'"
                    @click="selectMoment(item.id)"
                >
                    {{ item.name }}
                </UButton>
                <UBadge v-if="moment" :color="moment.windowOpen ? 'success' : 'neutral'" :variant="moment.windowOpen ? 'solid' : 'subtle'" class="ms-2 self-center">
                    {{ moment.windowOpen ? 'Carga abierta' : 'Carga cerrada' }}
                </UBadge>
            </div>

            <dl class="mb-6 grid grid-cols-2 overflow-hidden rounded-xl border border-default bg-default shadow-card lg:grid-cols-4">
                <div class="border-b border-default px-5 py-4 lg:border-b-0 lg:border-e">
                    <dt class="text-xs font-medium text-muted">Asignaturas</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-highlighted">{{ counts.total }}</dd>
                </div>
                <div class="border-b border-s border-default px-5 py-4 lg:border-b-0 lg:border-s-0 lg:border-e">
                    <dt class="text-xs font-medium text-muted">Completas</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-success">{{ counts.complete }}</dd>
                </div>
                <div class="px-5 py-4 lg:border-e lg:border-default">
                    <dt class="text-xs font-medium text-muted">En progreso</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-highlighted">{{ counts.inProgress }}</dd>
                </div>
                <div class="border-s border-default px-5 py-4 lg:border-s-0" :class="counts.pending > 0 ? 'bg-warning/10' : ''">
                    <dt class="text-xs font-medium text-muted">Pendientes (sin plan o sin iniciar)</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums" :class="counts.pending > 0 ? 'text-warning' : 'text-highlighted'">{{ counts.pending }}</dd>
                </div>
            </dl>

            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
                <UInput v-model="search" icon="i-lucide-search" placeholder="Buscar sección, asignatura o docente" class="w-full sm:max-w-sm" aria-label="Buscar" />
                <USwitch v-model="onlyPending" label="Ocultar completas" />
            </div>

            <p v-if="groups.length === 0" class="rounded-xl border border-dashed border-default px-5 py-10 text-center text-sm text-muted">
                {{ rows.length === 0 ? 'El periodo no tiene asignaturas asignadas a secciones.' : 'Nada coincide con el filtro.' }}
            </p>

            <div v-else class="space-y-5">
                <section v-for="group in groups" :key="group.offerId" class="overflow-hidden rounded-xl border border-default bg-default shadow-card">
                    <h2 class="border-b border-default bg-elevated/50 px-5 py-3 text-base font-bold text-highlighted">{{ group.label }}</h2>
                    <ul class="divide-y divide-default">
                        <li v-for="row in group.rows" :key="row.subjectId" class="grid gap-3 px-5 py-3 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)_minmax(0,1.2fr)_auto] md:items-center">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-highlighted">{{ row.subjectName }}</p>
                                <p class="truncate text-xs text-muted">{{ row.teacher ?? 'Sin docente titular' }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5">
                                <UBadge size="sm" :color="STATUS[row.status].color" :variant="STATUS[row.status].variant" :icon="STATUS[row.status].icon">
                                    {{ STATUS[row.status].label }}
                                </UBadge>
                                <UBadge v-if="row.correctionUntil" size="sm" color="warning" variant="solid" icon="i-lucide-pencil-ruler" :title="`Hasta el ${formatDateTime(row.correctionUntil)}`">
                                    En corrección
                                </UBadge>
                            </div>
                            <div v-if="row.planId" class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <UProgress :model-value="row.progress" size="sm" :color="row.progress === 100 ? 'success' : 'primary'" class="flex-1" />
                                    <span class="w-10 text-right text-xs font-semibold tabular-nums text-muted">{{ row.progress }}%</span>
                                </div>
                                <p class="mt-1 text-xs text-muted">
                                    {{ row.complete }} de {{ row.students }} completos<template v-if="row.failing > 0"> · <span class="text-error">{{ row.failing }} reprobados</span></template>
                                </p>
                            </div>
                            <p v-else class="text-xs text-muted">El docente todavía no armó el plan.</p>
                            <div class="flex gap-1.5 md:justify-end">
                                <UButton v-if="row.planId" :to="route('grades.sheet', row.planId, false)" size="sm" color="neutral" variant="outline" icon="i-lucide-table-2">
                                    Ver notas
                                </UButton>
                                <UButton
                                    v-else
                                    :to="route('grades.plan', { offer: row.offerId, subject: row.subjectId, moment: moment.id }, false)"
                                    size="sm"
                                    color="neutral"
                                    variant="ghost"
                                    icon="i-lucide-list-tree"
                                >
                                    Plan
                                </UButton>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </template>
    </DashboardLayout>
</template>
