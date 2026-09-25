<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { planCode, planLabel, SCOPE_LABELS } from '../planLabel';

const props = defineProps({
    // 'plan' | 'subject'
    kind: { type: String, required: true },
    plan: { type: Object, required: true },
    subject: { type: Object, default: null },
    // Plan only: { subjectCount, archivedSubjectCount, assignments }
    summary: { type: Object, default: null },
    // Plan only: [{ restored, holder|null }]
    restorations: { type: Array, default: () => [] },
    conflictIds: { type: Array, default: () => [] },
    // Subject only: assignments of open periods where it would be active again.
    coverage: { type: Array, default: () => [] },
    blockedReason: { type: String, default: null },
});

const isPlan = computed(() => props.kind === 'plan');
const hasConflicts = computed(() => props.conflictIds.length > 0);
const approved = ref(false);
const processing = ref(false);

const canSubmit = computed(() => props.blockedReason === null && (!hasConflicts.value || approved.value));

const backUrl = computed(() => route('subjects.plans.show', props.plan.id, false));

function submit() {
    if (!canSubmit.value) return;

    processing.value = true;
    const options = { preserveScroll: true, onFinish: () => (processing.value = false) };

    if (isPlan.value) {
        router.post(
            route('subjects.plans.reactivate', props.plan.id),
            { approved_assignment_ids: hasConflicts.value ? props.conflictIds : [] },
            options,
        );
    } else {
        router.post(route('subjects.subjects.reactivate', [props.plan.id, props.subject.id]), {}, options);
    }
}
</script>

<template>
    <Head :title="isPlan ? 'Reactivar plan' : 'Reactivar asignatura'" />

    <DashboardLayout active="module:subjects:subjects.index">
        <PageHeader
            :eyebrow="isPlan ? 'Reactivar plan de estudio' : 'Reactivar asignatura'"
            :title="isPlan ? planCode(plan) : subject.name"
            :description="isPlan ? plan.name : `${subject.gradeLevelName} · Plan ${planLabel(plan)}`"
            icon="i-lucide-rotate-ccw"
        >
            <template #leading>
                <UButton :to="backUrl" color="neutral" variant="link" icon="i-lucide-arrow-left" class="px-0">Volver al plan</UButton>
            </template>
        </PageHeader>

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <UCard class="shadow-card">
                <template #header>
                    <PanelHeader kicker="Revisión" title="Qué cambiará" />
                </template>

                <!-- Plan: slots it gets back -->
                <template v-if="isPlan">
                    <p v-if="restorations.length === 0" class="text-sm text-muted">
                        El plan vuelve a estar activo y disponible para asignar. No hay asignaciones de periodos vigentes que restaurar.
                    </p>
                    <template v-else>
                        <p class="mb-4 text-sm text-muted">
                            Mientras estuvo archivado, el plan perdió estos lugares en periodos vigentes. Al reactivarlo se restauran
                            <template v-if="hasConflicts">, y los que hoy ocupa otro plan se <strong class="text-highlighted">reemplazan</strong> (sus exclusiones se pierden)</template>.
                        </p>
                        <ul class="divide-y divide-default rounded-lg border border-default">
                            <li v-for="item in restorations" :key="item.restored.id" class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-highlighted">
                                        {{ item.restored.targetLabel }}
                                        <span class="font-normal text-muted">· {{ SCOPE_LABELS[item.restored.scope] }} · {{ item.restored.periodName }}</span>
                                    </p>
                                    <p v-if="item.holder" class="truncate text-xs text-muted" :title="planLabel(item.holder.plan)">
                                        Hoy usa: {{ planLabel(item.holder.plan) }}
                                    </p>
                                    <p v-else class="text-xs text-muted">Hoy está libre.</p>
                                </div>
                                <UBadge v-if="item.holder" color="warning" variant="subtle" icon="i-lucide-triangle-alert">Se reemplaza</UBadge>
                                <UBadge v-else color="success" variant="subtle" icon="i-lucide-check">Se restaura</UBadge>
                            </li>
                        </ul>
                    </template>
                </template>

                <!-- Subject: where it becomes active again -->
                <template v-else>
                    <UAlert
                        v-if="blockedReason === 'plan-archived'"
                        color="warning"
                        variant="subtle"
                        icon="i-lucide-triangle-alert"
                        title="El plan está archivado"
                        description="Reactiva primero el plan para poder reactivar sus asignaturas."
                    />
                    <template v-else>
                        <p v-if="coverage.length === 0" class="text-sm text-muted">
                            La asignatura vuelve a estar activa en el plan. El plan no está asignado en ningún periodo vigente para {{ subject.gradeLevelName }}.
                        </p>
                        <template v-else>
                            <p class="mb-4 text-sm text-muted">Volverá a estar activa en las secciones que usan estas asignaciones:</p>
                            <ul class="divide-y divide-default rounded-lg border border-default">
                                <li v-for="item in coverage" :key="item.id" class="flex items-center gap-3 px-4 py-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold text-highlighted">{{ item.targetLabel }}</p>
                                        <p class="text-xs text-muted">{{ SCOPE_LABELS[item.scope] }} · {{ item.periodName }}</p>
                                    </div>
                                    <UBadge v-if="item.excluded" color="neutral" variant="subtle">Seguirá excluida</UBadge>
                                    <UBadge v-else color="success" variant="subtle" icon="i-lucide-check">Activa</UBadge>
                                </li>
                            </ul>
                        </template>
                    </template>
                </template>

                <template #footer>
                    <div class="space-y-4">
                        <UCheckbox
                            v-if="hasConflicts"
                            v-model="approved"
                            :label="conflictIds.length === 1 ? 'Apruebo reemplazar la asignación marcada' : `Apruebo reemplazar las ${conflictIds.length} asignaciones marcadas`"
                            description="Si no lo apruebas, cancela: no se hará ningún cambio."
                        />
                        <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <UButton :to="backUrl" color="neutral" variant="ghost" size="lg" class="justify-center">Cancelar</UButton>
                            <UButton
                                size="lg"
                                icon="i-lucide-rotate-ccw"
                                class="justify-center"
                                :color="hasConflicts ? 'warning' : 'primary'"
                                :disabled="!canSubmit"
                                :loading="processing"
                                @click="submit"
                            >
                                {{ hasConflicts ? 'Reactivar y reemplazar' : 'Reactivar' }}
                            </UButton>
                        </div>
                    </div>
                </template>
            </UCard>

            <UCard class="shadow-card lg:sticky lg:top-4">
                <template #header>
                    <PanelHeader kicker="Datos" :title="isPlan ? 'El plan tiene' : 'La asignatura'" />
                </template>
                <dl v-if="isPlan" class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted">Asignaturas</dt>
                        <dd class="font-semibold tabular-nums text-highlighted">{{ summary.subjectCount }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted">Asignaturas archivadas</dt>
                        <dd class="font-semibold tabular-nums text-highlighted">{{ summary.archivedSubjectCount }}</dd>
                    </div>
                    <div>
                        <dt class="mb-1.5 text-muted">Asignaciones que conserva</dt>
                        <dd v-if="summary.assignments.length === 0" class="text-highlighted">Ninguna</dd>
                        <dd v-else>
                            <ul class="space-y-1.5">
                                <li v-for="a in summary.assignments" :key="a.id" class="text-highlighted">
                                    {{ a.targetLabel }} <span class="text-muted">· {{ a.periodName }}</span>
                                </li>
                            </ul>
                        </dd>
                    </div>
                    <p class="text-xs text-muted">Las asignaturas archivadas una por una siguen archivadas.</p>
                </dl>
                <dl v-else class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted">Año</dt>
                        <dd class="font-semibold text-highlighted">{{ subject.gradeLevelName }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted">Código</dt>
                        <dd class="font-semibold text-highlighted">{{ subject.code || '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted">Horas semanales</dt>
                        <dd class="font-semibold tabular-nums text-highlighted">{{ subject.weeklyHours ?? '—' }}</dd>
                    </div>
                </dl>
            </UCard>
        </div>
    </DashboardLayout>
</template>
