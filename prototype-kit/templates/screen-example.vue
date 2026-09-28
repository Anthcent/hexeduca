<!--
  Reference shape for screens/. The names are an example; replace them with your module's.
  Data arrives as props with the exact shape of the query in CONTRACT.md.
  Actions are stubs named after the use cases. The system provides the layout.

  Sample props:
  {
    sheets: [
      { id: 1, offerName: "1er grado A", date: "2026-10-01", present: 24, absent: 2, status: "open" }
    ],
    offers: [{ id: 12, name: "1er grado A" }],
    canCreate: true
  }
-->
<script setup>
import { ref } from 'vue'

const props = defineProps({
    sheets: { type: Array, required: true },
    offers: { type: Array, required: true },
    canCreate: { type: Boolean, default: false },
})

const showCreate = ref(false)
const form = ref({ academicOfferId: null, date: '' })

const columns = [
    { accessorKey: 'offerName', header: 'Curso' },
    { accessorKey: 'date', header: 'Fecha' },
    { accessorKey: 'present', header: 'Presentes' },
    { accessorKey: 'absent', header: 'Ausentes' },
    { accessorKey: 'status', header: 'Estado' },
]

function createAttendanceSheet(data) {
    console.log('CreateAttendanceSheet', data)
    showCreate.value = false
}
</script>

<template>
    <div class="space-y-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-muted">Asistencia</p>
                <h1 class="text-2xl font-semibold">Planillas de asistencia</h1>
                <p class="text-sm text-muted">Registrá la asistencia diaria de cada curso.</p>
            </div>
            <UButton v-if="props.canCreate" icon="i-lucide-plus" label="Nueva planilla" @click="showCreate = true" />
        </header>

        <UCard>
            <UTable v-if="props.sheets.length" :data="props.sheets" :columns="columns" />
            <p v-else class="py-10 text-center text-sm text-muted">Todavía no hay planillas en este período.</p>
        </UCard>

        <UModal v-model:open="showCreate" title="Nueva planilla">
            <template #body>
                <div class="space-y-4">
                    <UFormField label="Curso" required>
                        <USelect
                            v-model="form.academicOfferId"
                            :items="props.offers.map((offer) => ({ label: offer.name, value: offer.id }))"
                            class="w-full"
                        />
                    </UFormField>
                    <UFormField label="Fecha" required>
                        <UInput v-model="form.date" type="date" class="w-full" />
                    </UFormField>
                </div>
            </template>
            <template #footer>
                <UButton color="neutral" variant="ghost" label="Cancelar" @click="showCreate = false" />
                <UButton label="Crear" @click="createAttendanceSheet(form)" />
            </template>
        </UModal>
    </div>
</template>
