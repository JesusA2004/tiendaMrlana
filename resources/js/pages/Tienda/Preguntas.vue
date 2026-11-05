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
            class="relative overflow-hidden bg-brand-deep-pattern text-white"
        >
            <div
                class="mx-auto flex w-full max-w-5xl items-end justify-between gap-6 px-4 pt-12 sm:px-6 sm:pt-16 lg:px-8"
            >
                <div class="pb-12 sm:pb-16">
                    <Link
                        :href="urls.home"
                        class="text-sm text-white/70 hover:text-white"
                        >← Volver al inicio</Link
                    >
                    <h1
                        class="mt-4 font-display text-4xl leading-tight font-bold tracking-tight sm:text-5xl"
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
                    class="hidden h-48 w-auto self-end sm:block"
                    decoding="async"
                />
            </div>
        </section>

        <div class="mx-auto w-full max-w-3xl px-4 py-10 sm:px-6 sm:py-14">
            <FaqList :preguntas="faq" />
        </div>
    </PublicLayout>
</template>
