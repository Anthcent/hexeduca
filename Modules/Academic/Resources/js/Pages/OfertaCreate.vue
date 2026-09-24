<script setup>
import { usePage, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { AlertTriangle, CheckCircle2, XCircle } from 'lucide-vue-next';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import UiButton from '@/Components/UiButton.vue';

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

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const form = useForm({
    grado_id: '',
    seccion_id: '',
    teacher_id: '',
    capacity: '',
});

function submit() {
    form.post(route('academic.ofertas.store'));
}
</script>

<template>
    <DashboardLayout active="academico">
        <div class="mb-5">
            <p class="muted mb-1 text-xs font-bold uppercase tracking-[.14em]">Base académica</p>
            <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Crear oferta académica</h1>
            <p class="muted mt-1 text-sm">Combiná un grado y una sección para abrir un cupo dentro del período activo.</p>
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

            <div
                v-if="!hasActivePeriodo"
                class="mb-4 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                No hay un período académico activo para esta institución. No se pueden crear ofertas hasta activar uno.
            </div>
            <p v-else class="muted mb-4 text-sm">
                Período activo: <span class="font-semibold text-[rgb(var(--text))]">{{ periodoName }}</span>
            </p>

            <form class="space-y-4" @submit.prevent="submit">
                <fieldset :disabled="!hasActivePeriodo" class="space-y-4">
                    <div>
                        <label for="grado_id" class="label">Grado</label>
                        <select id="grado_id" v-model="form.grado_id" class="control" :class="form.errors.grado_id && 'border-red-400!'">
                            <option value="" disabled>Seleccioná un grado</option>
                            <option v-for="grado in grados" :key="grado.id" :value="grado.id">
                                {{ grado.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.grado_id" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.grado_id }}</p>
                    </div>

                    <div>
                        <label for="seccion_id" class="label">Sección</label>
                        <select id="seccion_id" v-model="form.seccion_id" class="control" :class="form.errors.seccion_id && 'border-red-400!'">
                            <option value="" disabled>Seleccioná una sección</option>
                            <option v-for="seccion in secciones" :key="seccion.id" :value="seccion.id">
                                {{ seccion.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.seccion_id" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.seccion_id }}</p>
                    </div>

                    <div>
                        <label for="teacher_id" class="label">Docente (opcional)</label>
                        <select id="teacher_id" v-model="form.teacher_id" class="control" :class="form.errors.teacher_id && 'border-red-400!'">
                            <option value="">Sin docente asignado</option>
                            <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">
                                {{ teacher.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.teacher_id" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.teacher_id }}</p>
                    </div>

                    <div>
                        <label for="capacity" class="label">Capacidad</label>
                        <input
                            id="capacity"
                            v-model="form.capacity"
                            type="number"
                            min="1"
                            class="control"
                            :class="form.errors.capacity && 'border-red-400!'"
                        />
                        <p v-if="form.errors.capacity" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.capacity }}</p>
                    </div>

                    <UiButton type="submit" :loading="form.processing">Crear oferta</UiButton>
                </fieldset>
            </form>
        </div>
    </DashboardLayout>
</template>
