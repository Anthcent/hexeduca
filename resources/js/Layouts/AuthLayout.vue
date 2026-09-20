<script setup>
import { GraduationCap } from 'lucide-vue-next';

defineProps({
    title: {
        type: String,
        default: null,
    },
});
</script>

<template>
    <div class="grid min-h-screen lg:grid-cols-2">
        <div class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-brand-950 via-brand-900 to-brand-800 p-10 text-white lg:flex">
            <span class="pointer-events-none absolute -right-20 -top-24 size-72 rounded-full border-[28px] border-white/5" />
            <span class="pointer-events-none absolute -bottom-16 left-10 size-56 rounded-full bg-brand-400/10 blur-2xl" />

            <div class="relative flex items-center gap-3">
                <div class="grid size-11 place-items-center rounded-2xl bg-white/10 text-brand-100">
                    <GraduationCap class="size-6" />
                </div>
                <div>
                    <p class="font-display text-lg font-extrabold">Educativo</p>
                    <p class="text-xs font-semibold uppercase tracking-[.14em] text-brand-200">Gestión escolar</p>
                </div>
            </div>

            <div class="relative max-w-md">
                <p class="font-display text-3xl font-extrabold leading-tight">Todo el contexto académico, en un solo lugar.</p>
                <p class="mt-4 text-sm leading-relaxed text-brand-100">
                    Períodos, matrículas, notas y comunicaciones de cada institución, siempre con trazabilidad y sin mezclar datos entre escuelas.
                </p>
            </div>

            <p class="relative text-xs text-brand-300">© {{ new Date().getFullYear() }} Educativo</p>
        </div>

        <div class="flex items-center justify-center bg-[rgb(var(--canvas))] px-4 py-10">
            <div class="w-full max-w-[400px]">
                <div class="mb-7 flex items-center gap-2.5 lg:hidden">
                    <div class="grid size-9 place-items-center rounded-xl bg-brand-950 text-base font-bold text-brand-100">
                        <GraduationCap class="size-4" />
                    </div>
                    <span class="font-display text-lg font-extrabold">Educativo</span>
                </div>

                <div class="section-card p-8">
                    <h1 v-if="title" class="font-display text-2xl font-extrabold">
                        {{ title }}
                    </h1>
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
