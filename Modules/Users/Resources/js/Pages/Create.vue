<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import { nullableField } from '@/lib/forms';
import { roleItems, roleLabel } from '../roles';

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

const roleOptions = computed(() => roleItems(props.roles));

const schoolId = nullableField(form, 'school_id');

const schoolName = computed(() => props.school?.name
    ?? props.schools.find((school) => school.id === form.school_id)?.name
    ?? null);

function submit() {
    form.post(route('users.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head title="Nuevo usuario" />

    <DashboardLayout active="usuarios">
        <PageHeader
            eyebrow="Usuarios"
            title="Nuevo usuario"
            :description="school ? `La cuenta se creará en ${school.name}.` : 'Crea una cuenta y asígnale una institución y un rol.'"
        >
            <template #leading>
                <UButton :to="route('users.index', undefined, false)" color="neutral" variant="link" icon="i-lucide-arrow-left" class="px-0">
                    Volver a usuarios
                </UButton>
            </template>
        </PageHeader>

        <form class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" novalidate @submit.prevent="submit">
            <UCard class="shadow-card">
                <template #header>
                    <PanelHeader kicker="Cuenta" title="Datos del usuario" />
                </template>

                <div class="space-y-6">
                    <UFormField v-if="!school" label="Institución" name="school_id" required :error="form.errors.school_id">
                        <USelectMenu
                            v-model="schoolId"
                            :items="schools"
                            value-key="id"
                            label-key="name"
                            placeholder="Selecciona una institución"
                            icon="i-lucide-school"
                            size="lg"
                            class="w-full"
                        />
                    </UFormField>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <UFormField label="Nombre completo" name="name" required :error="form.errors.name">
                            <UInput v-model="form.name" autocomplete="off" size="lg" class="w-full" />
                        </UFormField>
                        <UFormField label="Email" name="email" required :error="form.errors.email">
                            <UInput v-model="form.email" type="email" autocomplete="off" icon="i-lucide-mail" size="lg" class="w-full" />
                        </UFormField>
                    </div>

                    <UFormField label="Rol" name="role" required :error="form.errors.role">
                        <URadioGroup
                            v-model="form.role"
                            :items="roleOptions"
                            variant="card"
                            orientation="horizontal"
                            :ui="{ fieldset: 'grid gap-3 sm:grid-cols-2 xl:grid-cols-4' }"
                        />
                    </UFormField>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <UFormField
                            label="Contraseña inicial"
                            name="password"
                            required
                            :error="form.errors.password"
                            help="Mínimo 8 caracteres. Compártela con el usuario por un canal seguro."
                        >
                            <UInput v-model="form.password" type="password" autocomplete="new-password" size="lg" class="w-full" />
                        </UFormField>
                        <UFormField label="Confirmar contraseña" name="password_confirmation" required>
                            <UInput v-model="form.password_confirmation" type="password" autocomplete="new-password" size="lg" class="w-full" />
                        </UFormField>
                    </div>
                </div>

                <template #footer>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <UButton :to="route('users.index', undefined, false)" color="neutral" variant="ghost" size="lg" class="justify-center">
                            Cancelar
                        </UButton>
                        <UButton type="submit" icon="i-lucide-user-plus" size="lg" class="justify-center" :loading="form.processing">
                            Crear usuario
                        </UButton>
                    </div>
                </template>
            </UCard>

            <UCard class="shadow-card lg:sticky lg:top-4">
                <template #header>
                    <PanelHeader kicker="Resumen" title="Nueva cuenta" />
                </template>
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-muted">Nombre</dt>
                        <dd class="mt-1 truncate font-medium text-highlighted">{{ form.name || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-muted">Email</dt>
                        <dd class="mt-1 truncate font-medium text-highlighted">{{ form.email || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-muted">Institución</dt>
                        <dd class="mt-1 font-medium text-highlighted">{{ schoolName ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-muted">Rol</dt>
                        <dd class="mt-1 font-medium text-highlighted">{{ roleLabel(form.role) }}</dd>
                    </div>
                </dl>
            </UCard>
        </form>
    </DashboardLayout>
</template>
