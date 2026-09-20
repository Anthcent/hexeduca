<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import UiButton from '@/Components/UiButton.vue';

const props = defineProps({
    user: {
        type: Object,
        required: true,
    },
    roles: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    role: props.user.role,
});

function submit() {
    form.put(route('users.update', props.user.id));
}
</script>

<template>
    <DashboardLayout active="usuarios">
        <div class="mb-6">
            <Link :href="route('users.index')" class="mb-2 inline-flex items-center gap-1.5 text-sm font-bold text-brand-700 hover:underline dark:text-brand-300">
                <ArrowLeft class="size-4" />Usuarios
            </Link>
            <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Editar rol</h1>
        </div>

        <div class="section-card max-w-[420px] p-6">
            <p class="muted mb-5 text-sm">{{ props.user.name }} · {{ props.user.email }}</p>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label for="role" class="label">Rol</label>
                    <select id="role" v-model="form.role" class="control" :class="form.errors.role && '!border-red-400'">
                        <option v-for="role in props.roles" :key="role" :value="role">{{ role }}</option>
                    </select>
                    <p v-if="form.errors.role" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.role }}</p>
                </div>

                <UiButton type="submit" :disabled="form.processing" :loading="form.processing">Actualizar rol</UiButton>
            </form>
        </div>
    </DashboardLayout>
</template>
