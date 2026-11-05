<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { urls } from '@/components/public/tienda';
import type { TiendaCompartida } from '@/components/public/types';
import PublicLayout from '@/layouts/PublicLayout.vue';

const props = defineProps<{
    pagina: { slug: string; titulo: string; contenido: string };
    tienda?: TiendaCompartida | null;
}>();

const enlacesInformativos = computed(() => props.tienda?.enlaces ?? []);
</script>

<template>
    <PublicLayout>
        <section class="bg-brand-deep-pattern text-white">
            <div
                class="mx-auto w-full max-w-5xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8"
            >
                <Link
                    :href="urls.home"
                    class="text-sm text-white/70 hover:text-white"
                    >← Volver al inicio</Link
                >
                <h1
                    class="mt-4 font-display text-4xl leading-tight font-bold tracking-tight sm:text-5xl"
                >
                    {{ pagina.titulo }}
                </h1>
            </div>
        </section>

        <div
            class="mx-auto grid w-full max-w-5xl gap-10 px-4 py-10 sm:px-6 sm:py-14 lg:grid-cols-[1fr_220px] lg:px-8"
        >
            <article
                class="text-[15px] leading-[1.85] whitespace-pre-line text-foreground/85 sm:text-base"
            >
                {{ pagina.contenido }}
            </article>

            <nav
                v-if="enlacesInformativos.length > 1"
                class="lg:sticky lg:top-28 lg:self-start"
                aria-label="Información"
            >
                <p
                    class="mb-3 text-xs font-bold tracking-[0.14em] text-muted-foreground uppercase"
                >
                    Información
                </p>
                <ul class="space-y-1">
                    <li v-for="enlace in enlacesInformativos" :key="enlace.url">
                        <Link
                            :href="enlace.url"
                            class="block rounded-xl px-3 py-2 text-sm transition-colors"
                            :class="
                                enlace.url.endsWith(`/${pagina.slug}`)
                                    ? 'bg-brand-green-soft font-semibold'
                                    : 'text-foreground/80 hover:bg-muted'
                            "
                            >{{ enlace.titulo }}</Link
                        >
                    </li>
                </ul>
            </nav>
        </div>
    </PublicLayout>
</template>
