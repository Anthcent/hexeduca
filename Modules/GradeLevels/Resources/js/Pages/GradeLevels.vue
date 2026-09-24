<script setup>
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  gradeLevels: { type: Array, default: () => [] },
  academicLevels: { type: Array, default: () => [] },
})

const form = ref({ name: '', order: 1, academic_level_id: null })

function create() {
  router.post(route('grade-levels.store'), form.value, {
    onSuccess: () => { form.value = { name: '', order: 1, academic_level_id: null } },
  })
}

function destroy(id) {
  router.delete(route('grade-levels.destroy', id))
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-xl font-semibold mb-4">Grados</h1>

    <form class="flex gap-2 mb-6" @submit.prevent="create">
      <input v-model="form.name" type="text" placeholder="Nombre del grado" class="border rounded px-3 py-2" />
      <input v-model.number="form.order" type="number" min="1" class="border rounded px-3 py-2 w-24" />
      <select v-model.number="form.academic_level_id" class="border rounded px-3 py-2">
        <option v-for="level in academicLevels" :key="level.id" :value="level.id">{{ level.name }}</option>
      </select>
      <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded">Crear</button>
    </form>

    <ul class="divide-y">
      <li v-for="gradeLevel in gradeLevels" :key="gradeLevel.id" class="flex items-center justify-between py-2">
        <span>{{ gradeLevel.name }} — {{ gradeLevel.academic_level_name }}</span>
        <button class="text-red-600 text-sm" @click="destroy(gradeLevel.id)">Eliminar</button>
      </li>
    </ul>
  </div>
</template>
