<script setup>
import { X } from 'lucide-vue-next'
defineProps({ open: Boolean, title: { type: String, default: 'Confirmar acción' } })
defineEmits(['close'])
</script>
<template>
  <Teleport to="body">
    <Transition name="fade">
      <div v-if="open" class="fixed inset-0 z-[100] grid place-items-center bg-slate-950/45 p-4 backdrop-blur-sm" @click.self="$emit('close')">
        <div role="dialog" aria-modal="true" class="surface w-full max-w-md rounded-2xl p-5 shadow-lift">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="font-display text-lg font-bold">{{ title }}</p>
              <p class="muted mt-1 text-sm"><slot name="description" /></p>
            </div>
            <button class="grid size-9 place-items-center rounded-lg hover:bg-[rgb(var(--surface-muted))]" aria-label="Cerrar" @click="$emit('close')"><X class="size-4" /></button>
          </div>
          <div class="mt-5"><slot /></div>
          <div class="mt-6 flex justify-end gap-2"><slot name="footer" /></div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
