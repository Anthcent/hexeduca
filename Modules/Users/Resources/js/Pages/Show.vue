<script setup>
import { computed, ref } from 'vue';
import { usePage, useForm, Link } from '@inertiajs/vue3';
import { ArrowLeft, CheckCircle2, PencilLine, Trash2 } from 'lucide-vue-next';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiButton from '@/Components/UiButton.vue';
import UiModal from '@/Components/UiModal.vue';

const props = defineProps({
    user: { type: Object, required: true },
    can: { type: Object, default: () => ({ edit: false, delete: false }) },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const confirmingDelete = ref(false);
const deleteForm = useForm({});

const createdAt = computed(() => {
    if (! props.user.created_at) return '—';
    return new Date(props.user.created_at).toLocaleDateString('es', { day: 'numeric', month: 'long', year: 'numeric' });
});

function roleTone(role) {
    if (role === 'super-admin') return 'brand';
    if (role === 'staff/admin') return 'success';
    if (role === 'teacher') return 'warning';
    return 'neutral';
}

function destroy() {
    deleteForm.delete(route('users.destroy', props.user.id), {
        onFinish: () => { confirmingDelete.value = false; },
    });
}
</script>

<template>
    <DashboardLayout active="usuarios">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <Link :href="route('users.index')" class="mb-2 inline-flex items-center gap-1.5 text-sm font-bold text-brand-700 hover:underline dark:text-brand-300">
                    <ArrowLeft class="size-4" />Usuarios
                </Link>
                <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">{{ props.user.name }}</h1>
            </div>
            <div class="flex gap-2">
                <Link
                    v-if="props.can.edit"
                    :href="route('users.edit', props.user.id)"
                    class="inline-flex h-11 items-center gap-2 rounded-xl border bg-[rgb(var(--surface))] px-4 text-sm font-semibold text-[rgb(var(--text))] shadow-sm transition hover:border-brand-300 hover:bg-brand-50 dark:hover:bg-brand-950/40"
                >
                    <PencilLine class="size-4" />Editar rol
                </Link>
                <UiButton v-if="props.can.delete" variant="danger" @click="confirmingDelete = true">
                    <template #icon><Trash2 class="size-4" /></template>Eliminar
                </UiButton>
            </div>
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

        <div class="section-card max-w-[640px]">
            <dl class="divide-y">
                <div class="grid gap-1 px-6 py-4 sm:grid-cols-[160px_1fr]">
                    <dt class="muted text-xs font-bold uppercase tracking-wider">Nombre</dt>
                    <dd class="text-sm font-bold">{{ props.user.name }}</dd>
                </div>
                <div class="grid gap-1 px-6 py-4 sm:grid-cols-[160px_1fr]">
                    <dt class="muted text-xs font-bold uppercase tracking-wider">Email</dt>
                    <dd class="text-sm">{{ props.user.email }}</dd>
                </div>
                <div class="grid gap-1 px-6 py-4 sm:grid-cols-[160px_1fr]">
                    <dt class="muted text-xs font-bold uppercase tracking-wider">Rol</dt>
                    <dd><UiBadge :tone="roleTone(props.user.role)">{{ props.user.role ?? 'sin rol' }}</UiBadge></dd>
                </div>
                <div class="grid gap-1 px-6 py-4 sm:grid-cols-[160px_1fr]">
                    <dt class="muted text-xs font-bold uppercase tracking-wider">Escuela</dt>
                    <dd class="text-sm">{{ props.user.school ?? 'Plataforma (sin escuela)' }}</dd>
                </div>
                <div class="grid gap-1 px-6 py-4 sm:grid-cols-[160px_1fr]">
                    <dt class="muted text-xs font-bold uppercase tracking-wider">Alta</dt>
                    <dd class="text-sm">{{ createdAt }}</dd>
                </div>
            </dl>
        </div>

        <UiModal :open="confirmingDelete" title="Eliminar usuario" @close="confirmingDelete = false">
            <template #description>
                Vas a eliminar a <strong>{{ props.user.name }}</strong> ({{ props.user.email }}). Esta acción no se puede deshacer.
            </template>
            <template #footer>
                <UiButton variant="ghost" @click="confirmingDelete = false">Cancelar</UiButton>
                <UiButton variant="danger" :loading="deleteForm.processing" @click="destroy">Eliminar</UiButton>
            </template>
        </UiModal>
    </DashboardLayout>
</template>
