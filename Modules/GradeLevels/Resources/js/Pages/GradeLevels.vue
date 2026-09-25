<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import DataToolbar from '@/Components/DataToolbar.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { tableUi } from '@/Components/tableUi';
import { useConfirmAction } from '@/composables/useConfirmAction';

const props = defineProps({
    gradeLevels: { type: Array, default: () => [] },
    academicLevels: { type: Array, default: () => [] },
});

// Search and the level filter run client-side over the full list.
const search = ref('');
const levelFilter = ref('all');

const levelFilterItems = computed(() => [
    { value: 'all', label: 'Todos los niveles' },
    ...props.academicLevels.map((level) => ({ value: level.id, label: level.name })),
]);
const filterCount = computed(() => (levelFilter.value !== 'all' ? 1 : 0));

const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');

    return props.gradeLevels.filter((gradeLevel) => {
        if (levelFilter.value !== 'all' && gradeLevel.academic_level_id !== levelFilter.value) return false;
        if (!term) return true;

        return `${gradeLevel.name} ${gradeLevel.academic_level_name}`.toLocaleLowerCase('es').includes(term);
    });
});

const isFiltered = computed(() => search.value.trim() !== '' || filterCount.value > 0);

function resetFilters() {
    search.value = '';
    levelFilter.value = 'all';
}

const columns = [
    { accessorKey: 'order', header: 'Orden', meta: { class: { th: 'w-24' } } },
    { accessorKey: 'name', header: 'Grado' },
    { accessorKey: 'academic_level_name', header: 'Nivel' },
    { id: 'actions', header: '', meta: { class: { th: 'w-24', td: 'text-right' } } },
];

// Create

const createOpen = ref(false);
const form = useForm({ name: '', order: 1, academic_level_id: null });

function openCreate() {
    form.reset();
    form.clearErrors();
    createOpen.value = true;
}

function create() {
    form.post(route('grade-levels.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            createOpen.value = false;
        },
    });
}

// Delete

const { target: toDelete, open: deleteOpen, processing: deleting, ask: askDelete, run: runDelete, clear: clearDelete } = useConfirmAction();

function confirmDelete() {
    runDelete((options) => router.delete(route('grade-levels.destroy', toDelete.value.id), options));
}
</script>

<template>
    <Head title="Grados" />

    <DashboardLayout active="academico">
        <PageHeader
            eyebrow="Académico"
            title="Grados"
            description="Años de estudio de cada nivel académico, en el orden en que se cursan."
        >
            <template #actions>
                <UButton icon="i-lucide-plus" size="lg" :disabled="academicLevels.length === 0" @click="openCreate">Nuevo grado</UButton>
            </template>
        </PageHeader>

        <UAlert
            v-if="academicLevels.length === 0"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="Primero crea un nivel académico"
            description="Cada grado pertenece a un nivel académico."
            class="mb-6"
        />

        <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
                <div class="space-y-4">
                    <PanelHeader kicker="Catálogo" :title="filtered.length === 1 ? '1 grado' : `${filtered.length} grados`" />
                    <DataToolbar
                        v-model:search="search"
                        placeholder="Buscar grado o nivel"
                        :filter-count="filterCount"
                        @clear-filters="levelFilter = 'all'"
                    >
                        <template v-if="academicLevels.length > 1" #filters>
                            <UFormField label="Nivel académico">
                                <URadioGroup v-model="levelFilter" :items="levelFilterItems" />
                            </UFormField>
                        </template>
                    </DataToolbar>
                </div>
            </template>

            <EmptyState
                v-if="filtered.length === 0"
                :icon="isFiltered ? 'i-lucide-search-x' : 'i-lucide-graduation-cap'"
                :title="isFiltered ? 'Sin resultados' : 'Todavía no hay grados'"
                :description="isFiltered ? 'Prueba con otro término o quita los filtros.' : null"
                :actions="isFiltered ? [{ label: 'Quitar filtros', color: 'neutral', variant: 'outline', icon: 'i-lucide-rotate-ccw', onClick: resetFilters }] : []"
            />

            <template v-else>
                <UTable :data="filtered" :columns="columns" :ui="tableUi" class="hidden sm:block">
                    <template #order-cell="{ row }"><span class="font-semibold tabular-nums text-highlighted">{{ row.original.order }}</span></template>
                    <template #name-cell="{ row }"><span class="font-semibold text-highlighted">{{ row.original.name }}</span></template>
                    <template #academic_level_name-cell="{ row }">
                        <UBadge color="primary" variant="subtle" class="rounded-full">{{ row.original.academic_level_name }}</UBadge>
                    </template>
                    <template #actions-cell="{ row }">
                        <UTooltip text="Eliminar">
                            <UButton color="error" variant="ghost" icon="i-lucide-trash-2" :aria-label="`Eliminar ${row.original.name}`" @click="askDelete(row.original)" />
                        </UTooltip>
                    </template>
                </UTable>

                <ul class="divide-y divide-default sm:hidden">
                    <li v-for="gradeLevel in filtered" :key="gradeLevel.id" class="flex items-center gap-3 px-4 py-3.5">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-elevated text-sm font-semibold tabular-nums">{{ gradeLevel.order }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-highlighted">{{ gradeLevel.name }}</p>
                            <p class="truncate text-sm text-muted">{{ gradeLevel.academic_level_name }}</p>
                        </div>
                        <UButton color="error" variant="ghost" icon="i-lucide-trash-2" :aria-label="`Eliminar ${gradeLevel.name}`" @click="askDelete(gradeLevel)" />
                    </li>
                </ul>
            </template>
        </UCard>

        <UModal v-model:open="createOpen" title="Nuevo grado" :dismissible="!form.processing">
            <template #body>
                <form id="form-grade-level" class="space-y-5" novalidate @submit.prevent="create">
                    <UFormField label="Nombre" name="name" required :error="form.errors.name">
                        <UInput v-model="form.name" placeholder="Ej.: 3.º" size="lg" class="w-full" autofocus />
                    </UFormField>
                    <div class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_8rem]">
                        <UFormField label="Nivel académico" name="academic_level_id" required :error="form.errors.academic_level_id">
                            <USelect
                                v-model="form.academic_level_id"
                                :items="academicLevels"
                                value-key="id"
                                label-key="name"
                                placeholder="Selecciona un nivel"
                                size="lg"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField label="Orden" name="order" required :error="form.errors.order">
                            <UInputNumber v-model="form.order" :min="1" size="lg" class="w-full" />
                        </UFormField>
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UButton color="neutral" variant="ghost" class="justify-center" @click="createOpen = false">Cancelar</UButton>
                    <UButton type="submit" form="form-grade-level" icon="i-lucide-check" class="justify-center" :loading="form.processing">Crear</UButton>
                </div>
            </template>
        </UModal>

        <ConfirmModal
            v-model:open="deleteOpen"
            :title="`¿Eliminar «${toDelete?.name ?? ''}»?`"
            description="Esta acción no se puede deshacer."
            :loading="deleting"
            @confirm="confirmDelete"
            @after:leave="clearDelete"
        />
    </DashboardLayout>
</template>
