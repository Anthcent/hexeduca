<script setup>
import { useForm } from '@inertiajs/vue3';

defineProps({
    periods: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    name: '',
    starts_on: '',
    ends_on: '',
});

function submit() {
    form.post(route('academic-periods.store'), {
        onSuccess: () => form.reset(),
    });
}

function activate(period) {
    useForm({}).post(route('academic-periods.activate', period.id));
}
</script>

<template>
    <div class="academic-periods-page">
        <h1>Períodos académicos</h1>

        <form @submit.prevent="submit">
            <input v-model="form.name" type="text" placeholder="Nombre" required />
            <input v-model="form.starts_on" type="date" required />
            <input v-model="form.ends_on" type="date" required />
            <button type="submit" :disabled="form.processing">Crear</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Desde</th>
                    <th>Hasta</th>
                    <th>Activo</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="period in periods" :key="period.id">
                    <td>{{ period.name }}</td>
                    <td>{{ period.starts_on }}</td>
                    <td>{{ period.ends_on }}</td>
                    <td>{{ period.is_active ? 'Sí' : 'No' }}</td>
                    <td>
                        <button v-if="!period.is_active" @click="activate(period)">Activar</button>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
