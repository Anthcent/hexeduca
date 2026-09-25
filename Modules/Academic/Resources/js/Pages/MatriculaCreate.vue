<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { nullableField } from '@/lib/forms';

const props = defineProps({
    ofertas: {
        type: Array,
        default: () => [],
    },
    students: {
        type: Array,
        default: () => [],
    },
});

function ofertaLabel(oferta) {
    return `${oferta.grado?.name ?? ''} ${oferta.seccion?.name ?? ''}`.trim();
}

const form = useForm({
    oferta_academica_id: '',
    student_id: '',
});

const ofertaId = nullableField(form, 'oferta_academica_id');
const studentId = nullableField(form, 'student_id');

const ofertaItems = computed(() => props.ofertas.map((oferta) => ({ id: oferta.id, label: ofertaLabel(oferta) || `Oferta #${oferta.id}` })));
const selectedOferta = computed(() => ofertaItems.value.find((item) => item.id === ofertaId.value) ?? null);
const selectedStudent = computed(() => props.students.find((student) => student.id === studentId.value) ?? null);
const completed = computed(() => [selectedOferta.value, selectedStudent.value].filter(Boolean).length);

const canEnroll = computed(() => props.ofertas.length > 0 && props.students.length > 0);

function submit() {
    form.post(route('academic.matriculas.store'));
}
</script>

<template>
    <Head title="Inscribir estudiante" />

    <DashboardLayout active="matriculas">
        <PageHeader
            eyebrow="Matrículas"
            title="Inscribir estudiante"
            description="Asigna un estudiante a una oferta académica del período activo."
            icon="i-lucide-clipboard-list"
        >
            <template #notch>
                <UButton :to="route('academic.ofertas.create', undefined, false)" icon="i-lucide-book-plus" size="lg">
                    Nueva oferta
                </UButton>
            </template>
        </PageHeader>

        <form class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" novalidate @submit.prevent="submit">
            <UCard class="shadow-card">
                <template #header>
                    <PanelHeader kicker="Inscripción" title="Datos de la matrícula" />
                </template>

                <EmptyState
                    v-if="!canEnroll"
                    icon="i-lucide-book-open"
                    :title="ofertas.length === 0 ? 'No hay ofertas académicas' : 'No hay estudiantes registrados'"
                    :description="ofertas.length === 0
                        ? 'Crea una oferta académica en el período activo para poder inscribir estudiantes.'
                        : 'Crea cuentas con el rol Estudiante para poder inscribirlas.'"
                    class="py-6"
                />

                <div v-else class="space-y-6">
                    <UFormField
                        label="Oferta académica"
                        name="oferta_academica_id"
                        required
                        :error="form.errors.oferta_academica_id"
                        help="Grado y sección del período activo."
                    >
                        <USelectMenu
                            v-model="ofertaId"
                            :items="ofertaItems"
                            value-key="id"
                            placeholder="Selecciona una oferta"
                            icon="i-lucide-book-open"
                            size="lg"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField label="Estudiante" name="student_id" required :error="form.errors.student_id">
                        <USelectMenu
                            v-model="studentId"
                            :items="students"
                            value-key="id"
                            label-key="name"
                            placeholder="Selecciona un estudiante"
                            icon="i-lucide-user-round"
                            size="lg"
                            class="w-full"
                        />
                    </UFormField>
                </div>

                <template v-if="canEnroll" #footer>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <UButton color="neutral" variant="ghost" size="lg" class="justify-center" :disabled="form.processing || completed === 0" @click="form.reset()">
                            Limpiar
                        </UButton>
                        <UButton type="submit" icon="i-lucide-check" size="lg" class="justify-center" :loading="form.processing">
                            Inscribir estudiante
                        </UButton>
                    </div>
                </template>
            </UCard>

            <UCard class="shadow-card lg:sticky lg:top-4">
                <template #header>
                    <PanelHeader kicker="Resumen" title="Nueva matrícula">
                        <span class="text-sm tabular-nums text-muted">{{ completed }}/2</span>
                    </PanelHeader>
                </template>
                <UProgress :model-value="completed" :max="2" size="sm" class="mb-5" aria-label="Datos completados" />
                <dl class="space-y-4 text-sm">
                    <div class="flex items-start gap-3">
                        <UIcon name="i-lucide-book-open" class="mt-0.5 size-4 shrink-0 text-muted" />
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-muted">Oferta</dt>
                            <dd class="mt-1 font-medium text-highlighted">{{ selectedOferta?.label ?? 'Sin seleccionar' }}</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <UIcon name="i-lucide-user-round" class="mt-0.5 size-4 shrink-0 text-muted" />
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-muted">Estudiante</dt>
                            <dd class="mt-1 truncate font-medium text-highlighted">{{ selectedStudent?.name ?? 'Sin seleccionar' }}</dd>
                        </div>
                    </div>
                </dl>
            </UCard>
        </form>
    </DashboardLayout>
</template>
