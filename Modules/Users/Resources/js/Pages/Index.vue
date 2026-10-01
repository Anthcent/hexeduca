<script setup>
import { computed, ref } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import DataToolbar from '@/Components/DataToolbar.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { tableUi } from '@/Components/tableUi';
import { roleColor, roleLabel } from '../roles';

const props = defineProps({
    users: { type: Array, default: () => [] },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

// The controller sends the whole directory, so search and role chips filter client-side.
const search = ref('');
const role = ref('all');

const ROLE_ORDER = ['director', 'academic-control', 'administrative', 'teacher', 'student'];

const statuses = computed(() => {
    const counts = props.users.reduce((acc, user) => {
        acc[user.role] = (acc[user.role] ?? 0) + 1;

        return acc;
    }, {});

    return [
        { value: 'all', label: 'Todos', count: props.users.length },
        ...ROLE_ORDER.filter((key) => counts[key]).map((key) => ({ value: key, label: roleLabel(key), count: counts[key] })),
    ];
});

const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');

    return props.users.filter((user) => {
        if (role.value !== 'all' && user.role !== role.value) return false;
        if (!term) return true;

        return `${user.name} ${user.email}`.toLocaleLowerCase('es').includes(term);
    });
});

const isFiltered = computed(() => search.value.trim() !== '' || role.value !== 'all');

const columns = [
    { accessorKey: 'name', header: 'Nombre' },
    { accessorKey: 'role', header: 'Rol' },
    { id: 'actions', header: '', meta: { class: { th: 'w-24', td: 'text-right' } } },
];

function initials(name) {
    return (name ?? '').split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

function rowActions(user) {
    return [[
        { label: 'Ver ficha', icon: 'i-lucide-eye', to: route('users.show', user.id, false) },
        { label: 'Editar rol', icon: 'i-lucide-pencil-line', to: route('users.edit', user.id, false) },
    ]];
}

function resetFilters() {
    search.value = '';
    role.value = 'all';
}
</script>

<template>
    <Head title="Usuarios" />

    <DashboardLayout active="usuarios">
        <PageHeader
            eyebrow="Administración"
            title="Usuarios"
            description="Personal, docentes y estudiantes con acceso al sistema."
            icon="i-lucide-users"
        >
            <template #notch>
                <UButton :to="route('users.create', undefined, false)" icon="i-lucide-user-plus" size="lg">Nuevo usuario</UButton>
            </template>
        </PageHeader>

        <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0' }">
            <template #header>
                <div class="space-y-4">
                    <PanelHeader kicker="Directorio" :title="filtered.length === 1 ? '1 usuario' : `${filtered.length} usuarios`" />
                    <DataToolbar
                        v-model:search="search"
                        v-model:status="role"
                        placeholder="Buscar por nombre o email"
                        :statuses="statuses"
                    />
                </div>
            </template>

            <EmptyState
                v-if="filtered.length === 0"
                :icon="isFiltered ? 'i-lucide-search-x' : 'i-lucide-users'"
                :title="isFiltered ? 'Sin resultados' : 'Todavía no hay usuarios'"
                :description="isFiltered ? 'Prueba con otro nombre o quita los filtros.' : 'Crea el primer usuario para darle acceso al sistema.'"
                :actions="isFiltered ? [{ label: 'Quitar filtros', color: 'neutral', variant: 'outline', icon: 'i-lucide-rotate-ccw', onClick: resetFilters }] : []"
            />

            <template v-else>
                <UTable :data="filtered" :columns="columns" :ui="tableUi" class="hidden sm:block">
                    <template #name-cell="{ row }">
                        <div class="flex items-center gap-3">
                            <UAvatar :text="initials(row.original.name)" :alt="row.original.name" size="md" class="bg-primary/10 text-primary" :ui="{ fallback: 'font-semibold text-primary text-xs' }" />
                            <div class="min-w-0">
                                <ULink
                                    :to="route('users.show', row.original.id, false)"
                                    class="font-semibold text-highlighted hover:text-primary"
                                >{{ row.original.name }}</ULink>
                                <span v-if="row.original.id === currentUserId" class="ms-1.5 text-xs text-muted">(tú)</span>
                                <p class="truncate text-sm text-muted">{{ row.original.email }}</p>
                            </div>
                        </div>
                    </template>
                    <template #role-cell="{ row }">
                        <UBadge :color="roleColor(row.original.role)" variant="subtle" class="rounded-full">{{ roleLabel(row.original.role) }}</UBadge>
                    </template>
                    <template #actions-cell="{ row }">
                        <UDropdownMenu :items="rowActions(row.original)" :content="{ align: 'end' }">
                            <UButton color="neutral" variant="ghost" icon="i-lucide-ellipsis" :aria-label="`Acciones para ${row.original.name}`" />
                        </UDropdownMenu>
                    </template>
                </UTable>

                <ul class="divide-y divide-default sm:hidden">
                    <li v-for="user in filtered" :key="user.id" class="flex items-center gap-3 px-4 py-3.5">
                        <UAvatar :text="initials(user.name)" :alt="user.name" size="md" class="bg-primary/10" :ui="{ fallback: 'font-semibold text-primary text-xs' }" />
                        <ULink :to="route('users.show', user.id, false)" class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold text-highlighted">
                                {{ user.name }}<span v-if="user.id === currentUserId" class="ms-1 text-xs font-normal text-muted">(tú)</span>
                            </span>
                            <span class="block truncate text-sm text-muted">{{ user.email }}</span>
                        </ULink>
                        <UBadge :color="roleColor(user.role)" variant="subtle" class="shrink-0 rounded-full">{{ roleLabel(user.role) }}</UBadge>
                    </li>
                </ul>
            </template>
        </UCard>
    </DashboardLayout>
</template>
