<script setup>
import { CheckCircle2, X } from 'lucide-vue-next'
defineProps({
  show: Boolean,
  title: { type: String, default: 'Proceso completado' },
  message: { type: String, default: 'La información se actualizó correctamente.' },
})
defineEmits(['close'])
</script>
<template>
  <Teleport to="body">
    <Transition name="slide">
      <div v-if="show" class="fixed bottom-5 right-5 z-[110] flex w-[calc(100%-2.5rem)] max-w-sm items-start gap-3 rounded-[22px] border border-white/10 bg-brand-950 p-4 text-white shadow-[0_24px_70px_rgba(4,39,31,.42)]">
        <div class="grid size-9 shrink-0 place-items-center rounded-full bg-emerald-400/15 text-emerald-300"><CheckCircle2 class="size-5" /></div>
        <div class="min-w-0 flex-1">
          <p class="text-sm font-bold">{{ title }}</p>
          <p class="mt-0.5 text-sm text-brand-200">{{ message }}</p>
        </div>
        <button class="rounded-md p-1 text-brand-200 hover:bg-white/10 hover:text-white" aria-label="Cerrar" @click="$emit('close')"><X class="size-4" /></button>
      </div>
    </Transition>
  </Teleport>
</template>
