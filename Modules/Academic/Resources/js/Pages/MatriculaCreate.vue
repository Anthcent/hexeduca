<script setup>
import { usePage, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

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
    <AppLayout title="Enroll Student">
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

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label for="oferta_academica_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Academic Offering
                    </label>
                    <select
                        id="oferta_academica_id"
                        v-model="form.oferta_academica_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="" disabled>Select an offering</option>
                        <option v-for="oferta in ofertas" :key="oferta.id" :value="oferta.id">
                            {{ ofertaLabel(oferta) }}
                        </option>
                    </select>
                    <p v-if="form.errors.oferta_academica_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.oferta_academica_id }}
                    </p>
                </div>

                <div>
                    <label for="student_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Student
                    </label>
                    <select
                        id="student_id"
                        v-model="form.student_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                    >
                        <option value="" disabled>Select a student</option>
                        <option v-for="student in students" :key="student.id" :value="student.id">
                            {{ student.name }}
                        </option>
                    </select>
                    <p v-if="form.errors.student_id" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.student_id }}
                    </p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50"
                >
                    Enroll Student
                </button>
            </form>
        </div>
    </AppLayout>
</template>
