<script setup>
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import DataToolbar from '@/Components/DataToolbar.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { tableUi } from '@/Components/tableUi';

const props = defineProps({
    modules: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    entitlements: { type: Object, default: () => ({}) },
});

const syncing = ref(false);

const selectedSchoolId = ref(props.schools[0]?.id ?? null);
const selectedSchool = computed(() => props.schools.find((school) => school.id === selectedSchoolId.value) ?? null);

const optionalModules = computed(() => props.modules.filter((module) => !module.core));

const MATURITY_LABELS = { mature: 'Estable', skeleton: 'Esqueleto' };

function maturityLabel(maturity) {
    return MATURITY_LABELS[maturity] ?? maturity;
}

function entitledKeys(schoolId) {
    return props.entitlements[schoolId] ?? [];
}

function isEntitled(module) {
    if (!selectedSchool.value) return false;
    return entitledKeys(selectedSchool.value.id).includes(module.key);
}

function syncModules() {
    syncing.value = true;
    router.post(route('admin.modules.sync'), {}, {
        preserveScroll: true,
        onFinish: () => { syncing.value = false; },
    });
}

function toggleActive(module) {
    router.post(route('admin.modules.toggle-active', module.key), {}, { preserveScroll: true });
}

function toggleEntitlement(module) {
    if (!selectedSchool.value) return;
    router.post(route('admin.modules.toggle-entitlement', [module.key, selectedSchool.value.id]), {}, { preserveScroll: true });
}

// Registry filters (client-side: the page receives every module).

const search = ref('');
const status = ref('all');
const maturity = ref('all');

const maturityOptions = computed(() => [...new Set(props.modules.map((module) => module.maturity))]
    .map((value) => ({ value, label: maturityLabel(value) })));

const statuses = computed(() => {
    const active = props.modules.filter((module) => module.active).length;

    return [
        { value: 'all', label: 'Todos', count: props.modules.length },
        { value: 'active', label: 'Activos', count: active },
        { value: 'inactive', label: 'Inactivos', count: props.modules.length - active },
        { value: 'core', label: 'Core', count: props.modules.filter((module) => module.core).length },
    ];
});

const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');

    return props.modules.filter((module) => {
        if (status.value === 'active' && !module.active) return false;
        if (status.value === 'inactive' && module.active) return false;
        if (status.value === 'core' && !module.core) return false;
        if (maturity.value !== 'all' && module.maturity !== maturity.value) return false;
        if (!term) return true;

        return `${module.name} ${module.key}`.toLocaleLowerCase('es').includes(term);
    });
});

const filterCount = computed(() => (maturity.value !== 'all' ? 1 : 0));
const isFiltered = computed(() => search.value.trim() !== '' || status.value !== 'all' || filterCount.value > 0);

function resetFilters() {
    search.value = '';
    status.value = 'all';
    maturity.value = 'all';
}

const columns = [
    { accessorKey: 'name', header: 'Módulo' },
    { accessorKey: 'maturity', header: 'Madurez' },
    { accessorKey: 'dependencies', header: 'Dependencias' },
    { accessorKey: 'active', header: 'Estado' },
    { id: 'toggle', header: 'Activo', meta: { class: { th: 'w-24 text-right', td: 'text-right' } } },
];

const entitlementColumns = [
    { accessorKey: 'name', header: 'Módulo' },
    { id: 'entitled', header: 'Acceso' },
    { id: 'toggle', header: 'Habilitado', meta: { class: { th: 'w-28 text-right', td: 'text-right' } } },
];
</script>

<template>
    <Head title="Módulos" />

    <DashboardLayout active="modulos">
        <PageHeader
            eyebrow="Plataforma"
            title="Módulos"
            description="Activación global de módulos y acceso por institución."
        >
            <template #actions>
                <UButton color="neutral" variant="outline" icon="i-lucide-refresh-ccw" size="lg" :loading="syncing" @click="syncModules">
                    Sincronizar manifiestos
                </UButton>
            </template>
        </PageHeader>

        <div class="space-y-6">
            <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0' }">
                <template #header>
                    <div class="space-y-4">
                        <PanelHeader kicker="Registro" :title="filtered.length === 1 ? '1 módulo' : `${filtered.length} módulos`" />
                        <DataToolbar
                            v-model:search="search"
                            v-model:status="status"
                            placeholder="Buscar por nombre o clave"
                            :statuses="statuses"
                            :filter-count="filterCount"
                            @clear-filters="maturity = 'all'"
                        >
                            <template #filters>
                                <UFormField label="Madurez">
                                    <URadioGroup
                                        v-model="maturity"
                                        :items="[{ value: 'all', label: 'Todas' }, ...maturityOptions]"
                                    />
                                </UFormField>
                            </template>
                        </DataToolbar>
                    </div>
                </template>

                <EmptyState
                    v-if="filtered.length === 0"
                    :icon="isFiltered ? 'i-lucide-search-x' : 'i-lucide-blocks'"
                    :title="isFiltered ? 'Sin resultados' : 'Todavía no hay módulos registrados'"
                    :description="isFiltered ? 'Prueba con otro término o quita los filtros.' : 'Sincroniza los manifiestos para registrarlos.'"
                    :actions="isFiltered ? [{ label: 'Quitar filtros', color: 'neutral', variant: 'outline', icon: 'i-lucide-rotate-ccw', onClick: resetFilters }] : []"
                />

                <template v-else>
                    <UTable :data="filtered" :columns="columns" :ui="tableUi" class="hidden sm:block">
                        <template #name-cell="{ row }">
                            <p class="font-semibold text-highlighted">{{ row.original.name }}</p>
                            <p class="font-mono text-xs text-muted">{{ row.original.key }}</p>
                        </template>
                        <template #maturity-cell="{ row }">
                            <div class="flex items-center gap-1.5">
                                <UBadge v-if="row.original.core" color="primary" variant="subtle" class="rounded-full">Core</UBadge>
                                <UBadge :color="row.original.maturity === 'mature' ? 'success' : 'warning'" variant="subtle" class="rounded-full">
                                    {{ maturityLabel(row.original.maturity) }}
                                </UBadge>
                            </div>
                        </template>
                        <template #dependencies-cell="{ row }">
                            <span class="whitespace-normal text-sm text-muted">
                                {{ row.original.dependencies.length ? row.original.dependencies.join(', ') : '—' }}
                            </span>
                        </template>
                        <template #active-cell="{ row }">
                            <UBadge :color="row.original.active ? 'success' : 'neutral'" variant="subtle" class="rounded-full">
                                {{ row.original.active ? 'Activo' : 'Inactivo' }}
                            </UBadge>
                        </template>
                        <template #toggle-cell="{ row }">
                            <UTooltip :text="row.original.core ? 'Los módulos core no se pueden desactivar' : (row.original.active ? 'Desactivar' : 'Activar')">
                                <USwitch
                                    :model-value="row.original.active"
                                    :disabled="row.original.core"
                                    :aria-label="`Activar ${row.original.name}`"
                                    class="inline-flex"
                                    @update:model-value="toggleActive(row.original)"
                                />
                            </UTooltip>
                        </template>
                    </UTable>

                    <ul class="divide-y divide-default sm:hidden">
                        <li v-for="module in filtered" :key="module.key" class="flex items-center gap-3 px-4 py-3.5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-highlighted">{{ module.name }}</p>
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    <UBadge v-if="module.core" color="primary" variant="subtle" class="rounded-full">Core</UBadge>
                                    <UBadge :color="module.active ? 'success' : 'neutral'" variant="subtle" class="rounded-full">
                                        {{ module.active ? 'Activo' : 'Inactivo' }}
                                    </UBadge>
                                </div>
                            </div>
                            <USwitch
                                :model-value="module.active"
                                :disabled="module.core"
                                :aria-label="`Activar ${module.name}`"
                                @update:model-value="toggleActive(module)"
                            />
                        </li>
                    </ul>
                </template>
            </UCard>

            <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0' }">
                <template #header>
                    <PanelHeader kicker="Acceso por institución" title="Módulos opcionales">
                        <USelectMenu
                            v-if="schools.length > 0"
                            v-model="selectedSchoolId"
                            :items="schools"
                            value-key="id"
                            label-key="name"
                            icon="i-lucide-school"
                            aria-label="Institución"
                            class="w-full sm:w-64"
                        />
                    </PanelHeader>
                    <p class="mt-1 text-sm text-muted">Habilita o revoca módulos opcionales para una institución específica.</p>
                </template>

                <EmptyState
                    v-if="!selectedSchool"
                    icon="i-lucide-school"
                    title="Todavía no hay instituciones registradas"
                />
                <EmptyState
                    v-else-if="optionalModules.length === 0"
                    icon="i-lucide-blocks"
                    title="No hay módulos opcionales registrados"
                />

                <template v-else>
                    <UTable :data="optionalModules" :columns="entitlementColumns" :ui="tableUi" class="hidden sm:block">
                        <template #name-cell="{ row }">
                            <span class="font-semibold text-highlighted">{{ row.original.name }}</span>
                        </template>
                        <template #entitled-cell="{ row }">
                            <UBadge :color="isEntitled(row.original) ? 'success' : 'neutral'" variant="subtle" class="rounded-full">
                                {{ isEntitled(row.original) ? 'Habilitado' : 'Sin acceso' }}
                            </UBadge>
                        </template>
                        <template #toggle-cell="{ row }">
                            <UTooltip :text="!row.original.active ? 'Activa el módulo globalmente primero' : (isEntitled(row.original) ? 'Revocar acceso' : 'Habilitar acceso')">
                                <USwitch
                                    :model-value="isEntitled(row.original)"
                                    :disabled="!row.original.active"
                                    :aria-label="`Habilitar ${row.original.name} para ${selectedSchool.name}`"
                                    class="inline-flex"
                                    @update:model-value="toggleEntitlement(row.original)"
                                />
                            </UTooltip>
                        </template>
                    </UTable>

                    <ul class="divide-y divide-default sm:hidden">
                        <li v-for="module in optionalModules" :key="module.key" class="flex items-center gap-3 px-4 py-3.5">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-highlighted">{{ module.name }}</p>
                                <p class="text-xs text-muted">{{ isEntitled(module) ? 'Habilitado' : 'Sin acceso' }}</p>
                            </div>
                            <USwitch
                                :model-value="isEntitled(module)"
                                :disabled="!module.active"
                                :aria-label="`Habilitar ${module.name} para ${selectedSchool.name}`"
                                @update:model-value="toggleEntitlement(module)"
                            />
                        </li>
                    </ul>
                </template>
            </UCard>
        </div>
    </DashboardLayout>
</template>
