<script setup>
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  levels: { type: Array, default: () => [] },
})

const name = ref('')

function create() {
  router.post(route('academic-levels.store'), { name: name.value }, {
    onSuccess: () => { name.value = '' },
  })
}

function destroy(id) {
  router.delete(route('academic-levels.destroy', id))
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-xl font-semibold mb-4">Niveles académicos</h1>

    <form class="flex gap-2 mb-6" @submit.prevent="create">
      <input v-model="name" type="text" placeholder="Nombre del nivel" class="border rounded px-3 py-2" />
      <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded">Crear</button>
    </form>

    <ul class="divide-y">
      <li v-for="level in levels" :key="level.id" class="flex items-center justify-between py-2">
        <span>{{ level.name }}</span>
        <button class="text-red-600 text-sm" @click="destroy(level.id)">Eliminar</button>
      </li>
    </ul>
  </div>
</template>
