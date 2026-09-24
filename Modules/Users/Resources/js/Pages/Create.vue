<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import UiButton from '@/Components/UiButton.vue';

const props = defineProps({
    roles: { type: Array, default: () => [] },
    school: { type: Object, default: null },
    schools: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    email: '',
    role: 'student',
    school_id: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post(route('users.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <DashboardLayout active="usuarios">
        <div class="mb-6">
            <Link :href="route('users.index')" class="mb-2 inline-flex items-center gap-1.5 text-sm font-bold text-brand-700 hover:underline dark:text-brand-300">
                <ArrowLeft class="size-4" />Usuarios
            </Link>
            <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Nuevo usuario</h1>
            <p v-if="props.school" class="muted mt-1 text-sm">Se va a crear en {{ props.school.name }}.</p>
        </div>

        <div class="section-card max-w-[520px] p-6">
            <form class="space-y-5" @submit.prevent="submit">
                <div v-if="!props.school">
                    <label for="school_id" class="label">Escuela</label>
                    <select id="school_id" v-model="form.school_id" class="control" :class="form.errors.school_id && '!border-red-400'">
                        <option value="" disabled>Seleccionar escuela</option>
                        <option v-for="school in props.schools" :key="school.id" :value="school.id">{{ school.name }}</option>
                    </select>
                    <p v-if="form.errors.school_id" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.school_id }}</p>
                </div>

                <div>
                    <label for="name" class="label">Nombre completo</label>
                    <input id="name" v-model="form.name" type="text" autocomplete="off" class="control" :class="form.errors.name && '!border-red-400'" />
                    <p v-if="form.errors.name" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label for="email" class="label">Email</label>
                    <input id="email" v-model="form.email" type="email" autocomplete="off" class="control" :class="form.errors.email && '!border-red-400'" />
                    <p v-if="form.errors.email" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label for="role" class="label">Rol</label>
                    <select id="role" v-model="form.role" class="control" :class="form.errors.role && '!border-red-400'">
                        <option v-for="role in props.roles" :key="role" :value="role">{{ role }}</option>
                    </select>
                    <p v-if="form.errors.role" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.role }}</p>
                </div>

                <div>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="password" class="label">Contraseña inicial</label>
                            <input id="password" v-model="form.password" type="password" autocomplete="new-password" class="control" :class="form.errors.password && '!border-red-400'" />
                        </div>
                        <div>
                            <label for="password_confirmation" class="label">Confirmar contraseña</label>
                            <input id="password_confirmation" v-model="form.password_confirmation" type="password" autocomplete="new-password" class="control" />
                        </div>
                    </div>
                    <p v-if="form.errors.password" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.password }}</p>
                    <p v-else class="muted mt-1.5 text-xs">Mínimo 8 caracteres. Compartila con el usuario por un canal seguro.</p>
                </div>

                <div class="flex items-center gap-2">
                    <UiButton type="submit" :disabled="form.processing" :loading="form.processing">Crear usuario</UiButton>
                    <Link :href="route('users.index')" class="inline-flex h-11 items-center rounded-xl px-4 text-sm font-semibold text-[rgb(var(--muted))] transition hover:bg-[rgb(var(--surface-muted))] hover:text-[rgb(var(--text))]">Cancelar</Link>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
