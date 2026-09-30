<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';

const props = defineProps({
    gradeLevels: { type: Array, default: () => [] },
    sections: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
    hasActivePeriodo: { type: Boolean, default: false },
    periodoName: { type: String, default: null },
});

const form = useForm({ grade_level_id: null, section_id: null, teacher_id: null, capacity: 30 });

const gradeLevelName = computed(() => props.gradeLevels.find((item) => item.id === form.grade_level_id)?.name ?? null);
const sectionName = computed(() => props.sections.find((item) => item.id === form.section_id)?.name ?? null);
const teacherName = computed(() => props.teachers.find((item) => item.id === form.teacher_id)?.name ?? null);

const summary = computed(() => [
    { label: 'Período', value: props.periodoName ?? 'Sin período activo', icon: 'i-lucide-calendar-range' },
    { label: 'Grado y sección', value: [gradeLevelName.value, sectionName.value].filter(Boolean).join(' · ') || 'Sin seleccionar', icon: 'i-lucide-graduation-cap' },
    { label: 'Docente orientador', value: teacherName.value ?? 'Sin asignar', icon: 'i-lucide-user-round' },
    { label: 'Capacidad', value: form.capacity ? `${form.capacity} estudiantes` : 'Sin definir', icon: 'i-lucide-users' },
]);

const completed = computed(() => [form.grade_level_id, form.section_id, form.capacity].filter(Boolean).length);

function create() {
    form.post(route('academic-offers.store'));
}
</script>

<template>
    <Head title="Nueva oferta académica" />

    <DashboardLayout active="ofertas">
        <PageHeader
            eyebrow="Académico"
            title="Nueva oferta académica"
            description="Combina un grado y una sección para abrir un cupo dentro del período activo."
            icon="i-lucide-book-open"
        />

        <UAlert
            v-if="!hasActivePeriodo"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="No hay un período académico activo"
            description="No se pueden crear ofertas hasta activar un período."
            class="mb-6"
        />

        <form v-if="hasActivePeriodo" class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" novalidate @submit.prevent="create">
            <UCard class="shadow-card">
                <template #header>
                    <PanelHeader kicker="Oferta" title="Datos de la oferta">
                        <UBadge color="primary" variant="subtle" icon="i-lucide-calendar-range" class="rounded-full">{{ periodoName }}</UBadge>
                    </PanelHeader>
                </template>

                <div class="space-y-6">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <UFormField label="Grado" name="grade_level_id" required :error="form.errors.grade_level_id">
                            <USelectMenu
                                v-model="form.grade_level_id"
                                :items="gradeLevels"
                                value-key="id"
                                label-key="name"
                                placeholder="Selecciona un grado"
                                icon="i-lucide-graduation-cap"
                                size="lg"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField label="Sección" name="section_id" required :error="form.errors.section_id">
                            <USelectMenu
                                v-model="form.section_id"
                                :items="sections"
                                value-key="id"
                                label-key="name"
                                placeholder="Selecciona una sección"
                                icon="i-lucide-users"
                                size="lg"
                                class="w-full"
                            />
                        </UFormField>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-[minmax(0,1fr)_12rem]">
                        <UFormField label="Docente orientador (guía)" name="teacher_id" hint="Opcional" :error="form.errors.teacher_id">
                            <USelectMenu
                                v-model="form.teacher_id"
                                :items="teachers"
                                value-key="id"
                                label-key="name"
                                description-key="email"
                                placeholder="Sin asignar"
                                icon="i-lucide-user-round"
                                size="lg"
                                class="w-full"
                                clear
                                @clear="form.teacher_id = null"
                            />
                        </UFormField>
                        <UFormField label="Capacidad" name="capacity" required :error="form.errors.capacity">
                            <UInputNumber v-model="form.capacity" :min="1" placeholder="Capacidad" size="lg" class="w-full" />
                        </UFormField>
                    </div>
                </div>

                <template #footer>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <UButton color="neutral" variant="ghost" size="lg" class="justify-center" :disabled="form.processing" @click="form.reset()">
                            Limpiar
                        </UButton>
                        <UButton type="submit" icon="i-lucide-check" size="lg" class="justify-center" :loading="form.processing">
                            Crear oferta
                        </UButton>
                    </div>
                </template>
            </UCard>

            <UCard class="shadow-card lg:sticky lg:top-4">
                <template #header>
                    <PanelHeader kicker="Resumen" title="Nueva oferta">
                        <span class="text-sm tabular-nums text-muted">{{ completed }}/3</span>
                    </PanelHeader>
                </template>
                <UProgress :model-value="completed" :max="3" size="sm" class="mb-5" aria-label="Datos obligatorios completados" />
                <dl class="space-y-4 text-sm">
                    <div v-for="item in summary" :key="item.label" class="flex items-start gap-3">
                        <UIcon :name="item.icon" class="mt-0.5 size-4 shrink-0 text-muted" />
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-muted">{{ item.label }}</dt>
                            <dd class="mt-1 truncate font-medium text-highlighted">{{ item.value }}</dd>
                        </div>
                    </div>
                </dl>
            </UCard>
        </form>
    </DashboardLayout>
</template>
