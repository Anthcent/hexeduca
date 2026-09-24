<script setup>
import { CalendarRange, ChevronDown, GraduationCap, Layers3 } from 'lucide-vue-next'

defineProps({
  period: { type: String, default: '2026–2027' },
  moment: { type: String, default: '1.er momento' },
  section: { type: String, default: '5.º “A”' },
})
defineEmits(['change'])
</script>

<template>
  <div class="surface scrollbar-thin flex items-center gap-1.5 overflow-x-auto rounded-[28px] p-1.5">
    <button class="group relative flex min-w-[210px] overflow-hidden rounded-[23px] bg-brand-950 px-3.5 py-2 text-left text-white shadow-lg shadow-brand-950/15 transition hover:-translate-y-0.5 hover:shadow-xl lg:min-w-[250px]" @click="$emit('change', 'period')">
      <span class="absolute -right-5 -top-8 size-24 rounded-full border-18 border-white/5" />
      <span class="relative grid size-10 shrink-0 place-items-center rounded-full bg-white/10 text-brand-100"><CalendarRange class="size-[18px]" /></span>
      <span class="relative ml-3 min-w-0 flex-1"><span class="flex items-center gap-1.5 text-[10px] font-extrabold uppercase tracking-wider text-brand-200"><span class="size-1.5 rounded-full bg-brand-300" />Período activo</span><strong class="mt-0.5 block truncate font-display text-base">{{ period }}</strong></span>
      <ChevronDown class="relative ml-2 mt-3 size-4 text-brand-200 transition group-hover:translate-y-0.5" />
    </button>
    <button class="group flex min-w-[170px] items-center gap-3 rounded-[21px] px-3 py-2 text-left transition hover:bg-[rgb(var(--surface-muted))] lg:min-w-48" @click="$emit('change', 'moment')">
      <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand-950"><Layers3 class="size-[18px]" /></span>
      <span class="min-w-0 flex-1"><span class="muted block text-[11px] font-bold uppercase tracking-wider">Momento</span><strong class="block truncate text-sm">{{ moment }}</strong></span>
      <ChevronDown class="muted size-4 transition group-hover:translate-y-0.5" />
    </button>
    <button class="group flex min-w-[160px] items-center gap-3 rounded-[21px] px-3 py-2 text-left transition hover:bg-[rgb(var(--surface-muted))] lg:min-w-44" @click="$emit('change', 'section')">
      <span class="grid size-9 shrink-0 place-items-center rounded-full bg-amber-50 text-amber-700 dark:bg-amber-950"><GraduationCap class="size-[18px]" /></span>
      <span class="min-w-0 flex-1"><span class="muted block text-[11px] font-bold uppercase tracking-wider">Año y sección</span><strong class="block truncate text-sm">{{ section }}</strong></span>
      <ChevronDown class="muted size-4 transition group-hover:translate-y-0.5" />
    </button>
    <div class="hidden flex-1 xl:block" />
    <slot />
  </div>
</template>
