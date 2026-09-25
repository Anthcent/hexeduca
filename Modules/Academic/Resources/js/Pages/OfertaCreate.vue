<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { nullableField } from '@/lib/forms';

const props = defineProps({
    grados: {
        type: Array,
        default: () => [],
    },
    secciones: {
        type: Array,
        default: () => [],
    },
    teachers: {
        type: Array,
        default: () => [],
    },
    hasActivePeriodo: {
        type: Boolean,
        default: false,
    },
    periodoName: {
        type: String,
        default: null,
    },
});

const form = useForm({
    grado_id: '',
    seccion_id: '',
    teacher_id: '',
    capacity: '',
});

const gradoId = nullableField(form, 'grado_id');
const seccionId = nullableField(form, 'seccion_id');
const teacherId = nullableField(form, 'teacher_id');
const capacity = nullableField(form, 'capacity');

const gradoName = computed(() => props.grados.find((grado) => grado.id === gradoId.value)?.name ?? null);
const seccionName = computed(() => props.secciones.find((seccion) => seccion.id === seccionId.value)?.name ?? null);
const teacherName = computed(() => props.teachers.find((teacher) => teacher.id === teacherId.value)?.name ?? null);

const summary = computed(() => [
    { label: 'Período', value: props.periodoName ?? 'Sin período activo', icon: 'i-lucide-calendar-range' },
    { label: 'Grado y sección', value: [gradoName.value, seccionName.value].filter(Boolean).join(' · ') || 'Sin seleccionar', icon: 'i-lucide-graduation-cap' },
    { label: 'Docente', value: teacherName.value ?? 'Sin docente asignado', icon: 'i-lucide-user-round' },
    { label: 'Capacidad', value: capacity.value ? `${capacity.value} estudiantes` : 'Sin definir', icon: 'i-lucide-users' },
]);

const completed = computed(() => [gradoId.value, seccionId.value, capacity.value].filter(Boolean).length);

function submit() {
    form.post(route('academic.ofertas.store'));
}
</script>

<template>
    <Head title="Crear oferta académica" />

    <DashboardLayout active="academico">
        <PageHeader
            eyebrow="Académico"
            title="Crear oferta académica"
            description="Combina un grado y una sección para abrir un cupo dentro del período activo."
        >
            <template #actions>
                <UButton :to="route('academic.catalogos', undefined, false)" color="neutral" variant="outline" icon="i-lucide-library" size="lg">
                    Base académica
                </UButton>
            </template>
        </PageHeader>

        <UAlert
            v-if="!hasActivePeriodo"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="No hay un período académico activo"
            description="No se pueden crear ofertas hasta activar un período en la base académica."
            class="mb-6"
        />

        <form class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" novalidate @submit.prevent="submit">
            <UCard class="shadow-card">
                <template #header>
                    <PanelHeader kicker="Oferta" title="Datos de la oferta">
                        <UBadge v-if="hasActivePeriodo" color="primary" variant="subtle" icon="i-lucide-calendar-range" class="rounded-full">
                            {{ periodoName }}
                        </UBadge>
                    </PanelHeader>
                </template>

                <fieldset :disabled="!hasActivePeriodo" class="space-y-6 disabled:opacity-60">
                    <div class="grid gap-6 sm:grid-cols-2">
                        <UFormField label="Grado" name="grado_id" required :error="form.errors.grado_id">
                            <USelectMenu
                                v-model="gradoId"
                                :items="grados"
                                value-key="id"
                                label-key="name"
                                placeholder="Selecciona un grado"
                                icon="i-lucide-graduation-cap"
                                size="lg"
                                class="w-full"
                                :disabled="!hasActivePeriodo"
                            />
                        </UFormField>

                        <UFormField label="Sección" name="seccion_id" required :error="form.errors.seccion_id">
                            <USelectMenu
                                v-model="seccionId"
                                :items="secciones"
                                value-key="id"
                                label-key="name"
                                placeholder="Selecciona una sección"
                                icon="i-lucide-users"
                                size="lg"
                                class="w-full"
                                :disabled="!hasActivePeriodo"
                            />
                        </UFormField>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-[minmax(0,1fr)_12rem]">
                        <UFormField label="Docente" name="teacher_id" hint="Opcional" :error="form.errors.teacher_id">
                            <USelectMenu
                                v-model="teacherId"
                                :items="teachers"
                                value-key="id"
                                label-key="name"
                                placeholder="Sin docente asignado"
                                icon="i-lucide-user-round"
                                size="lg"
                                class="w-full"
                                clear
                                @clear="teacherId = null"
                                :disabled="!hasActivePeriodo"
                            />
                        </UFormField>

                        <UFormField label="Capacidad" name="capacity" required :error="form.errors.capacity">
                            <UInputNumber v-model="capacity" :min="1" placeholder="Ej.: 30" size="lg" class="w-full" :disabled="!hasActivePeriodo" />
                        </UFormField>
                    </div>
                </fieldset>

                <template #footer>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <UButton color="neutral" variant="ghost" size="lg" class="justify-center" :disabled="!hasActivePeriodo || form.processing" @click="form.reset()">
                            Limpiar
                        </UButton>
                        <UButton type="submit" icon="i-lucide-check" size="lg" class="justify-center" :loading="form.processing" :disabled="!hasActivePeriodo">
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
