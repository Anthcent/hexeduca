<script setup>
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

function submit() {
    form.post(route('register'));
}
</script>

<template>
    <AuthLayout title="Registrar institución">
        <p class="mb-6 text-sm text-ink-muted">Creá la cuenta del administrador de tu escuela.</p>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label for="school_id" class="mb-1.5 block text-xs font-medium text-ink-muted">
                    Escuela
                </label>
                <select
                    id="school_id"
                    v-model="form.school_id"
                    class="w-full rounded border bg-white px-3.5 py-2.5 text-sm text-ink outline-none focus:border-2 focus:border-blue focus:px-[13px] focus:py-[9px]"
                    :class="form.errors.school_id ? 'border-red' : 'border-outline-strong'"
                >
                    <option value="" disabled>Seleccionar escuela</option>
                    <option v-for="school in props.schools" :key="school.id" :value="school.id">
                        {{ school.name }}
                    </option>
                </select>
                <p v-if="form.errors.school_id" class="mt-1.5 text-xs text-red">
                    {{ form.errors.school_id }}
                </p>
            </div>

            <div class="relative">
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    autocomplete="name"
                    placeholder=" "
                    class="peer w-full rounded border bg-white px-3.5 pb-2 pt-4 text-sm text-ink outline-none transition-colors focus:border-2 focus:border-blue focus:px-[13px] focus:pb-[7px] focus:pt-[15px]"
                    :class="form.errors.name ? 'border-red' : 'border-outline-strong'"
                />
                <label
                    for="name"
                    class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 bg-white px-1 text-sm text-ink-muted transition-all peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:text-xs peer-focus:font-medium peer-focus:text-blue peer-[&:not(:placeholder-shown)]:top-0 peer-[&:not(:placeholder-shown)]:-translate-y-1/2 peer-[&:not(:placeholder-shown)]:text-xs peer-[&:not(:placeholder-shown)]:font-medium"
                >
                    Nombre completo
                </label>
                <p v-if="form.errors.name" class="mt-1.5 text-xs text-red">
                    {{ form.errors.name }}
                </p>
            </div>

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
                    autocomplete="new-password"
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

            <div class="relative">
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    placeholder=" "
                    class="peer w-full rounded border border-outline-strong bg-white px-3.5 pb-2 pt-4 text-sm text-ink outline-none transition-colors focus:border-2 focus:border-blue focus:px-[13px] focus:pb-[7px] focus:pt-[15px]"
                />
                <label
                    for="password_confirmation"
                    class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 bg-white px-1 text-sm text-ink-muted transition-all peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:text-xs peer-focus:font-medium peer-focus:text-blue peer-[&:not(:placeholder-shown)]:top-0 peer-[&:not(:placeholder-shown)]:-translate-y-1/2 peer-[&:not(:placeholder-shown)]:text-xs peer-[&:not(:placeholder-shown)]:font-medium"
                >
                    Confirmar contraseña
                </label>
            </div>

            <div class="flex justify-end pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex items-center justify-center rounded-full bg-blue px-6 py-2.5 text-sm font-medium text-white transition-colors hover:bg-blue-hover disabled:opacity-40"
                >
                    Crear cuenta
                </button>
            </div>
        </form>
    </AuthLayout>
</template>
