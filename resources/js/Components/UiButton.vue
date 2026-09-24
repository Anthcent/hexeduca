<script setup>
import { LoaderCircle } from 'lucide-vue-next'

const props = defineProps({
  variant: { type: String, default: 'primary' },
  size: { type: String, default: 'md' },
  type: { type: String, default: 'button' },
  loading: Boolean,
  iconOnly: Boolean,
  disabled: Boolean,
})

const variants = {
  primary: 'bg-brand-500 text-white shadow-[0_8px_18px_rgba(24,168,121,.24)] hover:bg-brand-600 active:bg-brand-700',
  secondary: 'border bg-[rgb(var(--surface))] text-[rgb(var(--text))] shadow-xs hover:border-brand-300 hover:bg-brand-50 dark:hover:bg-brand-950/40',
  soft: 'bg-brand-50 text-brand-700 hover:bg-brand-100 dark:bg-brand-950/60 dark:text-brand-200 dark:hover:bg-brand-900',
  danger: 'bg-red-600 text-white shadow-[0_8px_18px_rgba(220,38,38,.20)] hover:bg-red-700',
  success: 'bg-emerald-600 text-white shadow-[0_8px_18px_rgba(5,150,105,.24)] hover:bg-emerald-700 active:bg-emerald-800',
  warning: 'bg-amber-500 text-amber-950 shadow-[0_8px_18px_rgba(245,158,11,.25)] hover:bg-amber-400 active:bg-amber-600',
  info: 'bg-sky-600 text-white shadow-[0_8px_18px_rgba(2,132,199,.24)] hover:bg-sky-700 active:bg-sky-800',
  violet: 'bg-violet-600 text-white shadow-[0_8px_18px_rgba(124,58,237,.24)] hover:bg-violet-700 active:bg-violet-800',
  coral: 'bg-rose-500 text-white shadow-[0_8px_18px_rgba(244,63,94,.24)] hover:bg-rose-600 active:bg-rose-700',
  gradient: 'bg-linear-to-r from-emerald-500 via-teal-500 to-sky-500 text-white shadow-[0_10px_24px_rgba(13,148,136,.28)] hover:saturate-125 hover:shadow-lg',
  ghost: 'muted hover:bg-[rgb(var(--surface-muted))] hover:text-[rgb(var(--text))]',
  dark: 'bg-ink text-white shadow-lg hover:bg-slate-800 dark:bg-white dark:text-slate-900',
}
const sizes = {
  sm: 'h-9 px-3 text-xs rounded-lg gap-1.5',
  md: 'h-11 px-4 text-sm rounded-xl gap-2',
  lg: 'h-12 px-5 text-sm rounded-xl gap-2.5',
}
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    class="inline-flex select-none items-center justify-center whitespace-nowrap font-semibold transition duration-200 disabled:pointer-events-none disabled:opacity-50"
    :class="[variants[variant], sizes[size], iconOnly && 'aspect-square! px-0!']"
  >
    <LoaderCircle v-if="loading" class="size-4 animate-spin" />
    <slot name="icon" />
    <slot />
  </button>
</template>
