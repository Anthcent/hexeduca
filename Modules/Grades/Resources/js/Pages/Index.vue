<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    periods: { type: Array, default: () => [] },
    period: { type: Object, default: null },
    isStaff: { type: Boolean, default: false },
    cards: { type: Array, default: () => [] },
    homerooms: { type: Array, default: () => [] },
});

const periodId = ref(props.period?.id ?? null);
const periodItems = computed(() => props.periods.map((p) => ({ label: p.isActive ? `${p.name} (activo)` : p.name, value: p.id })));

watch(periodId, (value) => {
    if (value !== props.period?.id) {
        router.get(route('grades.index', undefined, false), { period: value }, { preserveScroll: true, replace: true });
    }
});

const search = ref('');

function plain(value) {
    return value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('es');
}

const visible = computed(() => {
    const term = plain(search.value.trim());

    return term === '' ? props.cards : props.cards.filter((c) => plain(`${c.subjectName} ${c.offerLabel}`).includes(term));
});

const WINDOW = {
    open: { label: 'Carga abierta', color: 'success', variant: 'solid', icon: 'i-lucide-lock-open' },
    upcoming: { label: 'Próxima', color: 'info', variant: 'subtle', icon: 'i-lucide-calendar-clock' },
    closed: { label: 'Cerrada', color: 'neutral', variant: 'subtle', icon: 'i-lucide-lock' },
    undefined: { label: 'Sin fechas', color: 'neutral', variant: 'outline', icon: 'i-lucide-calendar-off' },
};

function planUrl(card, moment) {
    return route('grades.plan', { offer: card.offerId, subject: card.subjectId, moment: moment.id }, false);
}
</script>

<template>
    <Head title="Notas" />

    <DashboardLayout active="notas">
        <PageHeader
            eyebrow="Académico"
            :title="isStaff ? 'Notas' : 'Mis materias'"
            :description="isStaff ? 'Planes de evaluación y carga de notas de todas las asignaturas del periodo.' : 'Tus asignaturas del periodo: arma el plan de cada momento y carga las notas.'"
            icon="i-lucide-clipboard-check"
        >
            <template v-if="isStaff" #notch>
                <div class="flex flex-wrap gap-2">
                    <UButton v-if="period" :to="route('grades.conduct', { period: period.id }, false)" icon="i-lucide-handshake" size="lg" color="neutral" variant="outline">
                        Convivir
                    </UButton>
                    <UButton v-if="period" :to="route('grades.results', { period: period.id }, false)" icon="i-lucide-graduation-cap" size="lg" color="neutral" variant="outline">
                        Resultado del año
                    </UButton>
                    <UButton :to="route('grades.monitor', undefined, false)" icon="i-lucide-gauge" size="lg">Monitor de carga</UButton>
                </div>
            </template>
        </PageHeader>

        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center">
            <USelect v-if="periods.length > 1" v-model="periodId" :items="periodItems" icon="i-lucide-calendar-range" class="w-full sm:w-56" aria-label="Periodo" />
            <UInput v-model="search" icon="i-lucide-search" placeholder="Buscar asignatura o sección" class="w-full sm:max-w-sm" aria-label="Buscar" />
            <p class="text-sm text-muted sm:ms-auto">{{ visible.length }} {{ visible.length === 1 ? 'asignatura' : 'asignaturas' }}</p>
        </div>

        <section v-if="homerooms.length > 0" class="mb-5 flex flex-col gap-3 rounded-xl border border-default bg-default px-5 py-4 shadow-card sm:flex-row sm:items-center">
            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-bold text-highlighted">Mis secciones (orientador)</h2>
                <p class="text-xs text-muted">Como docente orientador, calificas Convivir en cada momento.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <UButton
                    v-for="homeroom in homerooms"
                    :key="homeroom.id"
                    :to="route('grades.conduct', { period: period.id, offer: homeroom.id }, false)"
                    size="sm"
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-handshake"
                >
                    Convivir · {{ homeroom.label }}
                </UButton>
            </div>
        </section>

        <EmptyState
            v-if="!period"
            icon="i-lucide-calendar-x"
            title="No hay periodos académicos"
            description="Cuando exista un periodo, aquí aparecerán las asignaturas."
        />
        <EmptyState
            v-else-if="cards.length === 0"
            icon="i-lucide-book-open"
            :title="isStaff ? 'No hay asignaturas en el periodo' : 'No tienes asignaturas asignadas'"
            :description="isStaff ? 'Asigna un plan de estudios a las secciones del periodo.' : 'Cuando te asignen una asignatura en Asignación docente, aparecerá aquí.'"
        />
        <p v-else-if="visible.length === 0" class="rounded-xl border border-dashed border-default px-5 py-10 text-center text-sm text-muted">
            Nada coincide con la búsqueda.
        </p>

        <div v-else class="grid gap-4 [grid-template-columns:repeat(auto-fill,minmax(20rem,1fr))]">
            <article v-for="card in visible" :key="`${card.offerId}-${card.subjectId}`" class="flex flex-col rounded-xl border border-default bg-default shadow-card">
                <header class="border-b border-default px-5 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="min-w-0 text-base font-bold leading-snug text-highlighted">{{ card.subjectName }}</h2>
                        <UBadge v-if="card.role === 'suplente'" size="sm" color="neutral" variant="outline" class="shrink-0">Suplente</UBadge>
                    </div>
                    <p class="mt-0.5 text-sm text-muted">{{ card.offerLabel }} · {{ card.students }} {{ card.students === 1 ? 'estudiante' : 'estudiantes' }}</p>
                </header>

                <p v-if="card.moments.length === 0" class="px-5 py-4 text-sm text-muted">El periodo no tiene momentos académicos.</p>

                <ul v-else class="divide-y divide-default">
                    <li v-for="moment in card.moments" :key="moment.id" class="px-5 py-3">
                        <div class="flex items-center gap-2">
                            <p class="min-w-0 flex-1 truncate text-sm font-semibold text-highlighted">{{ moment.name }}</p>
                            <UBadge v-if="moment.correction" size="sm" color="warning" variant="solid" icon="i-lucide-pencil-ruler">
                                En corrección
                            </UBadge>
                            <UBadge v-else size="sm" :color="WINDOW[moment.window].color" :variant="WINDOW[moment.window].variant" :icon="WINDOW[moment.window].icon">
                                {{ WINDOW[moment.window].label }}
                            </UBadge>
                        </div>

                        <div v-if="moment.planId" class="mt-2 flex items-center gap-3">
                            <UProgress :model-value="moment.progress" size="sm" :color="moment.progress === 100 ? 'success' : 'primary'" class="flex-1" />
                            <span class="w-10 text-right text-xs font-semibold tabular-nums text-muted">{{ moment.progress }}%</span>
                        </div>

                        <div class="mt-2 flex flex-wrap gap-2">
                            <template v-if="moment.planId">
                                <UButton
                                    :to="route('grades.sheet', moment.planId, false)"
                                    size="sm"
                                    :icon="moment.window === 'open' || moment.correction ? 'i-lucide-pencil-line' : 'i-lucide-eye'"
                                    :variant="moment.window === 'open' || moment.correction ? 'solid' : 'soft'"
                                >
                                    {{ moment.window === 'open' || moment.correction ? 'Cargar notas' : 'Ver notas' }}
                                </UButton>
                                <UButton :to="planUrl(card, moment)" size="sm" color="neutral" variant="ghost" icon="i-lucide-list-tree">Plan</UButton>
                            </template>
                            <UButton v-else :to="planUrl(card, moment)" size="sm" color="neutral" variant="outline" icon="i-lucide-plus">
                                Crear plan de evaluación
                            </UButton>
                        </div>
                    </li>
                </ul>

                <footer v-if="card.moments.length > 0" class="mt-auto border-t border-default px-5 py-3">
                    <UButton
                        :to="route('grades.year', { period: period.id, offer: card.offerId, subject: card.subjectId }, false)"
                        size="sm"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-sigma"
                        class="px-0"
                    >
                        Resumen anual
                    </UButton>
                </footer>
            </article>
        </div>
    </DashboardLayout>
</template>
