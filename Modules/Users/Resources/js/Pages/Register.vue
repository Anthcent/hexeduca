<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';

const props = defineProps({
    schools: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    school_id: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('register'));
}
</script>

<template>
    <AuthLayout title="Registrar institución">
        <p class="muted mt-1 text-sm">Creá la cuenta del administrador de tu escuela.</p>

        <form class="mt-6 space-y-5" @submit.prevent="submit">
            <div>
                <label for="school_id" class="label">Escuela</label>
                <select id="school_id" v-model="form.school_id" class="control" :class="form.errors.school_id && 'border-red-400!'">
                    <option value="" disabled>Seleccionar escuela</option>
                    <option v-for="school in props.schools" :key="school.id" :value="school.id">{{ school.name }}</option>
                </select>
                <p v-if="form.errors.school_id" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.school_id }}</p>
            </div>

            <div>
                <label for="name" class="label">Nombre completo</label>
                <input id="name" v-model="form.name" type="text" autocomplete="name" class="control" :class="form.errors.name && 'border-red-400!'" />
                <p v-if="form.errors.name" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="email" class="label">Email</label>
                <input id="email" v-model="form.email" type="email" autocomplete="username" class="control" :class="form.errors.email && 'border-red-400!'" />
                <p v-if="form.errors.email" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="label">Contraseña</label>
                <input id="password" v-model="form.password" type="password" autocomplete="new-password" class="control" :class="form.errors.password && 'border-red-400!'" />
                <p v-if="form.errors.password" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="label">Confirmar contraseña</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" class="control" />
            </div>

            <UiButton type="submit" :disabled="form.processing" :loading="form.processing" size="lg" class="w-full">
                Crear cuenta
            </UiButton>
        </form>
    </AuthLayout>
</template>
