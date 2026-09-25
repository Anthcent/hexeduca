<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import DataToolbar from '@/Components/DataToolbar.vue';
import EmptyState from '@/Components/EmptyState.vue';
import PaginationBar from '@/Components/PaginationBar.vue';

const props = defineProps({
    inbox: { type: Object, required: true },
    canSend: { type: Boolean, default: false },
});

const page = usePage();

const items = computed(() => props.inbox.data ?? []);
const unreadCount = computed(() => page.props.notifications?.unreadCount ?? items.value.filter((item) => !item.readAt).length);
const total = computed(() => props.inbox.total ?? items.value.length);

// The inbox is paginated server-side and the controller takes no filters, so
// search and the read/unread chips apply to the current page only.
const search = ref('');
const status = ref('all');

const statuses = computed(() => {
    const unread = items.value.filter((item) => !item.readAt).length;

    return [
        { value: 'all', label: 'Todas', count: items.value.length },
        { value: 'unread', label: 'Sin leer', count: unread },
        { value: 'read', label: 'Leídas', count: items.value.length - unread },
    ];
});

const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');

    return items.value.filter((item) => {
        if (status.value === 'unread' && item.readAt) return false;
        if (status.value === 'read' && !item.readAt) return false;
        if (!term) return true;

        return `${item.title} ${item.body} ${item.senderName}`.toLocaleLowerCase('es').includes(term);
    });
});

const isFiltered = computed(() => search.value.trim() !== '' || status.value !== 'all');

function resetFilters() {
    search.value = '';
    status.value = 'all';
}

const dateFormatter = new Intl.DateTimeFormat('es', { dateStyle: 'medium', timeStyle: 'short' });

function formatDate(value) {
    return value ? dateFormatter.format(new Date(value)) : '';
}

function markAsRead(notification) {
    router.patch(route('notifications.read', notification.id), {}, { preserveScroll: true });
}

function markAllAsRead() {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Notificaciones" />

    <DashboardLayout active="module:notifications:notifications.index">
        <PageHeader eyebrow="Comunicación" title="Notificaciones">
            <template #description>
                Avisos y comunicados de la institución.
                <template v-if="unreadCount > 0">Tienes {{ unreadCount }} sin leer.</template>
            </template>
            <template #actions>
                <UButton
                    color="neutral"
                    variant="outline"
                    size="lg"
                    icon="i-lucide-check-check"
                    :disabled="unreadCount === 0"
                    @click="markAllAsRead"
                >
                    Marcar todas como leídas
                </UButton>
                <UButton
                    v-if="canSend"
                    :to="route('notifications.create', undefined, false)"
                    size="lg"
                    icon="i-lucide-plus"
                >
                    Nuevo anuncio
                </UButton>
            </template>
        </PageHeader>

        <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0', footer: 'p-0 sm:px-0' }">
            <template #header>
                <div class="space-y-4">
                    <PanelHeader kicker="Bandeja" :title="total === 1 ? '1 aviso' : `${total} avisos`">
                        <UBadge v-if="unreadCount > 0" color="primary" variant="subtle" class="rounded-full">{{ unreadCount }} sin leer</UBadge>
                    </PanelHeader>
                    <DataToolbar
                        v-if="items.length > 0"
                        v-model:search="search"
                        v-model:status="status"
                        placeholder="Buscar en esta página"
                        :statuses="statuses"
                    />
                </div>
            </template>

            <EmptyState
                v-if="items.length === 0"
                icon="i-lucide-inbox"
                title="No hay notificaciones por ahora"
                description="Cuando la institución publique un anuncio para ti, aparecerá aquí."
            />
            <EmptyState
                v-else-if="filtered.length === 0"
                icon="i-lucide-search-x"
                title="Sin resultados"
                description="Ningún aviso de esta página coincide con la búsqueda o el filtro."
                :actions="isFiltered ? [{ label: 'Quitar filtros', color: 'neutral', variant: 'outline', icon: 'i-lucide-rotate-ccw', onClick: resetFilters }] : []"
            />

            <ul v-else class="divide-y divide-default">
                <li
                    v-for="notification in filtered"
                    :key="notification.id"
                    class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start sm:gap-4 sm:px-5"
                    :class="!notification.readAt && 'bg-primary/5'"
                >
                    <span
                        class="grid size-9 shrink-0 place-items-center rounded-lg max-sm:hidden"
                        :class="notification.readAt ? 'bg-elevated text-muted' : 'bg-primary/10 text-primary'"
                        aria-hidden="true"
                    >
                        <UIcon name="i-lucide-megaphone" class="size-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm text-highlighted" :class="notification.readAt ? 'font-semibold' : 'font-bold'">{{ notification.title }}</h2>
                            <UBadge v-if="!notification.readAt" color="primary" variant="subtle" class="rounded-full">Nuevo</UBadge>
                        </div>
                        <p class="mt-1 whitespace-pre-line break-words text-sm text-toned">{{ notification.body }}</p>
                        <p class="mt-2 text-xs text-muted">
                            De {{ notification.senderName }} · <time :datetime="notification.createdAt">{{ formatDate(notification.createdAt) }}</time>
                        </p>
                    </div>
                    <div class="shrink-0 sm:self-center">
                        <UButton
                            v-if="!notification.readAt"
                            color="primary"
                            variant="ghost"
                            size="sm"
                            icon="i-lucide-check"
                            @click="markAsRead(notification)"
                        >
                            Marcar como leída
                        </UButton>
                        <span v-else class="inline-flex items-center gap-1 text-xs font-semibold text-muted">
                            <UIcon name="i-lucide-check" class="size-3.5" />Leída
                        </span>
                    </div>
                </li>
            </ul>

            <template v-if="inbox.last_page > 1" #footer>
                <PaginationBar :paginator="inbox" noun="avisos" />
            </template>
        </UCard>
    </DashboardLayout>
</template>
