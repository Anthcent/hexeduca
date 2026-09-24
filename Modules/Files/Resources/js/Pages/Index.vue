<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from '@nuxt/ui/composables/useToast';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

const props = defineProps({
    files: { type: Object, required: true },
    canUpload: { type: Boolean, default: false },
    maxSizeBytes: { type: Number, required: true },
    allowedExtensions: { type: Array, required: true },
});

const page = usePage();
const toast = useToast();

const TYPE_LABELS = {
    pdf: 'PDF',
    jpg: 'JPG',
    jpeg: 'JPG',
    png: 'PNG',
    doc: 'Word',
    docx: 'Word',
    xls: 'Excel',
    xlsx: 'Excel',
    ppt: 'PowerPoint',
    pptx: 'PowerPoint',
};

const items = computed(() => props.files.data ?? []);
const hasPages = computed(() => props.files.last_page > 1);
const accept = computed(() => props.allowedExtensions.map((extension) => `.${extension}`).join(','));
const maxSizeLabel = computed(() => formatSize(props.maxSizeBytes));

const sizeFormatter = new Intl.NumberFormat('es', { maximumFractionDigits: 1 });
const dateFormatter = new Intl.DateTimeFormat('es', { dateStyle: 'medium', timeStyle: 'short' });

function formatSize(bytes) {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${sizeFormatter.format(bytes / 1024)} KB`;
    }

    return `${sizeFormatter.format(bytes / (1024 * 1024))} MB`;
}

function formatDate(value) {
    return value ? dateFormatter.format(new Date(value)) : '';
}

function typeLabel(extension) {
    return TYPE_LABELS[extension?.toLowerCase()] ?? extension?.toUpperCase() ?? '';
}

// Upload

const form = useForm({ file: null });

const clientError = computed(() => {
    const file = form.file;

    if (!file) {
        return null;
    }

    const extension = file.name.includes('.') ? file.name.split('.').pop().toLowerCase() : '';

    if (!props.allowedExtensions.includes(extension)) {
        return 'El tipo de archivo no está permitido. Formatos permitidos: PDF, JPG, PNG, Word, Excel y PowerPoint.';
    }

    if (file.size > props.maxSizeBytes) {
        return `El archivo supera el límite de ${maxSizeLabel.value}.`;
    }

    return null;
});

const uploadError = computed(() => clientError.value ?? form.errors.file ?? null);
const uploadProgress = computed(() => form.progress?.percentage ?? null);

watch(
    () => form.file,
    () => form.clearErrors('file'),
);

function upload() {
    if (!form.file || clientError.value) {
        return;
    }

    form.post(route('files.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

// Delete

const fileToDelete = ref(null);
const deleting = ref(false);

const confirmOpen = computed({
    get: () => fileToDelete.value !== null,
    set: (open) => {
        if (!open && !deleting.value) {
            fileToDelete.value = null;
        }
    },
});

function askToDelete(file) {
    fileToDelete.value = file;
}

function confirmDelete() {
    if (!fileToDelete.value) {
        return;
    }

    deleting.value = true;

    router.delete(route('files.destroy', fileToDelete.value.id), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            fileToDelete.value = null;
        },
    });
}

watch(
    () => page.props.flash?.success,
    (message) => {
        if (message) {
            toast.add({ title: message, color: 'success', icon: 'i-lucide-circle-check' });
        }
    },
    { immediate: true },
);

watch(
    () => page.props.flash?.error,
    (message) => {
        if (message) {
            toast.add({ title: message, color: 'error', icon: 'i-lucide-circle-alert' });
        }
    },
    { immediate: true },
);
</script>

<template>
    <Head title="Archivos" />

    <DashboardLayout active="module:files:files.index">
        <div class="mb-5">
            <p class="muted mb-1 text-xs font-bold uppercase tracking-[.14em]">Documentos</p>
            <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Archivos</h1>
            <p class="muted mt-1 max-w-[60ch] text-sm">
                Repositorio privado de la institución. Solo el personal y los docentes pueden ver y descargar estos archivos.
            </p>
        </div>

        <UCard v-if="canUpload" class="mb-5 rounded-[24px] shadow-soft">
            <form class="space-y-4" novalidate @submit.prevent="upload">
                <UFormField name="file" :error="uploadError ?? undefined">
                    <UFileUpload
                        v-model="form.file"
                        :accept="accept"
                        :disabled="form.processing"
                        :preview="false"
                        icon="i-lucide-upload"
                        label="Arrastra un archivo aquí o haz clic para seleccionarlo"
                        :description="`PDF, JPG, PNG, Word, Excel o PowerPoint · Máximo ${maxSizeLabel}`"
                        class="min-h-36 w-full"
                        :ui="{ base: 'rounded-xl' }"
                    />
                </UFormField>

                <div v-if="form.file" class="flex items-center gap-3 rounded-xl border px-3 py-2">
                    <UIcon name="i-lucide-file" class="size-5 shrink-0 text-brand-700 dark:text-brand-200" />
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold" :title="form.file.name">{{ form.file.name }}</p>
                        <p class="muted text-xs">{{ formatSize(form.file.size) }}</p>
                    </div>
                    <UButton
                        color="neutral"
                        variant="ghost"
                        size="sm"
                        icon="i-lucide-x"
                        aria-label="Quitar archivo seleccionado"
                        class="rounded-xl"
                        :disabled="form.processing"
                        @click="form.file = null"
                    />
                </div>

                <UProgress
                    v-if="form.processing && uploadProgress !== null"
                    :model-value="uploadProgress"
                    size="sm"
                    aria-label="Progreso de la subida"
                />

                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="muted text-xs">
                        <template v-if="form.processing && uploadProgress !== null">Subiendo… {{ uploadProgress }} %</template>
                        <template v-else>El archivo quedará disponible para todo el personal y los docentes de la institución.</template>
                    </p>
                    <UButton
                        type="submit"
                        size="lg"
                        icon="i-lucide-upload"
                        :loading="form.processing"
                        :disabled="!form.file || clientError !== null"
                        class="justify-center rounded-xl"
                    >
                        Subir archivo
                    </UButton>
                </div>
            </form>
        </UCard>

        <UCard :ui="{ body: 'p-0 sm:p-0' }" class="rounded-[24px] shadow-soft">
            <div v-if="items.length === 0" class="flex flex-col items-center gap-3 px-6 py-14 text-center">
                <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-200">
                    <UIcon name="i-lucide-folder-open" class="size-6" />
                </span>
                <p class="font-semibold">Todavía no hay archivos.</p>
                <p class="muted max-w-[42ch] text-sm">
                    <template v-if="canUpload">Sube el primero con el formulario de arriba.</template>
                    <template v-else>Cuando se suba un archivo, aparecerá aquí.</template>
                </p>
            </div>

            <template v-else>
                <div
                    class="muted hidden grid-cols-[minmax(0,1fr)_6rem_10rem_10rem_auto] gap-4 border-b px-5 py-3 text-xs font-bold uppercase tracking-[.1em] md:grid"
                    aria-hidden="true"
                >
                    <span>Nombre</span>
                    <span>Tamaño</span>
                    <span>Subido por</span>
                    <span>Fecha</span>
                    <span class="w-[5.5rem] text-right">Acciones</span>
                </div>

                <ul class="divide-y">
                    <li
                        v-for="file in items"
                        :key="file.id"
                        class="flex flex-col gap-1.5 px-5 py-4 md:grid md:grid-cols-[minmax(0,1fr)_6rem_10rem_10rem_auto] md:items-center md:gap-4"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-200">
                                <UIcon name="i-lucide-file" class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold" :title="file.name">{{ file.name }}</p>
                                <UBadge color="neutral" variant="subtle" size="sm" class="mt-1">{{ typeLabel(file.extension) }}</UBadge>
                            </div>
                        </div>

                        <p class="muted text-sm md:text-[rgb(var(--text))]">
                            <span class="md:hidden">Tamaño: </span>{{ formatSize(file.sizeBytes) }}
                        </p>
                        <p class="muted truncate text-sm md:text-[rgb(var(--text))]" :title="file.uploaderName">
                            <span class="md:hidden">Subido por: </span>{{ file.uploaderName }}
                        </p>
                        <p class="muted text-sm">
                            <time :datetime="file.createdAt">{{ formatDate(file.createdAt) }}</time>
                        </p>

                        <div class="flex items-center gap-1 md:w-[5.5rem] md:justify-end">
                            <UTooltip text="Descargar">
                                <UButton
                                    :to="route('files.download', file.id)"
                                    external
                                    download
                                    color="neutral"
                                    variant="ghost"
                                    icon="i-lucide-download"
                                    :aria-label="`Descargar ${file.name}`"
                                    class="rounded-xl"
                                />
                            </UTooltip>
                            <UTooltip v-if="file.canDelete" text="Eliminar">
                                <UButton
                                    color="error"
                                    variant="ghost"
                                    icon="i-lucide-trash-2"
                                    :aria-label="`Eliminar ${file.name}`"
                                    class="rounded-xl"
                                    @click="askToDelete(file)"
                                />
                            </UTooltip>
                        </div>
                    </li>
                </ul>
            </template>
        </UCard>

        <nav v-if="hasPages" class="mt-4 flex items-center justify-between gap-3" aria-label="Paginación">
            <UButton
                class="rounded-xl"
                color="neutral"
                variant="outline"
                icon="i-lucide-arrow-left"
                :to="files.prev_page_url ?? undefined"
                :disabled="!files.prev_page_url"
            >
                Anteriores
            </UButton>
            <p class="muted text-sm">Página {{ files.current_page }} de {{ files.last_page }}</p>
            <UButton
                class="rounded-xl"
                color="neutral"
                variant="outline"
                trailing-icon="i-lucide-arrow-right"
                :to="files.next_page_url ?? undefined"
                :disabled="!files.next_page_url"
            >
                Siguientes
            </UButton>
        </nav>

        <UModal
            v-model:open="confirmOpen"
            title="¿Eliminar archivo?"
            :description="fileToDelete ? `Se eliminará «${fileToDelete.name}» de forma permanente. Esta acción no se puede deshacer.` : ''"
            :dismissible="!deleting"
            :ui="{ content: 'rounded-[24px]' }"
        >
            <template #footer>
                <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <UButton
                        color="neutral"
                        variant="ghost"
                        class="justify-center rounded-xl"
                        :disabled="deleting"
                        @click="confirmOpen = false"
                    >
                        Cancelar
                    </UButton>
                    <UButton
                        color="error"
                        icon="i-lucide-trash-2"
                        class="justify-center rounded-xl"
                        :loading="deleting"
                        @click="confirmDelete"
                    >
                        Eliminar
                    </UButton>
                </div>
            </template>
        </UModal>
    </DashboardLayout>
</template>
