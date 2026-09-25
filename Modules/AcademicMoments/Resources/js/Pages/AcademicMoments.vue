<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import DataToolbar from '@/Components/DataToolbar.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import DateInput from '@/Components/DateInput.vue';
import { tableUi } from '@/Components/tableUi';
import { useConfirmAction } from '@/composables/useConfirmAction';
import { formatDay } from '@/lib/dates';

const props = defineProps({
    moments: { type: Array, default: () => [] },
    activePeriod: { type: Object, default: null },
});

// Search runs client-side over the full list.
const search = ref('');
const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');

    return term ? props.moments.filter((moment) => moment.name.toLocaleLowerCase('es').includes(term)) : props.moments;
});

const columns = [
    { accessorKey: 'order', header: 'Orden', meta: { class: { th: 'w-24' } } },
    { accessorKey: 'name', header: 'Momento' },
    { id: 'range', header: 'Vigencia' },
    { id: 'actions', header: '', meta: { class: { th: 'w-24', td: 'text-right' } } },
];

// Create

const createOpen = ref(false);
const form = useForm({ name: '', order: 1, starts_on: '', ends_on: '' });

function openCreate() {
    form.reset();
    form.clearErrors();
    createOpen.value = true;
}

function create() {
    form
        .transform((data) => ({ ...data, academic_period_id: props.activePeriod?.id }))
        .post(route('academic-moments.store'), {
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
    runDelete((options) => router.delete(route('academic-moments.destroy', toDelete.value.id), options));
}
</script>

<template>
    <Head title="Momentos académicos" />

    <DashboardLayout active="momentos">
        <PageHeader
            eyebrow="Académico"
            title="Momentos académicos"
            :description="activePeriod ? `Cortes de evaluación del período ${activePeriod.name}.` : 'Cortes de evaluación del período académico activo.'"
            icon="i-lucide-milestone"
        >
            <template #notch>
                <UButton icon="i-lucide-plus" size="lg" :disabled="!activePeriod" @click="openCreate">Nuevo momento</UButton>
            </template>
        </PageHeader>

        <UAlert
            v-if="!activePeriod"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="No hay un período académico activo"
            description="Activa un período para poder crear sus momentos académicos."
            class="mb-6"
        />

        <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
                <div class="space-y-4">
                    <PanelHeader kicker="Calendario" :title="filtered.length === 1 ? '1 momento' : `${filtered.length} momentos`">
                        <UBadge v-if="activePeriod" color="primary" variant="subtle" icon="i-lucide-calendar-range" class="rounded-full">
                            {{ activePeriod.name }}
                        </UBadge>
                    </PanelHeader>
                    <DataToolbar v-model:search="search" placeholder="Buscar momento" />
                </div>
            </template>

            <EmptyState
                v-if="filtered.length === 0"
                :icon="search ? 'i-lucide-search-x' : 'i-lucide-calendar-range'"
                :title="search ? 'Sin resultados' : 'Todavía no hay momentos académicos'"
                :description="search ? 'Prueba con otro término.' : (activePeriod ? 'Crea el primero, por ejemplo 1.er momento.' : null)"
                :actions="!search && activePeriod ? [{ label: 'Nuevo momento', icon: 'i-lucide-plus', onClick: openCreate }] : []"
            />

            <template v-else>
                <UTable :data="filtered" :columns="columns" :ui="tableUi" class="hidden sm:block">
                    <template #order-cell="{ row }"><span class="font-semibold tabular-nums text-highlighted">{{ row.original.order }}</span></template>
                    <template #name-cell="{ row }"><span class="font-semibold text-highlighted">{{ row.original.name }}</span></template>
                    <template #range-cell="{ row }">{{ formatDay(row.original.starts_on) }} – {{ formatDay(row.original.ends_on) }}</template>
                    <template #actions-cell="{ row }">
                        <UTooltip text="Eliminar">
                            <UButton color="error" variant="ghost" icon="i-lucide-trash-2" :aria-label="`Eliminar ${row.original.name}`" @click="askDelete(row.original)" />
                        </UTooltip>
                    </template>
                </UTable>

                <ul class="divide-y divide-default sm:hidden">
                    <li v-for="moment in filtered" :key="moment.id" class="flex items-center gap-3 px-4 py-3.5">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-elevated text-sm font-semibold tabular-nums">{{ moment.order }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-highlighted">{{ moment.name }}</p>
                            <p class="text-sm text-muted">{{ formatDay(moment.starts_on) }} – {{ formatDay(moment.ends_on) }}</p>
                        </div>
                        <UButton color="error" variant="ghost" icon="i-lucide-trash-2" :aria-label="`Eliminar ${moment.name}`" @click="askDelete(moment)" />
                    </li>
                </ul>
            </template>
        </UCard>

        <UModal
            v-model:open="createOpen"
            title="Nuevo momento académico"
            :description="activePeriod ? `Se agregará al período ${activePeriod.name}.` : undefined"
            :dismissible="!form.processing"
        >
            <template #body>
                <form id="form-moment" class="space-y-5" novalidate @submit.prevent="create">
                    <div class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_8rem]">
                        <UFormField label="Nombre" name="name" required :error="form.errors.name">
                            <UInput v-model="form.name" placeholder="Ej.: 1.er momento" size="lg" class="w-full" autofocus />
                        </UFormField>
                        <UFormField label="Orden" name="order" required :error="form.errors.order">
                            <UInputNumber v-model="form.order" :min="1" size="lg" class="w-full" />
                        </UFormField>
                    </div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <UFormField label="Inicio" name="starts_on" required :error="form.errors.starts_on">
                            <DateInput v-model="form.starts_on" />
                        </UFormField>
                        <UFormField label="Fin" name="ends_on" required :error="form.errors.ends_on">
                            <DateInput v-model="form.ends_on" />
                        </UFormField>
                    </div>
                    <p v-if="form.errors.academic_period_id" class="text-sm text-error">{{ form.errors.academic_period_id }}</p>
                </form>
            </template>
            <template #footer>
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UButton color="neutral" variant="ghost" class="justify-center" @click="createOpen = false">Cancelar</UButton>
                    <UButton type="submit" form="form-moment" icon="i-lucide-check" class="justify-center" :loading="form.processing">Crear</UButton>
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
