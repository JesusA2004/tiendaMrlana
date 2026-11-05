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
        <section
            class="relative isolate overflow-hidden bg-brand-deep-pattern text-white"
        >
            <div
                class="pointer-events-none absolute -top-32 -right-24 -z-10 size-96 rounded-full bg-brand-green/25 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="mx-auto w-full max-w-5xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8"
            >
                <nav aria-label="Ruta de navegación">
                    <ol class="flex items-center gap-2 text-sm text-white/70">
                        <li>
                            <Link
                                :href="urls.home"
                                class="link-underline hover:text-white"
                                >Inicio</Link
                            >
                        </li>
                        <li aria-hidden="true">/</li>
                        <li class="text-white/90" aria-current="page">
                            {{ pagina.titulo }}
                        </li>
                    </ol>
                </nav>
                <p
                    class="mt-6 text-xs font-bold tracking-[0.16em] text-brand-green uppercase"
                >
                    Información · Tienda Mr Lana
                </p>
                <h1
                    class="mt-2 animate-fade-up font-display text-4xl leading-tight font-bold tracking-tight text-balance sm:text-5xl"
                >
                    {{ pagina.titulo }}
                </h1>
            </div>
        </section>

        <div
            class="mx-auto grid w-full max-w-5xl gap-10 px-4 py-10 sm:px-6 sm:py-14 lg:grid-cols-[1fr_220px] lg:px-8"
        >
            <article
                class="animate-fade-up surface-card p-6 text-[15px] leading-[1.85] whitespace-pre-line text-foreground/85 [--enter-delay:80ms] sm:p-10 sm:text-base"
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
                            class="block rounded-xl px-3 py-2 text-sm transition-[background-color,color,transform] duration-200"
                            :class="
                                enlace.url.endsWith(`/${pagina.slug}`)
                                    ? 'bg-brand-deep font-semibold text-white'
                                    : 'text-foreground/80 hover:translate-x-0.5 hover:bg-card motion-reduce:hover:translate-x-0'
                            "
                            :aria-current="
                                enlace.url.endsWith(`/${pagina.slug}`)
                                    ? 'page'
                                    : undefined
                            "
                            >{{ enlace.titulo }}</Link
                        >
                    </li>
                </ul>
            </nav>
        </div>
    </PublicLayout>
</template>
