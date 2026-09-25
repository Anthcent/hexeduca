<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import EmptyState from '@/Components/EmptyState.vue';

const props = defineProps({
    academicOffers: { type: Array, default: () => [] },
    students: { type: Array, default: () => [] },
});

const form = useForm({ academic_offer_id: null, student_id: null });

const offerItems = computed(() => props.academicOffers.map((offer) => ({
    id: offer.id,
    label: `${offer.gradeLevelName} ${offer.sectionName}`.trim(),
    description: `Capacidad: ${offer.capacity}`,
})));

const selectedOffer = computed(() => offerItems.value.find((offer) => offer.id === form.academic_offer_id) ?? null);
const selectedStudent = computed(() => props.students.find((student) => student.id === form.student_id) ?? null);
const completed = computed(() => [selectedOffer.value, selectedStudent.value].filter(Boolean).length);
const canEnroll = computed(() => props.academicOffers.length > 0 && props.students.length > 0);

function create() {
    form.post(route('enrollments.store'));
}
</script>

<template>
    <Head title="Nueva matrícula" />

    <DashboardLayout active="matriculas">
        <PageHeader
            eyebrow="Matrículas"
            title="Nueva matrícula"
            description="Inscribe a un estudiante en una oferta académica activa."
            icon="i-lucide-clipboard-list"
        />

        <form class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" novalidate @submit.prevent="create">
            <UCard class="shadow-card">
                <template #header>
                    <PanelHeader kicker="Inscripción" title="Datos de la matrícula" />
                </template>

                <EmptyState
                    v-if="!canEnroll"
                    icon="i-lucide-book-open"
                    :title="academicOffers.length === 0 ? 'No hay ofertas académicas activas' : 'No hay estudiantes registrados'"
                    :description="academicOffers.length === 0
                        ? 'Crea una oferta académica en el período activo para poder matricular.'
                        : 'Crea cuentas con el rol Estudiante para poder matricularlas.'"
                    class="py-6"
                />

                <div v-else class="space-y-6">
                    <UFormField label="Oferta académica" name="academic_offer_id" required :error="form.errors.academic_offer_id">
                        <USelectMenu
                            v-model="form.academic_offer_id"
                            :items="offerItems"
                            value-key="id"
                            placeholder="Selecciona una oferta"
                            icon="i-lucide-book-open"
                            size="lg"
                            class="w-full"
                        />
                    </UFormField>
                    <UFormField label="Estudiante" name="student_id" required :error="form.errors.student_id">
                        <USelectMenu
                            v-model="form.student_id"
                            :items="students"
                            value-key="id"
                            label-key="name"
                            description-key="email"
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
                            Matricular
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
                            <dd class="mt-1 font-medium text-highlighted">{{ selectedOffer?.label ?? 'Sin seleccionar' }}</dd>
                            <dd v-if="selectedOffer" class="text-muted">{{ selectedOffer.description }}</dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <UIcon name="i-lucide-user-round" class="mt-0.5 size-4 shrink-0 text-muted" />
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-muted">Estudiante</dt>
                            <dd class="mt-1 truncate font-medium text-highlighted">{{ selectedStudent?.name ?? 'Sin seleccionar' }}</dd>
                            <dd v-if="selectedStudent" class="truncate text-muted">{{ selectedStudent.email }}</dd>
                        </div>
                    </div>
                </dl>
            </UCard>
        </form>
    </DashboardLayout>
</template>
