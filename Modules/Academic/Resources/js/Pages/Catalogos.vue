<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { CheckCircle2, PencilLine, Plus, Trash2 } from 'lucide-vue-next';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiModal from '@/Components/UiModal.vue';

const props = defineProps({
    niveles: { type: Array, default: () => [] },
    grados: { type: Array, default: () => [] },
    secciones: { type: Array, default: () => [] },
    periodos: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const activeTab = ref('niveles');
const tabs = [
    { key: 'niveles', label: 'Niveles' },
    { key: 'grados', label: 'Grados' },
    { key: 'secciones', label: 'Secciones' },
    { key: 'periodos', label: 'Períodos' },
];

// ---- Dialog state: one generic dialog reused per catalog type ----
const dialog = ref(null); // 'nivel' | 'grado' | 'seccion' | 'periodo' | null
const editing = ref(null); // the row being edited, or null when creating

const nivelForm = useForm({ name: '' });
const gradoForm = useForm({ name: '', nivel_academico_id: '', order: 1 });
const seccionForm = useForm({ name: '' });
const periodoForm = useForm({ name: '', starts_on: '', ends_on: '' });

function openNivel(row = null) {
    editing.value = row;
    nivelForm.clearErrors();
    nivelForm.name = row?.name ?? '';
    dialog.value = 'nivel';
}

function openGrado(row = null) {
    editing.value = row;
    gradoForm.clearErrors();
    gradoForm.name = row?.name ?? '';
    gradoForm.nivel_academico_id = row?.nivel_academico_id ?? (props.niveles[0]?.id ?? '');
    gradoForm.order = row?.order ?? 1;
    dialog.value = 'grado';
}

function openSeccion(row = null) {
    editing.value = row;
    seccionForm.clearErrors();
    seccionForm.name = row?.name ?? '';
    dialog.value = 'seccion';
}

function openPeriodo(row = null) {
    editing.value = row;
    periodoForm.clearErrors();
    periodoForm.name = row?.name ?? '';
    periodoForm.starts_on = row?.starts_on ?? '';
    periodoForm.ends_on = row?.ends_on ?? '';
    dialog.value = 'periodo';
}

function closeDialog() {
    dialog.value = null;
    editing.value = null;
}

function submitNivel() {
    const options = { onSuccess: closeDialog, preserveScroll: true };
    if (editing.value) {
        nivelForm.put(route('academic.niveles.update', editing.value.id), options);
    } else {
        nivelForm.post(route('academic.niveles.store'), options);
    }
}

function submitGrado() {
    const options = { onSuccess: closeDialog, preserveScroll: true };
    if (editing.value) {
        gradoForm.put(route('academic.grados.update', editing.value.id), options);
    } else {
        gradoForm.post(route('academic.grados.store'), options);
    }
}

function submitSeccion() {
    const options = { onSuccess: closeDialog, preserveScroll: true };
    if (editing.value) {
        seccionForm.put(route('academic.secciones.update', editing.value.id), options);
    } else {
        seccionForm.post(route('academic.secciones.store'), options);
    }
}

function submitPeriodo() {
    const options = { onSuccess: closeDialog, preserveScroll: true };
    if (editing.value) {
        periodoForm.put(route('academic.periodos.update', editing.value.id), options);
    } else {
        periodoForm.post(route('academic.periodos.store'), options);
    }
}

function destroyRow(routeName, id, label) {
    if (! confirm(`¿Eliminar "${label}"? Esta acción no se puede deshacer.`)) return;
    router.delete(route(routeName, id), { preserveScroll: true });
}

function activatePeriodo(id) {
    router.post(route('academic.periodos.activate', id), {}, { preserveScroll: true });
}
</script>

<template>
    <DashboardLayout active="academico">
        <div class="mb-5 flex items-start justify-between gap-4 sm:items-center">
            <div>
                <p class="muted mb-1 text-xs font-bold uppercase tracking-[.14em]">Gestión escolar</p>
                <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Base académica</h1>
                <p class="muted mt-1 text-sm">Niveles, grados y secciones son la base con la que se arman las ofertas académicas y matrículas.</p>
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

        <div class="mb-5 flex gap-1 overflow-x-auto border-b">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-bold transition"
                :class="activeTab === tab.key ? 'border-brand-500 text-brand-700 dark:text-brand-300' : 'muted border-transparent hover:text-[rgb(var(--text))]'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- NIVELES -->
        <div v-if="activeTab === 'niveles'" class="section-card">
            <div class="flex items-center justify-between border-b p-4 sm:p-5">
                <div>
                    <h2 class="font-display text-lg font-extrabold">Niveles académicos</h2>
                    <p class="muted mt-1 text-sm">{{ niveles.length }} registrados</p>
                </div>
                <UiButton size="sm" @click="openNivel()"><template #icon><Plus class="size-4" /></template>Nuevo nivel</UiButton>
            </div>
            <table class="w-full text-left">
                <thead class="bg-[rgb(var(--surface-muted))] text-xs uppercase tracking-wider muted">
                    <tr><th class="px-5 py-3">Nombre</th><th class="px-5 py-3">Grados</th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="niveles.length === 0"><td colspan="3" class="muted px-5 py-8 text-center text-sm">Todavía no hay niveles académicos.</td></tr>
                    <tr v-for="row in niveles" :key="row.id" class="group transition hover:bg-brand-50/40 dark:hover:bg-brand-950/20">
                        <td class="px-5 py-4 text-sm font-bold">{{ row.name }}</td>
                        <td class="px-5 py-4"><UiBadge tone="brand">{{ row.grados_count }} {{ row.grados_count === 1 ? 'grado' : 'grados' }}</UiBadge></td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-1 opacity-0 transition group-hover:opacity-100">
                                <button class="muted rounded-lg p-2 hover:bg-[rgb(var(--surface-muted))] hover:text-[rgb(var(--text))]" @click="openNivel(row)"><PencilLine class="size-4" /></button>
                                <button class="rounded-lg p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40" @click="destroyRow('academic.niveles.destroy', row.id, row.name)"><Trash2 class="size-4" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- GRADOS -->
        <div v-if="activeTab === 'grados'" class="section-card">
            <div class="flex items-center justify-between border-b p-4 sm:p-5">
                <div>
                    <h2 class="font-display text-lg font-extrabold">Grados</h2>
                    <p class="muted mt-1 text-sm">{{ grados.length }} registrados</p>
                </div>
                <UiButton size="sm" :disabled="niveles.length === 0" @click="openGrado()"><template #icon><Plus class="size-4" /></template>Nuevo grado</UiButton>
            </div>
            <p v-if="niveles.length === 0" class="muted px-5 pt-4 text-sm">Creá un nivel académico primero para poder agregar grados.</p>
            <table class="w-full text-left">
                <thead class="bg-[rgb(var(--surface-muted))] text-xs uppercase tracking-wider muted">
                    <tr><th class="px-5 py-3">Orden</th><th class="px-5 py-3">Nombre</th><th class="px-5 py-3">Nivel</th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="grados.length === 0"><td colspan="4" class="muted px-5 py-8 text-center text-sm">Todavía no hay grados.</td></tr>
                    <tr v-for="row in grados" :key="row.id" class="group transition hover:bg-brand-50/40 dark:hover:bg-brand-950/20">
                        <td class="px-5 py-4 text-sm font-semibold tabular-nums">{{ row.order }}</td>
                        <td class="px-5 py-4 text-sm font-bold">{{ row.name }}</td>
                        <td class="px-5 py-4"><UiBadge tone="brand">{{ row.nivel_academico?.name }}</UiBadge></td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-1 opacity-0 transition group-hover:opacity-100">
                                <button class="muted rounded-lg p-2 hover:bg-[rgb(var(--surface-muted))] hover:text-[rgb(var(--text))]" @click="openGrado(row)"><PencilLine class="size-4" /></button>
                                <button class="rounded-lg p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40" @click="destroyRow('academic.grados.destroy', row.id, row.name)"><Trash2 class="size-4" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- SECCIONES -->
        <div v-if="activeTab === 'secciones'" class="section-card">
            <div class="flex items-center justify-between border-b p-4 sm:p-5">
                <div>
                    <h2 class="font-display text-lg font-extrabold">Secciones</h2>
                    <p class="muted mt-1 text-sm">{{ secciones.length }} registradas</p>
                </div>
                <UiButton size="sm" @click="openSeccion()"><template #icon><Plus class="size-4" /></template>Nueva sección</UiButton>
            </div>
            <table class="w-full text-left">
                <thead class="bg-[rgb(var(--surface-muted))] text-xs uppercase tracking-wider muted">
                    <tr><th class="px-5 py-3">Nombre</th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="secciones.length === 0"><td colspan="2" class="muted px-5 py-8 text-center text-sm">Todavía no hay secciones.</td></tr>
                    <tr v-for="row in secciones" :key="row.id" class="group transition hover:bg-brand-50/40 dark:hover:bg-brand-950/20">
                        <td class="px-5 py-4 text-sm font-bold">{{ row.name }}</td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-1 opacity-0 transition group-hover:opacity-100">
                                <button class="muted rounded-lg p-2 hover:bg-[rgb(var(--surface-muted))] hover:text-[rgb(var(--text))]" @click="openSeccion(row)"><PencilLine class="size-4" /></button>
                                <button class="rounded-lg p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40" @click="destroyRow('academic.secciones.destroy', row.id, row.name)"><Trash2 class="size-4" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- PERIODOS -->
        <div v-if="activeTab === 'periodos'" class="section-card">
            <div class="flex items-center justify-between border-b p-4 sm:p-5">
                <div>
                    <h2 class="font-display text-lg font-extrabold">Períodos académicos</h2>
                    <p class="muted mt-1 text-sm">{{ periodos.length }} registrados</p>
                </div>
                <UiButton size="sm" @click="openPeriodo()"><template #icon><Plus class="size-4" /></template>Nuevo período</UiButton>
            </div>
            <table class="w-full text-left">
                <thead class="bg-[rgb(var(--surface-muted))] text-xs uppercase tracking-wider muted">
                    <tr><th class="px-5 py-3">Nombre</th><th class="px-5 py-3">Inicio</th><th class="px-5 py-3">Fin</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="periodos.length === 0"><td colspan="5" class="muted px-5 py-8 text-center text-sm">Todavía no hay períodos académicos.</td></tr>
                    <tr v-for="row in periodos" :key="row.id" class="group transition hover:bg-brand-50/40 dark:hover:bg-brand-950/20">
                        <td class="px-5 py-4 text-sm font-bold">{{ row.name }}</td>
                        <td class="muted px-5 py-4 text-sm">{{ row.starts_on }}</td>
                        <td class="muted px-5 py-4 text-sm">{{ row.ends_on }}</td>
                        <td class="px-5 py-4">
                            <button v-if="!row.is_active" class="rounded-full border px-3 py-1 text-xs font-bold hover:border-brand-300 hover:bg-brand-50" @click="activatePeriodo(row.id)">Activar</button>
                            <UiBadge v-else tone="success" dot>Activo</UiBadge>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-1 opacity-0 transition group-hover:opacity-100">
                                <button class="muted rounded-lg p-2 hover:bg-[rgb(var(--surface-muted))] hover:text-[rgb(var(--text))]" @click="openPeriodo(row)"><PencilLine class="size-4" /></button>
                                <button class="rounded-lg p-2 text-red-500 hover:bg-red-50 dark:hover:bg-red-950/40" @click="destroyRow('academic.periodos.destroy', row.id, row.name)"><Trash2 class="size-4" /></button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Dialog: Nivel -->
        <UiModal :open="dialog === 'nivel'" :title="editing ? 'Editar nivel' : 'Nuevo nivel académico'" @close="closeDialog">
            <form :id="'form-nivel'" @submit.prevent="submitNivel">
                <label for="nivel-name" class="label">Nombre</label>
                <input id="nivel-name" v-model="nivelForm.name" class="control" :class="nivelForm.errors.name && 'border-red-400!'" />
                <p v-if="nivelForm.errors.name" class="mt-1.5 text-xs font-semibold text-red-600">{{ nivelForm.errors.name }}</p>
            </form>
            <template #footer>
                <UiButton variant="ghost" @click="closeDialog">Cancelar</UiButton>
                <UiButton type="submit" form="form-nivel" :loading="nivelForm.processing">Guardar</UiButton>
            </template>
        </UiModal>

        <!-- Dialog: Grado -->
        <UiModal :open="dialog === 'grado'" :title="editing ? 'Editar grado' : 'Nuevo grado'" @close="closeDialog">
            <form id="form-grado" class="space-y-4" @submit.prevent="submitGrado">
                <div>
                    <label for="grado-name" class="label">Nombre (ej: 3ro)</label>
                    <input id="grado-name" v-model="gradoForm.name" class="control" :class="gradoForm.errors.name && 'border-red-400!'" />
                    <p v-if="gradoForm.errors.name" class="mt-1.5 text-xs font-semibold text-red-600">{{ gradoForm.errors.name }}</p>
                </div>
                <div>
                    <label for="grado-nivel" class="label">Nivel académico</label>
                    <select id="grado-nivel" v-model="gradoForm.nivel_academico_id" class="control" :class="gradoForm.errors.nivel_academico_id && 'border-red-400!'">
                        <option v-for="nivel in niveles" :key="nivel.id" :value="nivel.id">{{ nivel.name }}</option>
                    </select>
                    <p v-if="gradoForm.errors.nivel_academico_id" class="mt-1.5 text-xs font-semibold text-red-600">{{ gradoForm.errors.nivel_academico_id }}</p>
                </div>
                <div>
                    <label for="grado-order" class="label">Orden</label>
                    <input id="grado-order" v-model="gradoForm.order" type="number" min="1" class="control" :class="gradoForm.errors.order && 'border-red-400!'" />
                    <p v-if="gradoForm.errors.order" class="mt-1.5 text-xs font-semibold text-red-600">{{ gradoForm.errors.order }}</p>
                </div>
            </form>
            <template #footer>
                <UiButton variant="ghost" @click="closeDialog">Cancelar</UiButton>
                <UiButton type="submit" form="form-grado" :loading="gradoForm.processing">Guardar</UiButton>
            </template>
        </UiModal>

        <!-- Dialog: Sección -->
        <UiModal :open="dialog === 'seccion'" :title="editing ? 'Editar sección' : 'Nueva sección'" @close="closeDialog">
            <form id="form-seccion" @submit.prevent="submitSeccion">
                <label for="seccion-name" class="label">Nombre (ej: C)</label>
                <input id="seccion-name" v-model="seccionForm.name" class="control" :class="seccionForm.errors.name && 'border-red-400!'" />
                <p v-if="seccionForm.errors.name" class="mt-1.5 text-xs font-semibold text-red-600">{{ seccionForm.errors.name }}</p>
            </form>
            <template #footer>
                <UiButton variant="ghost" @click="closeDialog">Cancelar</UiButton>
                <UiButton type="submit" form="form-seccion" :loading="seccionForm.processing">Guardar</UiButton>
            </template>
        </UiModal>

        <!-- Dialog: Período -->
        <UiModal :open="dialog === 'periodo'" :title="editing ? 'Editar período' : 'Nuevo período académico'" @close="closeDialog">
            <form id="form-periodo" class="space-y-4" @submit.prevent="submitPeriodo">
                <div>
                    <label for="periodo-name" class="label">Nombre (ej: 2026-2027)</label>
                    <input id="periodo-name" v-model="periodoForm.name" class="control" :class="periodoForm.errors.name && 'border-red-400!'" />
                    <p v-if="periodoForm.errors.name" class="mt-1.5 text-xs font-semibold text-red-600">{{ periodoForm.errors.name }}</p>
                </div>
                <div>
                    <label for="periodo-start" class="label">Inicio</label>
                    <input id="periodo-start" v-model="periodoForm.starts_on" type="date" class="control" :class="periodoForm.errors.starts_on && 'border-red-400!'" />
                    <p v-if="periodoForm.errors.starts_on" class="mt-1.5 text-xs font-semibold text-red-600">{{ periodoForm.errors.starts_on }}</p>
                </div>
                <div>
                    <label for="periodo-end" class="label">Fin</label>
                    <input id="periodo-end" v-model="periodoForm.ends_on" type="date" class="control" :class="periodoForm.errors.ends_on && 'border-red-400!'" />
                    <p v-if="periodoForm.errors.ends_on" class="mt-1.5 text-xs font-semibold text-red-600">{{ periodoForm.errors.ends_on }}</p>
                </div>
            </form>
            <template #footer>
                <UiButton variant="ghost" @click="closeDialog">Cancelar</UiButton>
                <UiButton type="submit" form="form-periodo" :loading="periodoForm.processing">Guardar</UiButton>
            </template>
        </UiModal>
    </DashboardLayout>
</template>
