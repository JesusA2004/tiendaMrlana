<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { ref } from 'vue';
import ProductCard from './ProductCard.vue';
import { vReveal } from './tienda';
import type { ProductoTarjeta } from './types';

/**
 * Fila de productos: carrusel con scroll-snap en móvil/tableta y cuadrícula en escritorio.
 */
defineProps<{ productos: ProductoTarjeta[]; etiqueta: string }>();

const pista = ref<HTMLElement | null>(null);

function desplazar(direccion: 1 | -1) {
    const el = pista.value;

    if (!el) {
        return;
    }

    el.scrollBy({
        left: direccion * el.clientWidth * 0.85,
        behavior: 'smooth',
    });
}
</script>

<template>
    <div class="relative">
        <ul
            ref="pista"
            class="-mx-4 flex snap-x snap-mandatory scroll-px-4 gap-3 overflow-x-auto px-4 pb-2 [scrollbar-width:none] sm:-mx-6 sm:scroll-px-6 sm:gap-4 sm:px-6 lg:mx-0 lg:grid lg:grid-cols-4 lg:gap-6 lg:overflow-visible lg:px-0 [&::-webkit-scrollbar]:hidden"
            :aria-label="etiqueta"
        >
            <li
                v-for="(producto, indice) in productos"
                :key="producto.id"
                v-reveal="(indice % 4) * 70"
                class="w-[68%] shrink-0 snap-start min-[430px]:w-[46%] md:w-[31%] lg:w-auto"
            >
                <ProductCard :producto="producto" />
            </li>
        </ul>

        <div
            v-if="productos.length > 2"
            class="mt-4 flex justify-end gap-2 lg:hidden"
        >
            <button
                type="button"
                class="inline-flex size-10 items-center justify-center rounded-full border bg-background text-foreground hover:bg-muted"
                aria-label="Ver anteriores"
                @click="desplazar(-1)"
            >
                <ChevronLeft class="size-5" />
            </button>
            <button
                type="button"
                class="inline-flex size-10 items-center justify-center rounded-full border bg-background text-foreground hover:bg-muted"
                aria-label="Ver siguientes"
                @click="desplazar(1)"
            >
                <ChevronRight class="size-5" />
            </button>
        </div>
    </div>
</template>
