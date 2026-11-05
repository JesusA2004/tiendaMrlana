<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Apariencia',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Apariencia" />

    <h1 class="sr-only">Apariencia</h1>

    <div
        class="space-y-6 rounded-3xl border border-border/70 bg-card p-5 shadow-sm sm:p-8"
    >
        <Heading
            variant="small"
            title="Apariencia"
            description="Elige cómo quieres ver el sitio: claro, oscuro o según tu sistema."
        />
        <AppearanceTabs />
    </div>
</template>
