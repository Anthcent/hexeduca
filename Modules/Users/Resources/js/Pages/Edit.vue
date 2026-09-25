<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { roleColor, roleItems, roleLabel } from '../roles';

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

const roleOptions = computed(() => roleItems(props.roles));

function submit() {
    form.put(route('users.update', props.user.id));
}
</script>

<template>
    <Head :title="`Editar rol · ${user.name}`" />

    <DashboardLayout active="usuarios">
        <PageHeader eyebrow="Usuarios" title="Editar rol" :description="`${user.name} · ${user.email}`">
            <template #leading>
                <UButton :to="route('users.show', user.id, false)" color="neutral" variant="link" icon="i-lucide-arrow-left" class="px-0">
                    Volver a la ficha
                </UButton>
            </template>
        </PageHeader>

        <form class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" novalidate @submit.prevent="submit">
            <UCard class="shadow-card">
                <template #header>
                    <PanelHeader kicker="Permisos" title="Rol en el sistema" />
                </template>

                <UFormField name="role" label="Rol" required :error="form.errors.role" help="El rol define a qué secciones puede acceder el usuario.">
                    <URadioGroup
                        v-model="form.role"
                        :items="roleOptions"
                        variant="card"
                        :ui="{ fieldset: 'grid gap-3 sm:grid-cols-2' }"
                    />
                </UFormField>

                <template #footer>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <UButton :to="route('users.show', user.id, false)" color="neutral" variant="ghost" size="lg" class="justify-center">
                            Cancelar
                        </UButton>
                        <UButton type="submit" icon="i-lucide-check" size="lg" class="justify-center" :loading="form.processing" :disabled="form.processing">
                            Actualizar rol
                        </UButton>
                    </div>
                </template>
            </UCard>

            <UCard class="shadow-card">
                <template #header>
                    <PanelHeader kicker="Resumen" title="Cambio de rol" />
                </template>
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-muted">Rol actual</dt>
                        <dd class="mt-1.5"><UBadge :color="roleColor(user.role)" variant="subtle" class="rounded-full">{{ roleLabel(user.role) }}</UBadge></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-muted">Nuevo rol</dt>
                        <dd class="mt-1.5"><UBadge :color="roleColor(form.role)" variant="subtle" class="rounded-full">{{ roleLabel(form.role) }}</UBadge></dd>
                    </div>
                </dl>
            </UCard>
        </form>
    </DashboardLayout>
</template>
