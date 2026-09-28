<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { planCode, planLabel, SCOPE_LABELS } from '../planLabel';

const props = defineProps({
    periods: { type: Array, default: () => [] },
    period: { type: Object, default: null },
    plans: { type: Array, default: () => [] },
    gradeLevels: { type: Array, default: () => [] },
    board: { type: Object, required: true },
});

const schoolAssignment = computed(() => props.board.assignments.school);
const gradeAssignments = computed(() => props.board.assignments.gradeLevels);
const offerAssignments = computed(() => props.board.assignments.offers);
const offers = computed(() => props.board.offers);

// A closed period is history: it can be viewed but not changed.
const readOnly = computed(() => props.period !== null && !props.period.isOpen);

// Period selector

const periodItems = computed(() => props.periods.map((p) => ({ label: p.isActive ? `${p.name} (activo)` : p.name, value: p.id })));
const selectedPeriod = computed({
    get: () => props.period?.id ?? null,
    set: (id) => router.get(route('subjects.assignments.index', undefined, false), { period: id }, { preserveScroll: true }),
});

// Assign / change a plan in one slot

const assignOpen = ref(false);
const assignForm = useForm({ period_id: null, plan_id: null, scope: 'school', grade_level_id: null, offer_id: null });
const planItems = computed(() => props.plans.map((plan) => ({ label: planLabel(plan), value: plan.id })));
const gradeItems = computed(() => props.gradeLevels.map((g) => ({ label: g.name, value: g.id })));
const offerItems = computed(() => offers.value.map((o) => ({ label: o.label, value: o.id })));

const ASSIGN_TITLES = {
    school: 'Plan por defecto del colegio',
    grade_level: 'Plan para un año',
    offer: 'Plan para una sección',
};

function openAssign(scope, current = null) {
    assignForm.defaults({
        period_id: props.period?.id ?? null,
        plan_id: current?.plan && !current.plan.archived ? current.plan.id : null,
        scope,
        grade_level_id: current?.gradeLevelId ?? null,
        offer_id: current?.offerId ?? null,
    });
    assignForm.reset();
    assignForm.clearErrors();
    assignOpen.value = true;
}

// The assignment that holds the slot the form targets, if any.
function slotHolder() {
    if (assignForm.scope === 'school') return schoolAssignment.value;
    if (assignForm.scope === 'grade_level') return gradeAssignments.value.find((a) => a.gradeLevelId === assignForm.grade_level_id) ?? null;

    return offerAssignments.value.find((a) => a.offerId === assignForm.offer_id) ?? null;
}

// Replacing a plan drops the slot's exclusions (an archived plan keeps them as history).
const lossWarning = ref({ open: false, description: '' });

function exclusionLossDescription(holder) {
    const count = holder.excludedCount;
    const subjects = count === 1 ? '1 asignatura excluida' : `${count} asignaturas excluidas`;
    const owner = assignForm.scope === 'school'
        ? 'El plan del colegio tiene'
        : assignForm.scope === 'grade_level'
            ? `El año «${holder.targetLabel}» tiene`
            : `La sección «${holder.targetLabel}» tiene`;

    return `${owner} ${subjects}. Se perderán al cambiar el plan.`;
}

function submitAssign() {
    const holder = slotHolder();

    if (holder && holder.plan?.id !== assignForm.plan_id && !holder.plan?.archived && holder.excludedCount > 0) {
        lossWarning.value = { open: true, description: exclusionLossDescription(holder) };

        return;
    }

    postAssign();
}

function postAssign() {
    assignForm.post(route('subjects.assignments.store'), {
        preserveScroll: true,
        onSuccess: () => (assignOpen.value = false),
        onFinish: () => (lossWarning.value.open = false),
    });
}

// Remove an assignment

const confirm = ref({ open: false, title: '', description: '', label: 'Quitar', icon: 'i-lucide-x', color: 'error', run: null });
const confirming = ref(false);

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

function askRemove(assignment) {
    const fallback = assignment.scope === 'offer'
        ? 'La sección volverá a usar el plan de su año o del colegio.'
        : assignment.scope === 'grade_level'
            ? 'Las secciones de este año volverán a usar el plan del colegio.'
            : 'Las secciones que no tengan un plan por año o por sección quedarán sin plan.';

    confirm.value = {
        open: true,
        title: `¿Quitar la asignación de ${assignment.targetLabel}?`,
        description: `${fallback} Las exclusiones de esta asignación se perderán.`,
        label: 'Quitar',
        icon: 'i-lucide-x',
        color: 'error',
        run: (options) => router.delete(route('subjects.assignments.destroy', assignment.id), options),
    };
}

// Subjects per offer: include / exclude

const expanded = ref(new Set());

function toggleExpanded(offerId) {
    const next = new Set(expanded.value);
    next.has(offerId) ? next.delete(offerId) : next.add(offerId);
    expanded.value = next;
}

function activeCount(offer) {
    return offer.subjects.filter((s) => s.active).length;
}

function originLabel(offer) {
    return SCOPE_LABELS[offer.effective.scope];
}

function inheritedCount(offer) {
    const id = offer.effective.assignmentId;
    const all = [schoolAssignment.value, ...gradeAssignments.value, ...offerAssignments.value].filter(Boolean);

    return all.find((a) => a.id === id)?.offerCount ?? 1;
}

const pending = ref(null);
const scopeChange = ref({ open: false, offer: null, subject: null, excluded: false });

function toggleSubject(offer, subject, checked) {
    const excluded = !checked;

    if (offer.effective.scope === 'offer') {
        applyToAssignment(offer, subject, excluded);

        return;
    }

    scopeChange.value = { open: true, offer, subject, excluded };
}

function applyToAssignment(offer, subject, excluded) {
    pending.value = `${offer.id}:${subject.id}`;
    router.put(
        route('subjects.assignments.exclusions', offer.effective.assignmentId),
        { subject_id: subject.id, excluded },
        { preserveScroll: true, onFinish: () => finishScopeChange() },
    );
}

function applyToOfferOnly() {
    const { offer, subject, excluded } = scopeChange.value;
    pending.value = `${offer.id}:${subject.id}`;
    router.post(
        route('subjects.assignments.offer-override'),
        { period_id: props.period.id, offer_id: offer.id, subject_id: subject.id, excluded },
        { preserveScroll: true, onFinish: () => finishScopeChange() },
    );
}

function finishScopeChange() {
    pending.value = null;
    scopeChange.value.open = false;
}

const scopeChangeDescription = computed(() => {
    const { offer, subject, excluded } = scopeChange.value;
    if (!offer) return '';

    const origin = offer.effective.scope === 'school' ? 'del colegio' : `del año ${offer.gradeLevelName}`;
    const count = inheritedCount(offer);
    const action = excluded ? 'excluir' : 'incluir';

    return `${offer.label} hereda el plan ${origin}. Si decides ${action} «${subject.name}» en la asignación ${origin}, el cambio afectará a ${count === 1 ? 'la única sección' : `las ${count} secciones`} que la heredan.`;
});
</script>

<template>
    <Head title="Asignaciones de planes" />

    <DashboardLayout active="module:subjects:subjects.index">
        <PageHeader
            eyebrow="Planes de estudio"
            title="Asignaciones por periodo"
            description="Plan del colegio, excepciones por año o por sección y las asignaturas activas de cada sección. Gana la asignación más específica."
            icon="i-lucide-list-checks"
        >
            <template #leading>
                <UButton :to="route('subjects.index', undefined, false)" color="neutral" variant="link" icon="i-lucide-arrow-left" class="px-0">
                    Volver a planes de estudio
                </UButton>
            </template>
            <template v-if="period && !readOnly" #notch>
                <UButton icon="i-lucide-plus" size="lg" :disabled="plans.length === 0" @click="openAssign('school', schoolAssignment)">
                    {{ schoolAssignment ? 'Cambiar plan del colegio' : 'Asignar plan del colegio' }}
                </UButton>
            </template>
        </PageHeader>

        <EmptyState
            v-if="!period"
            icon="i-lucide-calendar-x"
            title="No hay periodos académicos"
            description="Crea un periodo académico para poder asignar planes de estudio."
        />

        <template v-else>
            <UCard class="mb-6 shadow-card">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <UFormField label="Periodo" name="period" class="w-full sm:max-w-xs">
                        <USelect v-model="selectedPeriod" :items="periodItems" class="w-full" />
                    </UFormField>
                    <p v-if="plans.length === 0" class="text-sm text-muted">
                        No hay planes activos para asignar.
                        <ULink :to="route('subjects.index', undefined, false)" class="font-semibold text-primary">Crear un plan</ULink>
                    </p>
                </div>
            </UCard>

            <UAlert
                v-if="readOnly"
                class="mb-6"
                color="neutral"
                variant="subtle"
                icon="i-lucide-lock"
                title="Periodo cerrado: solo lectura"
                description="Puedes consultar sus asignaciones, pero ya no se pueden cambiar."
            />

            <div class="mb-6 grid items-start gap-6 lg:grid-cols-3">
                <!-- School default -->
                <UCard class="shadow-card">
                    <template #header>
                        <PanelHeader kicker="Por defecto" title="Colegio" />
                    </template>
                    <div v-if="schoolAssignment" class="space-y-3">
                        <div>
                            <p class="text-sm font-semibold text-highlighted">{{ planCode(schoolAssignment.plan) }}</p>
                            <p class="text-sm text-muted">{{ schoolAssignment.plan?.name }}</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <UBadge v-if="schoolAssignment.plan?.archived" color="neutral" variant="subtle" icon="i-lucide-archive">Plan archivado</UBadge>
                            <span class="text-xs text-muted">{{ schoolAssignment.offerCount }} {{ schoolAssignment.offerCount === 1 ? 'sección lo usa' : 'secciones lo usan' }}</span>
                        </div>
                        <div v-if="!readOnly" class="flex gap-2">
                            <UButton size="sm" color="neutral" variant="outline" icon="i-lucide-replace" :disabled="plans.length === 0" @click="openAssign('school', schoolAssignment)">Cambiar</UButton>
                            <UButton size="sm" color="error" variant="ghost" icon="i-lucide-x" @click="askRemove(schoolAssignment)">Quitar</UButton>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted">Sin plan por defecto. Las secciones sin excepción no tendrán plan.</p>
                </UCard>

                <!-- Grade level overrides -->
                <UCard class="shadow-card">
                    <template #header>
                        <PanelHeader kicker="Excepciones" title="Por año">
                            <UButton v-if="!readOnly" size="sm" color="neutral" variant="ghost" icon="i-lucide-plus" :disabled="plans.length === 0" @click="openAssign('grade_level')">Agregar</UButton>
                        </PanelHeader>
                    </template>
                    <p v-if="gradeAssignments.length === 0" class="text-sm text-muted">Ningún año tiene un plan distinto al del colegio.</p>
                    <ul v-else class="divide-y divide-default">
                        <li v-for="assignment in gradeAssignments" :key="assignment.id" class="flex items-start gap-2 py-2.5 first:pt-0 last:pb-0">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-highlighted">{{ assignment.targetLabel }}</p>
                                <p class="truncate text-xs text-muted" :title="planLabel(assignment.plan)">
                                    {{ planLabel(assignment.plan) }}<template v-if="assignment.plan?.archived"> · archivado</template>
                                </p>
                            </div>
                            <UButton v-if="!readOnly" size="xs" color="neutral" variant="ghost" icon="i-lucide-replace" :aria-label="`Cambiar el plan de ${assignment.targetLabel}`" :disabled="plans.length === 0" @click="openAssign('grade_level', assignment)" />
                            <UButton v-if="!readOnly" size="xs" color="error" variant="ghost" icon="i-lucide-x" :aria-label="`Quitar la asignación de ${assignment.targetLabel}`" @click="askRemove(assignment)" />
                        </li>
                    </ul>
                </UCard>

                <!-- Offer overrides -->
                <UCard class="shadow-card">
                    <template #header>
                        <PanelHeader kicker="Excepciones" title="Por sección">
                            <UButton v-if="!readOnly" size="sm" color="neutral" variant="ghost" icon="i-lucide-plus" :disabled="plans.length === 0 || offers.length === 0" @click="openAssign('offer')">Agregar</UButton>
                        </PanelHeader>
                    </template>
                    <p v-if="offerAssignments.length === 0" class="text-sm text-muted">Ninguna sección tiene un plan propio.</p>
                    <ul v-else class="divide-y divide-default">
                        <li v-for="assignment in offerAssignments" :key="assignment.id" class="flex items-start gap-2 py-2.5 first:pt-0 last:pb-0">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-highlighted">{{ assignment.targetLabel }}</p>
                                <p class="truncate text-xs text-muted" :title="planLabel(assignment.plan)">
                                    {{ planLabel(assignment.plan) }}<template v-if="assignment.plan?.archived"> · archivado</template>
                                </p>
                            </div>
                            <UButton v-if="!readOnly" size="xs" color="neutral" variant="ghost" icon="i-lucide-replace" :aria-label="`Cambiar el plan de ${assignment.targetLabel}`" :disabled="plans.length === 0" @click="openAssign('offer', assignment)" />
                            <UButton v-if="!readOnly" size="xs" color="error" variant="ghost" icon="i-lucide-x" :aria-label="`Quitar la asignación de ${assignment.targetLabel}`" @click="askRemove(assignment)" />
                        </li>
                    </ul>
                </UCard>
            </div>

            <!-- Offers with their effective plan and subjects -->
            <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0' }">
                <template #header>
                    <PanelHeader kicker="Secciones del periodo" :title="offers.length === 1 ? '1 sección' : `${offers.length} secciones`" />
                </template>

                <EmptyState
                    v-if="offers.length === 0"
                    icon="i-lucide-school"
                    title="Este periodo no tiene secciones"
                    description="Cuando se creen las ofertas académicas del periodo, aparecerán aquí con su plan."
                />

                <ul v-else class="divide-y divide-default">
                    <li v-for="offer in offers" :key="offer.id">
                        <div class="flex flex-col gap-2 px-5 py-3.5 sm:flex-row sm:items-center">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-highlighted">{{ offer.label }}</p>
                                <p v-if="offer.effective" class="truncate text-xs text-muted" :title="planLabel(offer.effective.plan)">
                                    {{ planLabel(offer.effective.plan) }}
                                </p>
                                <p v-else class="text-xs text-warning">Sin plan asignado</p>
                            </div>
                            <div v-if="offer.effective" class="flex flex-wrap items-center gap-2">
                                <UBadge color="neutral" variant="subtle">Desde: {{ originLabel(offer) }}</UBadge>
                                <UBadge v-if="offer.effective.plan?.archived" color="neutral" variant="subtle" icon="i-lucide-archive">Archivado</UBadge>
                                <UButton
                                    size="sm"
                                    color="neutral"
                                    variant="outline"
                                    :trailing-icon="expanded.has(offer.id) ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
                                    :aria-expanded="expanded.has(offer.id)"
                                    @click="toggleExpanded(offer.id)"
                                >
                                    Asignaturas {{ activeCount(offer) }}/{{ offer.subjects.length }}
                                </UButton>
                            </div>
                        </div>

                        <div v-if="offer.effective && expanded.has(offer.id)" class="border-t border-default bg-elevated/40 px-5 py-4">
                            <p v-if="offer.effective.plan?.archived" class="mb-3 text-xs text-muted">
                                El plan está archivado: sus asignaturas son de solo lectura. Asigna otro plan para cambiarlas.
                            </p>
                            <p v-else-if="!readOnly && offer.effective.scope !== 'offer'" class="mb-3 text-xs text-muted">
                                Esta sección hereda el plan {{ offer.effective.scope === 'school' ? 'del colegio' : 'de su año' }}. Al desmarcar una asignatura podrás elegir si el cambio es para todas las secciones que lo heredan o solo para esta.
                            </p>
                            <p v-if="offer.subjects.length === 0" class="text-sm text-muted">El plan no tiene asignaturas activas para {{ offer.gradeLevelName }}.</p>
                            <div v-else class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                                <UCheckbox
                                    v-for="subject in offer.subjects"
                                    :key="subject.id"
                                    :model-value="subject.active"
                                    :label="subject.name"
                                    :description="[subject.code, subject.weeklyHours ? `${subject.weeklyHours} h/semana` : null].filter(Boolean).join(' · ') || undefined"
                                    :disabled="readOnly || offer.effective.plan?.archived || pending !== null"
                                    @update:model-value="(checked) => toggleSubject(offer, subject, checked)"
                                />
                            </div>
                        </div>
                    </li>
                </ul>
            </UCard>
        </template>

        <UModal v-model:open="assignOpen" :title="ASSIGN_TITLES[assignForm.scope]" :dismissible="!assignForm.processing">
            <template #body>
                <form id="form-assign" class="space-y-5" novalidate @submit.prevent="submitAssign">
                    <p class="text-sm text-muted">Periodo: <span class="font-semibold text-highlighted">{{ period?.name }}</span></p>
                    <UFormField v-if="assignForm.scope === 'grade_level'" label="Año" name="grade_level_id" required :error="assignForm.errors.grade_level_id">
                        <USelect v-model="assignForm.grade_level_id" :items="gradeItems" placeholder="Selecciona el año" class="w-full" />
                    </UFormField>
                    <UFormField v-if="assignForm.scope === 'offer'" label="Sección" name="offer_id" required :error="assignForm.errors.offer_id">
                        <USelect v-model="assignForm.offer_id" :items="offerItems" placeholder="Selecciona la sección" class="w-full" />
                    </UFormField>
                    <UFormField label="Plan de estudio" name="plan_id" required :error="assignForm.errors.plan_id">
                        <USelect v-model="assignForm.plan_id" :items="planItems" placeholder="Selecciona el plan" class="w-full" />
                    </UFormField>
                    <p class="text-xs text-muted">Si ya había un plan en este lugar, se reemplaza y se pierden sus exclusiones.</p>
                </form>
            </template>
            <template #footer>
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UButton color="neutral" variant="ghost" class="justify-center" :disabled="assignForm.processing" @click="assignOpen = false">Cancelar</UButton>
                    <UButton type="submit" form="form-assign" icon="i-lucide-check" class="justify-center" :loading="assignForm.processing">Asignar</UButton>
                </div>
            </template>
        </UModal>

        <UModal v-model:open="lossWarning.open" title="¿Cambiar el plan?" :description="lossWarning.description" :dismissible="!assignForm.processing">
            <template #footer>
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UButton color="neutral" variant="ghost" class="justify-center" :disabled="assignForm.processing" @click="lossWarning.open = false">Cancelar</UButton>
                    <UButton color="error" icon="i-lucide-replace" class="justify-center" :loading="assignForm.processing" @click="postAssign">Cambiar plan</UButton>
                </div>
            </template>
        </UModal>

        <UModal
            v-model:open="scopeChange.open"
            :title="scopeChange.excluded ? 'Excluir asignatura' : 'Incluir asignatura'"
            :description="scopeChangeDescription"
            :dismissible="pending === null"
        >
            <template #footer>
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UButton color="neutral" variant="ghost" class="justify-center" :disabled="pending !== null" @click="scopeChange.open = false">Cancelar</UButton>
                    <UButton
                        color="neutral"
                        variant="outline"
                        icon="i-lucide-layers"
                        class="justify-center"
                        :loading="pending !== null"
                        @click="applyToAssignment(scopeChange.offer, scopeChange.subject, scopeChange.excluded)"
                    >
                        Todas las secciones
                    </UButton>
                    <UButton icon="i-lucide-target" class="justify-center" :loading="pending !== null" @click="applyToOfferOnly">
                        Solo esta sección
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
