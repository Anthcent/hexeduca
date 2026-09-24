<script setup>
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  moments: { type: Array, default: () => [] },
  activePeriod: { type: Object, default: null },
})

const form = ref({ name: '', order: 1, starts_on: '', ends_on: '' })

function create() {
  router.post(route('academic-moments.store'), {
    ...form.value,
    academic_period_id: props.activePeriod?.id,
  }, {
    onSuccess: () => { form.value = { name: '', order: 1, starts_on: '', ends_on: '' } },
  })
}

function destroy(id) {
  router.delete(route('academic-moments.destroy', id))
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-xl font-semibold mb-4">Momentos académicos</h1>
    <p v-if="!activePeriod" class="text-amber-600 mb-4">No hay período académico activo.</p>

    <form v-else class="flex gap-2 mb-6" @submit.prevent="create">
      <input v-model="form.name" type="text" placeholder="Nombre" class="border rounded px-3 py-2" />
      <input v-model.number="form.order" type="number" min="1" class="border rounded px-3 py-2 w-20" />
      <input v-model="form.starts_on" type="date" class="border rounded px-3 py-2" />
      <input v-model="form.ends_on" type="date" class="border rounded px-3 py-2" />
      <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded">Crear</button>
    </form>

    <ul class="divide-y">
      <li v-for="moment in moments" :key="moment.id" class="flex items-center justify-between py-2">
        <span>{{ moment.name }} ({{ moment.starts_on }} – {{ moment.ends_on }})</span>
        <button class="text-red-600 text-sm" @click="destroy(moment.id)">Eliminar</button>
      </li>
    </ul>
  </div>
</template>
