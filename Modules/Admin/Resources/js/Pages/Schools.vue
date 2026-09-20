<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import { CheckCircle2, PauseCircle, PencilLine, Plus, PlayCircle } from 'lucide-vue-next';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiModal from '@/Components/UiModal.vue';

const props = defineProps({
    schools: { type: Array, default: () => [] },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});

const dialog = ref(false);
const editing = ref(null);

const form = useForm({ name: '', subdomain: '' });

function openCreate() {
    editing.value = null;
    form.clearErrors();
    form.name = '';
    form.subdomain = '';
    dialog.value = true;
}

function openEdit(school) {
    editing.value = school;
    form.clearErrors();
    form.name = school.name;
    form.subdomain = school.subdomain;
    dialog.value = true;
}

function closeDialog() {
    dialog.value = false;
    editing.value = null;
}

function submit() {
    const options = { onSuccess: closeDialog, preserveScroll: true };
    if (editing.value) {
        form.put(route('admin.schools.update', editing.value.id), options);
    } else {
        form.post(route('admin.schools.store'), options);
    }
}

function toggleActive(school) {
    const verb = school.is_active ? 'desactivar' : 'activar';
    if (! confirm(`¿Seguro que querés ${verb} "${school.name}"?`)) return;
    router.post(route('admin.schools.toggle-active', school.id), {}, { preserveScroll: true });
}
</script>

<template>
    <DashboardLayout active="instituciones">
        <div class="mb-5 flex items-start justify-between gap-4 sm:items-center">
            <div>
                <p class="muted mb-1 text-xs font-bold uppercase tracking-[.14em]">Plataforma</p>
                <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Instituciones</h1>
                <p class="muted mt-1 max-w-[60ch] text-sm">
                    Cada institución es un tenant independiente, identificado por su propio subdominio (ej. <span class="kbd">demo.app.com</span>).
                </p>
            </div>
            <UiButton @click="openCreate"><template #icon><Plus class="size-4" /></template>Nueva institución</UiButton>
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

        <div class="section-card">
            <table class="w-full text-left">
                <thead class="bg-[rgb(var(--surface-muted))] text-xs uppercase tracking-wider text-[rgb(var(--muted))]">
                    <tr><th class="px-5 py-3">Nombre</th><th class="px-5 py-3">Subdominio</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="schools.length === 0"><td colspan="4" class="muted px-5 py-8 text-center text-sm">Todavía no hay instituciones registradas.</td></tr>
                    <tr v-for="school in schools" :key="school.id" class="group transition hover:bg-brand-50/40 dark:hover:bg-brand-950/20">
                        <td class="px-5 py-4 text-sm font-bold">{{ school.name }}</td>
                        <td class="muted px-5 py-4 font-mono text-xs">{{ school.subdomain }}</td>
                        <td class="px-5 py-4">
                            <UiBadge :tone="school.is_active ? 'success' : 'neutral'" dot>{{ school.is_active ? 'Activa' : 'Inactiva' }}</UiBadge>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex justify-end gap-1 opacity-0 transition group-hover:opacity-100">
                                <button class="muted rounded-lg p-2 hover:bg-[rgb(var(--surface-muted))] hover:text-[rgb(var(--text))]" title="Editar" @click="openEdit(school)"><PencilLine class="size-4" /></button>
                                <button class="muted rounded-lg p-2 hover:bg-[rgb(var(--surface-muted))] hover:text-[rgb(var(--text))]" :title="school.is_active ? 'Desactivar' : 'Activar'" @click="toggleActive(school)">
                                    <PauseCircle v-if="school.is_active" class="size-4" />
                                    <PlayCircle v-else class="size-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <UiModal :open="dialog" :title="editing ? 'Editar institución' : 'Nueva institución'" @close="closeDialog">
            <form id="form-school" class="space-y-4" @submit.prevent="submit">
                <div>
                    <label for="school-name" class="label">Nombre (ej: Colegio San Martín)</label>
                    <input id="school-name" v-model="form.name" class="control" :class="form.errors.name && '!border-red-400'" />
                    <p v-if="form.errors.name" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label for="school-subdomain" class="label">Subdominio (ej: sanmartin)</label>
                    <input id="school-subdomain" v-model="form.subdomain" class="control" :class="form.errors.subdomain && '!border-red-400'" />
                    <p v-if="form.errors.subdomain" class="mt-1.5 text-xs font-semibold text-red-600">{{ form.errors.subdomain }}</p>
                    <p v-else class="muted mt-1.5 text-xs">Va a quedar accesible en <span class="font-mono">{{ form.subdomain || '...' }}.app.com</span></p>
                </div>
            </form>
            <template #footer>
                <UiButton variant="ghost" @click="closeDialog">Cancelar</UiButton>
                <UiButton type="submit" form="form-school" :loading="form.processing">Guardar</UiButton>
            </template>
        </UiModal>
    </DashboardLayout>
</template>
