<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import TeacherPicker from '../Components/TeacherPicker.vue';

const props = defineProps({
    periods: { type: Array, default: () => [] },
    period: { type: Object, default: null },
    board: { type: Object, required: true },
});

const readOnly = computed(() => !props.period?.isOpen);
const teachersById = computed(() => Object.fromEntries(props.board.teachers.map((t) => [t.id, t])));

// Period: a server-side filter.

const periodId = ref(props.period?.id ?? null);
const periodItems = computed(() => props.periods.map((p) => ({ label: p.isActive ? `${p.name} (activo)` : p.name, value: p.id })));

watch(periodId, (value) => {
    if (value !== props.period?.id) {
        router.get(route('teachingassignments.index', undefined, false), { period: value }, { preserveScroll: true, replace: true });
    }
});

// View: by section (assign) or by teacher (workload). Per-browser preference.

const VIEW_KEY = 'teaching.view';

function storedView() {
    try {
        return localStorage.getItem(VIEW_KEY) === 'teachers' ? 'teachers' : 'offers';
    } catch {
        return 'offers';
    }
}

const view = ref(storedView());

watch(view, (value) => {
    try {
        localStorage.setItem(VIEW_KEY, value);
    } catch {
        // Storage can be unavailable (private mode); the view still switches.
    }
});

const search = ref('');
const onlyPending = ref(false);

function matches(text) {
    const term = search.value.trim().toLocaleLowerCase('es');
    const plain = (value) => value.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLocaleLowerCase('es');

    return term === '' || plain(text).includes(plain(term));
}

// Totals for the summary strip.

const totalSubjects = computed(() => props.board.offers.reduce((sum, o) => sum + o.subjects.length, 0));
const totalAssigned = computed(() => props.board.offers.reduce((sum, o) => sum + o.assignedCount, 0));
const offersWithoutPlan = computed(() => props.board.offers.filter((o) => o.subjects.length === 0).length);

// By section

const visibleOffers = computed(() => props.board.offers
    .map((offer) => {
        const label = `${offer.gradeLevelName} · Sección ${offer.sectionName}`;
        const subjects = offer.subjects.filter((s) => (!onlyPending.value || !s.titular)
            && (matches(label) || matches(s.name) || [s.titular, s.substitute].some((slot) => slot && matches(teachersById.value[slot.teacherId]?.name ?? ''))));

        return { ...offer, label, visibleSubjects: subjects };
    })
    .filter((offer) => offer.visibleSubjects.length > 0 || (!onlyPending.value && search.value.trim() === '')));

// By teacher: workload from the active assignments.

const workload = computed(() => {
    const rows = Object.fromEntries(props.board.teachers.map((t) => [t.id, { ...t, items: [], hours: 0, offers: new Set() }]));

    for (const offer of props.board.offers) {
        for (const subject of offer.subjects) {
            for (const [role, slot] of [['Titular', subject.titular], ['Suplente', subject.substitute]]) {
                if (!slot || !rows[slot.teacherId]) continue;

                const row = rows[slot.teacherId];
                row.items.push({ key: `${offer.id}-${subject.id}-${role}`, offer: `${offer.gradeLevelName} · ${offer.sectionName}`, subject: subject.name, role, hours: subject.weeklyHours });
                row.offers.add(offer.id);
                if (role === 'Titular') row.hours += subject.weeklyHours ?? 0;
            }
        }
    }

    return Object.values(rows)
        .filter((row) => matches(row.name) || row.items.some((item) => matches(item.subject) || matches(item.offer)))
        .sort((a, b) => b.hours - a.hours || a.name.localeCompare(b.name, 'es'));
});

// Saving: every picker saves on change.

const saving = ref(null);

function assign(offer, subject, role, teacherId) {
    const slot = role === 'titular' ? subject.titular : subject.substitute;
    const key = `${offer.id}-${subject.id}-${role}`;
    const options = { preserveScroll: true, preserveState: true, onFinish: () => (saving.value = null) };

    saving.value = key;

    if (teacherId === null) {
        if (slot) router.delete(route('teachingassignments.destroy', slot.assignmentId, false), options);
        else saving.value = null;

        return;
    }

    router.post(route('teachingassignments.store', undefined, false), {
        period_id: props.period.id,
        offer_id: offer.id,
        subject_id: subject.id,
        teacher_id: teacherId,
        role,
    }, options);
}

function setCoordinator(offer, teacherId) {
    saving.value = `coordinator-${offer.id}`;
    router.put(route('teachingassignments.coordinator', offer.id, false), { period_id: props.period.id, teacher_id: teacherId }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => (saving.value = null),
    });
}
</script>

<template>
    <Head title="Asignación docente" />

    <DashboardLayout active="module:teachingassignments:teachingassignments.index">
        <PageHeader
            eyebrow="Académico"
            title="Asignación docente"
            description="Quién dicta cada asignatura en cada sección del periodo. Las asignaturas salen del plan de estudios asignado a la sección."
            icon="i-lucide-users"
        />

        <EmptyState
            v-if="!period"
            icon="i-lucide-calendar-x"
            title="No hay periodos académicos"
            description="Crea un periodo académico y sus secciones para asignar docentes."
        />

        <template v-else>
            <UAlert
                v-if="readOnly"
                class="mb-6"
                color="neutral"
                variant="subtle"
                icon="i-lucide-lock"
                title="Periodo cerrado: solo lectura"
                description="Las asignaciones de un periodo cerrado se conservan como historial y no se pueden cambiar."
            />

            <dl class="mb-6 grid grid-cols-2 overflow-hidden rounded-xl border border-default bg-default shadow-card lg:grid-cols-4">
                <div class="border-b border-default px-5 py-4 lg:border-b-0 lg:border-e">
                    <dt class="text-xs font-medium text-muted">Secciones</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-highlighted">{{ board.offers.length }}</dd>
                </div>
                <div class="border-b border-s border-default px-5 py-4 lg:border-b-0 lg:border-s-0 lg:border-e">
                    <dt class="text-xs font-medium text-muted">Asignaturas con titular</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-highlighted">
                        {{ totalAssigned }}<span class="text-base font-semibold text-muted"> de {{ totalSubjects }}</span>
                    </dd>
                </div>
                <div class="px-5 py-4 lg:border-e lg:border-default">
                    <dt class="text-xs font-medium text-muted">Docentes con carga</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums text-highlighted">
                        {{ workload.filter((t) => t.items.length > 0).length }}<span class="text-base font-semibold text-muted"> de {{ board.teachers.length }}</span>
                    </dd>
                </div>
                <div class="border-s border-default px-5 py-4 lg:border-s-0" :class="offersWithoutPlan > 0 ? 'bg-warning/10' : ''">
                    <dt class="text-xs font-medium text-muted">Secciones sin plan</dt>
                    <dd class="mt-1 text-2xl font-bold tabular-nums" :class="offersWithoutPlan > 0 ? 'text-warning' : 'text-highlighted'">{{ offersWithoutPlan }}</dd>
                </div>
            </dl>

            <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
                <USelect v-model="periodId" :items="periodItems" icon="i-lucide-calendar-range" class="w-full lg:w-56" aria-label="Periodo" />
                <UInput v-model="search" icon="i-lucide-search" placeholder="Buscar sección, asignatura o docente" class="w-full lg:max-w-sm" aria-label="Buscar" />
                <USwitch v-if="view === 'offers'" v-model="onlyPending" label="Solo sin titular" />
                <UFieldGroup class="lg:ms-auto" aria-label="Vista">
                    <UButton
                        size="sm"
                        icon="i-lucide-layout-list"
                        :color="view === 'offers' ? 'primary' : 'neutral'"
                        :variant="view === 'offers' ? 'soft' : 'outline'"
                        :aria-pressed="view === 'offers'"
                        @click="view = 'offers'"
                    >
                        Por sección
                    </UButton>
                    <UButton
                        size="sm"
                        icon="i-lucide-user-round"
                        :color="view === 'teachers' ? 'primary' : 'neutral'"
                        :variant="view === 'teachers' ? 'soft' : 'outline'"
                        :aria-pressed="view === 'teachers'"
                        @click="view = 'teachers'"
                    >
                        Por docente
                    </UButton>
                </UFieldGroup>
            </div>

            <EmptyState
                v-if="board.offers.length === 0"
                icon="i-lucide-school"
                title="El periodo no tiene secciones"
                description="Crea las ofertas académicas (año y sección) del periodo para asignarles docentes."
            />

            <!-- By section -->
            <div v-else-if="view === 'offers'" class="space-y-5">
                <p v-if="visibleOffers.length === 0" class="rounded-xl border border-dashed border-default px-5 py-10 text-center text-sm text-muted">
                    Nada coincide con el filtro.
                </p>

                <section v-for="offer in visibleOffers" :key="offer.id" class="overflow-hidden rounded-xl border border-default bg-default shadow-card">
                    <header class="flex flex-wrap items-center gap-x-6 gap-y-3 border-b border-default bg-elevated/50 px-5 py-4">
                        <div class="min-w-0 flex-1">
                            <h2 class="text-base font-bold text-highlighted">{{ offer.label }}</h2>
                            <p class="text-xs text-muted">
                                Orientador:
                                <span v-if="offer.orientador" class="font-semibold text-default">{{ offer.orientador.name }}</span>
                                <span v-else>sin asignar (se define en la oferta académica)</span>
                            </p>
                        </div>
                        <UBadge
                            v-if="offer.subjects.length > 0"
                            :color="offer.assignedCount === offer.subjects.length ? 'success' : 'warning'"
                            variant="subtle"
                            :icon="offer.assignedCount === offer.subjects.length ? 'i-lucide-circle-check' : 'i-lucide-circle-dashed'"
                        >
                            {{ offer.assignedCount }} de {{ offer.subjects.length }} con titular
                        </UBadge>
                        <div class="w-full sm:w-64">
                            <p class="mb-1 text-xs font-medium text-muted">Coordinador</p>
                            <TeacherPicker
                                :teachers="board.teachers"
                                :model-value="offer.coordinatorId"
                                :disabled="readOnly"
                                :loading="saving === `coordinator-${offer.id}`"
                                :label="`Coordinador de ${offer.label}`"
                                @change="(id) => setCoordinator(offer, id)"
                            />
                        </div>
                    </header>

                    <p v-if="offer.subjects.length === 0" class="flex items-center gap-2 px-5 py-4 text-sm text-warning">
                        <UIcon name="i-lucide-triangle-alert" class="size-4 shrink-0" />
                        Esta sección no tiene un plan de estudios asignado en el periodo. Asígnalo desde Planes de estudio.
                    </p>

                    <table v-else class="w-full text-sm">
                        <thead class="hidden md:table-header-group">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-muted">
                                <th class="px-5 py-2.5">Asignatura</th>
                                <th class="w-72 px-3 py-2.5">Titular</th>
                                <th class="w-72 px-5 py-2.5">Suplente</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-default">
                            <tr v-for="subject in offer.visibleSubjects" :key="subject.id" class="flex flex-col gap-2 px-5 py-3 md:table-row md:p-0">
                                <td class="md:px-5 md:py-2.5">
                                    <p class="font-semibold text-highlighted">{{ subject.name }}</p>
                                    <p class="text-xs text-muted">
                                        <template v-if="subject.weeklyHours">{{ subject.weeklyHours }} h/semana</template>
                                        <template v-if="subject.weeklyHours && subject.code"> · </template>
                                        <template v-if="subject.code">Código {{ subject.code }}</template>
                                    </p>
                                </td>
                                <td class="md:px-3 md:py-2.5">
                                    <span class="mb-1 block text-xs text-muted md:hidden">Titular</span>
                                    <TeacherPicker
                                        :teachers="board.teachers"
                                        :model-value="subject.titular?.teacherId ?? null"
                                        :disabled="readOnly"
                                        :loading="saving === `${offer.id}-${subject.id}-titular`"
                                        :label="`Titular de ${subject.name}, ${offer.label}`"
                                        @change="(id) => assign(offer, subject, 'titular', id)"
                                    />
                                </td>
                                <td class="md:px-5 md:py-2.5">
                                    <span class="mb-1 block text-xs text-muted md:hidden">Suplente</span>
                                    <TeacherPicker
                                        :teachers="board.teachers"
                                        :model-value="subject.substitute?.teacherId ?? null"
                                        placeholder="Sin suplente"
                                        :disabled="readOnly || !subject.titular"
                                        :loading="saving === `${offer.id}-${subject.id}-suplente`"
                                        :label="`Suplente de ${subject.name}, ${offer.label}`"
                                        @change="(id) => assign(offer, subject, 'suplente', id)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </div>

            <!-- By teacher -->
            <div v-else class="grid gap-4 [grid-template-columns:repeat(auto-fill,minmax(19rem,1fr))]">
                <p v-if="workload.length === 0" class="col-span-full rounded-xl border border-dashed border-default px-5 py-10 text-center text-sm text-muted">
                    Nada coincide con la búsqueda.
                </p>
                <article v-for="teacher in workload" :key="teacher.id" class="flex flex-col rounded-xl border border-default bg-default p-5 shadow-card">
                    <header class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate font-bold text-highlighted">{{ teacher.name }}</h2>
                            <p class="truncate text-xs text-muted">{{ teacher.email }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-xl font-bold tabular-nums leading-none text-highlighted">{{ teacher.hours }}</p>
                            <p class="text-xs text-muted">h/semana</p>
                        </div>
                    </header>
                    <p class="mt-2 text-xs text-muted">
                        {{ teacher.items.length }} {{ teacher.items.length === 1 ? 'asignatura' : 'asignaturas' }} · {{ teacher.offers.size }} {{ teacher.offers.size === 1 ? 'sección' : 'secciones' }}
                    </p>
                    <ul v-if="teacher.items.length > 0" class="mt-3 space-y-1.5 border-t border-default pt-3">
                        <li v-for="item in teacher.items" :key="item.key" class="flex items-center gap-2 text-sm">
                            <span class="min-w-0 flex-1 truncate">
                                <span class="font-medium text-highlighted">{{ item.subject }}</span>
                                <span class="text-muted"> · {{ item.offer }}</span>
                            </span>
                            <UBadge v-if="item.role === 'Suplente'" size="sm" color="neutral" variant="outline">Suplente</UBadge>
                            <span v-if="item.hours" class="shrink-0 text-xs tabular-nums text-muted">{{ item.hours }} h</span>
                        </li>
                    </ul>
                    <p v-else class="mt-3 border-t border-default pt-3 text-sm text-muted">Sin carga en este periodo.</p>
                </article>
            </div>
        </template>
    </DashboardLayout>
</template>
