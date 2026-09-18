<script setup>
import { usePage, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

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
    <AppLayout title="Create Academic Offering">
        <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <div
                v-if="flashSuccess"
                class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900 dark:text-green-200"
            >
                {{ flashSuccess }}
            </div>
            <div
                v-if="flashError"
                class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900 dark:text-red-200"
            >
                {{ flashError }}
            </div>

            <div
                v-if="!hasActivePeriodo"
                class="mb-4 rounded-md bg-yellow-50 p-4 text-sm text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200"
            >
                There is no active academic period for this school. Offerings
                cannot be created until an academic period is activated.
            </div>
            <div v-else class="mb-4 text-sm text-gray-500 dark:text-gray-400">
                Active academic period: <span class="font-medium">{{ periodoName }}</span>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <fieldset :disabled="!hasActivePeriodo" class="space-y-4">
                    <div>
                        <label for="grado_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Grado
                        </label>
                        <select
                            id="grado_id"
                            v-model="form.grado_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="" disabled>Select a grado</option>
                            <option v-for="grado in grados" :key="grado.id" :value="grado.id">
                                {{ grado.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.grado_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.grado_id }}
                        </p>
                    </div>

                    <div>
                        <label for="seccion_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Sección
                        </label>
                        <select
                            id="seccion_id"
                            v-model="form.seccion_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="" disabled>Select a sección</option>
                            <option v-for="seccion in secciones" :key="seccion.id" :value="seccion.id">
                                {{ seccion.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.seccion_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.seccion_id }}
                        </p>
                    </div>

                    <div>
                        <label for="teacher_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Teacher (optional)
                        </label>
                        <select
                            id="teacher_id"
                            v-model="form.teacher_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        >
                            <option value="">No teacher assigned</option>
                            <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">
                                {{ teacher.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.teacher_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.teacher_id }}
                        </p>
                    </div>

                    <div>
                        <label for="capacity" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Capacity
                        </label>
                        <input
                            id="capacity"
                            v-model="form.capacity"
                            type="number"
                            min="1"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                        <p v-if="form.errors.capacity" class="mt-1 text-sm text-red-600 dark:text-red-400">
                            {{ form.errors.capacity }}
                        </p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                    >
                        Create Offering
                    </button>
                </fieldset>
            </form>
        </div>
    </AppLayout>
</template>
