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
    schools: { type: Array, default: () => [] },
});

// Search and status chips filter the full list client-side.
const search = ref('');
const status = ref('all');

const statuses = computed(() => {
    const active = props.schools.filter((school) => school.is_active).length;

    return [
        { value: 'all', label: 'Todas', count: props.schools.length },
        { value: 'active', label: 'Activas', count: active },
        { value: 'inactive', label: 'Inactivas', count: props.schools.length - active },
    ];
});

const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');

    return props.schools.filter((school) => {
        if (status.value === 'active' && !school.is_active) return false;
        if (status.value === 'inactive' && school.is_active) return false;
        if (!term) return true;

        return `${school.name} ${school.subdomain}`.toLocaleLowerCase('es').includes(term);
    });
});

const isFiltered = computed(() => search.value.trim() !== '' || status.value !== 'all');

const columns = [
    { accessorKey: 'name', header: 'Institución' },
    { accessorKey: 'is_active', header: 'Estado' },
    { id: 'actions', header: '', meta: { class: { th: 'w-24', td: 'text-right' } } },
];

function resetFilters() {
    search.value = '';
    status.value = 'all';
}

// Create / edit dialog

const dialog = ref(false);
const editing = ref(null);

const form = useForm({ name: '', subdomain: '' });

function openCreate() {
    editing.value = null;
    form.clearErrors();
    form.name = '';
    form.subdomain = '';
    dialog.value = true;
}

function openEdit(school) {
    editing.value = school;
    form.clearErrors();
    form.name = school.name;
    form.subdomain = school.subdomain;
    dialog.value = true;
}

function closeDialog() {
    dialog.value = false;
}

function submit() {
    const options = { onSuccess: closeDialog, preserveScroll: true };
    if (editing.value) {
        form.put(route('admin.schools.update', editing.value.id), options);
    } else {
        form.post(route('admin.schools.store'), options);
    }
}

// Activate / deactivate confirmation

const {
    target: toToggle,
    open: toggleOpen,
    processing: toggling,
    ask: askToggle,
    run: runToggle,
    clear: clearToggle,
} = useConfirmAction();

function confirmToggle() {
    runToggle((options) => router.post(route('admin.schools.toggle-active', toToggle.value.id), {}, options));
}

function rowActions(school) {
    return [[
        { label: 'Editar', icon: 'i-lucide-pencil-line', onSelect: () => openEdit(school) },
        school.is_active
            ? { label: 'Desactivar', icon: 'i-lucide-pause-circle', color: 'error', onSelect: () => askToggle(school) }
            : { label: 'Activar', icon: 'i-lucide-play-circle', onSelect: () => askToggle(school) },
    ]];
}
</script>

<template>
    <Head title="Instituciones" />

    <DashboardLayout active="instituciones">
        <PageHeader eyebrow="Plataforma" title="Instituciones">
            <template #description>
                Cada institución es un tenant independiente, identificado por su propio subdominio (ej. <UKbd value="demo.app.com" />).
            </template>
            <template #actions>
                <UButton icon="i-lucide-plus" size="lg" @click="openCreate">Nueva institución</UButton>
            </template>
        </PageHeader>

        <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
                <div class="space-y-4">
                    <PanelHeader kicker="Tenants" :title="filtered.length === 1 ? '1 institución' : `${filtered.length} instituciones`" />
                    <DataToolbar
                        v-model:search="search"
                        v-model:status="status"
                        placeholder="Buscar por nombre o subdominio"
                        :statuses="statuses"
                    />
                </div>
            </template>

            <EmptyState
                v-if="filtered.length === 0"
                :icon="isFiltered ? 'i-lucide-search-x' : 'i-lucide-school'"
                :title="isFiltered ? 'Sin resultados' : 'Todavía no hay instituciones'"
                :description="isFiltered ? 'Prueba con otro término o quita los filtros.' : 'Registra la primera institución para empezar.'"
                :actions="isFiltered
                    ? [{ label: 'Quitar filtros', color: 'neutral', variant: 'outline', icon: 'i-lucide-rotate-ccw', onClick: resetFilters }]
                    : [{ label: 'Nueva institución', icon: 'i-lucide-plus', onClick: openCreate }]"
            />

            <template v-else>
                <UTable :data="filtered" :columns="columns" :ui="tableUi" class="hidden sm:block">
                    <template #name-cell="{ row }">
                        <div class="flex items-center gap-3">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                                <UIcon name="i-lucide-school" class="size-[18px]" />
                            </span>
                            <div class="min-w-0">
                                <p class="font-semibold text-highlighted">{{ row.original.name }}</p>
                                <p class="font-mono text-xs text-muted">{{ row.original.subdomain }}</p>
                            </div>
                        </div>
                    </template>
                    <template #is_active-cell="{ row }">
                        <UBadge :color="row.original.is_active ? 'success' : 'neutral'" variant="subtle" class="rounded-full">
                            {{ row.original.is_active ? 'Activa' : 'Inactiva' }}
                        </UBadge>
                    </template>
                    <template #actions-cell="{ row }">
                        <UDropdownMenu :items="rowActions(row.original)" :content="{ align: 'end' }">
                            <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis" :aria-label="`Acciones para ${row.original.name}`" />
                        </UDropdownMenu>
                    </template>
                </UTable>

                <ul class="divide-y divide-default sm:hidden">
                    <li v-for="school in filtered" :key="school.id" class="flex items-center gap-3 px-4 py-3.5">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-highlighted">{{ school.name }}</p>
                            <p class="truncate font-mono text-xs text-muted">{{ school.subdomain }}</p>
                        </div>
                        <UBadge :color="school.is_active ? 'success' : 'neutral'" variant="subtle" class="shrink-0 rounded-full">
                            {{ school.is_active ? 'Activa' : 'Inactiva' }}
                        </UBadge>
                        <UDropdownMenu :items="rowActions(school)" :content="{ align: 'end' }">
                            <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis" :aria-label="`Acciones para ${school.name}`" />
                        </UDropdownMenu>
                    </li>
                </ul>
            </template>
        </UCard>

        <UModal
            v-model:open="dialog"
            :title="editing ? 'Editar institución' : 'Nueva institución'"
            description="El subdominio identifica a la institución en su dirección web."
            :dismissible="!form.processing"
            @after:leave="editing = null"
        >
            <template #body>
                <form id="form-school" class="space-y-5" novalidate @submit.prevent="submit">
                    <UFormField label="Nombre" name="name" required :error="form.errors.name">
                        <UInput v-model="form.name" placeholder="Ej.: Colegio San Martín" size="lg" class="w-full" autofocus />
                    </UFormField>
                    <UFormField
                        label="Subdominio"
                        name="subdomain"
                        required
                        :error="form.errors.subdomain"
                        :help="`Quedará accesible en ${form.subdomain || '…'}.app.com`"
                    >
                        <UInput v-model="form.subdomain" placeholder="Ej.: sanmartin" size="lg" class="w-full font-mono" />
                    </UFormField>
                </form>
            </template>
            <template #footer>
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UButton color="neutral" variant="ghost" class="justify-center" @click="closeDialog">Cancelar</UButton>
                    <UButton type="submit" form="form-school" icon="i-lucide-check" class="justify-center" :loading="form.processing">Guardar</UButton>
                </div>
            </template>
        </UModal>

        <ConfirmModal
            v-model:open="toggleOpen"
            :title="toToggle?.is_active ? '¿Desactivar institución?' : '¿Activar institución?'"
            :confirm-label="toToggle?.is_active ? 'Desactivar' : 'Activar'"
            :confirm-icon="toToggle?.is_active ? 'i-lucide-pause-circle' : 'i-lucide-play-circle'"
            :color="toToggle?.is_active ? 'error' : 'primary'"
            :loading="toggling"
            @confirm="confirmToggle"
            @after:leave="clearToggle"
        >
            <strong class="text-highlighted">{{ toToggle?.name }}</strong>
            {{ toToggle?.is_active
                ? 'dejará de estar disponible para sus usuarios hasta que se vuelva a activar.'
                : 'volverá a estar disponible para sus usuarios.' }}
        </ConfirmModal>
    </DashboardLayout>
</template>
