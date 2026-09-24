<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const TITLE_MAX = 120;
const BODY_MAX = 2000;

const audiences = [
    { value: 'teachers', label: 'Docentes', description: 'Todo el personal docente de la institución.' },
    { value: 'students', label: 'Estudiantes', description: 'Todos los estudiantes de la institución.' },
    { value: 'all', label: 'Docentes y estudiantes', description: 'Toda la comunidad escolar.' },
];

const form = useForm({
    title: '',
    body: '',
    audience: null,
});

function submit() {
    form.post(route('notifications.store'));
}
</script>

<template>
    <Head title="Nuevo anuncio" />

    <DashboardLayout active="module:notifications:notifications.index">
        <div class="mb-5">
            <UButton
                :to="route('notifications.index')"
                color="neutral"
                variant="link"
                icon="i-lucide-arrow-left"
                class="mb-2 px-0"
            >
                Volver a notificaciones
            </UButton>
            <p class="muted mb-1 text-xs font-bold uppercase tracking-[.14em]">Comunicación</p>
            <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Nuevo anuncio</h1>
            <p class="muted mt-1 max-w-[60ch] text-sm">Cada destinatario lo recibirá en su bandeja de notificaciones.</p>
        </div>

        <UCard class="max-w-3xl rounded-[24px] shadow-soft">
            <form class="space-y-6" novalidate @submit.prevent="submit">
                <UFormField
                    label="Título"
                    name="title"
                    required
                    :hint="`${form.title.length}/${TITLE_MAX}`"
                    :error="form.errors.title"
                >
                    <UInput
                        v-model="form.title"
                        :maxlength="TITLE_MAX"
                        size="lg"
                        placeholder="Ej.: Reunión de padres del viernes"
                        class="w-full"
                        :ui="{ base: 'rounded-xl' }"
                        autofocus
                    />
                </UFormField>

                <UFormField
                    label="Mensaje"
                    name="body"
                    required
                    :hint="`${form.body.length}/${BODY_MAX}`"
                    :error="form.errors.body"
                >
                    <UTextarea
                        v-model="form.body"
                        :maxlength="BODY_MAX"
                        :rows="6"
                        autoresize
                        size="lg"
                        placeholder="Escribe el contenido del anuncio."
                        class="w-full"
                        :ui="{ base: 'rounded-xl' }"
                    />
                </UFormField>

                <UFormField label="Destinatarios" name="audience" required :error="form.errors.audience">
                    <URadioGroup
                        v-model="form.audience"
                        :items="audiences"
                        variant="card"
                        orientation="horizontal"
                        :ui="{ fieldset: 'grid gap-3 sm:grid-cols-3', item: 'rounded-xl' }"
                    />
                </UFormField>

                <div class="flex flex-col-reverse gap-2 border-t pt-5 sm:flex-row sm:justify-end">
                    <UButton :to="route('notifications.index')" color="neutral" variant="ghost" size="lg" class="justify-center rounded-xl">
                        Cancelar
                    </UButton>
                    <UButton type="submit" size="lg" icon="i-lucide-send" :loading="form.processing" class="justify-center rounded-xl">
                        Enviar anuncio
                    </UButton>
                </div>
            </form>
        </UCard>
    </DashboardLayout>
</template>
