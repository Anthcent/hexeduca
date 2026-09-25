<script setup>
import { computed, ref, watch } from 'vue';
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
import { formatDay, toDay } from '@/lib/dates';

const props = defineProps({
    niveles: { type: Array, default: () => [] },
    grados: { type: Array, default: () => [] },
    secciones: { type: Array, default: () => [] },
    periodos: { type: Array, default: () => [] },
});

const activeTab = ref('niveles');

const tabs = computed(() => [
    { value: 'niveles', label: 'Niveles', icon: 'i-lucide-layers', badge: props.niveles.length },
    { value: 'grados', label: 'Grados', icon: 'i-lucide-graduation-cap', badge: props.grados.length },
    { value: 'secciones', label: 'Secciones', icon: 'i-lucide-users', badge: props.secciones.length },
    { value: 'periodos', label: 'Períodos', icon: 'i-lucide-calendar-range', badge: props.periodos.length },
]);

// Per-tab panel copy and the "new" action shown in the page header.
const PANELS = {
    niveles: { kicker: 'Catálogo', title: 'Niveles académicos', newLabel: 'Nuevo nivel', search: 'Buscar nivel' },
    grados: { kicker: 'Catálogo', title: 'Grados', newLabel: 'Nuevo grado', search: 'Buscar grado o nivel' },
    secciones: { kicker: 'Catálogo', title: 'Secciones', newLabel: 'Nueva sección', search: 'Buscar sección' },
    periodos: { kicker: 'Calendario', title: 'Períodos académicos', newLabel: 'Nuevo período', search: 'Buscar período' },
};
const panel = computed(() => PANELS[activeTab.value]);

// Every catalog arrives complete, so search and chips filter client-side.
const search = ref('');
const periodStatus = ref('all');
const gradoNivel = ref('all');

watch(activeTab, () => {
    search.value = '';
});

function matches(text) {
    const term = search.value.trim().toLocaleLowerCase('es');

    return !term || String(text ?? '').toLocaleLowerCase('es').includes(term);
}

const filteredNiveles = computed(() => props.niveles.filter((row) => matches(row.name)));
const filteredGrados = computed(() => props.grados.filter((row) => (gradoNivel.value === 'all' || row.nivel_academico_id === gradoNivel.value)
    && matches(`${row.name} ${row.nivel_academico?.name ?? ''}`)));
const filteredSecciones = computed(() => props.secciones.filter((row) => matches(row.name)));
const filteredPeriodos = computed(() => props.periodos.filter((row) => {
    if (periodStatus.value === 'active' && !row.is_active) return false;
    if (periodStatus.value === 'inactive' && row.is_active) return false;

    return matches(row.name);
}));

const periodStatuses = computed(() => {
    const active = props.periodos.filter((row) => row.is_active).length;

    return [
        { value: 'all', label: 'Todos', count: props.periodos.length },
        { value: 'active', label: 'Activo', count: active },
        { value: 'inactive', label: 'Inactivos', count: props.periodos.length - active },
    ];
});

const nivelFilterItems = computed(() => [{ value: 'all', label: 'Todos los niveles' }, ...props.niveles.map((nivel) => ({ value: nivel.id, label: nivel.name }))]);
const gradoFilterCount = computed(() => (gradoNivel.value !== 'all' ? 1 : 0));

const visibleCount = computed(() => ({
    niveles: filteredNiveles.value.length,
    grados: filteredGrados.value.length,
    secciones: filteredSecciones.value.length,
    periodos: filteredPeriodos.value.length,
})[activeTab.value]);

const totalCount = computed(() => props[activeTab.value].length);
const isFiltered = computed(() => search.value.trim() !== ''
    || (activeTab.value === 'periodos' && periodStatus.value !== 'all')
    || (activeTab.value === 'grados' && gradoNivel.value !== 'all'));

function resetFilters() {
    search.value = '';
    periodStatus.value = 'all';
    gradoNivel.value = 'all';
}

const actionsColumn = { id: 'actions', header: '', meta: { class: { th: 'w-24', td: 'text-right' } } };
const columns = {
    niveles: [{ accessorKey: 'name', header: 'Nombre' }, { accessorKey: 'grados_count', header: 'Grados' }, actionsColumn],
    grados: [{ accessorKey: 'order', header: 'Orden', meta: { class: { th: 'w-24' } } }, { accessorKey: 'name', header: 'Nombre' }, { id: 'nivel', header: 'Nivel' }, actionsColumn],
    secciones: [{ accessorKey: 'name', header: 'Nombre' }, actionsColumn],
    periodos: [{ accessorKey: 'name', header: 'Nombre' }, { id: 'range', header: 'Vigencia' }, { accessorKey: 'is_active', header: 'Estado' }, actionsColumn],
};

// ---- Dialog state: one dialog per catalog type ----
const dialog = ref(null); // 'nivel' | 'grado' | 'seccion' | 'periodo' | null
const editing = ref(null); // the row being edited, or null when creating

const nivelForm = useForm({ name: '' });
const gradoForm = useForm({ name: '', nivel_academico_id: '', order: 1 });
const seccionForm = useForm({ name: '' });
const periodoForm = useForm({ name: '', starts_on: '', ends_on: '' });

const DIALOGS = {
    nivel: { create: 'Nuevo nivel académico', edit: 'Editar nivel', form: 'form-nivel', processing: () => nivelForm.processing },
    grado: { create: 'Nuevo grado', edit: 'Editar grado', form: 'form-grado', processing: () => gradoForm.processing },
    seccion: { create: 'Nueva sección', edit: 'Editar sección', form: 'form-seccion', processing: () => seccionForm.processing },
    periodo: { create: 'Nuevo período académico', edit: 'Editar período', form: 'form-periodo', processing: () => periodoForm.processing },
};

// Remembers the last dialog so its title and form stay put while it animates out.
const lastDialog = ref('nivel');
watch(dialog, (value) => {
    if (value) lastDialog.value = value;
});

const dialogOpen = computed({
    get: () => dialog.value !== null,
    set: (open) => {
        if (!open) closeDialog();
    },
});
const dialogConfig = computed(() => DIALOGS[lastDialog.value]);

function openNivel(row = null) {
    editing.value = row;
    nivelForm.clearErrors();
    nivelForm.name = row?.name ?? '';
    dialog.value = 'nivel';
}

function openGrado(row = null) {
    editing.value = row;
    gradoForm.clearErrors();
    gradoForm.name = row?.name ?? '';
    gradoForm.nivel_academico_id = row?.nivel_academico_id ?? (props.niveles[0]?.id ?? '');
    gradoForm.order = row?.order ?? 1;
    dialog.value = 'grado';
}

function openSeccion(row = null) {
    editing.value = row;
    seccionForm.clearErrors();
    seccionForm.name = row?.name ?? '';
    dialog.value = 'seccion';
}

function openPeriodo(row = null) {
    editing.value = row;
    periodoForm.clearErrors();
    periodoForm.name = row?.name ?? '';
    periodoForm.starts_on = toDay(row?.starts_on);
    periodoForm.ends_on = toDay(row?.ends_on);
    dialog.value = 'periodo';
}

const OPENERS = { niveles: openNivel, grados: openGrado, secciones: openSeccion, periodos: openPeriodo };
const canCreate = computed(() => activeTab.value !== 'grados' || props.niveles.length > 0);

function openNew() {
    OPENERS[activeTab.value]();
}

function closeDialog() {
    dialog.value = null;
}

function submitNivel() {
    const options = { onSuccess: closeDialog, preserveScroll: true };
    if (editing.value) {
        nivelForm.put(route('academic.niveles.update', editing.value.id), options);
    } else {
        nivelForm.post(route('academic.niveles.store'), options);
    }
}

function submitGrado() {
    const options = { onSuccess: closeDialog, preserveScroll: true };
    if (editing.value) {
        gradoForm.put(route('academic.grados.update', editing.value.id), options);
    } else {
        gradoForm.post(route('academic.grados.store'), options);
    }
}

function submitSeccion() {
    const options = { onSuccess: closeDialog, preserveScroll: true };
    if (editing.value) {
        seccionForm.put(route('academic.secciones.update', editing.value.id), options);
    } else {
        seccionForm.post(route('academic.secciones.store'), options);
    }
}

function submitPeriodo() {
    const options = { onSuccess: closeDialog, preserveScroll: true };
    if (editing.value) {
        periodoForm.put(route('academic.periodos.update', editing.value.id), options);
    } else {
        periodoForm.post(route('academic.periodos.store'), options);
    }
}

// ---- Delete confirmation ----
const {
    target: toDelete,
    open: deleteOpen,
    processing: deleting,
    ask: askDelete,
    run: runDelete,
    clear: clearDelete,
} = useConfirmAction();

function destroyRow(routeName, row) {
    askDelete({ routeName, id: row.id, label: row.name });
}

function confirmDelete() {
    runDelete((options) => router.delete(route(toDelete.value.routeName, toDelete.value.id), options));
}

function activatePeriodo(id) {
    router.post(route('academic.periodos.activate', id), {}, { preserveScroll: true });
}

function rowActions(onEdit, routeName, row) {
    return [
        [{ label: 'Editar', icon: 'i-lucide-pencil-line', onSelect: () => onEdit(row) }],
        [{ label: 'Eliminar', icon: 'i-lucide-trash-2', color: 'error', onSelect: () => destroyRow(routeName, row) }],
    ];
}

function periodoActions(row) {
    const items = rowActions(openPeriodo, 'academic.periodos.destroy', row);

    if (!row.is_active) {
        items[0].push({ label: 'Marcar como activo', icon: 'i-lucide-circle-check', onSelect: () => activatePeriodo(row.id) });
    }

    return items;
}
</script>

<template>
    <Head title="Base académica" />

    <DashboardLayout active="academico">
        <PageHeader
            eyebrow="Académico"
            title="Base académica"
            description="Niveles, grados, secciones y períodos son la base con la que se arman las ofertas académicas y las matrículas."
        >
            <template #actions>
                <UButton icon="i-lucide-plus" size="lg" :disabled="!canCreate" @click="openNew">{{ panel.newLabel }}</UButton>
            </template>
        </PageHeader>

        <UTabs
            v-model="activeTab"
            :items="tabs"
            :content="false"
            variant="link"
            color="primary"
            class="mb-5"
            :ui="{ list: 'overflow-x-auto', trigger: 'shrink-0' }"
        />

        <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
                <div class="space-y-4">
                    <PanelHeader :kicker="panel.kicker" :title="panel.title">
                        <span class="text-sm text-muted tabular-nums">
                            {{ visibleCount === totalCount ? `${totalCount} registrados` : `${visibleCount} de ${totalCount}` }}
                        </span>
                    </PanelHeader>
                    <DataToolbar
                        v-if="activeTab === 'periodos'"
                        v-model:search="search"
                        v-model:status="periodStatus"
                        :placeholder="panel.search"
                        :statuses="periodStatuses"
                    />
                    <DataToolbar
                        v-else-if="activeTab === 'grados'"
                        v-model:search="search"
                        :placeholder="panel.search"
                        :filter-count="gradoFilterCount"
                        @clear-filters="gradoNivel = 'all'"
                    >
                        <template #filters>
                            <UFormField label="Nivel académico">
                                <URadioGroup v-model="gradoNivel" :items="nivelFilterItems" />
                            </UFormField>
                        </template>
                    </DataToolbar>
                    <DataToolbar v-else v-model:search="search" :placeholder="panel.search" />
                </div>
            </template>

            <UAlert
                v-if="activeTab === 'grados' && niveles.length === 0"
                color="warning"
                variant="subtle"
                icon="i-lucide-triangle-alert"
                title="Primero crea un nivel académico"
                description="Los grados pertenecen a un nivel. Crea uno en la pestaña Niveles para poder agregar grados."
                class="m-4 w-auto sm:m-5"
            />

            <!-- NIVELES -->
            <template v-if="activeTab === 'niveles'">
                <EmptyState
                    v-if="filteredNiveles.length === 0"
                    :icon="isFiltered ? 'i-lucide-search-x' : 'i-lucide-layers'"
                    :title="isFiltered ? 'Sin resultados' : 'Todavía no hay niveles académicos'"
                    :description="isFiltered ? 'Prueba con otro término.' : 'Crea el primero, por ejemplo Primaria o Secundaria.'"
                    :actions="isFiltered ? [{ label: 'Quitar filtros', color: 'neutral', variant: 'outline', onClick: resetFilters }] : [{ label: 'Nuevo nivel', icon: 'i-lucide-plus', onClick: () => openNivel() }]"
                />
                <template v-else>
                    <UTable :data="filteredNiveles" :columns="columns.niveles" :ui="tableUi" class="hidden sm:block">
                        <template #name-cell="{ row }"><span class="font-semibold text-highlighted">{{ row.original.name }}</span></template>
                        <template #grados_count-cell="{ row }">
                            <UBadge color="neutral" variant="subtle" class="rounded-full">
                                {{ row.original.grados_count }} {{ row.original.grados_count === 1 ? 'grado' : 'grados' }}
                            </UBadge>
                        </template>
                        <template #actions-cell="{ row }">
                            <UDropdownMenu :items="rowActions(openNivel, 'academic.niveles.destroy', row.original)" :content="{ align: 'end' }">
                                <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis" :aria-label="`Acciones para ${row.original.name}`" />
                            </UDropdownMenu>
                        </template>
                    </UTable>
                    <ul class="divide-y divide-default sm:hidden">
                        <li v-for="row in filteredNiveles" :key="row.id" class="flex items-center gap-3 px-4 py-3.5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-highlighted">{{ row.name }}</p>
                                <p class="text-sm text-muted">{{ row.grados_count }} {{ row.grados_count === 1 ? 'grado' : 'grados' }}</p>
                            </div>
                            <UDropdownMenu :items="rowActions(openNivel, 'academic.niveles.destroy', row)" :content="{ align: 'end' }">
                                <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis" :aria-label="`Acciones para ${row.name}`" />
                            </UDropdownMenu>
                        </li>
                    </ul>
                </template>
            </template>

            <!-- GRADOS -->
            <template v-else-if="activeTab === 'grados'">
                <EmptyState
                    v-if="filteredGrados.length === 0"
                    :icon="isFiltered ? 'i-lucide-search-x' : 'i-lucide-graduation-cap'"
                    :title="isFiltered ? 'Sin resultados' : 'Todavía no hay grados'"
                    :description="isFiltered ? 'Prueba con otro término o quita los filtros.' : null"
                    :actions="isFiltered ? [{ label: 'Quitar filtros', color: 'neutral', variant: 'outline', onClick: resetFilters }] : []"
                />
                <template v-else>
                    <UTable :data="filteredGrados" :columns="columns.grados" :ui="tableUi" class="hidden sm:block">
                        <template #order-cell="{ row }"><span class="font-semibold tabular-nums text-highlighted">{{ row.original.order }}</span></template>
                        <template #name-cell="{ row }"><span class="font-semibold text-highlighted">{{ row.original.name }}</span></template>
                        <template #nivel-cell="{ row }">
                            <UBadge color="primary" variant="subtle" class="rounded-full">{{ row.original.nivel_academico?.name ?? '—' }}</UBadge>
                        </template>
                        <template #actions-cell="{ row }">
                            <UDropdownMenu :items="rowActions(openGrado, 'academic.grados.destroy', row.original)" :content="{ align: 'end' }">
                                <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis" :aria-label="`Acciones para ${row.original.name}`" />
                            </UDropdownMenu>
                        </template>
                    </UTable>
                    <ul class="divide-y divide-default sm:hidden">
                        <li v-for="row in filteredGrados" :key="row.id" class="flex items-center gap-3 px-4 py-3.5">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-elevated text-sm font-semibold tabular-nums">{{ row.order }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-highlighted">{{ row.name }}</p>
                                <p class="truncate text-sm text-muted">{{ row.nivel_academico?.name ?? '—' }}</p>
                            </div>
                            <UDropdownMenu :items="rowActions(openGrado, 'academic.grados.destroy', row)" :content="{ align: 'end' }">
                                <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis" :aria-label="`Acciones para ${row.name}`" />
                            </UDropdownMenu>
                        </li>
                    </ul>
                </template>
            </template>

            <!-- SECCIONES -->
            <template v-else-if="activeTab === 'secciones'">
                <EmptyState
                    v-if="filteredSecciones.length === 0"
                    :icon="isFiltered ? 'i-lucide-search-x' : 'i-lucide-users'"
                    :title="isFiltered ? 'Sin resultados' : 'Todavía no hay secciones'"
                    :description="isFiltered ? 'Prueba con otro término.' : 'Crea las secciones que usarán las ofertas, por ejemplo A, B o C.'"
                    :actions="isFiltered ? [{ label: 'Quitar filtros', color: 'neutral', variant: 'outline', onClick: resetFilters }] : [{ label: 'Nueva sección', icon: 'i-lucide-plus', onClick: () => openSeccion() }]"
                />
                <template v-else>
                    <UTable :data="filteredSecciones" :columns="columns.secciones" :ui="tableUi" class="hidden sm:block">
                        <template #name-cell="{ row }"><span class="font-semibold text-highlighted">{{ row.original.name }}</span></template>
                        <template #actions-cell="{ row }">
                            <UDropdownMenu :items="rowActions(openSeccion, 'academic.secciones.destroy', row.original)" :content="{ align: 'end' }">
                                <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis" :aria-label="`Acciones para ${row.original.name}`" />
                            </UDropdownMenu>
                        </template>
                    </UTable>
                    <ul class="divide-y divide-default sm:hidden">
                        <li v-for="row in filteredSecciones" :key="row.id" class="flex items-center gap-3 px-4 py-3.5">
                            <p class="min-w-0 flex-1 truncate text-sm font-semibold text-highlighted">{{ row.name }}</p>
                            <UDropdownMenu :items="rowActions(openSeccion, 'academic.secciones.destroy', row)" :content="{ align: 'end' }">
                                <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis" :aria-label="`Acciones para ${row.name}`" />
                            </UDropdownMenu>
                        </li>
                    </ul>
                </template>
            </template>

            <!-- PERIODOS -->
            <template v-else>
                <EmptyState
                    v-if="filteredPeriodos.length === 0"
                    :icon="isFiltered ? 'i-lucide-search-x' : 'i-lucide-calendar-range'"
                    :title="isFiltered ? 'Sin resultados' : 'Todavía no hay períodos académicos'"
                    :description="isFiltered ? 'Prueba con otro término o quita los filtros.' : 'Crea un período, por ejemplo 2026-2027, y márcalo como activo.'"
                    :actions="isFiltered ? [{ label: 'Quitar filtros', color: 'neutral', variant: 'outline', onClick: resetFilters }] : [{ label: 'Nuevo período', icon: 'i-lucide-plus', onClick: () => openPeriodo() }]"
                />
                <template v-else>
                    <UTable :data="filteredPeriodos" :columns="columns.periodos" :ui="tableUi" class="hidden sm:block">
                        <template #name-cell="{ row }"><span class="font-semibold text-highlighted">{{ row.original.name }}</span></template>
                        <template #range-cell="{ row }">{{ formatDay(row.original.starts_on) }} – {{ formatDay(row.original.ends_on) }}</template>
                        <template #is_active-cell="{ row }">
                            <UBadge v-if="row.original.is_active" color="success" variant="subtle" icon="i-lucide-circle-check" class="rounded-full">Activo</UBadge>
                            <UButton v-else color="neutral" variant="outline" size="sm" class="rounded-full" @click="activatePeriodo(row.original.id)">Activar</UButton>
                        </template>
                        <template #actions-cell="{ row }">
                            <UDropdownMenu :items="periodoActions(row.original)" :content="{ align: 'end' }">
                                <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis" :aria-label="`Acciones para ${row.original.name}`" />
                            </UDropdownMenu>
                        </template>
                    </UTable>
                    <ul class="divide-y divide-default sm:hidden">
                        <li v-for="row in filteredPeriodos" :key="row.id" class="flex items-center gap-3 px-4 py-3.5">
                            <div class="min-w-0 flex-1">
                                <p class="flex items-center gap-2 text-sm font-semibold text-highlighted">
                                    <span class="truncate">{{ row.name }}</span>
                                    <UBadge v-if="row.is_active" color="success" variant="subtle" class="rounded-full">Activo</UBadge>
                                </p>
                                <p class="text-sm text-muted">{{ formatDay(row.starts_on) }} – {{ formatDay(row.ends_on) }}</p>
                            </div>
                            <UDropdownMenu :items="periodoActions(row)" :content="{ align: 'end' }">
                                <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis" :aria-label="`Acciones para ${row.name}`" />
                            </UDropdownMenu>
                        </li>
                    </ul>
                </template>
            </template>
        </UCard>

        <!-- Create / edit dialog (one form per catalog type) -->
        <UModal
            v-model:open="dialogOpen"
            :title="editing ? dialogConfig.edit : dialogConfig.create"
            :dismissible="!dialogConfig.processing()"
            @after:leave="editing = null"
        >
            <template #body>
                <form v-if="lastDialog === 'nivel'" id="form-nivel" class="space-y-5" novalidate @submit.prevent="submitNivel">
                    <UFormField label="Nombre" name="name" required :error="nivelForm.errors.name">
                        <UInput v-model="nivelForm.name" placeholder="Ej.: Primaria" size="lg" class="w-full" autofocus />
                    </UFormField>
                </form>

                <form v-else-if="lastDialog === 'grado'" id="form-grado" class="space-y-5" novalidate @submit.prevent="submitGrado">
                    <UFormField label="Nombre" name="name" required :error="gradoForm.errors.name">
                        <UInput v-model="gradoForm.name" placeholder="Ej.: 3.º" size="lg" class="w-full" autofocus />
                    </UFormField>
                    <div class="grid gap-5 sm:grid-cols-[minmax(0,1fr)_8rem]">
                        <UFormField label="Nivel académico" name="nivel_academico_id" required :error="gradoForm.errors.nivel_academico_id">
                            <USelect
                                v-model="gradoForm.nivel_academico_id"
                                :items="niveles"
                                value-key="id"
                                label-key="name"
                                placeholder="Selecciona un nivel"
                                size="lg"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField label="Orden" name="order" required :error="gradoForm.errors.order">
                            <UInputNumber v-model="gradoForm.order" :min="1" size="lg" class="w-full" />
                        </UFormField>
                    </div>
                </form>

                <form v-else-if="lastDialog === 'seccion'" id="form-seccion" class="space-y-5" novalidate @submit.prevent="submitSeccion">
                    <UFormField label="Nombre" name="name" required :error="seccionForm.errors.name">
                        <UInput v-model="seccionForm.name" placeholder="Ej.: C" size="lg" class="w-full" autofocus />
                    </UFormField>
                </form>

                <form v-else id="form-periodo" class="space-y-5" novalidate @submit.prevent="submitPeriodo">
                    <UFormField label="Nombre" name="name" required :error="periodoForm.errors.name">
                        <UInput v-model="periodoForm.name" placeholder="Ej.: 2026-2027" size="lg" class="w-full" autofocus />
                    </UFormField>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <UFormField label="Inicio" name="starts_on" required :error="periodoForm.errors.starts_on">
                            <DateInput v-model="periodoForm.starts_on" />
                        </UFormField>
                        <UFormField label="Fin" name="ends_on" required :error="periodoForm.errors.ends_on">
                            <DateInput v-model="periodoForm.ends_on" />
                        </UFormField>
                    </div>
                </form>
            </template>
            <template #footer>
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UButton color="neutral" variant="ghost" class="justify-center" @click="closeDialog">Cancelar</UButton>
                    <UButton type="submit" :form="dialogConfig.form" icon="i-lucide-check" class="justify-center" :loading="dialogConfig.processing()">
                        Guardar
                    </UButton>
                </div>
            </template>
        </UModal>

        <ConfirmModal
            v-model:open="deleteOpen"
            :title="`¿Eliminar «${toDelete?.label ?? ''}»?`"
            description="Esta acción no se puede deshacer."
            :loading="deleting"
            @confirm="confirmDelete"
            @after:leave="clearDelete"
        />
    </DashboardLayout>
</template>
