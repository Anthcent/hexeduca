<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import PlanFormModal from '../Components/PlanFormModal.vue';
import QuickAddSubjects from '../Components/QuickAddSubjects.vue';
import ViewToggle from '../Components/ViewToggle.vue';
import { planCode, pluralize, usageSummary, SCOPE_LABELS } from '../planLabel';

const props = defineProps({
    plan: { type: Object, required: true },
    canDelete: { type: Boolean, default: false },
    gradeLevels: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
    planCodes: { type: Array, default: () => [] },
    activePeriod: { type: Object, default: null },
});

const archived = computed(() => props.plan.archived);

// Subjects: active by default, with a chip for the archived ones.

const subjectStatus = ref('active');
const activeSubjects = computed(() => props.subjects.filter((s) => s.status === 'active'));
const activeCount = computed(() => activeSubjects.value.length);
const archivedCount = computed(() => props.subjects.length - activeCount.value);

// While adding (active subjects of an editable plan), every grade level shows,
// even an empty one, so it always has its quick-entry field.
const canAdd = computed(() => subjectStatus.value === 'active' && !archived.value);

const groups = computed(() => {
    const visible = props.subjects.filter((s) => s.status === subjectStatus.value);
    const known = new Set(props.gradeLevels.map((g) => g.id));

    const result = props.gradeLevels
        .map((grade) => ({ id: grade.id, name: grade.name, subjects: visible.filter((s) => s.gradeLevelId === grade.id) }))
        .filter((group) => canAdd.value || group.subjects.length > 0);

    const orphans = visible.filter((s) => !known.has(s.gradeLevelId));
    if (orphans.length > 0) {
        result.push({ id: 'none', name: 'Sin año', subjects: orphans });
    }

    return result;
});

function weeklyTotal(subjects) {
    return subjects.reduce((sum, s) => sum + (s.weeklyHours ?? 0), 0);
}

// Summary strip: the plan in numbers, and where it runs this period.

const coveredGrades = computed(() => new Set(activeSubjects.value.map((s) => s.gradeLevelId)).size);
const totalHours = computed(() => weeklyTotal(activeSubjects.value));
const activeUsage = computed(() => (props.activePeriod ? props.assignments.filter((a) => a.periodId === props.activePeriod.id) : []));

// List reads subject by subject; columns show the whole plan side by side.

const VIEW_KEY = 'subjects.plan.view';
const viewOptions = [
    { value: 'list', label: 'Lista', icon: 'i-lucide-list' },
    { value: 'columns', label: 'Columnas', icon: 'i-lucide-columns-3' },
];

function storedView() {
    try {
        return localStorage.getItem(VIEW_KEY) === 'columns' ? 'columns' : 'list';
    } catch {
        return 'list';
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

const gradeItems = computed(() => props.gradeLevels.map((g) => ({ label: g.name, value: g.id })));

// Plan edit

const editPlanOpen = ref(false);

// Subject create / edit (full form: name, grade, code, hours)

const subjectOpen = ref(false);
const editingSubject = ref(null);
const subjectForm = useForm({ name: '', code: '', grade_level_id: null, weekly_hours: null });

function openSubject(subject = null, gradeLevelId = null) {
    editingSubject.value = subject;
    subjectForm.defaults({
        name: subject?.name ?? '',
        code: subject?.code ?? '',
        grade_level_id: subject?.gradeLevelId ?? gradeLevelId ?? props.gradeLevels[0]?.id ?? null,
        weekly_hours: subject?.weeklyHours ?? null,
    });
    subjectForm.reset();
    subjectForm.clearErrors();
    subjectOpen.value = true;
}

function saveSubject() {
    const options = { preserveScroll: true, onSuccess: () => (subjectOpen.value = false) };

    if (editingSubject.value) {
        subjectForm.put(route('subjects.subjects.update', [props.plan.id, editingSubject.value.id]), options);
    } else {
        subjectForm.post(route('subjects.subjects.store', props.plan.id), options);
    }
}

// Confirmations: one modal, several actions.

const confirm = ref({ open: false, title: '', description: '', label: '', icon: '', color: 'error', run: null });
const confirming = ref(false);

function ask(options) {
    confirm.value = { ...options, open: true };
}

function runConfirmed() {
    confirming.value = true;
    confirm.value.run({
        preserveScroll: true,
        onFinish: () => {
            confirming.value = false;
            confirm.value.open = false;
        },
    });
}

function askDeletePlan() {
    ask({
        title: '¿Eliminar el plan?',
        description: `Se eliminará el plan ${planCode(props.plan)} de forma permanente.`,
        label: 'Eliminar',
        icon: 'i-lucide-trash-2',
        color: 'error',
        run: (options) => router.delete(route('subjects.plans.destroy', props.plan.id), options),
    });
}

function askArchivePlan() {
    ask({
        title: '¿Archivar el plan?',
        description: 'Quedará de solo lectura y no se podrá asignar a nuevos periodos. Sus asignaciones actuales se conservan como historial hasta que asignes otro plan en su lugar.',
        label: 'Archivar',
        icon: 'i-lucide-archive',
        color: 'warning',
        run: (options) => router.post(route('subjects.plans.archive', props.plan.id), {}, options),
    });
}

function askDeleteSubject(subject) {
    ask({
        title: `¿Eliminar «${subject.name}»?`,
        description: 'Esta acción no se puede deshacer.',
        label: 'Eliminar',
        icon: 'i-lucide-trash-2',
        color: 'error',
        run: (options) => router.delete(route('subjects.subjects.destroy', [props.plan.id, subject.id]), options),
    });
}

function askArchiveSubject(subject) {
    ask({
        title: `¿Archivar «${subject.name}»?`,
        description: 'Ya está en uso en asignaciones, por eso no se puede eliminar. Archivada deja de estar activa en las secciones y se conserva su historial.',
        label: 'Archivar',
        icon: 'i-lucide-archive',
        color: 'warning',
        run: (options) => router.post(route('subjects.subjects.archive', [props.plan.id, subject.id]), {}, options),
    });
}

function subjectActions(subject) {
    if (subject.status === 'archived') {
        return [[{
            label: 'Revisar reactivación',
            icon: 'i-lucide-rotate-ccw',
            to: route('subjects.subjects.reactivation', [props.plan.id, subject.id], false),
        }]];
    }

    return [
        [{ label: 'Editar', icon: 'i-lucide-pencil', onSelect: () => openSubject(subject) }],
        [subject.canDelete
            ? { label: 'Eliminar', icon: 'i-lucide-trash-2', color: 'error', onSelect: () => askDeleteSubject(subject) }
            : { label: 'Archivar', icon: 'i-lucide-archive', onSelect: () => askArchiveSubject(subject) }],
    ];
}

function canAct(subject) {
    return !archived.value || subject.status === 'archived';
}
</script>

<template>
    <Head :title="`Plan ${plan.code}`" />

    <DashboardLayout active="module:subjects:subjects.index">
        <PageHeader eyebrow="Plan de estudio" :title="plan.name" icon="i-lucide-book-open">
            <template #leading>
                <UButton :to="route('subjects.index', undefined, false)" color="neutral" variant="link" icon="i-lucide-arrow-left" class="px-0">
                    Volver a planes de estudio
                </UButton>
            </template>
            <template #description>
                Código <span class="font-semibold text-white">{{ plan.code }}</span>
                <template v-if="plan.observation"> · {{ plan.observation }}</template>
            </template>
            <template #actions>
                <UBadge v-if="archived" color="neutral" variant="solid" icon="i-lucide-archive" class="bg-white/15 text-white">Archivado</UBadge>
                <template v-else>
                    <UButton color="neutral" variant="outline" icon="i-lucide-pencil" class="bg-white/10 text-white ring-white/25 hover:bg-white/20" @click="editPlanOpen = true">
                        Editar
                    </UButton>
                    <UButton
                        v-if="canDelete"
                        color="neutral"
                        variant="outline"
                        icon="i-lucide-trash-2"
                        class="bg-white/10 text-white ring-white/25 hover:bg-white/20"
                        @click="askDeletePlan"
                    >
                        Eliminar
                    </UButton>
                    <UButton
                        v-else
                        color="neutral"
                        variant="outline"
                        icon="i-lucide-archive"
                        class="bg-white/10 text-white ring-white/25 hover:bg-white/20"
                        @click="askArchivePlan"
                    >
                        Archivar
                    </UButton>
                </template>
            </template>
            <template #notch>
                <UButton v-if="archived" icon="i-lucide-rotate-ccw" size="lg" :to="route('subjects.plans.reactivation', plan.id, false)">
                    Revisar reactivación
                </UButton>
                <UButton v-else icon="i-lucide-plus" size="lg" :disabled="gradeLevels.length === 0" @click="openSubject()">
                    Agregar asignatura
                </UButton>
            </template>
        </PageHeader>

        <UAlert
            v-if="archived"
            class="mb-6"
            color="neutral"
            variant="subtle"
            icon="i-lucide-archive"
            title="Plan archivado: solo lectura"
            description="No se puede editar ni asignar. Sus asignaciones se conservan como historial. Para volver a usarlo, revisa la reactivación."
        />
        <UAlert
            v-else-if="gradeLevels.length === 0"
            class="mb-6"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="No hay años registrados"
            description="Crea los años (grados) de la institución para poder agregar asignaturas."
        />

        <dl class="mb-6 grid grid-cols-2 overflow-hidden rounded-xl border border-default bg-default shadow-card lg:grid-cols-4">
            <div class="border-b border-default px-5 py-4 lg:border-b-0 lg:border-e">
                <dt class="text-xs font-medium text-muted">Asignaturas activas</dt>
                <dd class="mt-1 text-2xl font-bold tabular-nums text-highlighted">{{ activeCount }}</dd>
            </div>
            <div class="border-b border-s border-default px-5 py-4 lg:border-b-0 lg:border-s-0 lg:border-e">
                <dt class="text-xs font-medium text-muted">Años cubiertos</dt>
                <dd class="mt-1 text-2xl font-bold tabular-nums text-highlighted">
                    {{ coveredGrades }}<span class="text-base font-semibold text-muted"> de {{ gradeLevels.length }}</span>
                </dd>
            </div>
            <div class="px-5 py-4 lg:border-e lg:border-default">
                <dt class="text-xs font-medium text-muted">Horas semanales</dt>
                <dd class="mt-1 text-2xl font-bold tabular-nums text-highlighted">{{ totalHours }}</dd>
            </div>
            <div class="border-s border-default px-5 py-4 lg:border-s-0" :class="activeUsage.length > 0 ? 'bg-primary/5' : ''">
                <dt class="text-xs font-medium text-muted">{{ activePeriod ? `Uso en ${activePeriod.name}` : 'Uso' }}</dt>
                <dd v-if="activeUsage.length > 0" class="mt-1 flex min-w-0 items-center gap-1.5 text-sm font-semibold text-primary">
                    <UIcon name="i-lucide-circle-check" class="size-4 shrink-0" />
                    <span class="truncate" :title="usageSummary(activeUsage)">{{ usageSummary(activeUsage) }}</span>
                </dd>
                <dd v-else class="mt-1 text-sm font-semibold text-muted">{{ activePeriod ? 'Sin asignar' : 'Sin periodo activo' }}</dd>
            </div>
        </dl>

        <div class="grid items-start gap-6" :class="view === 'list' ? 'xl:grid-cols-[minmax(0,1fr)_22rem]' : ''">
            <UCard class="min-w-0 shadow-card" :ui="{ body: view === 'columns' ? 'p-4 sm:p-5' : 'p-0 sm:p-0' }">
                <template #header>
                    <div class="space-y-4">
                        <PanelHeader kicker="Asignaturas" title="Asignaturas por año">
                            <ViewToggle v-model="view" :options="viewOptions" label="Vista de las asignaturas" />
                        </PanelHeader>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar asignaturas por estado">
                                <UButton
                                    v-for="item in [{ value: 'active', label: 'Activas', count: activeCount }, { value: 'archived', label: 'Archivadas', count: archivedCount }]"
                                    :key="item.value"
                                    size="sm"
                                    :color="subjectStatus === item.value ? 'primary' : 'neutral'"
                                    :variant="subjectStatus === item.value ? 'solid' : 'outline'"
                                    class="rounded-full px-3"
                                    :aria-pressed="subjectStatus === item.value"
                                    @click="subjectStatus = item.value"
                                >
                                    {{ item.label }}
                                    <span class="tabular-nums opacity-70">{{ item.count }}</span>
                                </UButton>
                            </div>
                            <p v-if="canAdd && gradeLevels.length > 0" class="text-xs text-muted">
                                Escribe y presiona Enter para agregar. Pega una lista para cargar varias.
                            </p>
                        </div>
                    </div>
                </template>

                <EmptyState
                    v-if="groups.length === 0"
                    :icon="subjectStatus === 'archived' ? 'i-lucide-archive' : 'i-lucide-notebook-pen'"
                    :title="subjectStatus === 'archived' ? 'No hay asignaturas archivadas' : 'Todavía no hay asignaturas'"
                    :description="subjectStatus === 'archived' ? null : 'Este plan no tiene asignaturas activas.'"
                />

                <!-- List: one section per grade level. -->
                <div v-else-if="view === 'list'" class="divide-y divide-default">
                    <section v-for="group in groups" :key="group.id" :aria-label="group.name">
                        <div class="flex items-center justify-between gap-3 bg-elevated/60 px-5 py-2.5">
                            <h3 class="text-sm font-bold text-highlighted">
                                {{ group.name }}
                                <span class="ms-1 text-xs font-normal text-muted">
                                    {{ pluralize(group.subjects.length, 'asignatura', 'asignaturas') }}<template v-if="weeklyTotal(group.subjects) > 0"> · {{ weeklyTotal(group.subjects) }} h/semana</template>
                                </span>
                            </h3>
                        </div>
                        <ul v-if="group.subjects.length > 0" class="divide-y divide-default">
                            <li v-for="subject in group.subjects" :key="subject.id" class="flex items-center gap-3 px-5 py-2.5">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-highlighted" :title="subject.name">{{ subject.name }}</p>
                                    <p v-if="subject.code" class="text-xs text-muted">Código {{ subject.code }}</p>
                                </div>
                                <span
                                    v-if="subject.weeklyHours"
                                    class="shrink-0 rounded-md bg-elevated px-2 py-0.5 text-xs font-semibold tabular-nums text-default"
                                >
                                    {{ subject.weeklyHours }} h
                                </span>
                                <UDropdownMenu v-if="canAct(subject)" :items="subjectActions(subject)" :content="{ align: 'end' }">
                                    <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis" :aria-label="`Acciones para ${subject.name}`" />
                                </UDropdownMenu>
                            </li>
                        </ul>
                        <p v-else class="px-5 pt-3 text-xs text-muted">Sin asignaturas en este año.</p>
                        <QuickAddSubjects v-if="canAdd && group.id !== 'none'" :plan-id="plan.id" :grade-level="group" />
                    </section>
                </div>

                <!-- Columns: the whole plan side by side, one column per grade level. -->
                <div v-else class="-mx-4 overflow-x-auto px-4 pb-1 sm:-mx-5 sm:px-5">
                    <div class="flex items-start gap-4">
                        <section
                            v-for="group in groups"
                            :key="group.id"
                            :aria-label="group.name"
                            class="flex w-64 shrink-0 flex-col rounded-xl border border-default bg-elevated/40"
                        >
                            <header class="border-b border-default px-4 py-3">
                                <h3 class="truncate text-sm font-bold text-highlighted">{{ group.name }}</h3>
                                <p class="text-xs text-muted">
                                    {{ pluralize(group.subjects.length, 'asignatura', 'asignaturas') }}<template v-if="weeklyTotal(group.subjects) > 0"> · {{ weeklyTotal(group.subjects) }} h</template>
                                </p>
                            </header>
                            <ul class="space-y-1.5 p-2">
                                <li
                                    v-for="subject in group.subjects"
                                    :key="subject.id"
                                    class="flex items-center gap-2 rounded-lg bg-default px-3 py-2 ring-1 ring-default"
                                >
                                    <span class="min-w-0 flex-1 truncate text-sm font-medium text-highlighted" :title="subject.name">{{ subject.name }}</span>
                                    <span v-if="subject.weeklyHours" class="shrink-0 text-xs font-semibold tabular-nums text-muted">{{ subject.weeklyHours }} h</span>
                                    <UDropdownMenu v-if="canAct(subject)" :items="subjectActions(subject)" :content="{ align: 'end' }">
                                        <UButton size="xs" color="neutral" variant="ghost" icon="i-lucide-ellipsis" :aria-label="`Acciones para ${subject.name}`" />
                                    </UDropdownMenu>
                                </li>
                                <li v-if="group.subjects.length === 0" class="px-2 py-1 text-xs text-muted">Sin asignaturas.</li>
                            </ul>
                            <div v-if="canAdd && group.id !== 'none'" class="border-t border-default p-2">
                                <QuickAddSubjects :plan-id="plan.id" :grade-level="group" compact />
                            </div>
                        </section>
                    </div>
                </div>
            </UCard>

            <UCard v-if="view === 'list'" class="shadow-card xl:sticky xl:top-4">
                <template #header>
                    <PanelHeader kicker="Uso" title="Asignaciones vigentes">
                        <UButton :to="route('subjects.assignments.index', undefined, false)" size="sm" color="neutral" variant="ghost" trailing-icon="i-lucide-arrow-right">
                            Ver
                        </UButton>
                    </PanelHeader>
                </template>
                <p v-if="assignments.length === 0" class="text-sm text-muted">Este plan no está asignado en ningún periodo.</p>
                <ul v-else class="space-y-3">
                    <li v-for="assignment in assignments" :key="assignment.id" class="flex items-start gap-3">
                        <UBadge color="neutral" variant="subtle" class="mt-0.5 shrink-0">{{ SCOPE_LABELS[assignment.scope] }}</UBadge>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-highlighted">{{ assignment.targetLabel }}</p>
                            <p class="text-xs text-muted">
                                {{ assignment.periodName }}<template v-if="activePeriod && assignment.periodId === activePeriod.id"> · periodo activo</template>
                            </p>
                        </div>
                    </li>
                </ul>
            </UCard>
        </div>

        <PlanFormModal v-model:open="editPlanOpen" :plan="plan" :plan-codes="planCodes" />

        <UModal v-model:open="subjectOpen" :title="editingSubject ? 'Editar asignatura' : 'Nueva asignatura'" :dismissible="!subjectForm.processing">
            <template #body>
                <form id="form-subject" class="space-y-5" novalidate @submit.prevent="saveSubject">
                    <UFormField label="Nombre" name="name" required :error="subjectForm.errors.name">
                        <UInput v-model="subjectForm.name" :maxlength="150" size="lg" placeholder="Ej.: Matemática" class="w-full" autofocus />
                    </UFormField>
                    <UFormField label="Año" name="grade_level_id" required :error="subjectForm.errors.grade_level_id">
                        <USelect v-model="subjectForm.grade_level_id" :items="gradeItems" size="lg" placeholder="Selecciona el año" class="w-full" />
                    </UFormField>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <UFormField label="Código" name="code" hint="Opcional" :error="subjectForm.errors.code">
                            <UInput v-model="subjectForm.code" :maxlength="50" size="lg" class="w-full" />
                        </UFormField>
                        <UFormField label="Horas semanales" name="weekly_hours" hint="Opcional" :error="subjectForm.errors.weekly_hours">
                            <UInputNumber v-model="subjectForm.weekly_hours" :min="1" :max="60" size="lg" class="w-full" placeholder="—" />
                        </UFormField>
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UButton color="neutral" variant="ghost" class="justify-center" :disabled="subjectForm.processing" @click="subjectOpen = false">Cancelar</UButton>
                    <UButton type="submit" form="form-subject" icon="i-lucide-check" class="justify-center" :loading="subjectForm.processing">
                        {{ editingSubject ? 'Guardar cambios' : 'Agregar' }}
                    </UButton>
                </div>
            </template>
        </UModal>

        <ConfirmModal
            v-model:open="confirm.open"
            :title="confirm.title"
            :description="confirm.description"
            :confirm-label="confirm.label"
            :confirm-icon="confirm.icon"
            :color="confirm.color"
            :loading="confirming"
            @confirm="runConfirmed"
        />
    </DashboardLayout>
</template>
