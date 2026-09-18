<script setup>
import { ref, computed } from 'vue';
import { useForm, usePage, router } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

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
        <div class="mb-1 flex items-start justify-between gap-5">
            <div>
                <h1 class="text-[1.75rem] font-semibold text-ink">Instituciones</h1>
                <p class="mt-1 max-w-[60ch] text-sm text-ink-muted">
                    Cada institución es un tenant independiente, identificado por su propio subdominio (ej. <code class="rounded bg-blue-light px-1.5 py-0.5 text-blue">demo.app.com</code>).
                </p>
            </div>
            <button
                class="inline-flex flex-shrink-0 items-center gap-2 rounded-pill bg-blue px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-hover"
                @click="openCreate"
            >
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14" /></svg>
                Nueva institución
            </button>
        </div>

        <div
            v-if="flash.success || flash.error"
            class="mt-5 rounded-g2 px-4 py-3 text-sm"
            :class="flash.success ? 'bg-green-tint text-green' : 'bg-red-tint text-red'"
        >
            {{ flash.success || flash.error }}
        </div>

        <div class="mt-6 overflow-hidden rounded-g2 border border-outline bg-white">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-outline">
                        <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Nombre</th>
                        <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Subdominio</th>
                        <th class="px-4.5 py-3 text-left text-xs font-medium text-ink">Estado</th>
                        <th class="w-28 px-4.5 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="schools.length === 0"><td colspan="4" class="px-4.5 py-8 text-center text-ink-muted">Todavía no hay instituciones registradas.</td></tr>
                    <tr v-for="school in schools" :key="school.id" class="group border-t border-outline hover:bg-blue-light/40">
                        <td class="px-4.5 py-3">{{ school.name }}</td>
                        <td class="px-4.5 py-3 font-mono text-xs text-ink-muted">{{ school.subdomain }}</td>
                        <td class="px-4.5 py-3">
                            <span v-if="school.is_active" class="rounded-pill bg-green-tint px-3 py-1 text-xs font-medium text-green">Activa</span>
                            <span v-else class="rounded-pill bg-outline px-3 py-1 text-xs font-medium text-ink-muted">Inactiva</span>
                        </td>
                        <td class="px-2 py-3">
                            <div class="flex justify-end gap-1 opacity-0 group-hover:opacity-100">
                                <button class="rounded-full p-1.5 text-ink-muted hover:bg-black/[0.06]" title="Editar" @click="openEdit(school)">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
                                </button>
                                <button
                                    class="rounded-full p-1.5 text-ink-muted hover:bg-black/[0.06]"
                                    :title="school.is_active ? 'Desactivar' : 'Activar'"
                                    @click="toggleActive(school)"
                                >
                                    <svg v-if="school.is_active" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M12 8v4" /><path d="M12 16h.01" /></svg>
                                    <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12l2 2 4-4" /><circle cx="12" cy="12" r="9" /></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Dialog -->
        <div v-if="dialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-5" @click.self="closeDialog">
            <form class="w-full max-w-[420px] rounded-g2 bg-white shadow-e4" @submit.prevent="submit">
                <div class="px-6 pb-1 pt-5">
                    <h2 class="text-xl font-normal text-ink">{{ editing ? 'Editar institución' : 'Nueva institución' }}</h2>
                </div>
                <div class="flex flex-col gap-5 px-6 py-4">
                    <div class="relative">
                        <input id="school-name" v-model="form.name" placeholder=" " class="peer w-full rounded border bg-white px-3.5 pb-2 pt-4 text-sm text-ink outline-none focus:border-2 focus:border-blue focus:px-[13px] focus:pb-[7px] focus:pt-[15px]" :class="form.errors.name ? 'border-red' : 'border-outline-strong'" />
                        <label for="school-name" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 bg-white px-1 text-sm text-ink-muted transition-all peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:text-xs peer-focus:font-medium peer-focus:text-blue peer-[&:not(:placeholder-shown)]:top-0 peer-[&:not(:placeholder-shown)]:-translate-y-1/2 peer-[&:not(:placeholder-shown)]:text-xs peer-[&:not(:placeholder-shown)]:font-medium">Nombre (ej: Colegio San Martín)</label>
                        <p v-if="form.errors.name" class="mt-1.5 text-xs text-red">{{ form.errors.name }}</p>
                    </div>
                    <div class="relative">
                        <input id="school-subdomain" v-model="form.subdomain" placeholder=" " class="peer w-full rounded border bg-white px-3.5 pb-2 pt-4 text-sm text-ink outline-none focus:border-2 focus:border-blue focus:px-[13px] focus:pb-[7px] focus:pt-[15px]" :class="form.errors.subdomain ? 'border-red' : 'border-outline-strong'" />
                        <label for="school-subdomain" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 bg-white px-1 text-sm text-ink-muted transition-all peer-focus:top-0 peer-focus:-translate-y-1/2 peer-focus:text-xs peer-focus:font-medium peer-focus:text-blue peer-[&:not(:placeholder-shown)]:top-0 peer-[&:not(:placeholder-shown)]:-translate-y-1/2 peer-[&:not(:placeholder-shown)]:text-xs peer-[&:not(:placeholder-shown)]:font-medium">Subdominio (ej: sanmartin)</label>
                        <p v-if="form.errors.subdomain" class="mt-1.5 text-xs text-red">{{ form.errors.subdomain }}</p>
                        <p v-else class="mt-1.5 text-xs text-ink-muted">Va a quedar accesible en <span class="font-mono">{{ form.subdomain || '...' }}.app.com</span></p>
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-4 pb-4">
                    <button type="button" class="rounded-pill px-4 py-2.5 text-sm font-medium text-ink-muted hover:bg-black/[0.06]" @click="closeDialog">Cancelar</button>
                    <button type="submit" :disabled="form.processing" class="rounded-pill bg-blue px-5 py-2.5 text-sm font-medium text-white hover:bg-blue-hover disabled:opacity-40">Guardar</button>
                </div>
            </form>
        </div>
    </DashboardLayout>
</template>
