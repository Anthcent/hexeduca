<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { pluralize, usageSummary } from '../planLabel';

const props = defineProps({
    plan: { type: Object, required: true },
    activePeriod: { type: Object, default: null },
});

const MAX_GRADES = 6;

const archived = computed(() => props.plan.status === 'archived');
const inUse = computed(() => !archived.value && props.plan.usage.length > 0);
const shownGrades = computed(() => props.plan.grades.slice(0, MAX_GRADES));
const hiddenGrades = computed(() => props.plan.grades.length - shownGrades.value.length);
</script>

<template>
    <Link
        :href="route('subjects.plans.show', plan.id, false)"
        class="group flex h-full flex-col rounded-xl border bg-default p-5 transition-colors duration-200 hover:border-primary/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
        :class="inUse ? 'border-primary/40 bg-primary/5' : 'border-default'"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-lg font-bold tabular-nums leading-tight text-highlighted">{{ plan.code }}</p>
                <p v-if="plan.observation" class="truncate text-xs font-medium text-muted" :title="plan.observation">{{ plan.observation }}</p>
            </div>
            <UBadge v-if="archived" color="neutral" variant="subtle" icon="i-lucide-archive" class="shrink-0">Archivado</UBadge>
            <UBadge v-else-if="inUse" color="primary" variant="solid" icon="i-lucide-circle-check" class="shrink-0">
                En uso<template v-if="activePeriod"> en {{ activePeriod.name }}</template>
            </UBadge>
            <UBadge v-else color="neutral" variant="outline" class="shrink-0">Sin asignar</UBadge>
        </div>

        <p class="mt-3 line-clamp-2 text-sm font-semibold text-default" :title="plan.name">{{ plan.name }}</p>

        <ul v-if="shownGrades.length > 0" class="mt-4 flex flex-wrap gap-1.5" aria-label="Asignaturas por año">
            <li
                v-for="grade in shownGrades"
                :key="grade.gradeLevelId"
                class="inline-flex items-center gap-1 rounded-md bg-elevated px-2 py-1 text-xs text-default"
            >
                {{ grade.name }}
                <span class="font-semibold tabular-nums text-highlighted">{{ grade.subjectCount }}</span>
            </li>
            <li v-if="hiddenGrades > 0" class="inline-flex items-center rounded-md px-2 py-1 text-xs text-muted">+{{ hiddenGrades }} años</li>
        </ul>
        <p v-else class="mt-4 text-xs text-muted">Sin asignaturas todavía.</p>

        <div class="min-h-5 grow" aria-hidden="true" />

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-default pt-3 text-xs text-muted">
            <span class="inline-flex items-center gap-1">
                <UIcon name="i-lucide-notebook-pen" class="size-3.5" />
                {{ pluralize(plan.subjectCount, 'asignatura', 'asignaturas') }}
            </span>
            <span v-if="plan.weeklyHours > 0" class="inline-flex items-center gap-1">
                <UIcon name="i-lucide-clock" class="size-3.5" />
                {{ plan.weeklyHours }} h/semana
            </span>
            <span v-if="inUse" class="inline-flex min-w-0 items-center gap-1 text-primary">
                <UIcon name="i-lucide-map-pin" class="size-3.5 shrink-0" />
                <span class="truncate">{{ usageSummary(plan.usage) }}</span>
            </span>
        </div>
    </Link>
</template>
