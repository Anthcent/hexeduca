<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import DataToolbar from '@/Components/DataToolbar.vue';
import EmptyState from '@/Components/EmptyState.vue';
import DateInput from '@/Components/DateInput.vue';
import { tableUi } from '@/Components/tableUi';
import { formatDay } from '@/lib/dates';

const props = defineProps({
    periods: {
        type: Array,
        default: () => [],
    },
});

// Search and status chips run client-side over the full list.
const search = ref('');
const status = ref('all');

const statuses = computed(() => {
    const active = props.periods.filter((period) => period.is_active).length;

    return [
        { value: 'all', label: 'Todos', count: props.periods.length },
        { value: 'active', label: 'Activo', count: active },
        { value: 'inactive', label: 'Inactivos', count: props.periods.length - active },
    ];
});

const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');

    return props.periods.filter((period) => {
        if (status.value === 'active' && !period.is_active) return false;
        if (status.value === 'inactive' && period.is_active) return false;

        return !term || period.name.toLocaleLowerCase('es').includes(term);
    });
});

const isFiltered = computed(() => search.value.trim() !== '' || status.value !== 'all');

function resetFilters() {
    search.value = '';
    status.value = 'all';
}

const columns = [
    { accessorKey: 'name', header: 'Nombre' },
    { accessorKey: 'starts_on', header: 'Desde' },
    { accessorKey: 'ends_on', header: 'Hasta' },
    { accessorKey: 'is_active', header: 'Estado', meta: { class: { th: 'w-32' } } },
];

// Create

const createOpen = ref(false);

const form = useForm({
    name: '',
    starts_on: '',
    ends_on: '',
});

function openCreate() {
    form.reset();
    form.clearErrors();
    createOpen.value = true;
}

function submit() {
    form.post(route('academic-periods.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            createOpen.value = false;
        },
    });
}

const activating = ref(null);

function activate(period) {
    activating.value = period.id;
    useForm({}).post(route('academic-periods.activate', period.id), {
        preserveScroll: true,
        onFinish: () => { activating.value = null; },
    });
}
</script>

<template>
    <Head title="Períodos académicos" />

    <DashboardLayout active="academico">
        <PageHeader
            eyebrow="Académico"
            title="Períodos académicos"
            description="Años escolares de la institución. Solo un período puede estar activo a la vez."
        >
            <template #actions>
                <UButton icon="i-lucide-plus" size="lg" @click="openCreate">Nuevo período</UButton>
            </template>
        </PageHeader>

        <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
                <div class="space-y-4">
                    <PanelHeader kicker="Calendario" :title="filtered.length === 1 ? '1 período' : `${filtered.length} períodos`" />
                    <DataToolbar v-model:search="search" v-model:status="status" placeholder="Buscar período" :statuses="statuses" />
                </div>
            </template>

            <EmptyState
                v-if="filtered.length === 0"
                :icon="isFiltered ? 'i-lucide-search-x' : 'i-lucide-calendar-range'"
                :title="isFiltered ? 'Sin resultados' : 'Todavía no hay períodos académicos'"
                :description="isFiltered ? 'Prueba con otro término o quita los filtros.' : 'Crea un período, por ejemplo 2026-2027, y actívalo.'"
                :actions="isFiltered
                    ? [{ label: 'Quitar filtros', color: 'neutral', variant: 'outline', icon: 'i-lucide-rotate-ccw', onClick: resetFilters }]
                    : [{ label: 'Nuevo período', icon: 'i-lucide-plus', onClick: openCreate }]"
            />

            <template v-else>
                <UTable :data="filtered" :columns="columns" :ui="tableUi" class="hidden sm:block">
                    <template #name-cell="{ row }"><span class="font-semibold text-highlighted">{{ row.original.name }}</span></template>
                    <template #starts_on-cell="{ row }">{{ formatDay(row.original.starts_on) }}</template>
                    <template #ends_on-cell="{ row }">{{ formatDay(row.original.ends_on) }}</template>
                    <template #is_active-cell="{ row }">
                        <UBadge v-if="row.original.is_active" color="success" variant="subtle" icon="i-lucide-circle-check" class="rounded-full">Activo</UBadge>
                        <UButton
                            v-else
                            color="neutral"
                            variant="outline"
                            size="sm"
                            class="rounded-full"
                            :loading="activating === row.original.id"
                            @click="activate(row.original)"
                        >
                            Activar
                        </UButton>
                    </template>
                </UTable>

                <ul class="divide-y divide-default sm:hidden">
                    <li v-for="period in filtered" :key="period.id" class="flex items-center gap-3 px-4 py-3.5">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-highlighted">{{ period.name }}</p>
                            <p class="text-sm text-muted">{{ formatDay(period.starts_on) }} – {{ formatDay(period.ends_on) }}</p>
                        </div>
                        <UBadge v-if="period.is_active" color="success" variant="subtle" class="shrink-0 rounded-full">Activo</UBadge>
                        <UButton
                            v-else
                            color="neutral"
                            variant="outline"
                            size="sm"
                            class="shrink-0 rounded-full"
                            :loading="activating === period.id"
                            @click="activate(period)"
                        >
                            Activar
                        </UButton>
                    </li>
                </ul>
            </template>
        </UCard>

        <UModal v-model:open="createOpen" title="Nuevo período académico" :dismissible="!form.processing">
            <template #body>
                <form id="form-period" class="space-y-5" novalidate @submit.prevent="submit">
                    <UFormField label="Nombre" name="name" required :error="form.errors.name">
                        <UInput v-model="form.name" placeholder="Ej.: 2026-2027" size="lg" class="w-full" autofocus />
                    </UFormField>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <UFormField label="Desde" name="starts_on" required :error="form.errors.starts_on">
                            <DateInput v-model="form.starts_on" />
                        </UFormField>
                        <UFormField label="Hasta" name="ends_on" required :error="form.errors.ends_on">
                            <DateInput v-model="form.ends_on" />
                        </UFormField>
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UButton color="neutral" variant="ghost" class="justify-center" @click="createOpen = false">Cancelar</UButton>
                    <UButton type="submit" form="form-period" icon="i-lucide-check" class="justify-center" :loading="form.processing" :disabled="form.processing">
                        Crear
                    </UButton>
                </div>
            </template>
        </UModal>
    </DashboardLayout>
</template>
