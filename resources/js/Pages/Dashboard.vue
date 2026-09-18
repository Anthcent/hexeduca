<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';

// Datos de muestra: esta pantalla es un placeholder inicial, se reemplaza
// por datos reales cuando se construya el módulo correspondiente.
const stats = [
    { label: 'Estudiantes matriculados', value: '248', delta: '+6' },
    { label: 'Ofertas académicas activas', value: '12', delta: null },
    { label: 'Docentes activos', value: '18', delta: '+1' },
    { label: 'Matrículas pendientes', value: '5', delta: null },
];

const recentEnrollments = [
    { student: 'Ana Torres', section: '3° Grado B', guardian: 'Carla Torres', status: 'Confirmada' },
    { student: 'Luis Fernández', section: '1° Grado A', guardian: 'Jorge Fernández', status: 'Pendiente' },
    { student: 'María Gómez', section: '5° Grado C', guardian: 'Silvia Gómez', status: 'Confirmada' },
    { student: 'Diego Ramírez', section: '2° Grado A', guardian: 'Pablo Ramírez', status: 'Rechazada' },
];

function statusChipClass(status) {
    if (status === 'Confirmada') return 'bg-green-tint text-green';
    if (status === 'Pendiente') return 'bg-yellow-tint text-[#a15c00]';
    return 'bg-red-tint text-red';
}
</script>

<template>
    <DashboardLayout active="resumen">
        <div class="mb-6">
            <h1 class="text-[1.75rem] font-semibold text-ink">Resumen</h1>
            <p class="mt-1 text-sm text-ink-muted">
                Esta pantalla es un punto de partida — se va a reemplazar cuando construyamos el módulo real de reportes.
            </p>
        </div>

        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div v-for="stat in stats" :key="stat.label" class="rounded-g2 border border-outline bg-white p-5">
                <div class="mb-3.5 flex items-center justify-between">
                    <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-light text-blue">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" />
                        </svg>
                    </div>
                    <span v-if="stat.delta" class="text-xs font-medium text-green">{{ stat.delta }}</span>
                </div>
                <div class="text-[1.7rem] font-normal tabular-nums text-ink">{{ stat.value }}</div>
                <div class="mt-0.5 text-sm text-ink-muted">{{ stat.label }}</div>
            </div>
        </div>

        <div class="overflow-hidden rounded-g2 border border-outline bg-white">
            <div class="flex items-center justify-between border-b border-outline px-5 py-3.5">
                <h2 class="text-base font-medium text-ink">Matrículas recientes</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-outline">
                            <th class="w-10 px-4 py-3">
                                <input type="checkbox" class="h-4 w-4 rounded border-outline-strong text-blue focus:ring-blue" />
                            </th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-ink">Estudiante</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink">Sección</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink">Tutor</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-ink">Estado</th>
                            <th class="w-10 px-2 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in recentEnrollments"
                            :key="row.student"
                            class="group border-t border-outline hover:bg-blue-light/40"
                        >
                            <td class="px-4 py-3">
                                <input type="checkbox" class="h-4 w-4 rounded border-outline-strong text-blue focus:ring-blue" />
                            </td>
                            <td class="px-2 py-3">{{ row.student }}</td>
                            <td class="px-4 py-3">{{ row.section }}</td>
                            <td class="px-4 py-3">{{ row.guardian }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-pill px-3 py-1 text-xs font-medium" :class="statusChipClass(row.status)">
                                    {{ row.status }}
                                </span>
                            </td>
                            <td class="px-2 py-3">
                                <button class="rounded-full p-1.5 text-ink-muted opacity-0 hover:bg-black/[0.06] group-hover:opacity-100">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                        <circle cx="12" cy="5" r="1.5" /><circle cx="12" cy="12" r="1.5" /><circle cx="12" cy="19" r="1.5" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </DashboardLayout>
</template>
