<script setup>
// Academic context (period, moment, section) shown in the shell toolbar.
// The values are still placeholders until a module owns this context.
defineProps({
  period: { type: String, default: '2026–2027' },
  moment: { type: String, default: '1.er momento' },
  section: { type: String, default: '5.º “A”' },
})
defineEmits(['change'])
</script>

<template>
  <div class="flex min-w-0 items-center gap-1">
    <span class="me-1 hidden shrink-0 text-xs font-semibold uppercase tracking-[.1em] text-muted xl:inline">Contexto</span>
    <UButton
      color="neutral"
      variant="ghost"
      icon="i-lucide-calendar-range"
      trailing-icon="i-lucide-chevron-down"
      class="shrink-0 gap-2"
      :ui="{ leadingIcon: 'text-primary', trailingIcon: 'size-4 text-dimmed' }"
      :aria-label="`Período activo: ${period}`"
      @click="$emit('change', 'period')"
    >
      <span class="text-muted">Período</span>
      <span class="font-semibold text-highlighted">{{ period }}</span>
    </UButton>
    <USeparator orientation="vertical" class="h-5" />
    <UButton
      color="neutral"
      variant="ghost"
      icon="i-lucide-layers"
      trailing-icon="i-lucide-chevron-down"
      class="shrink-0 gap-2"
      :ui="{ leadingIcon: 'text-primary', trailingIcon: 'size-4 text-dimmed' }"
      :aria-label="`Momento: ${moment}`"
      @click="$emit('change', 'moment')"
    >
      <span class="hidden text-muted sm:inline">Momento</span>
      <span class="font-semibold text-highlighted">{{ moment }}</span>
    </UButton>
    <USeparator orientation="vertical" class="h-5" />
    <UButton
      color="neutral"
      variant="ghost"
      icon="i-lucide-graduation-cap"
      trailing-icon="i-lucide-chevron-down"
      class="shrink-0 gap-2"
      :ui="{ leadingIcon: 'text-primary', trailingIcon: 'size-4 text-dimmed' }"
      :aria-label="`Año y sección: ${section}`"
      @click="$emit('change', 'section')"
    >
      <span class="hidden text-muted sm:inline">Sección</span>
      <span class="font-semibold text-highlighted">{{ section }}</span>
    </UButton>
    <slot />
  </div>
</template>
