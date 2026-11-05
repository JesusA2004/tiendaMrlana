<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useDocumentVisibility, useWindowScroll } from '@vueuse/core';
import {
    ArrowRight,
    ChevronLeft,
    ChevronRight,
    Pause,
    Play,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { prefiereMenosMovimiento } from './tienda';
import type { Banner } from './types';

const props = defineProps<{ banners: Banner[] }>();

const INTERVALO = 6500;

const actual = ref(0);
const pausado = ref(false);
const pausaUsuario = ref(false);
const menosMovimiento = ref(false);
const visibilidad = useDocumentVisibility();
const { y } = useWindowScroll();
let temporizador: number | undefined;
let inicioToque: number | null = null;

const total = computed(() => props.banners.length);
const enMovimiento = computed(
    () =>
        total.value > 1 &&
        !pausado.value &&
        !pausaUsuario.value &&
        !menosMovimiento.value &&
        visibilidad.value === 'visible',
);
const desplazamiento = computed(() =>
    menosMovimiento.value ? 0 : Math.min(y.value, 600) * 0.18,
);

function ir(indice: number) {
    actual.value = (indice + total.value) % total.value;
    reiniciar();
}

function reiniciar() {
    window.clearInterval(temporizador);

    if (enMovimiento.value) {
        temporizador = window.setInterval(() => {
            actual.value = (actual.value + 1) % total.value;
        }, INTERVALO);
    }
}

function esExterno(url: string): boolean {
    return /^https?:\/\//i.test(url) && !url.startsWith(window.location.origin);
}

function alTocar(e: PointerEvent) {
    inicioToque = e.clientX;
}

function alSoltar(e: PointerEvent) {
    if (inicioToque === null) {
        return;
    }

    const delta = e.clientX - inicioToque;
    inicioToque = null;

    if (Math.abs(delta) > 50) {
        ir(actual.value + (delta < 0 ? 1 : -1));
    }
}

watch(enMovimiento, reiniciar);

onMounted(() => {
    menosMovimiento.value = prefiereMenosMovimiento();
    reiniciar();
});

onBeforeUnmount(() => window.clearInterval(temporizador));
</script>

<template>
    <section
        class="relative"
        aria-roledescription="carrusel"
        aria-label="Promociones de la tienda"
        @mouseenter="pausado = true"
        @mouseleave="pausado = false"
        @focusin="pausado = true"
        @focusout="pausado = false"
    >
        <div
            class="relative isolate overflow-hidden rounded-[28px] bg-brand-deep shadow-[0_30px_60px_-30px_rgba(20,51,43,0.55)] sm:rounded-[36px]"
            @pointerdown="alTocar"
            @pointerup="alSoltar"
        >
            <div
                class="relative aspect-[4/3] sm:aspect-[16/7] lg:aspect-[20/7]"
            >
                <div
                    v-for="(banner, indice) in banners"
                    :key="banner.id"
                    class="absolute inset-0 transition-[opacity,transform] duration-[900ms] ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none"
                    :class="
                        indice === actual
                            ? 'z-10 scale-100 opacity-100'
                            : 'pointer-events-none z-0 scale-[1.04] opacity-0'
                    "
                    role="group"
                    aria-roledescription="diapositiva"
                    :aria-label="`${indice + 1} de ${total}: ${banner.titulo}`"
                    :aria-hidden="indice !== actual"
                >
                    <img
                        :src="banner.imagen"
                        :alt="banner.titulo"
                        class="absolute inset-0 h-[112%] w-full object-cover will-change-transform"
                        :style="{
                            transform: `translate3d(0, ${-desplazamiento}px, 0)`,
                        }"
                        :loading="indice === 0 ? 'eager' : 'lazy'"
                        :fetchpriority="indice === 0 ? 'high' : 'auto'"
                        decoding="async"
                        draggable="false"
                    />
                    <div
                        class="absolute inset-0 bg-gradient-to-t from-black/65 via-black/15 to-transparent sm:bg-gradient-to-r sm:from-black/55 sm:via-black/10"
                        aria-hidden="true"
                    />

                    <div
                        class="absolute inset-x-0 bottom-0 p-5 sm:top-0 sm:flex sm:max-w-xl sm:flex-col sm:justify-end sm:p-10 lg:p-14"
                    >
                        <h2
                            class="font-display text-2xl leading-[1.05] font-bold text-balance text-white drop-shadow-sm sm:text-4xl lg:text-5xl"
                        >
                            {{ banner.titulo }}
                        </h2>
                        <p
                            v-if="banner.descripcion"
                            class="mt-2 line-clamp-2 max-w-md text-sm text-white/85 sm:mt-3 sm:text-base"
                        >
                            {{ banner.descripcion }}
                        </p>
                        <template v-if="banner.url">
                            <a
                                v-if="esExterno(banner.url)"
                                :href="banner.url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="mt-4 inline-flex h-11 w-fit items-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-primary-foreground transition-transform hover:scale-[1.03] active:scale-95 sm:mt-6"
                                :tabindex="indice === actual ? 0 : -1"
                            >
                                Ver más <ArrowRight class="size-4" />
                            </a>
                            <Link
                                v-else
                                :href="banner.url"
                                class="mt-4 inline-flex h-11 w-fit items-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-primary-foreground transition-transform hover:scale-[1.03] active:scale-95 sm:mt-6"
                                :tabindex="indice === actual ? 0 : -1"
                            >
                                Ver más <ArrowRight class="size-4" />
                            </Link>
                        </template>
                    </div>
                </div>
            </div>

            <template v-if="total > 1">
                <div
                    class="absolute top-4 right-4 z-20 flex items-center gap-2 sm:top-auto sm:right-8 sm:bottom-8"
                >
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-md transition-colors hover:bg-white/30"
                        aria-label="Anterior"
                        @click="ir(actual - 1)"
                    >
                        <ChevronLeft class="size-5" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-md transition-colors hover:bg-white/30"
                        :aria-label="
                            pausaUsuario
                                ? 'Reanudar carrusel'
                                : 'Pausar carrusel'
                        "
                        @click="pausaUsuario = !pausaUsuario"
                    >
                        <Play v-if="pausaUsuario" class="size-4" />
                        <Pause v-else class="size-4" />
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-full bg-white/15 text-white backdrop-blur-md transition-colors hover:bg-white/30"
                        aria-label="Siguiente"
                        @click="ir(actual + 1)"
                    >
                        <ChevronRight class="size-5" />
                    </button>
                </div>
            </template>
        </div>

        <div
            v-if="total > 1"
            class="mt-4 flex items-center justify-center gap-2"
            role="tablist"
            aria-label="Elegir diapositiva"
        >
            <button
                v-for="(banner, indice) in banners"
                :key="banner.id"
                type="button"
                role="tab"
                class="group relative h-2 overflow-hidden rounded-full bg-foreground/15 transition-[width] duration-500"
                :class="
                    indice === actual
                        ? 'w-10'
                        : 'w-2 hover:bg-muted-foreground/40'
                "
                :aria-selected="indice === actual"
                :aria-label="`Ir a la diapositiva ${indice + 1}`"
                @click="ir(indice)"
            >
                <span
                    v-if="indice === actual"
                    :key="`p-${actual}`"
                    class="absolute inset-y-0 left-0 rounded-full bg-brand-green"
                    :class="enMovimiento ? 'hero-progreso' : 'w-full'"
                    :style="{ animationDuration: `${INTERVALO}ms` }"
                />
            </button>
        </div>
    </section>
</template>

<style scoped>
.hero-progreso {
    animation-name: hero-progreso;
    animation-timing-function: linear;
    animation-fill-mode: forwards;
}

@keyframes hero-progreso {
    from {
        width: 0%;
    }
    to {
        width: 100%;
    }
}
</style>
