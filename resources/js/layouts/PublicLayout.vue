<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { watch } from 'vue';
import { toast, Toaster } from 'vue-sonner';
import 'vue-sonner/style.css';
import WhaticketWidget from '@/components/integrations/WhaticketWidget.vue';
import InstallPrompt from '@/components/public/InstallPrompt.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import StoreFooter from '@/components/public/StoreFooter.vue';
import StoreNavbar from '@/components/public/StoreNavbar.vue';
import type { PropsCompartidasTienda } from '@/components/public/types';

/**
 * Layout de la tienda pública (también lo usan carrito/checkout de otros equipos).
 * Muestra los mensajes flash de sesión (success|error) como avisos no intrusivos y
 * mantiene las metaetiquetas SEO/Open Graph de la prop `seo` (SeoHead). Fondo verde suave de
 * marca (`bg-brand-app`) y transición de entrada en cada página (`animate-page-in`).
 */
const page = usePage<PropsCompartidasTienda>();

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            toast.success(String(flash.success));
        }

        if (flash?.error) {
            toast.error(String(flash.error));
        }
    },
    { immediate: true },
);
</script>

<template>
    <SeoHead />

    <div
        class="flex min-h-svh flex-col overflow-x-clip bg-brand-app text-foreground"
    >
        <WhaticketWidget />

        <StoreNavbar />

        <main
            id="contenido"
            class="w-full flex-1 animate-page-in focus:outline-none"
            tabindex="-1"
        >
            <slot />
        </main>

        <StoreFooter />

        <InstallPrompt />

        <Toaster
            position="top-center"
            rich-colors
            close-button
            :offset="84"
            :toast-options="{
                class: 'font-sans rounded-2xl',
            }"
        />
    </div>
</template>
