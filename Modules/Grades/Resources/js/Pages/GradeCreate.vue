<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { nullableField } from '@/lib/forms';

const form = useForm({
    academic_offer_id: '',
    student_id: '',
    value: '',
});

const offerId = nullableField(form, 'academic_offer_id');
const studentId = nullableField(form, 'student_id');
const value = nullableField(form, 'value');

function submit() {
    form.post(route('grades.store'), {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Cargar nota" />

    <DashboardLayout active="notas">
        <PageHeader
            eyebrow="Académico"
            title="Cargar nota"
            description="Registra la calificación de un estudiante en una oferta académica del período activo."
        />

        <form class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" novalidate @submit.prevent="submit">
            <UCard class="shadow-card">
                <template #header>
                    <PanelHeader kicker="Calificación" title="Datos de la nota" />
                </template>

                <div class="space-y-6">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <UFormField label="ID de la oferta académica" name="academic_offer_id" required :error="form.errors.academic_offer_id">
                            <UInputNumber v-model="offerId" :min="1" :format-options="{ useGrouping: false }" placeholder="Ej.: 12" size="lg" class="w-full" />
                        </UFormField>
                        <UFormField label="ID del estudiante" name="student_id" required :error="form.errors.student_id">
                            <UInputNumber v-model="studentId" :min="1" :format-options="{ useGrouping: false }" placeholder="Ej.: 48" size="lg" class="w-full" />
                        </UFormField>
                    </div>

                    <UFormField label="Nota" name="value" required :error="form.errors.value" help="De 0 a 100, con hasta dos decimales.">
                        <UInputNumber
                            v-model="value"
                            :min="0"
                            :max="100"
                            :step="0.01"
                            :format-options="{ maximumFractionDigits: 2 }"
                            placeholder="Ej.: 85,5"
                            size="lg"
                            class="w-full sm:max-w-48"
                        />
                    </UFormField>
                </div>

                <template #footer>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <UButton color="neutral" variant="ghost" size="lg" class="justify-center" :disabled="form.processing" @click="form.reset()">
                            Limpiar
                        </UButton>
                        <UButton type="submit" icon="i-lucide-check" size="lg" class="justify-center" :loading="form.processing" :disabled="form.processing">
                            Guardar nota
                        </UButton>
                    </div>
                </template>
            </UCard>

            <UCard class="shadow-card">
                <template #header>
                    <PanelHeader kicker="Ayuda" title="Antes de guardar" />
                </template>
                <ul class="space-y-3 text-sm text-muted">
                    <li class="flex gap-3">
                        <UIcon name="i-lucide-calendar-range" class="mt-0.5 size-4 shrink-0 text-primary" />
                        La nota se registra en el período académico activo.
                    </li>
                    <li class="flex gap-3">
                        <UIcon name="i-lucide-user-check" class="mt-0.5 size-4 shrink-0 text-primary" />
                        El estudiante debe estar matriculado en la oferta indicada.
                    </li>
                    <li class="flex gap-3">
                        <UIcon name="i-lucide-shield-check" class="mt-0.5 size-4 shrink-0 text-primary" />
                        Solo el docente asignado a la oferta, o el personal en su nombre, puede cargarla.
                    </li>
                </ul>
            </UCard>
        </form>
    </DashboardLayout>
</template>
