<script setup>
import { ChevronDown, ChevronRight, ClipboardCheck, FileCheck2, UserCheck, Users } from 'lucide-vue-next';
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import UiBadge from '@/Components/UiBadge.vue';
import ResponsiveRoster from '@/Components/school/ResponsiveRoster.vue';
import TrackingTimeline from '@/Components/school/TrackingTimeline.vue';

// Datos de muestra: esta pantalla es un placeholder inicial, se reemplaza
// por datos reales cuando se construya el módulo correspondiente.
const cards = [
    { label: 'Estudiantes matriculados', value: '248', meta: '+6 este período', icon: Users, tone: 'bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-200' },
    { label: 'Asistencia de hoy', value: '92,8%', meta: '18 ausencias', icon: UserCheck, tone: 'bg-sky-50 text-sky-700 dark:bg-sky-950 dark:text-sky-200' },
    { label: 'Notas pendientes', value: '12', meta: '2 ofertas académicas', icon: ClipboardCheck, tone: 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-200' },
    { label: 'Matrículas pendientes', value: '5', meta: 'Requieren revisión', icon: FileCheck2, tone: 'bg-violet-50 text-violet-700 dark:bg-violet-950 dark:text-violet-200' },
];

const pending = [
    { title: 'Revisar matrículas pendientes', meta: '5 solicitudes', icon: FileCheck2 },
    { title: 'Confirmar período activo', meta: '2026-2027', icon: ClipboardCheck },
    { title: 'Actualizar catálogo de secciones', meta: 'Base académica', icon: Users },
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
</script>

<template>
    <DashboardLayout active="resumen">
        <div class="mb-5 flex items-start justify-between gap-4 sm:items-center">
            <div>
                <p class="muted mb-1 text-xs font-bold uppercase tracking-[.14em]">Gestión escolar</p>
                <h1 class="font-display text-2xl font-extrabold tracking-tight sm:text-3xl">Inicio</h1>
                <p class="muted mt-1 text-sm">Esta pantalla es un punto de partida — se va a reemplazar cuando construyamos el módulo real de reportes.</p>
            </div>
        </div>

        <section class="animate-float-in space-y-5">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <article v-for="card in cards" :key="card.label" class="section-card group p-5 transition hover:-translate-y-0.5 hover:shadow-lift">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="muted text-sm font-semibold">{{ card.label }}</p>
                            <p class="mt-2 font-display text-3xl font-extrabold">{{ card.value }}</p>
                            <p class="mt-2 text-xs font-bold text-brand-600 dark:text-brand-300">{{ card.meta }}</p>
                        </div>
                        <span class="grid size-11 place-items-center rounded-2xl transition group-hover:scale-105" :class="card.tone">
                            <component :is="card.icon" class="size-5" />
                        </span>
                    </div>
                </article>
            </div>

            <div class="grid gap-5 xl:grid-cols-[1.45fr_.75fr]">
                <div class="section-card p-5 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-display text-lg font-extrabold">Actividad académica</h2>
                            <p class="muted mt-1 text-sm">Asistencia registrada durante la semana.</p>
                        </div>
                        <button class="flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold hover:bg-[rgb(var(--surface-muted))]">
                            Esta semana<ChevronDown class="size-3" />
                        </button>
                    </div>
                    <div class="mt-7 flex h-56 items-end gap-2 sm:gap-4">
                        <div v-for="(bar, index) in [76, 88, 65, 94, 82]" :key="index" class="flex h-full flex-1 flex-col justify-end gap-2">
                            <div class="relative flex-1 rounded-xl bg-[rgb(var(--surface-muted))]">
                                <div class="absolute inset-x-0 bottom-0 origin-bottom animate-progress-in rounded-xl bg-gradient-to-t from-brand-800 to-brand-400" :style="{ height: bar + '%', animationDelay: index * .08 + 's' }">
                                    <span class="absolute -top-6 left-1/2 -translate-x-1/2 text-xs font-bold">{{ bar }}%</span>
                                </div>
                            </div>
                            <span class="muted text-center text-xs font-semibold">{{ ['Lun', 'Mar', 'Mié', 'Jue', 'Vie'][index] }}</span>
                        </div>
                    </div>
                </div>

                <div class="section-card p-5 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="font-display text-lg font-extrabold">Pendientes</h2>
                            <p class="muted mt-1 text-sm">Requieren atención</p>
                        </div>
                        <UiBadge tone="warning">{{ pending.length }} tareas</UiBadge>
                    </div>
                    <div class="mt-5 space-y-3">
                        <button v-for="item in pending" :key="item.title" class="flex w-full items-center gap-3 rounded-xl border p-3 text-left transition hover:border-brand-300 hover:bg-brand-50/40">
                            <span class="grid size-10 place-items-center rounded-xl bg-[rgb(var(--surface-muted))] text-brand-700">
                                <component :is="item.icon" class="size-[18px]" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <strong class="block truncate text-sm">{{ item.title }}</strong>
                                <span class="muted mt-0.5 block text-xs">{{ item.meta }}</span>
                            </span>
                            <ChevronRight class="muted size-4" />
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-5 xl:grid-cols-[1fr_.8fr]">
                <ResponsiveRoster :students="students" />
                <TrackingTimeline title="Matrícula ANA-0248" :items="trackingItems">
                    <template #action>
                        <UiBadge tone="brand" dot>En revisión</UiBadge>
                    </template>
                </TrackingTimeline>
            </div>
        </section>
    </DashboardLayout>
</template>
