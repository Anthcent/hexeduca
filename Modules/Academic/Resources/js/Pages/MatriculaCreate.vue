<script setup>
import { usePage, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { CheckCircle2, XCircle } from 'lucide-vue-next';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import UiButton from '@/Components/UiButton.vue';

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

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

function ofertaLabel(oferta) {
    return `${oferta.grado?.name ?? ''} ${oferta.seccion?.name ?? ''}`.trim();
}

const form = useForm({
    oferta_academica_id: '',
    student_id: '',
});

function submit() {
    form.post(route('academic.matriculas.store'));
}
</script>

<template>
    <DashboardLayout active="matriculas">
        <div class="mb-5">
            <p class="muted mb-1 text-xs font-bold uppercase tracking-[.14em]">Matrículas</p>
            <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Inscribir estudiante</h1>
            <p class="muted mt-1 text-sm">Asigná un estudiante a una oferta académica del período activo.</p>
        </div>

        <div class="section-card max-w-xl p-5 sm:p-6">
            <Transition name="fade">
                <div
                    v-if="flashSuccess"
                    class="mb-4 flex items-center gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-800 dark:border-brand-800 dark:bg-brand-950 dark:text-brand-200"
                >
                    <CheckCircle2 class="size-4 shrink-0" />
                    {{ flashSuccess }}
                </div>
            </Transition>
            <Transition name="fade">
                <div
                    v-if="flashError"
                    class="mb-4 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                >
                    <XCircle class="size-4 shrink-0" />
                    {{ flashError }}
                </div>
            </Transition>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label for="oferta_academica_id" class="label">Oferta académica</label>
                    <select
                        id="oferta_academica_id"
                        v-model="form.oferta_academica_id"
                        class="control"
                        :class="form.errors.oferta_academica_id && '!border-red-400'"
                    >
                        <option value="" disabled>Seleccioná una oferta</option>
                        <option v-for="oferta in ofertas" :key="oferta.id" :value="oferta.id">
                            {{ ofertaLabel(oferta) }}
                        </option>
                    </select>
                    <p v-if="form.errors.oferta_academica_id" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.oferta_academica_id }}</p>
                </div>

                <div>
                    <label for="student_id" class="label">Estudiante</label>
                    <select id="student_id" v-model="form.student_id" class="control" :class="form.errors.student_id && '!border-red-400'">
                        <option value="" disabled>Seleccioná un estudiante</option>
                        <option v-for="student in students" :key="student.id" :value="student.id">
                            {{ student.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.student_id" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.student_id }}</p>
                </div>

                <UiButton type="submit" :loading="form.processing">Inscribir estudiante</UiButton>
            </form>
        </div>
    </DashboardLayout>
</template>
