<script setup>
import { Check, Clock3 } from 'lucide-vue-next'
defineProps({
  title: { type: String, default: 'Seguimiento del proceso' },
  items: { type: Array, required: true },
})
</script>
<template>
  <div class="section-card p-5 sm:p-6">
    <div class="flex items-center justify-between gap-3"><div><p class="font-display text-lg font-extrabold">{{ title }}</p><p class="muted mt-1 text-sm">Historial visible y estado actual del trámite.</p></div><slot name="action" /></div>
    <ol class="relative mt-6 space-y-0">
      <li v-for="(item, index) in items" :key="item.title" class="relative flex gap-4 pb-6 last:pb-0">
        <div v-if="index < items.length - 1" class="absolute left-[17px] top-9 h-[calc(100%-14px)] w-0.5" :class="item.done ? 'bg-brand-400' : 'bg-[rgb(var(--line))]'" />
        <span class="relative z-10 grid size-9 shrink-0 place-items-center rounded-full border-2 transition" :class="item.done ? 'border-brand-500 bg-brand-500 text-white' : item.current ? 'border-brand-500 bg-[rgb(var(--surface))] text-brand-600 shadow-focus' : 'border-[rgb(var(--line))] bg-[rgb(var(--surface))] text-[rgb(var(--muted))]'">
          <Check v-if="item.done" class="size-4" /><span v-else-if="item.current" class="size-2.5 animate-soft-pulse rounded-full bg-brand-500" /><Clock3 v-else class="size-4" />
        </span>
        <div class="min-w-0 flex-1 pt-0.5"><div class="flex flex-wrap items-start justify-between gap-2"><div><p class="text-sm font-bold">{{ item.title }}</p><p class="muted mt-1 text-sm leading-relaxed">{{ item.description }}</p></div><span class="muted text-xs font-semibold">{{ item.time }}</span></div><div v-if="item.meta" class="mt-2 inline-flex rounded-lg bg-[rgb(var(--surface-muted))] px-2.5 py-1 text-xs font-semibold">{{ item.meta }}</div></div>
      </li>
    </ol>
  </div>
</template>
