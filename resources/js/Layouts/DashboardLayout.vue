<script setup>
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';

defineProps({
    active: {
        type: String,
        default: 'resumen',
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const initials = computed(() => {
    if (!user.value?.name) return '?';
    return user.value.name
        .split(' ')
        .map((part) => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase();
});

const isLandlord = computed(() => user.value?.role === 'super-admin');

const navItems = computed(() => {
    const items = [
        {
            key: 'resumen',
            label: 'Resumen',
            icon: 'M3 3h7v9H3V3Zm11 0h7v5h-7V3Zm0 9h7v9h-7v-9Zm-11 4h7v5H3v-5Z',
            href: route('dashboard'),
        },
    ];

    if (isLandlord.value) {
        items.push({
            key: 'instituciones',
            label: 'Instituciones',
            icon: 'M4 21V8l8-5 8 5v13M9 21v-6h6v6',
            href: route('admin.schools.index'),
        });
    } else {
        items.push(
            {
                key: 'matriculas',
                label: 'Matrículas',
                icon: 'M4 19V5a2 2 0 0 1 2-2h9l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z',
                href: route('academic.matriculas.create'),
            },
            {
                key: 'academico',
                label: 'Académico',
                icon: 'M12 3 2 8l10 5 10-5-10-5Zm-10 9 10 5 10-5',
                href: route('academic.catalogos'),
            },
        );
    }

    items.push({
        key: 'usuarios',
        label: 'Usuarios',
        icon: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-8 9a8 8 0 0 1 16 0',
        href: route('users.index'),
    });

    return items;
});
</script>

<template>
    <div class="min-h-screen bg-canvas">
        <header class="sticky top-0 z-10 flex h-16 items-center justify-between border-b border-outline bg-white px-4">
            <Link :href="route('home')" class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-g1 bg-brand text-sm font-medium text-white">
                    E
                </div>
                <span class="text-[1.2rem] text-ink-muted"><strong class="font-medium text-ink">Educativo</strong></span>
            </Link>

            <div class="flex items-center gap-3">
                <span
                    v-if="user"
                    class="hidden rounded-pill bg-blue-light px-3 py-1 text-xs font-medium text-blue sm:inline-block"
                >
                    {{ user.role ?? 'Usuario' }}
                </span>
                <div v-if="user" class="flex items-center gap-2.5">
                    <div class="text-right leading-tight">
                        <div class="text-sm font-medium text-ink">{{ user.name }}</div>
                        <div class="text-xs text-ink-muted">{{ user.email }}</div>
                    </div>
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-green text-xs font-medium text-white">
                        {{ initials }}
                    </div>
                </div>
            </div>
        </header>

        <div class="flex items-start">
            <aside class="sticky top-16 flex w-64 flex-shrink-0 flex-col gap-1 p-3">
                <button
                    class="mb-3 flex w-fit items-center gap-2.5 rounded-pill bg-blue-light py-3.5 pl-4 pr-5 text-sm font-medium text-blue shadow-e1 transition-shadow hover:shadow-e2"
                >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M12 5v14M5 12h14" />
                    </svg>
                    Nuevo
                </button>

                <Link
                    v-for="item in navItems"
                    :key="item.key"
                    :href="item.href"
                    class="flex items-center gap-4 rounded-r-g4 py-2.5 pl-6 pr-4 text-sm"
                    :class="active === item.key
                        ? 'bg-blue-light font-medium text-blue'
                        : 'text-ink hover:bg-black/[0.04]'"
                >
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path :d="item.icon" />
                    </svg>
                    {{ item.label }}
                </Link>
            </aside>

            <main class="min-w-0 flex-1 p-6">
                <slot />
            </main>
        </div>
    </div>
</template>
