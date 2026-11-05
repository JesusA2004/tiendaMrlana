<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import FaqList from '@/components/public/FaqList.vue';
import { urls } from '@/components/public/tienda';
import type { PreguntaFrecuente } from '@/components/public/types';
import mascota from '@/img/mascota-mr-lana.webp';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineProps<{
    faq: PreguntaFrecuente[];
}>();
</script>

<template>
    <PublicLayout>
        <section
            class="relative isolate overflow-hidden bg-brand-deep-pattern text-white"
        >
            <div
                class="pointer-events-none absolute -bottom-40 -left-24 -z-10 size-96 rounded-full bg-brand-teal/25 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="mx-auto flex w-full max-w-5xl items-end justify-between gap-6 px-4 pt-12 sm:px-6 sm:pt-16 lg:px-8"
            >
                <div class="pb-12 sm:pb-16">
                    <Link
                        :href="urls.home"
                        class="link-underline text-sm text-white/70 hover:text-white"
                        >← Volver al inicio</Link
                    >
                    <p
                        class="mt-6 text-xs font-bold tracking-[0.16em] text-brand-green uppercase"
                    >
                        Ayuda · Tienda Mr Lana
                    </p>
                    <h1
                        class="mt-2 animate-fade-up font-display text-4xl leading-tight font-bold tracking-tight sm:text-5xl"
                    >
                        Preguntas frecuentes
                    </h1>
                    <p class="mt-2 max-w-md text-white/75">
                        Resolvemos tus dudas sobre compras, pagos y entregas.
                    </p>
                </div>
                <img
                    :src="mascota"
                    alt=""
                    width="710"
                    height="848"
                    class="hidden h-48 w-auto animate-float self-end drop-shadow-[0_18px_24px_rgba(0,0,0,0.35)] sm:block"
                    decoding="async"
                />
            </div>
        </section>

        <div
            class="mx-auto w-full max-w-3xl animate-fade-up px-4 py-10 [--enter-delay:80ms] sm:px-6 sm:py-14"
        >
            <FaqList :preguntas="faq" />
        </div>
    </PublicLayout>
</template>
