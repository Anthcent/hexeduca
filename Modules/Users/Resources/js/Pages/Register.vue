<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

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

const schoolItems = computed(() => props.schools.map((school) => ({ label: school.name, value: school.id })));

function submit() {
    form.post(route('register'));
}
</script>

<template>
    <AuthLayout eyebrow="Alta de institución" title="Registrar institución" description="Crea la cuenta del administrador de tu escuela.">
        <form class="mt-6 space-y-5" novalidate @submit.prevent="submit">
            <UFormField label="Escuela" name="school_id" :error="form.errors.school_id">
                <USelect
                    id="school_id"
                    v-model="form.school_id"
                    :items="schoolItems"
                    placeholder="Seleccionar escuela"
                    icon="i-lucide-school"
                    size="xl"
                    class="w-full"
                />
            </UFormField>

            <UFormField label="Nombre completo" name="name" :error="form.errors.name">
                <UInput id="name" v-model="form.name" type="text" autocomplete="name" icon="i-lucide-user-round" size="xl" class="w-full" />
            </UFormField>

            <UFormField label="Correo electrónico" name="email" :error="form.errors.email">
                <UInput id="email" v-model="form.email" type="email" autocomplete="username" icon="i-lucide-mail" size="xl" class="w-full" />
            </UFormField>

            <UFormField label="Contraseña" name="password" :error="form.errors.password">
                <UInput id="password" v-model="form.password" type="password" autocomplete="new-password" icon="i-lucide-lock" size="xl" class="w-full" />
            </UFormField>

            <UFormField label="Confirmar contraseña" name="password_confirmation">
                <UInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    icon="i-lucide-lock"
                    size="xl"
                    class="w-full"
                />
            </UFormField>

            <UButton type="submit" size="xl" block :loading="form.processing" :disabled="form.processing">
                Crear cuenta
            </UButton>
        </form>
    </AuthLayout>
</template>
