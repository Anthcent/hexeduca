<script setup>
import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import UiButton from '@/Components/UiButton.vue';

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

function submit() {
    form.post(route('login'));
}
</script>

<template>
    <AuthLayout title="Iniciar sesión">
        <p class="muted mt-1 text-sm">Accedé con tu cuenta de la escuela.</p>

        <div
            v-if="props.registered"
            class="mt-5 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-800 dark:border-brand-800 dark:bg-brand-950 dark:text-brand-200"
        >
            Cuenta creada — iniciá sesión abajo.
        </div>

        <form class="mt-6 space-y-5" @submit.prevent="submit">
            <div>
                <label for="email" class="label">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    class="control"
                    :class="form.errors.email && '!border-red-400 !ring-red-500/15'"
                />
                <p v-if="form.errors.email" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="label">Contraseña</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    class="control"
                    :class="form.errors.password && '!border-red-400 !ring-red-500/15'"
                />
                <p v-if="form.errors.password" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.password }}</p>
            </div>

            <UiButton type="submit" :disabled="form.processing" :loading="form.processing" size="lg" class="w-full">
                Iniciar sesión
            </UiButton>
        </form>
    </AuthLayout>
</template>
