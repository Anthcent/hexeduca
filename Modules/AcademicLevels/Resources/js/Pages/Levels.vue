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
    levels: { type: Array, default: () => [] },
});

// Search runs client-side over the full list.
const search = ref('');
const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');

    return term ? props.levels.filter((level) => level.name.toLocaleLowerCase('es').includes(term)) : props.levels;
});

const columns = [
    { accessorKey: 'name', header: 'Nombre' },
    { id: 'actions', header: '', meta: { class: { th: 'w-24', td: 'text-right' } } },
];

// Create

const createOpen = ref(false);
const form = useForm({ name: '' });

function openCreate() {
    form.reset();
    form.clearErrors();
    createOpen.value = true;
}

function create() {
    form.post(route('academic-levels.store'), {
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
    runDelete((options) => router.delete(route('academic-levels.destroy', toDelete.value.id), options));
}
</script>

<template>
    <Head title="Niveles académicos" />

    <DashboardLayout active="academico">
        <PageHeader
            eyebrow="Académico"
            title="Niveles académicos"
            description="Etapas de la institución (por ejemplo Primaria o Secundaria) a las que pertenecen los grados."
        >
            <template #actions>
                <UButton icon="i-lucide-plus" size="lg" @click="openCreate">Nuevo nivel</UButton>
            </template>
        </PageHeader>

        <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
                <div class="space-y-4">
                    <PanelHeader kicker="Catálogo" :title="filtered.length === 1 ? '1 nivel' : `${filtered.length} niveles`" />
                    <DataToolbar v-model:search="search" placeholder="Buscar nivel" />
                </div>
            </template>

            <EmptyState
                v-if="filtered.length === 0"
                :icon="search ? 'i-lucide-search-x' : 'i-lucide-layers'"
                :title="search ? 'Sin resultados' : 'Todavía no hay niveles académicos'"
                :description="search ? 'Prueba con otro término.' : 'Crea el primero para poder organizar los grados.'"
                :actions="search ? [] : [{ label: 'Nuevo nivel', icon: 'i-lucide-plus', onClick: openCreate }]"
            />

            <UTable v-else :data="filtered" :columns="columns" :ui="tableUi">
                <template #name-cell="{ row }">
                    <div class="flex items-center gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                            <UIcon name="i-lucide-layers" class="size-4" />
                        </span>
                        <span class="font-semibold text-highlighted">{{ row.original.name }}</span>
                    </div>
                </template>
                <template #actions-cell="{ row }">
                    <UTooltip text="Eliminar">
                        <UButton color="error" variant="ghost" icon="i-lucide-trash-2" :aria-label="`Eliminar ${row.original.name}`" @click="askDelete(row.original)" />
                    </UTooltip>
                </template>
            </UTable>
        </UCard>

        <UModal v-model:open="createOpen" title="Nuevo nivel académico" :dismissible="!form.processing">
            <template #body>
                <form id="form-level" novalidate @submit.prevent="create">
                    <UFormField label="Nombre" name="name" required :error="form.errors.name">
                        <UInput v-model="form.name" placeholder="Ej.: Primaria" size="lg" class="w-full" autofocus />
                    </UFormField>
                </form>
            </template>
            <template #footer>
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UButton color="neutral" variant="ghost" class="justify-center" @click="createOpen = false">Cancelar</UButton>
                    <UButton type="submit" form="form-level" icon="i-lucide-check" class="justify-center" :loading="form.processing">Crear</UButton>
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
