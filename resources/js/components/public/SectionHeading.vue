<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { vReveal } from './tienda';

withDefaults(
    defineProps<{
        titulo: string;
        antetitulo?: string | null;
        descripcion?: string | null;
        enlace?: string | null;
        textoEnlace?: string;
        id?: string;
        oscuro?: boolean;
    }>(),
    {
        antetitulo: null,
        descripcion: null,
        enlace: null,
        textoEnlace: 'Ver todo',
        id: undefined,
        oscuro: false,
    },
);
</script>

<template>
    <div
        v-reveal
        class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:items-end sm:justify-between"
    >
        <div class="max-w-2xl">
            <p
                v-if="antetitulo"
                class="mb-2 inline-flex items-center gap-2 text-xs font-bold tracking-[0.16em] uppercase"
                :class="oscuro ? 'text-brand-green' : 'text-brand-green-strong'"
            >
                <span class="h-px w-6 bg-current" aria-hidden="true" />
                {{ antetitulo }}
            </p>
            <h2
                :id="id"
                class="font-display text-[1.75rem] leading-[1.08] font-bold tracking-tight text-balance sm:text-4xl"
                :class="oscuro ? 'text-white' : 'text-foreground'"
            >
                {{ titulo }}
            </h2>
            <p
                v-if="descripcion"
                class="mt-2 text-sm leading-relaxed sm:text-base"
                :class="oscuro ? 'text-white/75' : 'text-muted-foreground'"
            >
                {{ descripcion }}
            </p>
        </div>
        <Link
            v-if="enlace"
            :href="enlace"
            class="group inline-flex h-10 w-fit shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-semibold transition-[background-color,border-color,transform] duration-200 hover:-translate-y-0.5 motion-reduce:hover:translate-y-0"
            :class="
                oscuro
                    ? 'border-white/25 text-white hover:bg-white/10'
                    : 'border-border bg-card/70 text-foreground shadow-brand-sm hover:border-brand-green hover:bg-brand-green-soft'
            "
        >
            {{ textoEnlace }}
            <ArrowRight
                class="size-4 transition-transform group-hover:translate-x-0.5"
            />
        </Link>
    </div>
</template>
