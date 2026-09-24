<script setup>
import { useForm } from '@inertiajs/vue3';

const form = useForm({
    academic_offer_id: '',
    student_id: '',
    value: '',
});

function submit() {
    form.post(route('grades.store'), {
        onSuccess: () => form.reset(),
    });
}
</script>

<template>
    <div class="grades-page">
        <h1>Cargar nota</h1>

        <form @submit.prevent="submit">
            <input v-model="form.academic_offer_id" type="number" placeholder="ID de la oferta académica" required />
            <input v-model="form.student_id" type="number" placeholder="ID del estudiante" required />
            <input v-model="form.value" type="number" min="0" max="100" step="0.01" placeholder="Nota" required />
            <button type="submit" :disabled="form.processing">Guardar</button>
        </form>
    </div>
</template>
