<script setup>
import { computed, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { useToast } from '@nuxt/ui/composables/useToast';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps({
    inbox: { type: Object, required: true },
    canSend: { type: Boolean, default: false },
});

const page = usePage();
const toast = useToast();

const items = computed(() => props.inbox.data ?? []);
const unreadCount = computed(() => page.props.notifications?.unreadCount ?? items.value.filter((item) => !item.readAt).length);
const hasPages = computed(() => props.inbox.last_page > 1);

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

watch(
    () => page.props.flash?.success,
    (message) => {
        if (message) {
            toast.add({ title: message, color: 'success', icon: 'i-lucide-circle-check' });
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Notificaciones" />

    <DashboardLayout active="module:notifications:notifications.index">
        <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="muted mb-1 text-xs font-bold uppercase tracking-[.14em]">Comunicación</p>
                <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Notificaciones</h1>
                <p class="muted mt-1 max-w-[60ch] text-sm">
                    Avisos y comunicados de la institución.
                    <template v-if="unreadCount > 0">Tienes {{ unreadCount }} sin leer.</template>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <UButton
                    class="rounded-xl"
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
                    class="rounded-xl"
                    v-if="canSend"
                    :to="route('notifications.create')"
                    size="lg"
                    icon="i-lucide-plus"
                >
                    Nuevo anuncio
                </UButton>
            </div>
        </div>

        <UCard :ui="{ body: 'p-0 sm:p-0' }" class="rounded-[24px] shadow-soft">
            <div v-if="items.length === 0" class="flex flex-col items-center gap-3 px-6 py-14 text-center">
                <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-200">
                    <UIcon name="i-lucide-inbox" class="size-6" />
                </span>
                <p class="font-semibold">No hay notificaciones por ahora.</p>
                <p class="muted max-w-[42ch] text-sm">Cuando la institución publique un anuncio para ti, aparecerá aquí.</p>
            </div>

            <ul v-else class="divide-y">
                <li
                    v-for="notification in items"
                    :key="notification.id"
                    class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:gap-4"
                    :class="!notification.readAt && 'bg-brand-50/50 dark:bg-brand-950/30'"
                >
                    <span
                        class="mt-2 hidden size-2 shrink-0 rounded-full sm:block"
                        :class="notification.readAt ? 'bg-transparent' : 'bg-brand-500'"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm" :class="notification.readAt ? 'font-semibold' : 'font-extrabold'">{{ notification.title }}</h2>
                            <UBadge v-if="!notification.readAt" color="primary" variant="subtle" size="sm">Nuevo</UBadge>
                        </div>
                        <p class="mt-1 whitespace-pre-line break-words text-sm text-[rgb(var(--text))]/85">{{ notification.body }}</p>
                        <p class="muted mt-2 text-xs">
                            De {{ notification.senderName }} · <time :datetime="notification.createdAt">{{ formatDate(notification.createdAt) }}</time>
                        </p>
                    </div>
                    <div class="shrink-0 sm:self-center">
                        <UButton
                            class="rounded-xl"
                            v-if="!notification.readAt"
                            color="primary"
                            variant="ghost"
                            size="sm"
                            icon="i-lucide-check"
                            @click="markAsRead(notification)"
                        >
                            Marcar como leída
                        </UButton>
                        <span v-else class="muted inline-flex items-center gap-1 text-xs font-semibold">
                            <UIcon name="i-lucide-check" class="size-3.5" />Leída
                        </span>
                    </div>
                </li>
            </ul>
        </UCard>

        <nav v-if="hasPages" class="mt-4 flex items-center justify-between gap-3" aria-label="Paginación">
            <UButton
                class="rounded-xl"
                color="neutral"
                variant="outline"
                icon="i-lucide-arrow-left"
                :to="inbox.prev_page_url ?? undefined"
                :disabled="!inbox.prev_page_url"
            >
                Anteriores
            </UButton>
            <p class="muted text-sm">Página {{ inbox.current_page }} de {{ inbox.last_page }}</p>
            <UButton
                class="rounded-xl"
                color="neutral"
                variant="outline"
                trailing-icon="i-lucide-arrow-right"
                :to="inbox.next_page_url ?? undefined"
                :disabled="!inbox.next_page_url"
            >
                Siguientes
            </UButton>
        </nav>
    </DashboardLayout>
</template>
