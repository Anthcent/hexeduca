<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps({
    registered: {
        type: Boolean,
        default: false,
    },
});

const form = useForm({
    email: '',
    password: '',
});

const showPassword = ref(false);

function submit() {
    form.post(route('login'));
}
</script>

<template>
    <AuthLayout title="Iniciar sesión" description="Ingresa con tu cuenta de la institución para continuar.">
        <UAlert
            v-if="props.registered"
            class="mt-5"
            color="success"
            variant="subtle"
            icon="i-lucide-circle-check"
            title="Cuenta creada. Inicia sesión a continuación."
        />

        <form class="mt-6 space-y-5" novalidate @submit.prevent="submit">
            <UFormField label="Correo electrónico" name="email" :error="form.errors.email">
                <UInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    icon="i-lucide-mail"
                    placeholder="usuario@institucion.edu"
                    size="xl"
                    class="w-full"
                />
            </UFormField>

            <UFormField label="Contraseña" name="password" :error="form.errors.password">
                <UInput
                    id="password"
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    autocomplete="current-password"
                    icon="i-lucide-lock"
                    size="xl"
                    class="w-full"
                    :ui="{ trailing: 'pe-1' }"
                >
                    <template #trailing>
                        <UButton
                            color="neutral"
                            variant="link"
                            size="sm"
                            :icon="showPassword ? 'i-lucide-eye-off' : 'i-lucide-eye'"
                            :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                            :aria-pressed="showPassword"
                            @click="showPassword = !showPassword"
                        />
                    </template>
                </UInput>
            </UFormField>

            <UButton type="submit" size="xl" block :loading="form.processing" :disabled="form.processing">
                Iniciar sesión
            </UButton>
        </form>
    </AuthLayout>
</template>
