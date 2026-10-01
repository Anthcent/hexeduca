<script setup>
import { computed, toRef, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { useToast } from '@nuxt/ui/composables/useToast';
import SchoolContextBar from '@/Components/school/SchoolContextBar.vue';
import { useTheme } from '@/composables/useTheme';
import { toPath, useShellNavigation } from './navigation';

const props = defineProps({
    // Key of the nav entry to highlight when the current URL matches none
    // (see navigation.js); kept so every page can keep passing it.
    active: {
        type: String,
        default: 'resumen',
    },
});

const page = usePage();
const { user, isLandlord, navigationLists, quickActions, searchGroups } = useShellNavigation(toRef(props, 'active'));
const { dark, toggleTheme } = useTheme();

const school = computed(() => page.props.school ?? null);
const brandName = computed(() => school.value?.name ?? (isLandlord.value ? 'Panel central' : 'Gestión escolar'));
const homePath = computed(() => route('dashboard', undefined, false));
const roleLabel = computed(() => user.value?.roleLabel ?? user.value?.role ?? '');

// Shared by the Notifications module only when it is usable for this user;
// absent or null otherwise, and then the bell stays disabled.
const notificationsBell = computed(() => page.props.notifications ?? null);
const unreadNotifications = computed(() => notificationsBell.value?.unreadCount ?? 0);
const inboxPath = computed(() => (notificationsBell.value ? toPath(notificationsBell.value.inboxUrl) : null));
const bellLabel = computed(() => (unreadNotifications.value > 0
    ? `Notificaciones: ${unreadNotifications.value} sin leer`
    : 'Notificaciones'));

// Flash messages (`flash.success` / `flash.error`, shared by HandleInertiaRequests)
// become toasts here, once for every page. Each response carries a new flash
// object, so a repeated message still shows again.
const toast = useToast();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            toast.add({ title: flash.success, color: 'success', icon: 'i-lucide-circle-check' });
        }

        if (flash?.error) {
            toast.add({ title: flash.error, color: 'error', icon: 'i-lucide-circle-alert' });
        }
    },
    { immediate: true },
);

function logout() {
    router.post(route('logout'));
}

const userMenu = computed(() => [
    [{ type: 'label', label: user.value?.name, description: user.value?.email }],
    [{
        label: dark.value ? 'Usar modo claro' : 'Usar modo oscuro',
        icon: dark.value ? 'i-lucide-sun' : 'i-lucide-moon',
        onSelect: (event) => {
            event.preventDefault();
            toggleTheme();
        },
    }],
    [{ label: 'Cerrar sesión', icon: 'i-lucide-log-out', onSelect: logout }],
]);

const navigationUi = {
    link: 'py-2',
    linkLabel: 'text-[0.84375rem]',
    childLink: 'py-2',
    separator: 'my-3 mx-2.5',
};
</script>

<template>
    <UDashboardGroup storage="local" storage-key="hexeduca-shell" unit="rem">
        <a
            href="#main-content"
            class="sr-only z-50 rounded-md bg-default px-3 py-2 text-sm font-semibold text-highlighted shadow-card focus:not-sr-only focus:fixed focus:left-3 focus:top-3"
        >Saltar al contenido</a>

        <UDashboardSidebar
            id="main"
            collapsible
            :resizable="false"
            :default-size="16"
            :min-size="16"
            :max-size="16"
            :collapsed-size="4"
            :ui="{
                root: 'app-sidebar',
                content: 'app-sidebar max-w-xs',
                header: 'border-b border-default',
                body: 'scrollbar-thin gap-0 py-4',
                footer: 'border-t border-default py-3',
            }"
        >
            <template #header="{ collapsed }">
                <!-- Collapse handle on the sidebar edge (desktop only). -->
                <UDashboardSidebarCollapse
                    color="neutral"
                    variant="outline"
                    size="xs"
                    class="absolute -end-3.5 top-5 z-10 rounded-full bg-white text-brand-900 shadow-card ring-black/10 hover:bg-brand-50 dark:bg-brand-900 dark:text-brand-100 dark:ring-white/15 dark:hover:bg-brand-800"
                    :ui="{ leadingIcon: 'size-4' }"
                />
                <Link :href="homePath" class="flex min-w-0 items-center gap-3 rounded-md" :aria-label="`${brandName} — Inicio`">
                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-500/25 text-brand-100 ring-1 ring-inset ring-white/10">
                        <UIcon name="i-lucide-graduation-cap" class="size-[18px]" />
                    </span>
                    <span v-if="!collapsed" class="min-w-0 leading-tight">
                        <span class="block text-xs font-semibold uppercase tracking-[.12em] text-dimmed">Educativo</span>
                        <span class="block truncate text-sm font-bold text-highlighted">{{ brandName }}</span>
                    </span>
                </Link>
            </template>

            <template #default="{ collapsed }">
                <UNavigationMenu
                    :items="navigationLists(collapsed)"
                    orientation="vertical"
                    color="neutral"
                    variant="pill"
                    :collapsed="collapsed"
                    tooltip
                    popover
                    :ui="navigationUi"
                    aria-label="Navegación principal"
                />
            </template>

            <template #footer="{ collapsed }">
                <UDropdownMenu
                    v-if="user"
                    :items="userMenu"
                    :content="{ side: collapsed ? 'right' : 'top', align: collapsed ? 'end' : 'start' }"
                    :ui="{ content: collapsed ? 'w-60' : 'w-(--reka-dropdown-menu-trigger-width) min-w-56' }"
                >
                    <UButton
                        color="neutral"
                        variant="ghost"
                        block
                        class="data-[state=open]:bg-elevated"
                        :class="collapsed ? 'justify-center px-0' : 'justify-start px-2'"
                        :aria-label="`Menú de usuario: ${user.name}`"
                    >
                        <UUser
                            :name="collapsed ? undefined : user.name"
                            :description="collapsed ? undefined : roleLabel"
                            :avatar="{ alt: user.name, ui: { root: 'bg-brand-600', fallback: 'font-semibold text-white' } }"
                            size="md"
                            class="min-w-0"
                            :ui="{ wrapper: 'min-w-0 text-start', name: 'truncate', description: 'truncate text-xs' }"
                        />
                        <UIcon v-if="!collapsed" name="i-lucide-chevrons-up-down" class="ms-auto size-4 shrink-0 text-dimmed" />
                    </UButton>
                </UDropdownMenu>
            </template>
        </UDashboardSidebar>

        <UDashboardPanel id="content">
            <UDashboardNavbar
                :ui="{
                    root: 'gap-2 bg-default/80 px-3 backdrop-blur sm:px-4 lg:px-6',
                    left: 'flex-1 gap-2',
                    right: 'gap-1',
                }"
            >
                <template #leading>
                    <UDashboardSearchButton
                        label="Buscar en el sistema…"
                        variant="outline"
                        class="w-full max-w-md bg-default"
                        :ui="{ label: 'hidden text-muted sm:inline', trailing: 'hidden lg:flex items-center gap-0.5 ms-auto' }"
                    />
                </template>

                <template #right>
                    <UDropdownMenu v-if="quickActions.length > 0" :items="quickActions" :content="{ align: 'end' }" :ui="{ content: 'min-w-56' }">
                        <UButton
                            icon="i-lucide-plus"
                            trailing-icon="i-lucide-chevron-down"
                            color="primary"
                            label="Acción rápida"
                            aria-label="Acción rápida"
                            :ui="{ label: 'hidden md:inline', trailingIcon: 'hidden md:inline-flex size-4' }"
                        />
                    </UDropdownMenu>

                    <UTooltip :text="bellLabel">
                        <UButton
                            v-if="notificationsBell"
                            :to="inboxPath"
                            color="neutral"
                            variant="outline"
                            square
                            :aria-label="bellLabel"
                        >
                            <UChip
                                :show="unreadNotifications > 0"
                                :text="unreadNotifications > 99 ? '99+' : unreadNotifications"
                                color="error"
                                size="3xl"
                                :ui="{ base: 'px-1 text-[10px] font-bold ring-2 ring-(--ui-bg)' }"
                            >
                                <UIcon name="i-lucide-bell" class="size-5" />
                            </UChip>
                        </UButton>
                        <!-- Without the Notifications module there is no inbox to open. -->
                        <UButton v-else color="neutral" variant="outline" icon="i-lucide-bell" aria-label="Notificaciones" disabled />
                    </UTooltip>

                    <UTooltip :text="dark ? 'Usar modo claro' : 'Usar modo oscuro'">
                        <UButton
                            color="neutral"
                            variant="outline"
                            :icon="dark ? 'i-lucide-sun' : 'i-lucide-moon'"
                            class="hidden sm:inline-flex"
                            :aria-label="dark ? 'Usar modo claro' : 'Usar modo oscuro'"
                            @click="toggleTheme"
                        />
                    </UTooltip>

                    <UDropdownMenu v-if="user" :items="userMenu" :content="{ align: 'end' }" :ui="{ content: 'w-60' }">
                        <UButton color="neutral" variant="ghost" class="ms-1 gap-2 px-1.5" :aria-label="`Menú de usuario: ${user.name}`">
                            <UUser
                                :name="user.name"
                                :description="roleLabel"
                                :avatar="{ alt: user.name, ui: { root: 'bg-brand-700', fallback: 'font-semibold text-white' } }"
                                size="sm"
                                :ui="{ wrapper: 'hidden max-w-44 text-start md:block', name: 'truncate', description: 'truncate text-xs' }"
                            />
                            <UIcon name="i-lucide-chevron-down" class="hidden size-4 text-dimmed md:block" />
                        </UButton>
                    </UDropdownMenu>
                </template>
            </UDashboardNavbar>

            <UDashboardToolbar v-if="!isLandlord" :ui="{ root: 'min-h-12 bg-default/50 px-2 sm:px-4 lg:px-6' }">
                <template #left>
                    <SchoolContextBar />
                </template>
            </UDashboardToolbar>

            <main id="main-content" scroll-region class="min-h-0 flex-1 overflow-y-auto" tabindex="-1">
                <div class="mx-auto w-full max-w-[1500px] p-4 sm:p-6 lg:p-8">
                    <slot />
                </div>
            </main>
        </UDashboardPanel>

        <UDashboardSearch
            :groups="searchGroups"
            :color-mode="false"
            placeholder="Buscar páginas y acciones…"
            title="Buscar"
            description="Busca una página o una acción"
        />
    </UDashboardGroup>
</template>
