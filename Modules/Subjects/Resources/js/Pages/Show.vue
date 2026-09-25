<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import PlanFormModal from '../Components/PlanFormModal.vue';
import { planCode, SCOPE_LABELS } from '../planLabel';

const props = defineProps({
    plan: { type: Object, required: true },
    canDelete: { type: Boolean, default: false },
    gradeLevels: { type: Array, default: () => [] },
    subjects: { type: Array, default: () => [] },
    assignments: { type: Array, default: () => [] },
    planCodes: { type: Array, default: () => [] },
});

const archived = computed(() => props.plan.archived);

// Subjects: active by default, with a chip for the archived ones.

const subjectStatus = ref('active');
const activeCount = computed(() => props.subjects.filter((s) => s.status === 'active').length);
const archivedCount = computed(() => props.subjects.length - activeCount.value);

const groups = computed(() => {
    const visible = props.subjects.filter((s) => s.status === subjectStatus.value);
    const known = new Set(props.gradeLevels.map((g) => g.id));

    const result = props.gradeLevels
        .map((grade) => ({ id: grade.id, name: grade.name, subjects: visible.filter((s) => s.gradeLevelId === grade.id) }))
        .filter((group) => group.subjects.length > 0);

    const orphans = visible.filter((s) => !known.has(s.gradeLevelId));
    if (orphans.length > 0) {
        result.push({ id: 'none', name: 'Sin año', subjects: orphans });
    }

    return result;
});

function weeklyTotal(subjects) {
    return subjects.reduce((sum, s) => sum + (s.weeklyHours ?? 0), 0);
}

const gradeItems = computed(() => props.gradeLevels.map((g) => ({ label: g.name, value: g.id })));

// Plan edit

const editPlanOpen = ref(false);

// Subject create / edit

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

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0' }">
                <template #header>
                    <div class="space-y-4">
                        <PanelHeader kicker="Asignaturas" title="Asignaturas por año" />
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
                    </div>
                </template>

                <EmptyState
                    v-if="groups.length === 0"
                    :icon="subjectStatus === 'archived' ? 'i-lucide-archive' : 'i-lucide-notebook-pen'"
                    :title="subjectStatus === 'archived' ? 'No hay asignaturas archivadas' : 'Todavía no hay asignaturas'"
                    :description="subjectStatus === 'archived' ? null : 'Agrega las asignaturas de cada año del plan.'"
                    :actions="subjectStatus === 'active' && !archived && gradeLevels.length > 0 ? [{ label: 'Agregar asignatura', icon: 'i-lucide-plus', onClick: () => openSubject() }] : []"
                />

                <div v-else class="divide-y divide-default">
                    <section v-for="group in groups" :key="group.id" :aria-label="group.name">
                        <div class="flex items-center justify-between gap-3 bg-elevated/60 px-5 py-2.5">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-muted">
                                {{ group.name }}
                                <span class="ms-1 font-normal normal-case tracking-normal">· {{ group.subjects.length }} {{ group.subjects.length === 1 ? 'asignatura' : 'asignaturas' }}<template v-if="weeklyTotal(group.subjects) > 0"> · {{ weeklyTotal(group.subjects) }} h/semana</template></span>
                            </h3>
                            <UButton
                                v-if="!archived && subjectStatus === 'active' && group.id !== 'none'"
                                size="xs"
                                color="neutral"
                                variant="ghost"
                                icon="i-lucide-plus"
                                :aria-label="`Agregar asignatura a ${group.name}`"
                                @click="openSubject(null, group.id)"
                            />
                        </div>
                        <ul class="divide-y divide-default">
                            <li v-for="subject in group.subjects" :key="subject.id" class="flex items-center gap-3 px-5 py-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-semibold text-highlighted" :title="subject.name">{{ subject.name }}</p>
                                    <p class="text-xs text-muted">
                                        <template v-if="subject.code">Código {{ subject.code }}</template>
                                        <template v-if="subject.code && subject.weeklyHours"> · </template>
                                        <template v-if="subject.weeklyHours">{{ subject.weeklyHours }} h/semana</template>
                                        <template v-if="!subject.code && !subject.weeklyHours">Sin código ni horas</template>
                                    </p>
                                </div>
                                <UDropdownMenu v-if="!archived || subject.status === 'archived'" :items="subjectActions(subject)" :content="{ align: 'end' }">
                                    <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis" :aria-label="`Acciones para ${subject.name}`" />
                                </UDropdownMenu>
                            </li>
                        </ul>
                    </section>
                </div>
            </UCard>

            <UCard class="shadow-card xl:sticky xl:top-4">
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
                            <p class="text-xs text-muted">{{ assignment.periodName }}</p>
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
