<script setup>
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'

const props = defineProps({
  academicOffers: { type: Array, default: () => [] },
  students: { type: Array, default: () => [] },
})

const form = ref({ academic_offer_id: null, student_id: null })

function create() {
  router.post(route('enrollments.store'), form.value)
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-xl font-semibold mb-4">Nueva matrícula</h1>

    <form class="flex flex-col gap-3 max-w-md" @submit.prevent="create">
      <select v-model.number="form.academic_offer_id" class="border rounded-sm px-3 py-2">
        <option :value="null" disabled>Oferta académica</option>
        <option v-for="offer in academicOffers" :key="offer.id" :value="offer.id">
          {{ offer.gradeLevelName }} {{ offer.sectionName }} (cap. {{ offer.capacity }})
        </option>
      </select>
      <select v-model.number="form.student_id" class="border rounded-sm px-3 py-2">
        <option :value="null" disabled>Estudiante</option>
        <option v-for="s in students" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <button type="submit" class="px-4 py-2 bg-slate-900 text-white rounded-sm">Matricular</button>
    </form>
  </div>
</template>
