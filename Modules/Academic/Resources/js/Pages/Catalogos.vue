<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

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
        <div class="mb-1 flex items-start justify-between gap-5">
            <div>
                <h1 class="text-[1.75rem] font-semibold text-ink">Catálogos académicos</h1>
                <p class="mt-1 max-w-[60ch] text-sm text-ink-muted">
                    Niveles, grados y secciones son la base con la que se arman las ofertas académicas y matrículas.
                </p>
            </div>
        </div>

        <div
            v-if="flash.success || flash.error"
            class="mt-5 flex items-center gap-3 rounded-g2 px-4 py-3 text-sm"
            :class="flash.success ? 'bg-green-tint text-green' : 'bg-red-tint text-red'"
        >
            {{ flash.success || flash.error }}
        </div>

        <div class="mb-5 mt-6 flex gap-1 border-b border-outline">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                class="border-b-2 px-4 py-3 text-sm font-medium"
                :class="activeTab === tab.key ? 'border-blue text-blue' : 'border-transparent text-ink-muted hover:text-ink'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- NIVELES -->
        <div v-if="activeTab === 'niveles'">
            <div class="mb-3.5 flex justify-end">
                <button
                    class="inline-flex items-center gap-2 rounded-pill bg-blue px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-hover"
                    @click="openNivel()"
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14" /></svg>
                    Nuevo nivel
                </button>
            </div>
            <div class="overflow-hidden rounded-g2 border border-outline bg-white">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-outline">
                            <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Nombre</th>
                            <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Grados</th>
                            <th class="w-20 px-4.5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="niveles.length === 0"><td colspan="3" class="px-4.5 py-8 text-center text-ink-muted">Todavía no hay niveles académicos.</td></tr>
                        <tr v-for="row in niveles" :key="row.id" class="group border-t border-outline hover:bg-blue-light/40">
                            <td class="px-4.5 py-3">{{ row.name }}</td>
                            <td class="px-4.5 py-3">
                                <span class="rounded-pill bg-blue-light px-3 py-1 text-xs font-medium text-blue">{{ row.grados_count }} {{ row.grados_count === 1 ? 'grado' : 'grados' }}</span>
                            </td>
                            <td class="px-2 py-3">
                                <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100">
                                    <button class="rounded-full p-1.5 text-ink-muted hover:bg-black/[0.06]" @click="openNivel(row)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
                                    </button>
                                    <button class="rounded-full p-1.5 text-ink-muted hover:bg-red-tint hover:text-red" @click="destroyRow('academic.niveles.destroy', row.id, row.name)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- GRADOS -->
        <div v-if="activeTab === 'grados'">
            <div class="mb-3.5 flex justify-end">
                <button
                    class="inline-flex items-center gap-2 rounded-pill bg-blue px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-hover disabled:opacity-40"
                    :disabled="niveles.length === 0"
                    @click="openGrado()"
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14" /></svg>
                    Nuevo grado
                </button>
            </div>
            <p v-if="niveles.length === 0" class="mb-3.5 text-sm text-ink-muted">Creá un nivel académico primero para poder agregar grados.</p>
            <div class="overflow-hidden rounded-g2 border border-outline bg-white">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-outline">
                            <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Orden</th>
                            <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Nombre</th>
                            <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Nivel</th>
                            <th class="w-20 px-4.5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="grados.length === 0"><td colspan="4" class="px-4.5 py-8 text-center text-ink-muted">Todavía no hay grados.</td></tr>
                        <tr v-for="row in grados" :key="row.id" class="group border-t border-outline hover:bg-blue-light/40">
                            <td class="px-4.5 py-3 tabular-nums">{{ row.order }}</td>
                            <td class="px-4.5 py-3">{{ row.name }}</td>
                            <td class="px-4.5 py-3">
                                <span class="rounded-pill bg-blue-light px-3 py-1 text-xs font-medium text-blue">{{ row.nivel_academico?.name }}</span>
                            </td>
                            <td class="px-2 py-3">
                                <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100">
                                    <button class="rounded-full p-1.5 text-ink-muted hover:bg-black/[0.06]" @click="openGrado(row)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
                                    </button>
                                    <button class="rounded-full p-1.5 text-ink-muted hover:bg-red-tint hover:text-red" @click="destroyRow('academic.grados.destroy', row.id, row.name)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECCIONES -->
        <div v-if="activeTab === 'secciones'">
            <div class="mb-3.5 flex justify-end">
                <button
                    class="inline-flex items-center gap-2 rounded-pill bg-blue px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-hover"
                    @click="openSeccion()"
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14" /></svg>
                    Nueva sección
                </button>
            </div>
            <div class="overflow-hidden rounded-g2 border border-outline bg-white">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-outline">
                            <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Nombre</th>
                            <th class="w-20 px-4.5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="secciones.length === 0"><td colspan="2" class="px-4.5 py-8 text-center text-ink-muted">Todavía no hay secciones.</td></tr>
                        <tr v-for="row in secciones" :key="row.id" class="group border-t border-outline hover:bg-blue-light/40">
                            <td class="px-4.5 py-3">{{ row.name }}</td>
                            <td class="px-2 py-3">
                                <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100">
                                    <button class="rounded-full p-1.5 text-ink-muted hover:bg-black/[0.06]" @click="openSeccion(row)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
                                    </button>
                                    <button class="rounded-full p-1.5 text-ink-muted hover:bg-red-tint hover:text-red" @click="destroyRow('academic.secciones.destroy', row.id, row.name)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PERIODOS -->
        <div v-if="activeTab === 'periodos'">
            <div class="mb-3.5 flex justify-end">
                <button
                    class="inline-flex items-center gap-2 rounded-pill bg-blue px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-hover"
                    @click="openPeriodo()"
                >
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14" /></svg>
                    Nuevo período
                </button>
            </div>
            <div class="overflow-hidden rounded-g2 border border-outline bg-white">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-outline">
                            <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Nombre</th>
                            <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Inicio</th>
                            <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Fin</th>
                            <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Estado</th>
                            <th class="w-28 px-4.5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="periodos.length === 0"><td colspan="5" class="px-4.5 py-8 text-center text-ink-muted">Todavía no hay períodos académicos.</td></tr>
                        <tr v-for="row in periodos" :key="row.id" class="group border-t border-outline hover:bg-blue-light/40">
                            <td class="px-4.5 py-3">{{ row.name }}</td>
                            <td class="px-4.5 py-3">{{ row.starts_on }}</td>
                            <td class="px-4.5 py-3">{{ row.ends_on }}</td>
                            <td class="px-4.5 py-3">
                                <button
                                    v-if="!row.is_active"
                                    class="rounded-pill bg-outline px-3 py-1 text-xs font-medium text-ink-muted hover:bg-blue-light hover:text-blue"
                                    @click="activatePeriodo(row.id)"
                                >
                                    Activar
                                </button>
                                <span v-else class="rounded-pill bg-green-tint px-3 py-1 text-xs font-medium text-green">Activo</span>
                            </td>
                            <td class="px-2 py-3">
                                <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100">
                                    <button class="rounded-full p-1.5 text-ink-muted hover:bg-black/[0.06]" @click="openPeriodo(row)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
                                    </button>
                                    <button class="rounded-full p-1.5 text-ink-muted hover:bg-red-tint hover:text-red" @click="destroyRow('academic.periodos.destroy', row.id, row.name)">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18" /><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Dialog: Nivel -->
        <div v-if="dialog === 'nivel'" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-5" @click.self="closeDialog">
            <form class="w-full max-w-[420px] rounded-g2 bg-white shadow-e4" @submit.prevent="submitNivel">
                <div class="px-6 pb-1 pt-5">
                    <h2 class="text-xl font-normal text-ink">{{ editing ? 'Editar nivel' : 'Nuevo nivel académico' }}</h2>
                </div>
                <div class="px-6 py-4">
                    <div class="relative">
                        <input id="nivel-name" v-model="nivelForm.name" placeholder=" " class="peer w-full rounded border bg-white px-3.5 pb-2 pt-4 text-sm text-ink outline-none focus:border-2 focus:border-blue focus:px-[13px] focus:pb-[7px] focus:pt-[15px]" :class="nivelForm.errors.name ? 'border-red' : 'border-outline-strong'" />
                        <label for="nivel-name" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 bg-white px-1 text-sm text-ink-muted transition-all peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:text-xs peer-focus:font-medium peer-focus:text-blue peer-[&:not(:placeholder-shown)]:top-0 peer-[&:not(:placeholder-shown)]:-translate-y-1/2 peer-[&:not(:placeholder-shown)]:text-xs peer-[&:not(:placeholder-shown)]:font-medium">Nombre</label>
                        <p v-if="nivelForm.errors.name" class="mt-1.5 text-xs text-red">{{ nivelForm.errors.name }}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-4 pb-4">
                    <button type="button" class="rounded-pill px-4 py-2.5 text-sm font-medium text-ink-muted hover:bg-black/[0.06]" @click="closeDialog">Cancelar</button>
                    <button type="submit" :disabled="nivelForm.processing" class="rounded-pill bg-blue px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-hover disabled:opacity-40">Guardar</button>
                </div>
            </form>
        </div>

        <!-- Dialog: Grado -->
        <div v-if="dialog === 'grado'" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-5" @click.self="closeDialog">
            <form class="w-full max-w-[420px] rounded-g2 bg-white shadow-e4" @submit.prevent="submitGrado">
                <div class="px-6 pb-1 pt-5">
                    <h2 class="text-xl font-normal text-ink">{{ editing ? 'Editar grado' : 'Nuevo grado' }}</h2>
                </div>
                <div class="flex flex-col gap-5 px-6 py-4">
                    <div class="relative">
                        <input id="grado-name" v-model="gradoForm.name" placeholder=" " class="peer w-full rounded border bg-white px-3.5 pb-2 pt-4 text-sm text-ink outline-none focus:border-2 focus:border-blue focus:px-[13px] focus:pb-[7px] focus:pt-[15px]" :class="gradoForm.errors.name ? 'border-red' : 'border-outline-strong'" />
                        <label for="grado-name" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 bg-white px-1 text-sm text-ink-muted transition-all peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:text-xs peer-focus:font-medium peer-focus:text-blue peer-[&:not(:placeholder-shown)]:top-0 peer-[&:not(:placeholder-shown)]:-translate-y-1/2 peer-[&:not(:placeholder-shown)]:text-xs peer-[&:not(:placeholder-shown)]:font-medium">Nombre (ej: 3ro)</label>
                        <p v-if="gradoForm.errors.name" class="mt-1.5 text-xs text-red">{{ gradoForm.errors.name }}</p>
                    </div>
                    <div>
                        <label for="grado-nivel" class="mb-1.5 block text-xs font-medium text-ink-muted">Nivel académico</label>
                        <select id="grado-nivel" v-model="gradoForm.nivel_academico_id" class="w-full rounded border bg-white px-3.5 py-2.5 text-sm text-ink outline-none focus:border-2 focus:border-blue" :class="gradoForm.errors.nivel_academico_id ? 'border-red' : 'border-outline-strong'">
                            <option v-for="nivel in niveles" :key="nivel.id" :value="nivel.id">{{ nivel.name }}</option>
                        </select>
                        <p v-if="gradoForm.errors.nivel_academico_id" class="mt-1.5 text-xs text-red">{{ gradoForm.errors.nivel_academico_id }}</p>
                    </div>
                    <div class="relative">
                        <input id="grado-order" v-model="gradoForm.order" type="number" min="1" placeholder=" " class="peer w-full rounded border bg-white px-3.5 pb-2 pt-4 text-sm text-ink outline-none focus:border-2 focus:border-blue focus:px-[13px] focus:pb-[7px] focus:pt-[15px]" :class="gradoForm.errors.order ? 'border-red' : 'border-outline-strong'" />
                        <label for="grado-order" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 bg-white px-1 text-sm text-ink-muted transition-all peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:text-xs peer-focus:font-medium peer-focus:text-blue peer-[&:not(:placeholder-shown)]:top-0 peer-[&:not(:placeholder-shown)]:-translate-y-1/2 peer-[&:not(:placeholder-shown)]:text-xs peer-[&:not(:placeholder-shown)]:font-medium">Orden</label>
                        <p v-if="gradoForm.errors.order" class="mt-1.5 text-xs text-red">{{ gradoForm.errors.order }}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-4 pb-4">
                    <button type="button" class="rounded-pill px-4 py-2.5 text-sm font-medium text-ink-muted hover:bg-black/[0.06]" @click="closeDialog">Cancelar</button>
                    <button type="submit" :disabled="gradoForm.processing" class="rounded-pill bg-blue px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-hover disabled:opacity-40">Guardar</button>
                </div>
            </form>
        </div>

        <!-- Dialog: Sección -->
        <div v-if="dialog === 'seccion'" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-5" @click.self="closeDialog">
            <form class="w-full max-w-[420px] rounded-g2 bg-white shadow-e4" @submit.prevent="submitSeccion">
                <div class="px-6 pb-1 pt-5">
                    <h2 class="text-xl font-normal text-ink">{{ editing ? 'Editar sección' : 'Nueva sección' }}</h2>
                </div>
                <div class="px-6 py-4">
                    <div class="relative">
                        <input id="seccion-name" v-model="seccionForm.name" placeholder=" " class="peer w-full rounded border bg-white px-3.5 pb-2 pt-4 text-sm text-ink outline-none focus:border-2 focus:border-blue focus:px-[13px] focus:pb-[7px] focus:pt-[15px]" :class="seccionForm.errors.name ? 'border-red' : 'border-outline-strong'" />
                        <label for="seccion-name" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 bg-white px-1 text-sm text-ink-muted transition-all peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:text-xs peer-focus:font-medium peer-focus:text-blue peer-[&:not(:placeholder-shown)]:top-0 peer-[&:not(:placeholder-shown)]:-translate-y-1/2 peer-[&:not(:placeholder-shown)]:text-xs peer-[&:not(:placeholder-shown)]:font-medium">Nombre (ej: C)</label>
                        <p v-if="seccionForm.errors.name" class="mt-1.5 text-xs text-red">{{ seccionForm.errors.name }}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-4 pb-4">
                    <button type="button" class="rounded-pill px-4 py-2.5 text-sm font-medium text-ink-muted hover:bg-black/[0.06]" @click="closeDialog">Cancelar</button>
                    <button type="submit" :disabled="seccionForm.processing" class="rounded-pill bg-blue px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-hover disabled:opacity-40">Guardar</button>
                </div>
            </form>
        </div>

        <!-- Dialog: Período -->
        <div v-if="dialog === 'periodo'" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-5" @click.self="closeDialog">
            <form class="w-full max-w-[420px] rounded-g2 bg-white shadow-e4" @submit.prevent="submitPeriodo">
                <div class="px-6 pb-1 pt-5">
                    <h2 class="text-xl font-normal text-ink">{{ editing ? 'Editar período' : 'Nuevo período académico' }}</h2>
                </div>
                <div class="flex flex-col gap-5 px-6 py-4">
                    <div class="relative">
                        <input id="periodo-name" v-model="periodoForm.name" placeholder=" " class="peer w-full rounded border bg-white px-3.5 pb-2 pt-4 text-sm text-ink outline-none focus:border-2 focus:border-blue focus:px-[13px] focus:pb-[7px] focus:pt-[15px]" :class="periodoForm.errors.name ? 'border-red' : 'border-outline-strong'" />
                        <label for="periodo-name" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 bg-white px-1 text-sm text-ink-muted transition-all peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:text-xs peer-focus:font-medium peer-focus:text-blue peer-[&:not(:placeholder-shown)]:top-0 peer-[&:not(:placeholder-shown)]:-translate-y-1/2 peer-[&:not(:placeholder-shown)]:text-xs peer-[&:not(:placeholder-shown)]:font-medium">Nombre (ej: 2026-2027)</label>
                        <p v-if="periodoForm.errors.name" class="mt-1.5 text-xs text-red">{{ periodoForm.errors.name }}</p>
                    </div>
                    <div>
                        <label for="periodo-start" class="mb-1.5 block text-xs font-medium text-ink-muted">Inicio</label>
                        <input id="periodo-start" v-model="periodoForm.starts_on" type="date" class="w-full rounded border bg-white px-3.5 py-2.5 text-sm text-ink outline-none focus:border-2 focus:border-blue" :class="periodoForm.errors.starts_on ? 'border-red' : 'border-outline-strong'" />
                        <p v-if="periodoForm.errors.starts_on" class="mt-1.5 text-xs text-red">{{ periodoForm.errors.starts_on }}</p>
                    </div>
                    <div>
                        <label for="periodo-end" class="mb-1.5 block text-xs font-medium text-ink-muted">Fin</label>
                        <input id="periodo-end" v-model="periodoForm.ends_on" type="date" class="w-full rounded border bg-white px-3.5 py-2.5 text-sm text-ink outline-none focus:border-2 focus:border-blue" :class="periodoForm.errors.ends_on ? 'border-red' : 'border-outline-strong'" />
                        <p v-if="periodoForm.errors.ends_on" class="mt-1.5 text-xs text-red">{{ periodoForm.errors.ends_on }}</p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-4 pb-4">
                    <button type="button" class="rounded-pill px-4 py-2.5 text-sm font-medium text-ink-muted hover:bg-black/[0.06]" @click="closeDialog">Cancelar</button>
                    <button type="submit" :disabled="periodoForm.processing" class="rounded-pill bg-blue px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-hover disabled:opacity-40">Guardar</button>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
