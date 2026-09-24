<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import {
    Bell, ChevronDown, GraduationCap, LayoutDashboard, ListChecks, Menu,
    Moon, PanelLeftClose, PanelLeftOpen, Search, Sun, Users, X,
} from 'lucide-vue-next';
import SchoolContextBar from '@/Components/school/SchoolContextBar.vue';

const props = defineProps({
    active: {
        type: String,
        default: 'resumen',
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user ?? null);
const isLandlord = computed(() => user.value?.role === 'super-admin');
const availableModules = computed(() => page.props.modules ?? []);
const initials = computed(() => {
    if (!user.value?.name) return '?';
    return user.value.name.split(' ').map((part) => part[0]).slice(0, 2).join('').toUpperCase();
});

const navItems = computed(() => {
    const items = [
        { key: 'resumen', label: 'Inicio', icon: LayoutDashboard, href: route('dashboard') },
    ];

    if (isLandlord.value) {
        items.push({ key: 'instituciones', label: 'Instituciones', icon: GraduationCap, href: route('admin.schools.index') });
    } else {
        items.push(
            { key: 'matriculas', label: 'Matrículas', icon: ListChecks, href: route('academic.matriculas.create'), moduleKey: 'academic' },
            { key: 'academico', label: 'Base académica', icon: GraduationCap, href: route('academic.catalogos'), moduleKey: 'academic' },
        );
    }

    items.push({ key: 'usuarios', label: 'Usuarios', icon: Users, href: route('users.index') });

    return items.filter((item) => !item.moduleKey || availableModules.value.includes(item.moduleKey));
});

const compactSidebar = ref(false);
const mobileMenu = ref(false);
const dark = ref(false);
const contextDockOpen = ref(true);

watch(dark, (value) => {
    document.documentElement.classList.toggle('dark', value);
    localStorage.setItem('educativo-theme', value ? 'dark' : 'light');
});
onMounted(() => {
    dark.value = localStorage.getItem('educativo-theme') === 'dark';
});
</script>

<template>
    <div class="min-h-screen pb-20 lg:pb-0">
        <header class="fixed inset-x-0 top-0 z-50 h-16 border-b bg-[rgb(var(--surface))]/92 backdrop-blur-xl">
            <div class="flex h-full items-center gap-2 px-3 sm:gap-3 sm:px-5">
                <button class="grid size-10 shrink-0 place-items-center rounded-xl hover:bg-[rgb(var(--surface-muted))] lg:hidden" aria-label="Abrir menú" @click="mobileMenu = true">
                    <Menu class="size-5" />
                </button>

                <Link :href="route('home')" class="flex shrink-0 items-center gap-3 lg:w-60">
                    <div class="grid size-10 place-items-center rounded-2xl bg-brand-950 text-brand-100 shadow-lg">
                        <GraduationCap class="size-5" />
                    </div>
                    <div class="hidden sm:block">
                        <p class="font-display text-sm font-extrabold">Educativo</p>
                        <p class="muted text-[11px] font-bold uppercase tracking-[.14em]">Gestión escolar</p>
                    </div>
                </Link>

                <button class="muted mx-auto hidden h-10 min-w-0 max-w-sm flex-1 items-center gap-3 rounded-xl border bg-[rgb(var(--canvas))] px-3 text-left text-sm hover:border-brand-300 xl:flex">
                    <Search class="size-4 shrink-0" />
                    <span class="truncate">Buscar en el sistema…</span>
                    <span class="kbd ml-auto">⌘ K</span>
                </button>

                <div class="ml-auto flex items-center gap-0.5">
                    <button class="hidden size-10 place-items-center rounded-xl hover:bg-[rgb(var(--surface-muted))] sm:grid" :aria-label="dark ? 'Usar modo claro' : 'Usar modo oscuro'" @click="dark = !dark">
                        <Sun v-if="dark" class="size-[18px]" />
                        <Moon v-else class="size-[18px]" />
                    </button>
                    <button class="relative grid size-10 place-items-center rounded-xl hover:bg-[rgb(var(--surface-muted))]" aria-label="Notificaciones">
                        <Bell class="size-[18px]" />
                    </button>
                    <div v-if="user" class="ml-0.5 hidden items-center gap-2 rounded-xl p-1 pr-2 sm:flex">
                        <span class="grid size-8 place-items-center rounded-xl bg-brand-700 text-xs font-bold text-white">{{ initials }}</span>
                        <div class="hidden text-left leading-tight md:block">
                            <p class="text-xs font-bold">{{ user.name }}</p>
                            <p class="muted text-[11px]">{{ user.role }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div
            v-if="!isLandlord"
            class="fixed right-0 top-16 z-[45] border-b border-brand-100/80 bg-[rgb(var(--canvas))]/94 backdrop-blur-xl transition-[height,left] duration-300 dark:border-brand-900"
            :class="[compactSidebar ? 'lg:left-[82px]' : 'lg:left-64', contextDockOpen ? 'left-0 h-[76px]' : 'left-0 h-7']"
        >
            <Transition name="bubble">
                <div v-if="contextDockOpen" class="mx-auto flex h-full max-w-[1500px] items-center px-3 sm:px-5 lg:px-8">
                    <SchoolContextBar class="w-full border border-brand-200/70 shadow-[0_12px_35px_rgba(4,39,31,.12)] dark:border-brand-700">
                        <button class="flex h-10 shrink-0 items-center gap-2 rounded-2xl px-3 text-sm font-bold text-brand-700 transition hover:bg-brand-50 dark:text-brand-200 dark:hover:bg-brand-950" aria-label="Ocultar contexto académico" @click="contextDockOpen = false">
                            <ChevronDown class="size-4 rotate-180" /><span class="hidden sm:inline">Ocultar</span>
                        </button>
                    </SchoolContextBar>
                </div>
            </Transition>
            <button v-if="!contextDockOpen" class="absolute left-1/2 top-0 flex h-7 -translate-x-1/2 items-center gap-2 rounded-b-2xl bg-brand-950 px-4 text-[11px] font-bold text-white shadow-lg transition hover:h-8 hover:bg-brand-900" aria-label="Mostrar contexto académico" @click="contextDockOpen = true">
                <span class="size-1.5 rounded-full bg-brand-300" /><span>Contexto académico</span><ChevronDown class="size-3 text-brand-200" />
            </button>
        </div>

        <aside class="fixed bottom-0 left-0 top-16 z-40 hidden border-r bg-[rgb(var(--surface))] transition-all duration-300 lg:block" :class="compactSidebar ? 'w-[82px]' : 'w-64'">
            <div class="flex h-full flex-col p-3">
                <nav class="scrollbar-thin flex-1 space-y-0.5 overflow-y-auto" :class="isLandlord ? '' : 'mt-1'">
                    <Link
                        v-for="item in navItems"
                        :key="item.key"
                        :href="item.href"
                        class="flex h-10 w-full items-center rounded-[18px] text-sm font-semibold transition"
                        :class="[compactSidebar ? 'justify-center' : 'gap-3 px-3', active === item.key ? 'bg-brand-50 text-brand-800 dark:bg-brand-950 dark:text-brand-100' : 'muted hover:bg-[rgb(var(--surface-muted))] hover:text-[rgb(var(--text))]']"
                        :title="compactSidebar ? item.label : undefined"
                    >
                        <component :is="item.icon" class="size-[18px] shrink-0" />
                        <span v-if="!compactSidebar">{{ item.label }}</span>
                    </Link>
                </nav>
                <button class="muted mt-2 flex h-10 items-center rounded-[18px] hover:bg-[rgb(var(--surface-muted))]" :class="compactSidebar ? 'justify-center' : 'gap-3 px-3'" @click="compactSidebar = !compactSidebar">
                    <PanelLeftOpen v-if="compactSidebar" class="size-4" />
                    <PanelLeftClose v-else class="size-4" />
                    <span v-if="!compactSidebar" class="text-sm font-semibold">Contraer menú</span>
                </button>
            </div>
        </aside>

        <Transition name="fade">
            <div v-if="mobileMenu" class="fixed inset-0 z-[70] bg-brand-950/55 backdrop-blur-sm lg:hidden" @click="mobileMenu = false" />
        </Transition>
        <Transition name="slide">
            <aside v-if="mobileMenu" class="fixed inset-y-0 left-0 z-[80] w-[86%] max-w-sm bg-[rgb(var(--surface))] p-4 shadow-lift lg:hidden">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <span class="grid size-10 place-items-center rounded-2xl bg-brand-950 text-brand-100"><GraduationCap class="size-5" /></span>
                        <div>
                            <p class="font-display font-extrabold">Educativo</p>
                            <p class="muted text-xs">Gestión escolar</p>
                        </div>
                    </div>
                    <button class="rounded-xl p-2 hover:bg-[rgb(var(--surface-muted))]" @click="mobileMenu = false"><X class="size-5" /></button>
                </div>
                <nav class="mt-4 space-y-1">
                    <Link
                        v-for="item in navItems"
                        :key="item.key"
                        :href="item.href"
                        class="flex h-12 w-full items-center gap-3 rounded-xl px-3 text-sm font-semibold"
                        :class="active === item.key ? 'bg-brand-50 text-brand-800 dark:bg-brand-950 dark:text-brand-100' : 'muted'"
                        @click="mobileMenu = false"
                    >
                        <component :is="item.icon" class="size-[18px]" />{{ item.label }}
                    </Link>
                </nav>
            </aside>
        </Transition>

        <nav class="fixed inset-x-0 bottom-0 z-50 border-t bg-[rgb(var(--surface))]/95 px-2 pb-[max(.45rem,env(safe-area-inset-bottom))] pt-1.5 backdrop-blur-xl lg:hidden">
            <div class="mx-auto grid max-w-lg" :style="{ gridTemplateColumns: `repeat(${navItems.length}, minmax(0, 1fr))` }">
                <Link v-for="item in navItems" :key="item.key" :href="item.href" class="flex min-w-0 flex-col items-center gap-1 rounded-xl px-1 py-1.5 text-[10px] font-bold" :class="active === item.key ? 'text-brand-700 dark:text-brand-300' : 'muted'">
                    <span class="relative grid size-8 place-items-center rounded-xl" :class="active === item.key && 'bg-brand-50 dark:bg-brand-950'">
                        <component :is="item.icon" class="size-[18px]" />
                        <span v-if="active === item.key" class="absolute -top-1 h-0.5 w-5 rounded-full bg-brand-500" />
                    </span>
                    <span class="truncate">{{ item.label.split(' ')[0] }}</span>
                </Link>
            </div>
        </nav>

        <main class="transition-all duration-300" :class="[compactSidebar ? 'lg:pl-[82px]' : 'lg:pl-64', !isLandlord && contextDockOpen ? 'pt-[8.75rem]' : 'pt-[5.75rem]']">
            <div class="mx-auto max-w-[1500px] p-4 sm:p-6 lg:p-8">
                <slot />
            </div>
        </main>
    </div>
</template>
