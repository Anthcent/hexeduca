<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    context: { type: Object, required: true },
    summary: { type: Object, required: true },
    pass: { type: Number, default: 10 },
});

const counts = computed(() => {
    const graded = props.summary.rows.filter((row) => row.year !== null);

    return {
        total: props.summary.rows.length,
        graded: graded.length,
        failing: graded.filter((row) => !row.passes).length,
    };
});

function pad(value) {
    return String(value).padStart(2, '0');
}

function gradeClass(value) {
    return value < props.pass ? 'text-error' : 'text-highlighted';
}
</script>

<template>
    <Head :title="`Resumen anual · ${context.subjectName}`" />

    <DashboardLayout active="notas">
        <PageHeader :eyebrow="`Resumen anual · ${context.periodName}`" :title="context.subjectName" :description="context.offerLabel" icon="i-lucide-sigma">
            <template #leading>
                <UButton :to="route('grades.index', { period: context.periodId }, false)" color="neutral" variant="link" icon="i-lucide-arrow-left" class="px-0">
                    Volver a notas
                </UButton>
            </template>
        </PageHeader>

        <EmptyState
            v-if="summary.moments.length === 0"
            icon="i-lucide-calendar-x"
            title="El periodo no tiene momentos académicos"
            description="Crea los momentos del periodo para ver el resumen anual."
        />
        <EmptyState
            v-else-if="summary.rows.length === 0"
            icon="i-lucide-users"
            title="La sección no tiene estudiantes inscritos"
            description="Cuando haya estudiantes inscritos, aquí verás sus notas del año."
        />

        <template v-else>
            <p class="mb-4 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted">
                <span><span class="font-semibold tabular-nums text-highlighted">{{ counts.graded }}</span> de {{ counts.total }} con definitiva</span>
                <span v-if="counts.failing > 0" class="text-error"><span class="font-semibold tabular-nums">{{ counts.failing }}</span> aplazados</span>
            </p>

            <div class="overflow-x-auto rounded-xl border border-default bg-default shadow-card">
                <table class="w-full min-w-[32rem] text-sm">
                    <thead class="border-b border-default bg-elevated/50 text-xs text-muted">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left font-semibold">Estudiante</th>
                            <th v-for="moment in summary.moments" :key="moment.id" scope="col" class="px-3 py-3 text-center font-semibold">
                                <ULink v-if="moment.planId" :to="route('grades.sheet', moment.planId, false)" class="hover:text-highlighted">{{ moment.name }}</ULink>
                                <span v-else :title="'Sin plan de evaluación'">{{ moment.name }}</span>
                            </th>
                            <th scope="col" class="px-4 py-3 text-center font-semibold text-highlighted">Definitiva</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-default">
                        <tr v-for="row in summary.rows" :key="row.id">
                            <th scope="row" class="px-4 py-2.5 text-left font-medium text-highlighted">{{ row.name }}</th>
                            <td v-for="(grade, index) in row.moments" :key="summary.moments[index].id" class="px-3 py-2.5 text-center tabular-nums">
                                <span v-if="grade.final === null" class="text-dimmed">—</span>
                                <span v-else-if="!grade.complete" class="text-dimmed" title="Carga incompleta">{{ pad(grade.final) }}*</span>
                                <span v-else :class="gradeClass(grade.final)">{{ pad(grade.final) }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                <span v-if="row.year === null" class="text-dimmed">—</span>
                                <span
                                    v-else
                                    class="inline-block min-w-10 rounded-md px-2 py-0.5 font-bold tabular-nums"
                                    :class="row.passes ? 'bg-success/10 text-success' : 'bg-error/10 text-error'"
                                >{{ pad(row.year) }}</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="mt-3 text-xs text-muted">
                La definitiva es el promedio de los momentos, redondeado, y aparece cuando todos tienen la carga completa. * Carga incompleta.
            </p>
        </template>
    </DashboardLayout>
</template>
