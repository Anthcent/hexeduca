<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
import PanelHeader from '@/Components/PanelHeader.vue';
import ResponsiveRoster from '@/Components/school/ResponsiveRoster.vue';
import TrackingTimeline from '@/Components/school/TrackingTimeline.vue';
import { useShellNavigation } from '@/Layouts/navigation';

// Datos de muestra: esta pantalla es un placeholder inicial, se reemplaza
// por datos reales cuando se construya el módulo correspondiente.
const summary = { label: 'Estudiantes matriculados', value: '248', meta: '+6 este período' };

const cards = [
    { label: 'Asistencia de hoy', value: '92,8%', meta: '18 ausencias', icon: 'i-lucide-user-check', tone: 'bg-info/10 text-info' },
    { label: 'Notas pendientes', value: '12', meta: '2 ofertas académicas', icon: 'i-lucide-clipboard-check', tone: 'bg-warning/10 text-warning' },
    { label: 'Matrículas pendientes', value: '5', meta: 'Requieren revisión', icon: 'i-lucide-file-check-2', tone: 'bg-secondary/10 text-secondary' },
];

const pending = [
    { title: 'Revisar matrículas pendientes', meta: '5 solicitudes', icon: 'i-lucide-file-check-2' },
    { title: 'Confirmar período activo', meta: '2026-2027', icon: 'i-lucide-clipboard-check' },
    { title: 'Actualizar catálogo de secciones', meta: 'Base académica', icon: 'i-lucide-users' },
];

const students = [
    { initials: 'AT', name: 'Ana Torres', id: '3° Grado B', attendance: 96, average: '18,2', status: 'Al día' },
    { initials: 'LF', name: 'Luis Fernández', id: '1° Grado A', attendance: 88, average: '15,8', status: 'Pendiente' },
    { initials: 'MG', name: 'María Gómez', id: '5° Grado C', attendance: 94, average: '17,5', status: 'Al día' },
    { initials: 'DR', name: 'Diego Ramírez', id: '2° Grado A', attendance: 91, average: '16,4', status: 'Pendiente' },
];

const trackingItems = [
    { title: 'Matrícula registrada', description: 'Ana Torres — 3° Grado B', time: '08:42', meta: '18 sep 2026', done: true },
    { title: 'Documentos validados', description: 'Se verificaron los documentos requeridos.', time: '09:15', meta: 'Staff Admin', done: true },
    { title: 'Confirmación de cupo', description: 'Sección con disponibilidad confirmada.', time: 'En curso', meta: 'Responsable: Demo Staff', current: true },
];

const attendance = [76, 88, 65, 94, 82];
const weekdays = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie'];

const page = usePage();
const { quickActions } = useShellNavigation(computed(() => 'resumen'));

const firstName = computed(() => page.props.auth?.user?.name?.split(' ')[0] ?? '');
const greeting = computed(() => {
    const hour = new Date().getHours();
    const salute = hour < 12 ? 'Buenos días' : hour < 19 ? 'Buenas tardes' : 'Buenas noches';

    return firstName.value ? `${salute}, ${firstName.value}` : salute;
});
const primaryAction = computed(() => quickActions.value[0] ?? null);
// Only offered when the user may enroll (the quick actions are already filtered).
const enrollPath = route().has('academic.matriculas.create') ? route('academic.matriculas.create', undefined, false) : null;
const enrollAction = computed(() => quickActions.value.find((action) => action.to === enrollPath) ?? null);
</script>

<template>
    <Head title="Inicio" />

    <DashboardLayout active="resumen">
        <PageHeader
            eyebrow="Panel principal"
            :title="greeting"
            description="Este es el estado actual de la institución y las tareas que requieren atención. Los datos son de muestra."
        >
            <template v-if="primaryAction" #actions>
                <UButton :to="primaryAction.to" :icon="primaryAction.icon" size="lg">{{ primaryAction.label }}</UButton>
            </template>
        </PageHeader>

        <section class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-[1.5fr_1fr_1fr_1fr]">
                <article class="relative overflow-hidden rounded-lg bg-linear-to-br from-brand-950 via-brand-900 to-brand-800 p-6 text-white shadow-card sm:col-span-2 xl:col-span-1">
                    <span class="pointer-events-none absolute -right-10 -top-14 size-48 rounded-full border-[24px] border-white/5" aria-hidden="true" />
                    <p class="relative text-sm font-semibold text-brand-100">Resumen general</p>
                    <p class="relative mt-4 text-5xl font-bold tracking-tight">{{ summary.value }}</p>
                    <p class="relative mt-1 text-base font-medium">{{ summary.label }}</p>
                    <p class="relative mt-4 text-sm text-brand-200">{{ summary.meta }}</p>
                </article>

                <UCard v-for="card in cards" :key="card.label" class="shadow-card" :ui="{ body: 'p-5 sm:p-5' }">
                    <div class="flex items-start gap-4">
                        <span class="grid size-11 shrink-0 place-items-center rounded-lg" :class="card.tone">
                            <UIcon :name="card.icon" class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-muted">{{ card.label }}</p>
                            <p class="mt-1 text-3xl font-bold tracking-tight text-highlighted">{{ card.value }}</p>
                            <p class="mt-1 text-sm text-muted">{{ card.meta }}</p>
                        </div>
                    </div>
                </UCard>
            </div>

            <UAlert
                color="info"
                variant="subtle"
                icon="i-lucide-info"
                title="5 matrículas requieren revisión"
                description="Faltan documentos o la confirmación de cupo."
            />

            <div class="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
                <ResponsiveRoster :students="students" :add-to="enrollAction?.to ?? null" />

                <div class="space-y-6">
                    <UCard v-if="quickActions.length > 0" class="shadow-card">
                        <template #header>
                            <PanelHeader kicker="Accesos rápidos" title="Acciones frecuentes" />
                        </template>
                        <div class="grid grid-cols-2 gap-3">
                            <Link
                                v-for="(action, index) in quickActions.slice(0, 4)"
                                :key="action.label"
                                :href="action.to"
                                class="group flex flex-col gap-3 rounded-lg p-4 transition"
                                :class="index === 0
                                    ? 'bg-primary text-inverted hover:bg-primary/90'
                                    : 'bg-elevated/60 text-highlighted hover:bg-elevated'"
                            >
                                <UIcon :name="action.icon" class="size-5" />
                                <span>
                                    <span class="block text-sm font-semibold">{{ action.label }}</span>
                                    <span class="mt-0.5 block text-xs" :class="index === 0 ? 'text-inverted/80' : 'text-muted'">{{ action.description }}</span>
                                </span>
                            </Link>
                        </div>
                    </UCard>

                    <UCard class="shadow-card">
                        <template #header>
                            <PanelHeader kicker="Pendientes" title="Requieren atención">
                                <UBadge color="warning" variant="subtle">{{ pending.length }} tareas</UBadge>
                            </PanelHeader>
                        </template>
                        <ul class="-my-2 divide-y divide-default">
                            <li v-for="item in pending" :key="item.title" class="flex items-center gap-3 py-3">
                                <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-elevated text-primary">
                                    <UIcon :name="item.icon" class="size-[18px]" />
                                </span>
                                <span class="min-w-0 flex-1">
                                    <strong class="block truncate text-sm font-semibold text-highlighted">{{ item.title }}</strong>
                                    <span class="mt-0.5 block text-xs text-muted">{{ item.meta }}</span>
                                </span>
                            </li>
                        </ul>
                    </UCard>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1.45fr_1fr]">
                <UCard class="shadow-card">
                    <template #header>
                        <PanelHeader kicker="Asistencia" title="Actividad académica">
                            <UBadge color="neutral" variant="outline">Esta semana</UBadge>
                        </PanelHeader>
                    </template>
                    <div class="flex h-56 items-end gap-2 pt-6 sm:gap-4">
                        <div v-for="(bar, index) in attendance" :key="index" class="flex h-full flex-1 flex-col justify-end gap-2">
                            <div class="relative flex-1 rounded-lg bg-elevated">
                                <div class="absolute inset-x-0 bottom-0 origin-bottom animate-progress-in rounded-lg bg-linear-to-t from-brand-800 to-brand-400" :style="{ height: bar + '%', animationDelay: index * .08 + 's' }">
                                    <span class="absolute -top-6 left-1/2 -translate-x-1/2 text-xs font-bold text-highlighted">{{ bar }}%</span>
                                </div>
                            </div>
                            <span class="text-center text-xs font-semibold text-muted">{{ weekdays[index] }}</span>
                        </div>
                    </div>
                </UCard>

                <TrackingTimeline title="Matrícula ANA-0248" :items="trackingItems">
                    <template #action>
                        <UBadge color="primary" variant="subtle" class="rounded-full">En revisión</UBadge>
                    </template>
                </TrackingTimeline>
            </div>
        </section>
    </DashboardLayout>
</template>
