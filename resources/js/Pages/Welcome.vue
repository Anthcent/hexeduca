<script setup>
import DashboardLayout from '@/Layouts/DashboardLayout.vue';
import PageHeader from '@/Components/PageHeader.vue';
</script>

<template>
    <DashboardLayout active="resumen">
        <PageHeader eyebrow="Educativo" title="Bienvenido" />

        <UCard class="shadow-card">
            <p class="text-sm text-muted">
                Educativo foundation scaffold — base layout is rendering
                successfully via Inertia + Vue 3.
            </p>
        </UCard>
    </DashboardLayout>
</template>
