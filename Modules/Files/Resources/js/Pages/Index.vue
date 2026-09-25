<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import DataToolbar from '@/Components/DataToolbar.vue';
import EmptyState from '@/Components/EmptyState.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import PaginationBar from '@/Components/PaginationBar.vue';
import { tableUi } from '@/Components/tableUi';
import { useConfirmAction } from '@/composables/useConfirmAction';

const props = defineProps({
    files: { type: Object, required: true },
    canUpload: { type: Boolean, default: false },
    maxSizeBytes: { type: Number, required: true },
    allowedExtensions: { type: Array, required: true },
});

// File kinds: label for the badge and chips, plus a literal icon name.
const KINDS = {
    pdf: { label: 'PDF', icon: 'i-lucide-file-text' },
    image: { label: 'Imagen', icon: 'i-lucide-file-image' },
    word: { label: 'Word', icon: 'i-lucide-file-type' },
    excel: { label: 'Excel', icon: 'i-lucide-file-spreadsheet' },
    powerpoint: { label: 'PowerPoint', icon: 'i-lucide-presentation' },
    other: { label: 'Otro', icon: 'i-lucide-file' },
};

const EXTENSION_KINDS = {
    pdf: 'pdf',
    jpg: 'image',
    jpeg: 'image',
    png: 'image',
    doc: 'word',
    docx: 'word',
    xls: 'excel',
    xlsx: 'excel',
    ppt: 'powerpoint',
    pptx: 'powerpoint',
};

const items = computed(() => props.files.data ?? []);
const total = computed(() => props.files.total ?? items.value.length);
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

function kindOf(file) {
    return EXTENSION_KINDS[file.extension?.toLowerCase()] ?? 'other';
}

// Filters: the list is paginated server-side and the controller takes no
// filters, so search and type chips apply to the current page only.

const search = ref('');
const kind = ref('all');

const statuses = computed(() => {
    const counts = items.value.reduce((acc, file) => {
        const key = kindOf(file);
        acc[key] = (acc[key] ?? 0) + 1;

        return acc;
    }, {});

    return [
        { value: 'all', label: 'Todos', count: items.value.length },
        ...Object.keys(KINDS).filter((key) => counts[key]).map((key) => ({ value: key, label: KINDS[key].label, count: counts[key] })),
    ];
});

const filtered = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');

    return items.value.filter((file) => {
        if (kind.value !== 'all' && kindOf(file) !== kind.value) return false;
        if (!term) return true;

        return `${file.name} ${file.uploaderName}`.toLocaleLowerCase('es').includes(term);
    });
});

const isFiltered = computed(() => search.value.trim() !== '' || kind.value !== 'all');

function resetFilters() {
    search.value = '';
    kind.value = 'all';
}

const columns = [
    { accessorKey: 'name', header: 'Nombre' },
    { accessorKey: 'sizeBytes', header: 'Tamaño', meta: { class: { th: 'w-28' } } },
    { accessorKey: 'uploaderName', header: 'Subido por' },
    { accessorKey: 'createdAt', header: 'Fecha' },
    { id: 'actions', header: '', meta: { class: { th: 'w-28', td: 'text-right' } } },
];

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

const { target: fileToDelete, open: deleteOpen, processing: deleting, ask: askToDelete, run: runDelete, clear: clearDelete } = useConfirmAction();

function confirmDelete() {
    runDelete((options) => router.delete(route('files.destroy', fileToDelete.value.id), options));
}
</script>

<template>
    <Head title="Archivos" />

    <DashboardLayout active="module:files:files.index">
        <PageHeader
            eyebrow="Documentos"
            title="Archivos"
            description="Repositorio privado de la institución. Solo el personal y los docentes pueden ver y descargar estos archivos."
            icon="i-lucide-folder-open"
        />

        <div class="grid items-start gap-6" :class="canUpload && 'xl:grid-cols-[minmax(0,1fr)_22rem]'">
            <UCard class="shadow-card" :ui="{ body: 'p-0 sm:p-0', footer: 'p-0 sm:px-0' }">
                <template #header>
                    <div class="space-y-4">
                        <PanelHeader kicker="Repositorio" :title="total === 1 ? '1 archivo' : `${total} archivos`" />
                        <DataToolbar
                            v-if="items.length > 0"
                            v-model:search="search"
                            v-model:status="kind"
                            placeholder="Buscar en esta página"
                            :statuses="statuses"
                        />
                    </div>
                </template>

                <EmptyState
                    v-if="items.length === 0"
                    icon="i-lucide-folder-open"
                    title="Todavía no hay archivos"
                    :description="canUpload ? 'Sube el primero desde el panel de subida.' : 'Cuando se suba un archivo, aparecerá aquí.'"
                />
                <EmptyState
                    v-else-if="filtered.length === 0"
                    icon="i-lucide-search-x"
                    title="Sin resultados"
                    description="Ningún archivo de esta página coincide con la búsqueda o el filtro."
                    :actions="isFiltered ? [{ label: 'Quitar filtros', color: 'neutral', variant: 'outline', icon: 'i-lucide-rotate-ccw', onClick: resetFilters }] : []"
                />

                <template v-else>
                    <UTable :data="filtered" :columns="columns" :ui="tableUi" class="hidden md:block">
                        <template #name-cell="{ row }">
                            <div class="flex min-w-0 max-w-md items-center gap-3">
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                                    <UIcon :name="KINDS[kindOf(row.original)].icon" class="size-[18px]" />
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-highlighted" :title="row.original.name">{{ row.original.name }}</p>
                                    <p class="text-xs text-muted">{{ KINDS[kindOf(row.original)].label }}</p>
                                </div>
                            </div>
                        </template>
                        <template #sizeBytes-cell="{ row }"><span class="tabular-nums">{{ formatSize(row.original.sizeBytes) }}</span></template>
                        <template #uploaderName-cell="{ row }"><span class="block max-w-48 truncate" :title="row.original.uploaderName">{{ row.original.uploaderName }}</span></template>
                        <template #createdAt-cell="{ row }">
                            <time :datetime="row.original.createdAt" class="text-muted">{{ formatDate(row.original.createdAt) }}</time>
                        </template>
                        <template #actions-cell="{ row }">
                            <div class="flex justify-end gap-1">
                                <UTooltip text="Descargar">
                                    <UButton
                                        :to="route('files.download', row.original.id)"
                                        external
                                        download
                                        color="neutral"
                                        variant="ghost"
                                        icon="i-lucide-download"
                                        :aria-label="`Descargar ${row.original.name}`"
                                    />
                                </UTooltip>
                                <UTooltip v-if="row.original.canDelete" text="Eliminar">
                                    <UButton
                                        color="error"
                                        variant="ghost"
                                        icon="i-lucide-trash-2"
                                        :aria-label="`Eliminar ${row.original.name}`"
                                        @click="askToDelete(row.original)"
                                    />
                                </UTooltip>
                            </div>
                        </template>
                    </UTable>

                    <ul class="divide-y divide-default md:hidden">
                        <li v-for="file in filtered" :key="file.id" class="flex items-center gap-3 px-4 py-3.5">
                            <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
                                <UIcon :name="KINDS[kindOf(file)].icon" class="size-[18px]" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-highlighted" :title="file.name">{{ file.name }}</p>
                                <p class="truncate text-xs text-muted">
                                    {{ formatSize(file.sizeBytes) }} · {{ file.uploaderName }} · {{ formatDate(file.createdAt) }}
                                </p>
                            </div>
                            <UButton
                                :to="route('files.download', file.id)"
                                external
                                download
                                color="neutral"
                                variant="ghost"
                                icon="i-lucide-download"
                                :aria-label="`Descargar ${file.name}`"
                            />
                            <UButton
                                v-if="file.canDelete"
                                color="error"
                                variant="ghost"
                                icon="i-lucide-trash-2"
                                :aria-label="`Eliminar ${file.name}`"
                                @click="askToDelete(file)"
                            />
                        </li>
                    </ul>
                </template>

                <template v-if="files.last_page > 1" #footer>
                    <PaginationBar :paginator="files" noun="archivos" />
                </template>
            </UCard>

            <UCard v-if="canUpload" class="shadow-card xl:sticky xl:top-4">
                <template #header>
                    <PanelHeader kicker="Subida" title="Subir archivo" />
                </template>
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
                            class="min-h-40 w-full"
                        />
                    </UFormField>

                    <div v-if="form.file" class="flex items-center gap-3 rounded-md bg-elevated px-3 py-2">
                        <UIcon name="i-lucide-file" class="size-5 shrink-0 text-primary" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-highlighted" :title="form.file.name">{{ form.file.name }}</p>
                            <p class="text-xs text-muted">{{ formatSize(form.file.size) }}</p>
                        </div>
                        <UButton
                            color="neutral"
                            variant="ghost"
                            size="sm"
                            icon="i-lucide-x"
                            aria-label="Quitar archivo seleccionado"
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

                    <p class="text-xs text-muted">
                        <template v-if="form.processing && uploadProgress !== null">Subiendo… {{ uploadProgress }} %</template>
                        <template v-else>El archivo quedará disponible para todo el personal y los docentes de la institución.</template>
                    </p>

                    <UButton
                        type="submit"
                        size="lg"
                        icon="i-lucide-upload"
                        block
                        :loading="form.processing"
                        :disabled="!form.file || clientError !== null"
                    >
                        Subir archivo
                    </UButton>
                </form>
            </UCard>
        </div>

        <ConfirmModal
            v-model:open="deleteOpen"
            title="¿Eliminar archivo?"
            :description="fileToDelete ? `Se eliminará «${fileToDelete.name}» de forma permanente. Esta acción no se puede deshacer.` : ''"
            :loading="deleting"
            @confirm="confirmDelete"
            @after:leave="clearDelete"
        />
    </DashboardLayout>
</template>
