<script setup>
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

defineProps({
    users: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const currentUserId = computed(() => page.props.auth?.user?.id);

function roleChipClass(role) {
    if (role === 'super-admin') return 'bg-blue-light text-blue';
    if (role === 'staff/admin') return 'bg-green-tint text-green';
    if (role === 'teacher') return 'bg-yellow-tint text-[#a15c00]';
    return 'bg-outline text-ink-muted';
}
</script>

<template>
    <DashboardLayout active="usuarios">
        <div class="mb-1">
            <h1 class="text-[1.75rem] font-semibold text-ink">Usuarios</h1>
            <p class="mt-1 max-w-[60ch] text-sm text-ink-muted">
                Personal, docentes y estudiantes con acceso al sistema.
            </p>
        </div>

        <div
            v-if="flash.success || flash.error"
            class="mt-5 rounded-g2 px-4 py-3 text-sm"
            :class="flash.success ? 'bg-green-tint text-green' : 'bg-red-tint text-red'"
        >
            {{ flash.success || flash.error }}
        </div>

        <div class="mt-6 overflow-hidden rounded-g2 border border-outline bg-white">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline">
                        <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Nombre</th>
                        <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Email</th>
                        <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Rol</th>
                        <th class="w-20 px-4.5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="users.length === 0"><td colspan="4" class="px-4.5 py-8 text-center text-ink-muted">No hay usuarios todavía.</td></tr>
                    <tr v-for="row in users" :key="row.id" class="group border-t border-outline hover:bg-blue-light/40">
                        <td class="px-4.5 py-3">
                            {{ row.name }}
                            <span v-if="row.id === currentUserId" class="ml-1.5 text-xs text-ink-muted">(vos)</span>
                        </td>
                        <td class="px-4.5 py-3 text-ink-muted">{{ row.email }}</td>
                        <td class="px-4.5 py-3">
                            <span class="rounded-pill px-3 py-1 text-xs font-medium" :class="roleChipClass(row.role)">{{ row.role ?? 'sin rol' }}</span>
                        </td>
                        <td class="px-2 py-3">
                            <Link
                                :href="route('users.edit', row.id)"
                                class="inline-flex rounded-full p-1.5 text-ink-muted opacity-0 hover:bg-black/[0.06] group-hover:opacity-100"
                                title="Editar rol"
                            >
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </DashboardLayout>
</template>
