<script setup>
import { Check, ChevronLeft, ChevronRight, FileCheck2, GraduationCap, School, UserRound } from 'lucide-vue-next'
import UiButton from '../UiButton.vue'

const props = defineProps({ step: { type: Number, default: 1 } })
const emit = defineEmits(['update:step', 'finish'])
const steps = [
  { title: 'Estudiante', short: 'Datos', icon: UserRound },
  { title: 'Contexto académico', short: 'Curso', icon: School },
  { title: 'Documentos', short: 'Soportes', icon: FileCheck2 },
  { title: 'Confirmación', short: 'Final', icon: GraduationCap },
]

function next() {
  if (props.step < steps.length) emit('update:step', props.step + 1)
  else emit('finish')
}
</script>

<template>
  <div class="section-card overflow-visible">
    <div class="border-b p-5 sm:p-6">
      <div class="flex items-start justify-between gap-4">
        <div><p class="font-display text-lg font-extrabold">Nueva inscripción</p><p class="muted mt-1 text-sm">Completa la información sin perder el contexto del período.</p></div>
        <span class="rounded-full bg-brand-50 px-3 py-1.5 text-xs font-bold text-brand-700 dark:bg-brand-950 dark:text-brand-200">Paso {{ step }} de {{ steps.length }}</span>
      </div>
      <div class="relative mt-6 hidden grid-cols-4 gap-2 sm:grid">
        <div class="absolute left-[12.5%] right-[12.5%] top-5 h-0.5 bg-[rgb(var(--line))]" />
        <div class="absolute left-[12.5%] top-5 h-0.5 origin-left bg-brand-500 transition-all duration-500" :style="{ width: `${((step - 1) / 3) * 75}%` }" />
        <button v-for="(item, index) in steps" :key="item.title" class="relative z-10 flex flex-col items-center text-center" @click="index + 1 <= step && emit('update:step', index + 1)">
          <span class="grid size-10 place-items-center rounded-full border-2 transition-all duration-300" :class="index + 1 < step ? 'border-brand-500 bg-brand-500 text-white' : index + 1 === step ? 'border-brand-500 bg-[rgb(var(--surface))] text-brand-600 shadow-focus' : 'border-[rgb(var(--line))] bg-[rgb(var(--surface))] text-[rgb(var(--muted))]'">
            <Check v-if="index + 1 < step" class="size-4" /><component :is="item.icon" v-else class="size-4" />
          </span>
          <span class="mt-2 text-xs font-bold" :class="index + 1 <= step ? 'text-[rgb(var(--text))]' : 'muted'">{{ item.short }}</span>
        </button>
      </div>
      <div class="mt-5 flex items-center gap-3 sm:hidden">
        <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-500 text-white"><component :is="steps[step - 1].icon" class="size-4" /></span>
        <div class="min-w-0 flex-1"><p class="text-sm font-bold">{{ steps[step - 1].title }}</p><div class="mt-2 h-1.5 rounded-full bg-[rgb(var(--surface-muted))]"><div class="h-full rounded-full bg-brand-500 transition-all duration-500" :style="{width:`${(step/steps.length)*100}%`}" /></div></div>
      </div>
    </div>

    <div class="min-h-[280px] p-5 sm:p-6">
      <Transition name="step" mode="out-in">
        <div :key="step" class="animate-float-in">
          <div v-if="step === 1" class="grid gap-4 sm:grid-cols-2">
            <label><span class="label">Cédula escolar o documento</span><input class="control" value="V-30.245.810" /></label>
            <label><span class="label">Fecha de nacimiento</span><input class="control" type="date" value="2015-04-18" /></label>
            <label><span class="label">Nombres</span><input class="control" value="Sofía Valentina" /></label>
            <label><span class="label">Apellidos</span><input class="control" value="Rojas Méndez" /></label>
          </div>
          <div v-else-if="step === 2" class="grid gap-4 sm:grid-cols-2">
            <label><span class="label">Período académico</span><select class="control"><option>2026–2027 · Actual</option><option>2025–2026 · Histórico</option></select></label>
            <label><span class="label">Año y sección</span><select class="control"><option>5.º “A”</option><option>5.º “B”</option><option>6.º “A”</option></select></label>
            <label><span class="label">Turno</span><select class="control"><option>Mañana</option><option>Tarde</option></select></label>
            <label><span class="label">Condición</span><select class="control"><option>Regular</option><option>Repitiente</option></select></label>
          </div>
          <div v-else-if="step === 3" class="space-y-3">
            <label v-for="(doc, index) in ['Partida de nacimiento','Cédula del representante','Constancia de promoción']" :key="doc" class="flex cursor-pointer items-center gap-3 rounded-xl border p-3.5 transition hover:border-brand-300 hover:bg-brand-50/40"><input class="size-4 accent-brand-600" type="checkbox" :checked="index < 2" /><span class="min-w-0 flex-1 text-sm font-semibold">{{ doc }}</span><span class="text-xs font-bold" :class="index < 2 ? 'text-brand-600' : 'muted'">{{ index < 2 ? 'Verificado' : 'Pendiente' }}</span></label>
          </div>
          <div v-else class="rounded-2xl border bg-brand-50/60 p-5 dark:bg-brand-950/30"><div class="flex gap-4"><span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-brand-600 text-white"><Check class="size-6" /></span><div><p class="font-display text-lg font-extrabold">Todo listo para inscribir</p><p class="muted mt-1 text-sm leading-relaxed">Sofía Valentina quedará inscrita en 5.º “A”, período 2026–2027. Los documentos pendientes podrán completarse después.</p></div></div></div>
        </div>
      </Transition>
    </div>
    <div class="flex items-center justify-between border-t p-4 sm:px-6">
      <UiButton variant="ghost" :disabled="step === 1" @click="emit('update:step', step - 1)"><template #icon><ChevronLeft class="size-4" /></template>Anterior</UiButton>
      <UiButton @click="next">{{ step === steps.length ? 'Confirmar inscripción' : 'Continuar' }}<template #icon><Check v-if="step === steps.length" class="size-4" /><ChevronRight v-else class="size-4" /></template></UiButton>
    </div>
  </div>
</template>
