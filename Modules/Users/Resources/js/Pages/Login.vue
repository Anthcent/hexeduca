<script setup>
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

function submit() {
    form.post(route('login'));
}
</script>

<template>
    <AuthLayout title="Iniciar sesión">
        <p class="mb-6 text-sm text-ink-muted">Accedé con tu cuenta de la escuela.</p>

        <div
            v-if="props.registered"
            class="mb-5 rounded border border-green-tint bg-green-tint px-4 py-3 text-sm text-green"
        >
            Cuenta creada — iniciá sesión abajo.
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div class="relative">
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    placeholder=" "
                    class="peer w-full rounded border bg-white px-3.5 pb-2 pt-4 text-sm text-ink outline-none transition-colors focus:border-2 focus:border-blue focus:px-[13px] focus:pb-[7px] focus:pt-[15px]"
                    :class="form.errors.email ? 'border-red' : 'border-outline-strong'"
                />
                <label
                    for="email"
                    class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 bg-white px-1 text-sm text-ink-muted transition-all peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:text-xs peer-focus:font-medium peer-focus:text-blue peer-[&:not(:placeholder-shown)]:top-0 peer-[&:not(:placeholder-shown)]:-translate-y-1/2 peer-[&:not(:placeholder-shown)]:text-xs peer-[&:not(:placeholder-shown)]:font-medium"
                >
                    Email
                </label>
                <p v-if="form.errors.email" class="mt-1.5 text-xs text-red">
                    {{ form.errors.email }}
                </p>
            </div>

            <div class="relative">
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    placeholder=" "
                    class="peer w-full rounded border bg-white px-3.5 pb-2 pt-4 text-sm text-ink outline-none transition-colors focus:border-2 focus:border-blue focus:px-[13px] focus:pb-[7px] focus:pt-[15px]"
                    :class="form.errors.password ? 'border-red' : 'border-outline-strong'"
                />
                <label
                    for="password"
                    class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 bg-white px-1 text-sm text-ink-muted transition-all peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:text-xs peer-focus:font-medium peer-focus:text-blue peer-[&:not(:placeholder-shown)]:top-0 peer-[&:not(:placeholder-shown)]:-translate-y-1/2 peer-[&:not(:placeholder-shown)]:text-xs peer-[&:not(:placeholder-shown)]:font-medium"
                >
                    Contraseña
                </label>
                <p v-if="form.errors.password" class="mt-1.5 text-xs text-red">
                    {{ form.errors.password }}
                </p>
            </div>

            <div class="flex justify-end pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center justify-center rounded-full bg-blue px-6 py-2.5 text-sm font-medium text-white transition-colors hover:bg-blue-hover disabled:opacity-40"
                >
                    Iniciar sesión
                </button>
            </div>
        </form>
    </AuthLayout>
</template>
