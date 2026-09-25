<script setup>
import { computed, ref } from 'vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { tableUi } from '@/Components/tableUi';

// Student roster: a table on desktop and a card list below 640px.
// Each student: { initials, name, id, attendance, average, status }.
const props = defineProps({
    students: { type: Array, required: true },
    // Optional link for the "Inscribir" action.
    addTo: { type: String, default: null },
});
defineEmits(['select']);

const search = ref('');

const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');

    return term ? props.students.filter((student) => `${student.name} ${student.id}`.toLocaleLowerCase('es').includes(term)) : props.students;
});

const columns = [
    { accessorKey: 'name', header: 'Estudiante' },
    { accessorKey: 'attendance', header: 'Asistencia' },
    { accessorKey: 'average', header: 'Promedio' },
    { accessorKey: 'status', header: 'Estado' },
];

function statusColor(status) {
    return status === 'Al día' ? 'success' : 'warning';
}
</script>

<template>
    <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0' }">
        <template #header>
            <div class="space-y-4">
                <PanelHeader kicker="Sección" title="Estudiantes de la sección">
                    <UButton v-if="addTo" :to="addTo" icon="i-lucide-user-plus" size="sm">Inscribir</UButton>
                </PanelHeader>
                <UInput v-model="search" icon="i-lucide-search" placeholder="Buscar estudiante" aria-label="Buscar estudiante" class="w-full sm:max-w-xs" />
            </div>
        </template>

        <EmptyState v-if="filtered.length === 0" icon="i-lucide-search-x" title="Sin resultados" description="Prueba con otro nombre." />

        <template v-else>
            <UTable :data="filtered" :columns="columns" :ui="tableUi" class="hidden sm:block" @select="(_, row) => $emit('select', row.original)">
                <template #name-cell="{ row }">
                    <div class="flex items-center gap-3">
                        <UAvatar :text="row.original.initials" size="md" class="bg-primary/10" :ui="{ fallback: 'text-xs font-semibold text-primary' }" />
                        <div class="min-w-0">
                            <p class="font-semibold text-highlighted">{{ row.original.name }}</p>
                            <p class="text-xs text-muted">{{ row.original.id }}</p>
                        </div>
                    </div>
                </template>
                <template #attendance-cell="{ row }"><span class="tabular-nums">{{ row.original.attendance }}%</span></template>
                <template #average-cell="{ row }"><span class="font-semibold tabular-nums text-highlighted">{{ row.original.average }}</span></template>
                <template #status-cell="{ row }">
                    <UBadge :color="statusColor(row.original.status)" variant="subtle" class="rounded-full">{{ row.original.status }}</UBadge>
                </template>
            </UTable>

            <ul class="divide-y divide-default sm:hidden">
                <li v-for="student in filtered" :key="student.name">
                    <button type="button" class="flex w-full items-center gap-3 px-4 py-3.5 text-start transition-colors hover:bg-elevated/50" @click="$emit('select', student)">
                        <UAvatar :text="student.initials" size="md" class="bg-primary/10" :ui="{ fallback: 'text-xs font-semibold text-primary' }" />
                        <span class="min-w-0 flex-1">
                            <strong class="block truncate text-sm font-semibold text-highlighted">{{ student.name }}</strong>
                            <span class="block text-xs text-muted">{{ student.id }} · Asistencia {{ student.attendance }}%</span>
                        </span>
                        <span class="flex flex-col items-end gap-1">
                            <UBadge :color="statusColor(student.status)" variant="subtle" class="rounded-full">{{ student.status }}</UBadge>
                            <strong class="text-sm tabular-nums text-highlighted">{{ student.average }}</strong>
                        </span>
                    </button>
                </li>
            </ul>
        </template>
    </UCard>
</template>
