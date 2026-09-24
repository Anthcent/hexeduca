<script setup>
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { CheckCircle2, PencilLine, Plus } from 'lucide-vue-next';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import UiBadge from '@/Components/UiBadge.vue';

defineProps({
    users: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const currentUserId = computed(() => page.props.auth?.user?.id);

function roleTone(role) {
    if (role === 'super-admin') return 'brand';
    if (role === 'staff/admin') return 'success';
    if (role === 'teacher') return 'warning';
    return 'neutral';
}
</script>

<template>
    <DashboardLayout active="usuarios">
        <div class="mb-5 flex items-start justify-between gap-4 sm:items-center">
            <div>
                <p class="muted mb-1 text-xs font-bold uppercase tracking-[.14em]">Gestión escolar</p>
                <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Usuarios</h1>
                <p class="muted mt-1 max-w-[60ch] text-sm">Personal, docentes y estudiantes con acceso al sistema.</p>
            </div>
            <Link
                :href="route('users.create')"
                class="inline-flex h-11 shrink-0 items-center gap-2 whitespace-nowrap rounded-xl bg-brand-500 px-4 text-sm font-semibold text-white shadow-[0_8px_18px_rgba(24,168,121,.24)] transition hover:bg-brand-600 active:bg-brand-700"
            >
                <Plus class="size-4" />Nuevo usuario
            </Link>
        </div>

        <Transition name="fade">
            <div
                v-if="flash.success || flash.error"
                class="mb-5 flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-semibold"
                :class="flash.success ? 'border-brand-200 bg-brand-50 text-brand-800 dark:border-brand-800 dark:bg-brand-950 dark:text-brand-200' : 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300'"
            >
                <CheckCircle2 class="size-4 shrink-0" />
                {{ flash.success || flash.error }}
            </div>
        </Transition>

        <div class="section-card">
            <table class="w-full text-left">
                <thead class="bg-[rgb(var(--surface-muted))] text-xs uppercase tracking-wider muted">
                    <tr><th class="px-5 py-3">Nombre</th><th class="px-5 py-3">Email</th><th class="px-5 py-3">Rol</th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="users.length === 0"><td colspan="4" class="muted px-5 py-8 text-center text-sm">No hay usuarios todavía.</td></tr>
                    <tr v-for="row in users" :key="row.id" class="group transition hover:bg-brand-50/40 dark:hover:bg-brand-950/20">
                        <td class="px-5 py-4 text-sm font-bold">
                            <Link :href="route('users.show', row.id)" class="hover:text-brand-700 hover:underline dark:hover:text-brand-300">{{ row.name }}</Link>
                            <span v-if="row.id === currentUserId" class="muted ml-1.5 text-xs font-medium">(vos)</span>
                        </td>
                        <td class="muted px-5 py-4 text-sm">{{ row.email }}</td>
                        <td class="px-5 py-4"><UiBadge :tone="roleTone(row.role)">{{ row.role ?? 'sin rol' }}</UiBadge></td>
                        <td class="px-5 py-4">
                            <Link
                                :href="route('users.edit', row.id)"
                                class="muted inline-flex rounded-lg p-2 opacity-0 transition hover:bg-[rgb(var(--surface-muted))] hover:text-[rgb(var(--text))] group-hover:opacity-100"
                                title="Editar rol"
                            >
                                <PencilLine class="size-4" />
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </DashboardLayout>
</template>
