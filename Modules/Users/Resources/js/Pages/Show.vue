<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { roleColor, roleLabel } from '../roles';

const props = defineProps({
    user: { type: Object, required: true },
    can: { type: Object, default: () => ({ edit: false, delete: false }) },
});

const confirmingDelete = ref(false);
const deleteForm = useForm({});

const createdAt = computed(() => {
    if (!props.user.created_at) return '—';

    return new Date(props.user.created_at).toLocaleDateString('es', { day: 'numeric', month: 'long', year: 'numeric' });
});

const initials = computed(() => (props.user.name ?? '')
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase());

const details = computed(() => [
    { label: 'Nombre completo', value: props.user.name, icon: 'i-lucide-user-round' },
    { label: 'Email', value: props.user.email, icon: 'i-lucide-mail' },
    { label: 'Institución', value: props.user.school ?? 'Plataforma (sin institución)', icon: 'i-lucide-school' },
    { label: 'Alta en el sistema', value: createdAt.value, icon: 'i-lucide-calendar' },
]);

const hasActions = computed(() => props.can.edit || props.can.delete);

function destroy() {
    deleteForm.delete(route('users.destroy', props.user.id), {
        onFinish: () => { confirmingDelete.value = false; },
    });
}
</script>

<template>
    <Head :title="user.name" />

    <DashboardLayout active="usuarios">
        <PageHeader eyebrow="Usuarios" :title="user.name" :description="user.email">
            <template #leading>
                <UButton :to="route('users.index', undefined, false)" color="neutral" variant="link" icon="i-lucide-arrow-left" class="px-0">
                    Volver a usuarios
                </UButton>
            </template>
        </PageHeader>

        <div class="grid items-start gap-6" :class="hasActions && 'lg:grid-cols-[minmax(0,1fr)_20rem]'">
            <UCard class="shadow-card">
                <template #header>
                    <div class="flex flex-wrap items-center gap-4">
                        <UAvatar :text="initials" :alt="user.name" size="xl" class="rounded-lg bg-primary/10" :ui="{ fallback: 'font-bold text-primary' }" />
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold uppercase tracking-[.12em] text-muted">Ficha de usuario</p>
                            <h2 class="mt-0.5 truncate text-base font-bold text-highlighted">{{ user.name }}</h2>
                        </div>
                        <UBadge :color="roleColor(user.role)" variant="subtle" size="lg" class="rounded-full">{{ roleLabel(user.role) }}</UBadge>
                    </div>
                </template>

                <dl class="grid gap-x-8 gap-y-6 sm:grid-cols-2">
                    <div v-for="item in details" :key="item.label" class="flex min-w-0 gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-elevated text-muted">
                            <UIcon :name="item.icon" class="size-4" />
                        </span>
                        <div class="min-w-0">
                            <dt class="text-xs font-semibold uppercase tracking-wide text-muted">{{ item.label }}</dt>
                            <dd class="mt-1 break-words text-sm font-medium text-highlighted">{{ item.value }}</dd>
                        </div>
                    </div>
                </dl>
            </UCard>

            <UCard v-if="hasActions" class="shadow-card">
                <template #header>
                    <PanelHeader kicker="Acciones" title="Gestionar usuario" />
                </template>
                <div class="flex flex-col gap-2">
                    <UButton
                        v-if="can.edit"
                        :to="route('users.edit', user.id, false)"
                        icon="i-lucide-shield-check"
                        size="lg"
                        block
                    >
                        Cambiar rol
                    </UButton>
                    <UButton
                        v-if="can.delete"
                        color="error"
                        variant="soft"
                        icon="i-lucide-trash-2"
                        size="lg"
                        block
                        @click="confirmingDelete = true"
                    >
                        Eliminar usuario
                    </UButton>
                </div>
            </UCard>
        </div>

        <UModal
            v-model:open="confirmingDelete"
            title="¿Eliminar usuario?"
            :dismissible="!deleteForm.processing"
        >
            <template #body>
                <p class="text-sm text-muted">
                    Vas a eliminar a <strong class="text-highlighted">{{ user.name }}</strong> ({{ user.email }}). Esta acción no se puede deshacer.
                </p>
            </template>
            <template #footer>
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UButton color="neutral" variant="ghost" class="justify-center" :disabled="deleteForm.processing" @click="confirmingDelete = false">
                        Cancelar
                    </UButton>
                    <UButton color="error" icon="i-lucide-trash-2" class="justify-center" :loading="deleteForm.processing" @click="destroy">
                        Eliminar
                    </UButton>
                </div>
            </template>
        </UModal>
    </DashboardLayout>
</template>
