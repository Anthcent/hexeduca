<script setup>
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { RefreshCcw, CheckCircle2 } from 'lucide-vue-next';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import UiButton from '@/Components/UiButton.vue';
import UiBadge from '@/Components/UiBadge.vue';
import UiSwitch from '@/Components/UiSwitch.vue';

const props = defineProps({
    modules: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    entitlements: { type: Object, default: () => ({}) },
});

const page = usePage();
const flash = computed(() => page.props.flash ?? {});
const syncing = ref(false);

const selectedSchoolId = ref(props.schools[0]?.id ?? null);
const selectedSchool = computed(() => props.schools.find((school) => school.id === selectedSchoolId.value) ?? null);

const optionalModules = computed(() => props.modules.filter((module) => !module.core));

function entitledKeys(schoolId) {
    return props.entitlements[schoolId] ?? [];
}

function isEntitled(module) {
    if (!selectedSchool.value) return false;
    return entitledKeys(selectedSchool.value.id).includes(module.key);
}

function syncModules() {
    syncing.value = true;
    router.post(route('admin.modules.sync'), {}, {
        preserveScroll: true,
        onFinish: () => { syncing.value = false; },
    });
}

function toggleActive(module) {
    router.post(route('admin.modules.toggle-active', module.key), {}, { preserveScroll: true });
}

function toggleEntitlement(module) {
    if (!selectedSchool.value) return;
    router.post(route('admin.modules.toggle-entitlement', [module.key, selectedSchool.value.id]), {}, { preserveScroll: true });
}

function maturityTone(maturity) {
    return maturity === 'mature' ? 'success' : 'warning';
}
</script>

<template>
    <DashboardLayout active="modulos">
        <div class="mb-5 flex items-start justify-between gap-4 sm:items-center">
            <div>
                <p class="muted mb-1 text-xs font-bold uppercase tracking-[.14em]">Plataforma</p>
                <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Módulos</h1>
                <p class="muted mt-1 max-w-[60ch] text-sm">
                    Activación global de módulos y acceso por institución.
                </p>
            </div>
            <UiButton variant="secondary" :loading="syncing" @click="syncModules">
                <template #icon><RefreshCcw class="size-4" /></template>Sincronizar manifiestos
            </UiButton>
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

        <div class="section-card mb-6">
            <table class="w-full text-left">
                <thead class="bg-[rgb(var(--surface-muted))] text-xs uppercase tracking-wider text-[rgb(var(--muted))]">
                    <tr>
                        <th class="px-5 py-3">Módulo</th>
                        <th class="px-5 py-3">Madurez</th>
                        <th class="px-5 py-3">Dependencias</th>
                        <th class="px-5 py-3">Estado</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="modules.length === 0"><td colspan="5" class="muted px-5 py-8 text-center text-sm">Todavía no hay módulos registrados. Sincronizá los manifiestos.</td></tr>
                    <tr v-for="module in modules" :key="module.key" class="group transition hover:bg-brand-50/40 dark:hover:bg-brand-950/20">
                        <td class="px-5 py-4 text-sm font-bold">
                            {{ module.name }}
                            <span class="muted block font-mono text-xs font-normal">{{ module.key }}</span>
                        </td>
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-1.5">
                                <UiBadge v-if="module.core" tone="brand">core</UiBadge>
                                <UiBadge :tone="maturityTone(module.maturity)">{{ module.maturity }}</UiBadge>
                            </div>
                        </td>
                        <td class="muted px-5 py-4 text-xs">
                            {{ module.dependencies.length ? module.dependencies.join(', ') : '—' }}
                        </td>
                        <td class="px-5 py-4">
                            <UiBadge :tone="module.active ? 'success' : 'neutral'" dot>{{ module.active ? 'Activo' : 'Inactivo' }}</UiBadge>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <UiSwitch
                                :model-value="module.active"
                                :disabled="module.core"
                                :title="module.core ? 'Los módulos core no se pueden desactivar' : undefined"
                                @update:model-value="toggleActive(module)"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section-card">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="font-display text-lg font-extrabold">Acceso por institución</h2>
                    <p class="muted text-sm">Habilitá o revocá módulos opcionales para una institución específica.</p>
                </div>
                <select v-model="selectedSchoolId" class="control w-auto">
                    <option v-for="school in schools" :key="school.id" :value="school.id">{{ school.name }}</option>
                </select>
            </div>

            <table v-if="selectedSchool" class="w-full text-left">
                <thead class="bg-[rgb(var(--surface-muted))] text-xs uppercase tracking-wider text-[rgb(var(--muted))]">
                    <tr><th class="px-5 py-3">Módulo</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3"></th></tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-if="optionalModules.length === 0"><td colspan="3" class="muted px-5 py-8 text-center text-sm">No hay módulos opcionales registrados.</td></tr>
                    <tr v-for="module in optionalModules" :key="module.key">
                        <td class="px-5 py-4 text-sm font-bold">{{ module.name }}</td>
                        <td class="px-5 py-4">
                            <UiBadge :tone="isEntitled(module) ? 'success' : 'neutral'" dot>{{ isEntitled(module) ? 'Habilitado' : 'Sin acceso' }}</UiBadge>
                        </td>
                        <td class="px-5 py-4 text-right">
                            <UiSwitch
                                :model-value="isEntitled(module)"
                                :disabled="!module.active"
                                :title="!module.active ? 'Activá el módulo globalmente primero' : undefined"
                                @update:model-value="toggleEntitlement(module)"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="muted px-1 py-6 text-center text-sm">No hay instituciones registradas todavía.</p>
        </div>
    </DashboardLayout>
</template>
