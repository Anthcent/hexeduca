<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';

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

const audienceLabel = computed(() => audiences.find((audience) => audience.value === form.audience)?.label ?? 'Sin seleccionar');

function submit() {
    form.post(route('notifications.store'));
}
</script>

<template>
    <Head title="Nuevo anuncio" />

    <DashboardLayout active="module:notifications:notifications.index">
        <PageHeader
            eyebrow="Comunicación"
            title="Nuevo anuncio"
            description="Cada destinatario lo recibirá en su bandeja de notificaciones."
        >
            <template #leading>
                <UButton
                    :to="route('notifications.index', undefined, false)"
                    color="neutral"
                    variant="link"
                    icon="i-lucide-arrow-left"
                    class="px-0"
                >
                    Volver a notificaciones
                </UButton>
            </template>
        </PageHeader>

        <form class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]" novalidate @submit.prevent="submit">
            <UCard class="shadow-card">
                <template #header>
                    <PanelHeader kicker="Anuncio" title="Contenido del aviso" />
                </template>

                <div class="space-y-6">
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
                        />
                    </UFormField>

                    <UFormField label="Destinatarios" name="audience" required :error="form.errors.audience">
                        <URadioGroup
                            v-model="form.audience"
                            :items="audiences"
                            variant="card"
                            orientation="horizontal"
                            :ui="{ fieldset: 'grid gap-3 sm:grid-cols-3' }"
                        />
                    </UFormField>
                </div>

                <template #footer>
                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <UButton :to="route('notifications.index', undefined, false)" color="neutral" variant="ghost" size="lg" class="justify-center">
                            Cancelar
                        </UButton>
                        <UButton type="submit" size="lg" icon="i-lucide-send" :loading="form.processing" class="justify-center">
                            Enviar anuncio
                        </UButton>
                    </div>
                </template>
            </UCard>

            <UCard class="shadow-card lg:sticky lg:top-4">
                <template #header>
                    <PanelHeader kicker="Vista previa" title="Así se verá" />
                </template>
                <div class="flex gap-3">
                    <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">
                        <UIcon name="i-lucide-megaphone" class="size-4" />
                    </span>
                    <div class="min-w-0">
                        <p class="break-words text-sm font-bold text-highlighted">{{ form.title || 'Título del anuncio' }}</p>
                        <p class="mt-1 line-clamp-6 whitespace-pre-line break-words text-sm text-toned">{{ form.body || 'El mensaje aparecerá aquí.' }}</p>
                    </div>
                </div>
                <template #footer>
                    <p class="flex items-center gap-2 text-sm text-muted">
                        <UIcon name="i-lucide-users" class="size-4 shrink-0" />
                        Para: <span class="font-medium text-highlighted">{{ audienceLabel }}</span>
                    </p>
                </template>
            </UCard>
        </form>
    </DashboardLayout>
</template>
