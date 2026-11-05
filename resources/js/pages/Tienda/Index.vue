<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, ArrowUpRight, Store, Truck } from 'lucide-vue-next';
import { computed } from 'vue';
import AvisoTarjetas from '@/components/public/AvisoTarjetas.vue';
import BenefitsMarquee from '@/components/public/BenefitsMarquee.vue';
import BrandHero from '@/components/public/BrandHero.vue';
import FaqList from '@/components/public/FaqList.vue';
import HeroCarousel from '@/components/public/HeroCarousel.vue';
import MetodoPagoIcono from '@/components/public/MetodoPagoIcono.vue';
import ProductRail from '@/components/public/ProductRail.vue';
import SectionHeading from '@/components/public/SectionHeading.vue';
import { urls, vReveal } from '@/components/public/tienda';
import type {
    Banner,
    Beneficio,
    CategoriaPublica,
    MetodoPagoPublico,
    PreguntaFrecuente,
    ProductoTarjeta,
    TiendaCompartida,
} from '@/components/public/types';
import mascota from '@/img/mascota-mr-lana.webp';
import PublicLayout from '@/layouts/PublicLayout.vue';

const props = defineProps<{
    banners: Banner[];
    categorias: CategoriaPublica[];
    destacados: ProductoTarjeta[];
    nuevos: ProductoTarjeta[];
    ofertas: ProductoTarjeta[];
    coleccion: CategoriaPublica[];
    resumen: { productos: number; categorias: number; marcas: number };
    beneficios: Beneficio[];
    envios: string | null;
    metodosPago: MetodoPagoPublico[];
    faq: PreguntaFrecuente[];
    tienda?: TiendaCompartida | null;
}>();

const eslogan = computed(() => props.tienda?.eslogan ?? null);

const textoPagos = computed(() => {
    const claves = props.metodosPago.map((m) => m.clave);
    const transferencia = claves.includes('transferencia');
    const efectivo =
        claves.includes('efectivo_presencial') ||
        claves.includes('efectivo_contra_entrega');

    if (transferencia && efectivo) {
        return 'Paga por transferencia o en efectivo.';
    }

    if (transferencia) {
        return 'Paga por transferencia bancaria.';
    }

    if (efectivo) {
        return 'Paga en efectivo.';
    }

    return null;
});

// No se repiten en "Nuevos" los productos que ya aparecen en "Destacados".
const nuevosSinRepetir = computed(() => {
    const ids = new Set(props.destacados.map((p) => p.id));
    const filtrados = props.nuevos.filter((p) => !ids.has(p.id));

    return filtrados.length >= 4 ? filtrados : props.nuevos;
});

const enviosResumen = computed(() => {
    if (!props.envios) {
        return null;
    }

    const texto = props.envios.trim();

    return texto.length > 420 ? `${texto.slice(0, 400).trimEnd()}…` : texto;
});
const enlaceEnvios = computed(
    () =>
        props.tienda?.enlaces?.find((e) => e.url.endsWith('/envios'))?.url ??
        null,
);
const faqVisible = computed(() => props.faq.slice(0, 6));

// Distribución de la colección de muebles según cuántas categorías tienen productos.
const columnasColeccion = computed(() => {
    const n = props.coleccion.length;

    return (
        (
            {
                1: 'lg:grid-cols-1',
                2: 'lg:grid-cols-2',
                3: 'lg:grid-cols-3',
                4: 'lg:grid-cols-4',
            } as Record<number, string>
        )[n] ?? 'lg:grid-cols-3'
    );
});

function claseColeccion(indice: number): string {
    const n = props.coleccion.length;

    if (indice !== 0) {
        return '';
    }

    if (n === 5) {
        return 'col-span-2 lg:col-span-1 lg:row-span-2';
    }

    return n % 2 === 1 ? 'col-span-2 lg:col-span-1' : '';
}
</script>

<template>
    <PublicLayout>
        <!-- Hero -->
        <div class="mx-auto w-full max-w-7xl px-4 pt-4 sm:px-6 sm:pt-6 lg:px-8">
            <HeroCarousel v-if="banners.length" :banners="banners" />
            <BrandHero
                v-else
                :eslogan="eslogan"
                :resumen="resumen"
                :hay-categorias="categorias.length > 0"
                :pagos="textoPagos"
            />
        </div>

        <div class="mt-8 sm:mt-12">
            <BenefitsMarquee :beneficios="beneficios" />
        </div>

        <!-- Categorías -->
        <section
            v-if="categorias.length"
            class="mx-auto mt-14 w-full max-w-7xl px-4 sm:mt-20 sm:px-6 lg:px-8"
            aria-labelledby="categorias"
        >
            <SectionHeading
                id="categorias"
                antetitulo="Explora"
                titulo="Compra por categoría"
                :enlace="urls.catalogo"
                texto-enlace="Todo el catálogo"
            />
            <ul
                class="-mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-2 [scrollbar-width:none] sm:-mx-6 sm:px-6 lg:mx-0 lg:grid lg:grid-cols-[repeat(auto-fill,minmax(130px,1fr))] lg:gap-4 lg:overflow-visible lg:px-0 [&::-webkit-scrollbar]:hidden"
            >
                <li
                    v-for="(categoria, indice) in categorias"
                    :key="categoria.id"
                    v-reveal="(indice % 6) * 60"
                    class="w-[38%] shrink-0 snap-start min-[430px]:w-[30%] sm:w-[22%] lg:w-auto"
                >
                    <Link
                        :href="urls.categoria(categoria.slug)"
                        class="group flex flex-col items-center gap-3 rounded-3xl p-2 text-center focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        <span
                            class="relative flex aspect-square w-full items-center justify-center overflow-hidden rounded-full bg-surface ring-1 ring-border/70 transition-[box-shadow,transform] duration-300 group-hover:-translate-y-1 group-hover:shadow-[0_16px_30px_-16px_rgba(20,51,43,0.45)] group-hover:ring-2 group-hover:ring-brand-green motion-reduce:transform-none"
                        >
                            <img
                                v-if="categoria.imagen"
                                :src="categoria.imagen"
                                :alt="categoria.nombre"
                                loading="lazy"
                                decoding="async"
                                class="size-full object-cover transition-transform duration-500 group-hover:scale-110 motion-reduce:transition-none"
                            />
                            <span
                                v-else
                                class="flex size-full items-center justify-center bg-brand-gradient font-display text-3xl font-bold text-brand-ink/80"
                                aria-hidden="true"
                                >{{ categoria.nombre.charAt(0) }}</span
                            >
                        </span>
                        <span>
                            <span
                                class="block text-sm leading-tight font-semibold text-foreground"
                                >{{ categoria.nombre }}</span
                            >
                            <span
                                class="mt-0.5 block text-xs text-muted-foreground"
                                >{{ categoria.total }}
                                {{
                                    categoria.total === 1
                                        ? 'producto'
                                        : 'productos'
                                }}</span
                            >
                        </span>
                    </Link>
                </li>
            </ul>
        </section>

        <!-- Destacados -->
        <section
            v-if="destacados.length"
            class="mx-auto mt-16 w-full max-w-7xl px-4 sm:mt-24 sm:px-6 lg:px-8"
            aria-labelledby="destacados"
        >
            <SectionHeading
                id="destacados"
                antetitulo="Selección"
                titulo="Productos destacados"
                :enlace="urls.catalogo"
            />
            <ProductRail
                :productos="destacados"
                etiqueta="Productos destacados"
            />
        </section>

        <!-- Ofertas vigentes -->
        <section
            v-if="ofertas.length"
            class="relative mt-16 overflow-hidden bg-brand-deep-pattern py-14 text-white sm:mt-24 sm:py-20"
            aria-labelledby="ofertas"
        >
            <div
                class="pointer-events-none absolute -top-24 -right-24 size-80 rounded-full bg-brand-green/20 blur-3xl"
                aria-hidden="true"
            />
            <div class="relative mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8">
                <SectionHeading
                    id="ofertas"
                    antetitulo="Precios especiales"
                    titulo="Ofertas vigentes"
                    descripcion="Descuentos activos en este momento; el precio con descuento se aplica automáticamente."
                    oscuro
                />
                <ProductRail :productos="ofertas" etiqueta="Ofertas vigentes" />
            </div>
        </section>

        <!-- Colección de muebles -->
        <section
            v-if="coleccion.length"
            class="mx-auto mt-16 w-full max-w-7xl px-4 sm:mt-24 sm:px-6 lg:px-8"
            aria-labelledby="coleccion"
        >
            <SectionHeading
                id="coleccion"
                antetitulo="Colección"
                titulo="Muebles para cada espacio"
                descripcion="Encuentra piezas para tu sala, comedor, recámara y más."
            />
            <ul
                class="grid auto-rows-[170px] grid-cols-2 gap-3 sm:auto-rows-[210px] sm:gap-4 lg:auto-rows-[240px]"
                :class="columnasColeccion"
            >
                <li
                    v-for="(categoria, indice) in coleccion"
                    :key="categoria.id"
                    v-reveal="indice * 70"
                    :class="claseColeccion(indice)"
                >
                    <Link
                        :href="urls.categoria(categoria.slug)"
                        class="group relative flex size-full overflow-hidden rounded-3xl bg-brand-deep-pattern focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        <img
                            v-if="categoria.imagen"
                            :src="categoria.imagen"
                            :alt="categoria.nombre"
                            loading="lazy"
                            decoding="async"
                            class="absolute inset-0 size-full object-cover transition-transform duration-700 ease-out group-hover:scale-105 motion-reduce:transition-none"
                        />
                        <span
                            class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"
                            aria-hidden="true"
                        />
                        <span
                            class="relative mt-auto flex w-full items-end justify-between gap-3 p-4 sm:p-6"
                        >
                            <span class="min-w-0">
                                <span
                                    class="block font-display text-lg leading-tight font-bold break-words text-white sm:text-2xl"
                                    :class="{
                                        'lg:text-4xl':
                                            indice === 0 &&
                                            coleccion.length === 5,
                                    }"
                                    >{{ categoria.nombre }}</span
                                >
                                <span class="text-xs text-white/80 sm:text-sm"
                                    >{{ categoria.total }}
                                    {{
                                        categoria.total === 1
                                            ? 'producto'
                                            : 'productos'
                                    }}</span
                                >
                            </span>
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-full bg-white text-brand-ink transition-transform duration-300 group-hover:rotate-45 sm:size-10"
                                aria-hidden="true"
                            >
                                <ArrowUpRight class="size-5" />
                            </span>
                        </span>
                    </Link>
                </li>
            </ul>
        </section>

        <!-- Nuevos -->
        <section
            v-if="nuevosSinRepetir.length"
            class="mx-auto mt-16 w-full max-w-7xl px-4 sm:mt-24 sm:px-6 lg:px-8"
            aria-labelledby="nuevos"
        >
            <SectionHeading
                id="nuevos"
                antetitulo="Recién llegados"
                titulo="Lo más nuevo"
                :enlace="`${urls.catalogo}?orden=nuevos`"
            />
            <ProductRail
                :productos="nuevosSinRepetir"
                etiqueta="Productos nuevos"
            />
        </section>

        <!-- Catálogo vacío -->
        <section
            v-if="resumen.productos === 0"
            class="mx-auto mt-16 w-full max-w-3xl px-4 text-center sm:mt-24 sm:px-6"
        >
            <img
                :src="mascota"
                alt=""
                width="710"
                height="848"
                class="mx-auto h-40 w-auto animate-float"
                loading="lazy"
                decoding="async"
            />
            <h2 class="mt-6 font-display text-3xl font-bold">
                Estamos preparando el catálogo
            </h2>
            <p class="mt-2 text-muted-foreground">
                Muy pronto podrás ver y comprar nuestros productos aquí.
            </p>
        </section>

        <!-- Pagos y envíos -->
        <section
            class="mx-auto mt-16 w-full max-w-7xl px-4 sm:mt-24 sm:px-6 lg:px-8"
            aria-labelledby="como-comprar"
        >
            <SectionHeading
                id="como-comprar"
                antetitulo="Compra fácil"
                titulo="Pagos y entregas"
            />
            <div
                class="grid gap-4 sm:gap-6"
                :class="{ 'lg:grid-cols-2': !!enviosResumen }"
            >
                <div
                    v-reveal
                    class="flex flex-col gap-5 rounded-3xl border border-border/70 bg-card p-5 sm:p-8"
                >
                    <div>
                        <h3 class="font-display text-xl font-bold">
                            Formas de pago
                        </h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Elige cómo pagar al finalizar tu compra.
                        </p>
                    </div>
                    <ul
                        v-if="metodosPago.length"
                        class="grid gap-3 sm:grid-cols-2"
                    >
                        <li
                            v-for="metodo in metodosPago"
                            :key="metodo.clave"
                            class="flex items-center gap-3 rounded-2xl bg-surface p-3.5"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary text-primary-foreground [&>svg]:size-5"
                            >
                                <MetodoPagoIcono :clave="metodo.clave" />
                            </span>
                            <span class="text-sm font-semibold">{{
                                metodo.nombre
                            }}</span>
                        </li>
                    </ul>
                    <AvisoTarjetas />
                </div>

                <div
                    v-if="enviosResumen"
                    v-reveal="100"
                    class="relative flex flex-col overflow-hidden rounded-3xl bg-brand-green-soft p-5 sm:p-8 dark:bg-brand-green-soft"
                >
                    <Truck
                        class="absolute -right-6 -bottom-6 size-40 text-brand-green/15"
                        aria-hidden="true"
                    />
                    <h3 class="font-display text-xl font-bold">
                        Envíos y entregas
                    </h3>
                    <p
                        class="relative mt-3 text-sm leading-relaxed whitespace-pre-line text-foreground/80"
                    >
                        {{ enviosResumen }}
                    </p>
                    <Link
                        v-if="enlaceEnvios"
                        :href="enlaceEnvios"
                        class="relative mt-5 inline-flex w-fit items-center gap-2 text-sm font-semibold text-brand-green-strong underline-offset-4 hover:underline"
                    >
                        Leer política completa <ArrowRight class="size-4" />
                    </Link>
                </div>
            </div>
        </section>

        <!-- Preguntas frecuentes -->
        <section
            v-if="faqVisible.length"
            class="mx-auto mt-16 w-full max-w-7xl px-4 sm:mt-24 sm:px-6 lg:px-8"
            aria-labelledby="faq"
        >
            <div class="grid gap-8 lg:grid-cols-[0.8fr_1.2fr] lg:gap-14">
                <div class="lg:sticky lg:top-28 lg:self-start">
                    <SectionHeading
                        id="faq"
                        antetitulo="Ayuda"
                        titulo="Preguntas frecuentes"
                        :enlace="
                            faq.length > faqVisible.length
                                ? urls.preguntas
                                : null
                        "
                        texto-enlace="Ver todas"
                    />
                    <img
                        :src="mascota"
                        alt=""
                        width="710"
                        height="848"
                        class="hidden h-56 w-auto lg:block"
                        loading="lazy"
                        decoding="async"
                    />
                </div>
                <div v-reveal="80">
                    <FaqList :preguntas="faqVisible" />
                </div>
            </div>
        </section>

        <!-- Llamado final -->
        <section
            v-if="resumen.productos > 0"
            class="mx-auto mt-16 w-full max-w-7xl px-4 sm:mt-24 sm:px-6 lg:px-8"
        >
            <div
                v-reveal
                class="relative isolate flex flex-col items-start gap-6 overflow-hidden rounded-[28px] bg-brand-gradient p-6 sm:flex-row sm:items-center sm:justify-between sm:p-10"
            >
                <div class="max-w-xl">
                    <p
                        class="font-display text-2xl leading-tight font-bold text-brand-ink sm:text-3xl"
                    >
                        ¿Listo para encontrar lo que buscas?
                    </p>
                    <p class="mt-2 text-sm text-brand-ink/80 sm:text-base">
                        Filtra por categoría, marca, precio y disponibilidad.
                    </p>
                </div>
                <Link
                    :href="urls.catalogo"
                    class="inline-flex h-12 shrink-0 items-center gap-2 rounded-full bg-brand-ink px-7 text-[15px] font-semibold text-white transition-transform hover:scale-[1.03] active:scale-95"
                >
                    <Store class="size-4" /> Ir al catálogo
                </Link>
            </div>
        </section>
    </PublicLayout>
</template>
