<script setup lang="ts">
import BeneficioIcono from './BeneficioIcono.vue';
import type { Beneficio } from './types';

/**
 * Cinta en movimiento con los beneficios configurados (o hechos verdaderos del sistema).
 * Con prefers-reduced-motion se muestra estática y centrada.
 */
defineProps<{ beneficios: Beneficio[] }>();
</script>

<template>
    <section
        class="group/marquee relative overflow-hidden border-y border-border/70 bg-surface py-4"
        aria-label="Beneficios de comprar en Mr Lana"
    >
        <div
            class="pointer-events-none absolute inset-y-0 left-0 z-10 w-12 bg-gradient-to-r from-surface to-transparent motion-reduce:hidden sm:w-24"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute inset-y-0 right-0 z-10 w-12 bg-gradient-to-l from-surface to-transparent motion-reduce:hidden sm:w-24"
            aria-hidden="true"
        />

        <ul class="sr-only">
            <li v-for="b in beneficios" :key="b.titulo">
                {{ b.titulo
                }}<template v-if="b.descripcion"
                    >: {{ b.descripcion }}</template
                >
            </li>
        </ul>

        <div
            class="flex w-max animate-marquee group-hover/marquee:[animation-play-state:paused] motion-reduce:w-full motion-reduce:flex-wrap motion-reduce:justify-center"
            style="--marquee-duration: 38s"
            aria-hidden="true"
        >
            <div
                v-for="copia in 6"
                :key="copia"
                class="flex shrink-0 items-center"
                :class="{ 'motion-reduce:hidden': copia > 1 }"
            >
                <div
                    v-for="b in beneficios"
                    :key="`${copia}-${b.titulo}`"
                    class="flex items-center gap-3 px-6 sm:px-10"
                >
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-green-soft text-brand-green-strong [&>svg]:size-[18px]"
                    >
                        <BeneficioIcono :nombre="b.icono" />
                    </span>
                    <span
                        class="text-sm font-semibold whitespace-nowrap text-foreground"
                    >
                        {{ b.titulo }}
                    </span>
                    <span
                        class="ml-4 text-xs text-brand-teal sm:ml-8"
                        aria-hidden="true"
                        >◆</span
                    >
                </div>
            </div>
        </div>
    </section>
</template>
