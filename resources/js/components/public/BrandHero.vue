<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useWindowScroll } from '@vueuse/core';
import { ArrowRight, LayoutGrid } from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import logo from '@/img/logo.png';
import mascota from '@/img/mascota-mr-lana.webp';
import CountUp from './CountUp.vue';
import { prefiereMenosMovimiento, urls } from './tienda';

/**
 * Hero de marca cuando no hay banners activos: sin promesas comerciales,
 * sólo identidad (logo + mascota), eslogan configurable y datos reales.
 */
const props = defineProps<{
    eslogan?: string | null;
    resumen?: { productos: number; categorias: number; marcas: number } | null;
    hayCategorias?: boolean;
    pagos?: string | null;
}>();

const { y } = useWindowScroll();
const menosMovimiento = ref(true);

onMounted(() => {
    menosMovimiento.value = prefiereMenosMovimiento();
});

const parallax = computed(() =>
    menosMovimiento.value ? 0 : Math.min(y.value, 500) * 0.12,
);

const cifras = computed(() =>
    [
        { etiqueta: 'productos', valor: props.resumen?.productos ?? 0 },
        { etiqueta: 'categorías', valor: props.resumen?.categorias ?? 0 },
        { etiqueta: 'marcas', valor: props.resumen?.marcas ?? 0 },
    ].filter((c) => c.valor > 0),
);
</script>

<template>
    <section
        class="relative isolate overflow-hidden rounded-[28px] bg-brand-deep-pattern text-white shadow-[0_40px_80px_-40px_rgba(20,51,43,0.7)] sm:rounded-[40px]"
    >
        <!-- Formas orgánicas del manual -->
        <div
            class="pointer-events-none absolute -top-40 -left-32 size-[420px] rounded-full bg-brand-green/25 blur-3xl"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute -right-24 -bottom-48 size-[520px] rounded-full bg-brand-teal/25 blur-3xl"
            aria-hidden="true"
        />
        <svg
            class="pointer-events-none absolute right-0 bottom-0 h-full w-[70%] text-brand-green/15 max-lg:hidden"
            viewBox="0 0 600 500"
            preserveAspectRatio="xMaxYMax slice"
            aria-hidden="true"
        >
            <path
                d="M600 0 C 420 40 330 160 360 300 C 390 440 300 500 180 500 L 600 500 Z"
                fill="currentColor"
            />
        </svg>

        <div
            class="relative grid items-center gap-6 px-6 pt-10 pb-0 sm:px-10 sm:pt-14 lg:grid-cols-[1.15fr_0.85fr] lg:gap-4 lg:px-16 lg:py-16"
        >
            <div class="relative z-10 max-w-xl">
                <img
                    :src="logo"
                    alt="Mr Lana"
                    width="508"
                    height="192"
                    decoding="async"
                    class="h-12 w-auto animate-in duration-700 fade-in slide-in-from-bottom-3 sm:h-14"
                />
                <h1
                    class="mt-6 animate-in font-display text-[2.15rem] leading-[1.02] font-extrabold tracking-tight text-balance duration-700 fade-in slide-in-from-bottom-4 sm:text-5xl lg:text-6xl"
                >
                    <template v-if="eslogan">{{ eslogan }}</template>
                    <template v-else>
                        Muebles y artículos para tu
                        <span class="text-brand-gradient">hogar</span>
                    </template>
                </h1>
                <p
                    class="mt-4 max-w-md animate-in text-base leading-relaxed text-white/80 delay-100 duration-700 fade-in slide-in-from-bottom-4 sm:text-lg"
                >
                    Explora el catálogo, arma tu carrito y sigue tu pedido en
                    línea.{{ pagos ? ` ${pagos}` : '' }}
                </p>

                <div
                    class="mt-8 flex animate-in flex-col gap-3 delay-200 duration-700 fade-in slide-in-from-bottom-4 sm:flex-row"
                >
                    <Link
                        :href="urls.catalogo"
                        class="group inline-flex h-12 items-center justify-center gap-2 rounded-full bg-primary px-7 text-[15px] font-semibold text-primary-foreground shadow-[0_10px_30px_-10px_rgba(115,200,58,0.8)] transition-transform hover:scale-[1.03] active:scale-95"
                    >
                        Explorar catálogo
                        <ArrowRight
                            class="size-4 transition-transform group-hover:translate-x-0.5"
                        />
                    </Link>
                    <a
                        v-if="hayCategorias"
                        href="#categorias"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-full border border-white/25 px-7 text-[15px] font-semibold text-white transition-colors hover:bg-white/10"
                    >
                        <LayoutGrid class="size-4" /> Ver categorías
                    </a>
                </div>

                <dl
                    v-if="cifras.length"
                    class="mt-10 grid max-w-md grid-cols-3 gap-4 border-t border-white/15 pt-6"
                >
                    <div v-for="cifra in cifras" :key="cifra.etiqueta">
                        <dt class="sr-only">{{ cifra.etiqueta }}</dt>
                        <dd>
                            <span
                                class="block font-display text-3xl leading-none font-bold text-white sm:text-4xl"
                            >
                                <CountUp :valor="cifra.valor" />
                            </span>
                            <span
                                class="mt-1 block text-xs text-white/70 sm:text-sm"
                                >{{ cifra.etiqueta }}</span
                            >
                        </dd>
                    </div>
                </dl>
            </div>

            <div
                class="relative mx-auto -mb-2 w-[78%] max-w-[420px] sm:w-[60%] lg:mb-[-4rem] lg:w-full"
                :style="{ transform: `translate3d(0, ${parallax}px, 0)` }"
            >
                <div
                    class="absolute inset-x-[12%] bottom-[6%] h-[18%] rounded-[50%] bg-black/35 blur-2xl"
                    aria-hidden="true"
                />
                <img
                    :src="mascota"
                    alt="Mr. Lana, la mascota de la tienda: un borrego elegante con sombrero de copa"
                    width="710"
                    height="848"
                    class="relative h-auto w-full animate-float drop-shadow-[0_20px_30px_rgba(0,0,0,0.35)]"
                    fetchpriority="high"
                />
            </div>
        </div>
    </section>
</template>
