<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useTheme } from '@/composables/useTheme';

defineProps({
    title: {
        type: String,
        default: null,
    },
    description: {
        type: String,
        default: null,
    },
    eyebrow: {
        type: String,
        default: 'Acceso seguro',
    },
});

const page = usePage();
const { dark, toggleTheme } = useTheme();

// On a school subdomain the login shows that school; the landlord host shows the product.
const schoolName = computed(() => page.props.school?.name ?? null);
const brandTitle = computed(() => schoolName.value ?? 'Gestión escolar');
const monogram = computed(() => {
    const words = (schoolName.value ?? 'Educativo').split(/\s+/).filter(Boolean);
    const letters = words.length > 1 ? words.map((word) => word[0]).join('') : words[0] ?? '';

    return letters.slice(0, 3).toUpperCase();
});

const highlights = [
    { icon: 'i-lucide-calendar-range', text: 'Períodos, momentos y secciones en un solo lugar' },
    { icon: 'i-lucide-clipboard-check', text: 'Matrículas y notas con trazabilidad' },
    { icon: 'i-lucide-shield-check', text: 'Datos de cada institución siempre aislados' },
];
</script>

<template>
    <div class="flex min-h-screen bg-[rgb(var(--canvas))]">
        <aside class="relative hidden w-[54%] flex-col overflow-hidden bg-linear-to-br from-brand-950 via-brand-900 to-brand-800 px-12 py-10 text-white lg:flex xl:px-16">
            <span class="auth-grid pointer-events-none absolute inset-0" aria-hidden="true" />
            <span class="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full border border-white/10" aria-hidden="true" />
            <span class="pointer-events-none absolute -bottom-20 left-16 size-64 rounded-full bg-brand-400/10 blur-3xl" aria-hidden="true" />

            <div class="relative flex flex-1 flex-col justify-center">
                <p class="text-xs font-bold uppercase tracking-[.16em] text-brand-200">Educativo</p>
                <h2 class="mt-3 max-w-xl text-4xl font-bold leading-tight tracking-tight xl:text-5xl">{{ brandTitle }}</h2>
                <p class="mt-4 max-w-lg text-base leading-relaxed text-brand-100/90">
                    Períodos, matrículas, notas y comunicaciones de la institución, con trazabilidad y sin mezclar datos entre escuelas.
                </p>

                <div class="my-12 flex items-center justify-center gap-6" aria-hidden="true">
                    <span class="h-[3px] w-16 rounded-full bg-amber" />
                    <span class="text-6xl font-bold tracking-[.18em] xl:text-7xl">{{ monogram }}</span>
                    <span class="h-[3px] w-16 rounded-full bg-amber" />
                </div>

                <ul class="grid max-w-2xl grid-cols-3 gap-3">
                    <li v-for="item in highlights" :key="item.text" class="rounded-xl bg-white/6 p-4 ring-1 ring-inset ring-white/12 backdrop-blur-sm">
                        <span class="grid size-8 place-items-center rounded-lg bg-white/10">
                            <UIcon :name="item.icon" class="size-4 text-brand-100" />
                        </span>
                        <p class="mt-3 text-sm font-medium leading-snug">{{ item.text }}</p>
                    </li>
                </ul>
            </div>

            <p class="relative border-t border-white/10 pt-5 text-xs text-brand-200">
                © {{ new Date().getFullYear() }} Educativo — Acceso restringido a personal y estudiantes autorizados.
            </p>
        </aside>

        <main class="relative flex flex-1 flex-col items-center justify-center px-4 py-12 sm:px-8">
            <UButton
                color="neutral"
                variant="ghost"
                :icon="dark ? 'i-lucide-sun' : 'i-lucide-moon'"
                class="absolute right-4 top-4"
                :aria-label="dark ? 'Usar modo claro' : 'Usar modo oscuro'"
                @click="toggleTheme"
            />

            <div class="w-full max-w-[420px]">
                <div class="mb-8 text-center lg:hidden">
                    <p class="text-4xl font-bold tracking-[.18em] text-brand-900 dark:text-brand-100">{{ monogram }}</p>
                    <p class="mt-2 text-sm font-semibold text-highlighted">{{ brandTitle }}</p>
                </div>

                <div class="rounded-xl bg-default p-6 shadow-[0_18px_50px_rgba(4,39,31,.10)] ring-1 ring-default sm:p-8">
                    <div class="flex items-center gap-2.5">
                        <span class="grid size-8 place-items-center rounded-lg bg-brand-900 text-white dark:bg-brand-700">
                            <UIcon name="i-lucide-lock" class="size-4" />
                        </span>
                        <span class="text-xs font-bold uppercase tracking-[.12em] text-brand-800 dark:text-brand-200">{{ eyebrow }}</span>
                    </div>

                    <h1 v-if="title" class="mt-5 text-2xl font-bold tracking-tight text-highlighted sm:text-[1.75rem]">{{ title }}</h1>
                    <p v-if="description" class="mt-1.5 text-sm text-muted">{{ description }}</p>

                    <slot />
                </div>

                <p class="mt-5 flex items-center justify-center gap-1.5 text-center text-xs text-muted">
                    <UIcon name="i-lucide-shield-check" class="size-4 shrink-0 text-primary" />
                    Acceso solo para cuentas autorizadas de la institución.
                </p>
            </div>
        </main>
    </div>
</template>

<style scoped>
.auth-grid {
    background-image:
        linear-gradient(rgb(255 255 255 / 0.04) 1px, transparent 1px),
        linear-gradient(90deg, rgb(255 255 255 / 0.04) 1px, transparent 1px);
    background-size: 32px 32px;
    mask-image: radial-gradient(ellipse at 30% 40%, black 30%, transparent 80%);
}
</style>
