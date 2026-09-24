<script setup>
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  gradeLevels: { type: Array, default: () => [] },
  sections: { type: Array, default: () => [] },
  teachers: { type: Array, default: () => [] },
  hasActivePeriodo: { type: Boolean, default: false },
  periodoName: { type: String, default: null },
})

const form = ref({ grade_level_id: null, section_id: null, teacher_id: null, capacity: 30 })

function create() {
  router.post(route('academic-offers.store'), form.value)
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-xl font-semibold mb-4">Nueva oferta académica</h1>
    <p v-if="!hasActivePeriodo" class="text-amber-600 mb-4">No hay período académico activo.</p>
    <p v-else class="text-sm text-slate-500 mb-4">Período activo: {{ periodoName }}</p>

    <form v-if="hasActivePeriodo" class="flex flex-col gap-3 max-w-md" @submit.prevent="create">
      <select v-model.number="form.grade_level_id" class="border rounded px-3 py-2">
        <option :value="null" disabled>Grado</option>
        <option v-for="gl in gradeLevels" :key="gl.id" :value="gl.id">{{ gl.name }}</option>
      </select>
      <select v-model.number="form.section_id" class="border rounded px-3 py-2">
        <option :value="null" disabled>Sección</option>
        <option v-for="s in sections" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <select v-model.number="form.teacher_id" class="border rounded px-3 py-2">
        <option :value="null">Sin asignar</option>
        <option v-for="t in teachers" :key="t.id" :value="t.id">{{ t.name }}</option>
      </select>
      <input v-model.number="form.capacity" type="number" min="1" placeholder="Capacidad" class="border rounded px-3 py-2" />
      <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded">Crear oferta</button>
    </form>
  </div>
</template>
