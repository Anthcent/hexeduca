<script setup>
defineProps({ tone: { type: String, default: 'neutral' }, dot: Boolean })
const tones = {
  neutral: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-200',
  brand: 'bg-brand-100 text-brand-700 dark:bg-brand-950 dark:text-brand-200',
  success: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
  warning: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
  danger: 'bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300',
  violet: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-200',
}
</script>
<template>
  <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" :class="tones[tone]">
    <span v-if="dot" class="size-1.5 rounded-full bg-current opacity-70" />
    <slot />
  </span>
</template>
