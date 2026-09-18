<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

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
            <Link :href="route('users.index')" class="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-blue hover:underline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M15 18l-6-6 6-6" /></svg>
                Usuarios
            </Link>
            <h1 class="text-[1.75rem] font-semibold text-ink">Editar rol</h1>
        </div>

        <div class="max-w-[420px] rounded-g2 border border-outline bg-white p-6">
            <p class="mb-5 text-sm text-ink-muted">{{ props.user.name }} · {{ props.user.email }}</p>

            <form class="flex flex-col gap-5" @submit.prevent="submit">
                <div>
                    <label for="role" class="mb-1.5 block text-xs font-medium text-ink-muted">Rol</label>
                    <select
                        id="role"
                        v-model="form.role"
                        class="w-full rounded border bg-white px-3.5 py-2.5 text-sm text-ink outline-none focus:border-2 focus:border-blue"
                        :class="form.errors.role ? 'border-red' : 'border-outline-strong'"
                    >
                        <option v-for="role in props.roles" :key="role" :value="role">
                            {{ role }}
                        </option>
                    </select>
                    <p v-if="form.errors.role" class="mt-1.5 text-xs text-red">{{ form.errors.role }}</p>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="inline-flex w-fit items-center justify-center rounded-pill bg-blue px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-hover disabled:opacity-40"
                >
                    Actualizar rol
                </button>
            </form>
        </div>
    </DashboardLayout>
</template>
