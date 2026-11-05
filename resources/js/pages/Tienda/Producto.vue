<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useIntersectionObserver } from '@vueuse/core';
import {
    CircleAlert,
    CircleCheck,
    Clock3,
    Loader2,
    Minus,
    PackageCheck,
    Plus,
    ShoppingBag,
    Truck,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AvisoTarjetas from '@/components/public/AvisoTarjetas.vue';
import MetodoPagoIcono from '@/components/public/MetodoPagoIcono.vue';
import ProductGallery from '@/components/public/ProductGallery.vue';
import ProductRail from '@/components/public/ProductRail.vue';
import SectionHeading from '@/components/public/SectionHeading.vue';
import {
    fechaCorta,
    moneda,
    porcentajeDescuento,
    urls,
} from '@/components/public/tienda';
import type {
    Disponibilidad,
    MetodoPagoPublico,
    ProductoTarjeta,
    TiendaCompartida,
} from '@/components/public/types';
import { useAgregarAlCarrito } from '@/components/public/useAgregarAlCarrito';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import PublicLayout from '@/layouts/PublicLayout.vue';

interface Dato {
    nombre: string;
    valor: string;
}

interface ProductoDetalle {
    id: number;
    nombre: string;
    slug: string;
    sku: string | null;
    descripcion: string | null;
    descripcion_larga: string | null;
    modelo: string | null;
    acabado: string | null;
    dimensiones: string | null;
    atributos: Dato[];
    medidas: Dato[];
    imagenes: string[];
    precio_original: number | null;
    precio_final: number | null;
    descuento: number;
    tiene_oferta: boolean;
    oferta: {
        nombre: string;
        tipo: string;
        valor: number;
        termina_en: string | null;
    } | null;
    disponibilidad: Disponibilidad;
    stock: number | null;
    tiempo_entrega_dias: number | null;
    cantidad_maxima: number;
    categoria: {
        nombre: string;
        slug: string;
        padre: { nombre: string; slug: string } | null;
    } | null;
    marca: { nombre: string; slug: string } | null;
}

const props = defineProps<{
    producto: ProductoDetalle;
    relacionados: ProductoTarjeta[];
    metodosPago: MetodoPagoPublico[];
    envios: boolean;
    tienda?: TiendaCompartida | null;
}>();

const cantidad = ref(1);
const { enviando, agregar } = useAgregarAlCarrito();

watch(
    () => props.producto.id,
    () => {
        cantidad.value = 1;
    },
);

const agotado = computed(() => props.producto.disponibilidad === 'agotado');
const bajoPedido = computed(
    () => props.producto.disponibilidad === 'bajo_pedido',
);
const puedeComprar = computed(
    () => !agotado.value && props.producto.cantidad_maxima > 0,
);
const descuento = computed(() =>
    porcentajeDescuento(
        props.producto.precio_original,
        props.producto.precio_final,
    ),
);
const terminaOferta = computed(() =>
    fechaCorta(props.producto.oferta?.termina_en),
);

const especificaciones = computed<Dato[]>(() => {
    const p = props.producto;
    const lista: Dato[] = [];

    if (p.marca) {
        lista.push({ nombre: 'Marca', valor: p.marca.nombre });
    }

    if (p.modelo) {
        lista.push({ nombre: 'Modelo', valor: p.modelo });
    }

    if (p.acabado) {
        lista.push({ nombre: 'Acabado', valor: p.acabado });
    }

    if (p.dimensiones) {
        lista.push({ nombre: 'Dimensiones', valor: p.dimensiones });
    }

    lista.push(...p.medidas, ...p.atributos);

    if (p.sku) {
        lista.push({ nombre: 'SKU', valor: p.sku });
    }

    return lista;
});

// La descripción corta ya aparece junto al precio; la pestaña sólo muestra la larga.
const descripcionCompleta = computed(() =>
    props.producto.descripcion_larga &&
    props.producto.descripcion_larga.trim() !==
        (props.producto.descripcion ?? '').trim()
        ? props.producto.descripcion_larga
        : null,
);
const enlaceEnvios = computed(
    () =>
        props.tienda?.enlaces?.find((e) => e.url.endsWith('/envios'))?.url ??
        null,
);
const enlaceDevoluciones = computed(
    () =>
        props.tienda?.enlaces?.find((e) => e.url.endsWith('/devoluciones'))
            ?.url ?? null,
);

function cambiarCantidad(delta: number) {
    const siguiente = cantidad.value + delta;
    cantidad.value = Math.min(
        Math.max(1, siguiente),
        Math.max(1, props.producto.cantidad_maxima),
    );
}

function normalizarCantidad() {
    const n = Math.floor(Number(cantidad.value) || 1);
    cantidad.value = Math.min(
        Math.max(1, n),
        Math.max(1, props.producto.cantidad_maxima),
    );
}

function comprar() {
    if (!puedeComprar.value) {
        return;
    }

    normalizarCantidad();
    agregar(props.producto.id, cantidad.value);
}

// Barra fija inferior en móvil cuando el botón principal sale de pantalla.
const botonPrincipal = ref<HTMLElement | null>(null);
const barraVisible = ref(false);
useIntersectionObserver(botonPrincipal, ([entrada]) => {
    barraVisible.value =
        !!entrada &&
        !entrada.isIntersecting &&
        entrada.boundingClientRect.top < 0;
});
</script>

<template>
    <PublicLayout>
        <div class="mx-auto w-full max-w-7xl px-4 pt-5 sm:px-6 lg:px-8 lg:pt-8">
            <Breadcrumb>
                <BreadcrumbList>
                    <BreadcrumbItem>
                        <BreadcrumbLink as-child>
                            <Link :href="urls.home">Inicio</Link>
                        </BreadcrumbLink>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator />
                    <BreadcrumbItem>
                        <BreadcrumbLink as-child>
                            <Link :href="urls.catalogo">Catálogo</Link>
                        </BreadcrumbLink>
                    </BreadcrumbItem>
                    <template v-if="producto.categoria?.padre">
                        <BreadcrumbSeparator class="max-sm:hidden" />
                        <BreadcrumbItem class="max-sm:hidden">
                            <BreadcrumbLink as-child>
                                <Link
                                    :href="
                                        urls.categoria(
                                            producto.categoria.padre.slug,
                                        )
                                    "
                                    >{{ producto.categoria.padre.nombre }}</Link
                                >
                            </BreadcrumbLink>
                        </BreadcrumbItem>
                    </template>
                    <template v-if="producto.categoria">
                        <BreadcrumbSeparator />
                        <BreadcrumbItem>
                            <BreadcrumbLink as-child>
                                <Link
                                    :href="
                                        urls.categoria(producto.categoria.slug)
                                    "
                                    >{{ producto.categoria.nombre }}</Link
                                >
                            </BreadcrumbLink>
                        </BreadcrumbItem>
                    </template>
                    <BreadcrumbSeparator class="max-md:hidden" />
                    <BreadcrumbItem class="max-md:hidden">
                        <BreadcrumbPage class="line-clamp-1 max-w-[28ch]">{{
                            producto.nombre
                        }}</BreadcrumbPage>
                    </BreadcrumbItem>
                </BreadcrumbList>
            </Breadcrumb>

            <div
                class="mt-5 grid gap-8 lg:mt-8 lg:grid-cols-[1.1fr_0.9fr] lg:gap-14"
            >
                <ProductGallery
                    :imagenes="producto.imagenes"
                    :nombre="producto.nombre"
                />

                <!-- Información de compra -->
                <div
                    class="rounded-3xl bg-card p-5 ring-1 shadow-brand-sm ring-border/70 sm:p-7 lg:sticky lg:top-28 lg:self-start"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            v-if="producto.marca"
                            :href="urls.marca(producto.marca.slug)"
                            class="text-xs font-bold tracking-[0.14em] text-brand-green-strong uppercase hover:underline"
                        >
                            {{ producto.marca.nombre }}
                        </Link>
                        <span
                            v-if="producto.tiene_oferta && descuento > 0"
                            class="rounded-full bg-brand-ink px-2.5 py-1 text-[11px] font-bold text-white"
                            >−{{ descuento }}%</span
                        >
                    </div>

                    <h1
                        class="mt-2 font-display text-[1.9rem] leading-[1.08] font-bold tracking-tight text-balance sm:text-4xl"
                    >
                        {{ producto.nombre }}
                    </h1>
                    <p
                        v-if="producto.modelo"
                        class="mt-1 text-sm text-muted-foreground"
                    >
                        Modelo {{ producto.modelo }}
                    </p>

                    <!-- Precio -->
                    <div class="mt-5 flex flex-wrap items-end gap-x-3 gap-y-1">
                        <p
                            class="font-display text-4xl leading-none font-bold sm:text-[2.75rem]"
                        >
                            {{ moneda(producto.precio_final) }}
                        </p>
                        <p
                            v-if="
                                producto.tiene_oferta &&
                                producto.precio_original
                            "
                            class="pb-1 text-lg text-muted-foreground line-through"
                        >
                            {{ moneda(producto.precio_original) }}
                        </p>
                    </div>
                    <p
                        v-if="producto.tiene_oferta && producto.oferta"
                        class="mt-2 text-sm font-medium text-brand-green-strong"
                    >
                        Ahorras {{ moneda(producto.descuento) }} ·
                        {{ producto.oferta.nombre
                        }}<template v-if="terminaOferta">
                            · válido hasta el {{ terminaOferta }}</template
                        >
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Precio en MXN.
                    </p>

                    <p
                        v-if="producto.descripcion"
                        class="mt-5 text-[15px] leading-relaxed text-foreground/80"
                    >
                        {{ producto.descripcion }}
                    </p>

                    <!-- Disponibilidad -->
                    <div
                        class="mt-6 flex items-start gap-3 rounded-2xl p-4"
                        :class="{
                            'bg-brand-green-soft':
                                producto.disponibilidad === 'en_stock',
                            'bg-brand-teal-soft': bajoPedido,
                            'bg-muted': agotado,
                        }"
                    >
                        <CircleCheck
                            v-if="producto.disponibilidad === 'en_stock'"
                            class="mt-0.5 size-5 shrink-0 text-brand-green-strong"
                            aria-hidden="true"
                        />
                        <Clock3
                            v-else-if="bajoPedido"
                            class="mt-0.5 size-5 shrink-0 text-brand-teal-strong"
                            aria-hidden="true"
                        />
                        <CircleAlert
                            v-else
                            class="mt-0.5 size-5 shrink-0 text-muted-foreground"
                            aria-hidden="true"
                        />
                        <div class="text-sm">
                            <p class="font-semibold">
                                <template
                                    v-if="
                                        producto.disponibilidad === 'en_stock'
                                    "
                                >
                                    En existencia
                                    <span
                                        v-if="
                                            producto.stock !== null &&
                                            producto.stock <= 5
                                        "
                                        class="font-normal text-warning"
                                    >
                                        ·
                                        {{
                                            producto.stock === 1
                                                ? 'última pieza'
                                                : `quedan ${producto.stock}`
                                        }}
                                    </span>
                                </template>
                                <template v-else-if="bajoPedido">
                                    Bajo pedido<template
                                        v-if="producto.tiempo_entrega_dias"
                                    >
                                        · entrega estimada
                                        {{ producto.tiempo_entrega_dias }}
                                        días</template
                                    >
                                </template>
                                <template v-else>Agotado por ahora</template>
                            </p>
                            <p
                                v-if="agotado"
                                class="mt-0.5 text-muted-foreground"
                            >
                                Vuelve pronto o explora productos similares.
                            </p>
                        </div>
                    </div>

                    <!-- Cantidad + agregar -->
                    <div
                        ref="botonPrincipal"
                        class="mt-6 flex flex-col gap-3 sm:flex-row"
                    >
                        <div
                            class="flex h-14 items-center justify-between rounded-full border bg-card px-1.5 sm:w-40"
                            :class="{ 'opacity-50': !puedeComprar }"
                        >
                            <button
                                type="button"
                                class="inline-flex size-11 items-center justify-center rounded-full hover:bg-muted disabled:opacity-40"
                                :disabled="!puedeComprar || cantidad <= 1"
                                aria-label="Disminuir cantidad"
                                @click="cambiarCantidad(-1)"
                            >
                                <Minus class="size-4" />
                            </button>
                            <label for="cantidad" class="sr-only"
                                >Cantidad</label
                            >
                            <input
                                id="cantidad"
                                v-model.number="cantidad"
                                type="number"
                                inputmode="numeric"
                                min="1"
                                :max="producto.cantidad_maxima || 1"
                                class="w-12 [appearance:textfield] bg-transparent text-center text-base font-semibold tabular-nums outline-none [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none"
                                :disabled="!puedeComprar"
                                @blur="normalizarCantidad"
                            />
                            <button
                                type="button"
                                class="inline-flex size-11 items-center justify-center rounded-full hover:bg-muted disabled:opacity-40"
                                :disabled="
                                    !puedeComprar ||
                                    cantidad >= producto.cantidad_maxima
                                "
                                aria-label="Aumentar cantidad"
                                @click="cambiarCantidad(1)"
                            >
                                <Plus class="size-4" />
                            </button>
                        </div>

                        <button
                            type="button"
                            class="group shine inline-flex h-14 w-full shrink-0 items-center justify-center gap-2 rounded-full bg-primary px-8 text-base font-semibold text-primary-foreground shadow-[0_12px_30px_-12px_rgba(115,200,58,0.9)] transition-[transform,background-color] hover:bg-primary/90 active:scale-[0.98] disabled:cursor-not-allowed disabled:bg-muted disabled:text-muted-foreground disabled:shadow-none sm:w-auto sm:flex-1"
                            :disabled="!puedeComprar || enviando !== null"
                            @click="comprar"
                        >
                            <Loader2
                                v-if="enviando !== null"
                                class="size-5 animate-spin"
                            />
                            <ShoppingBag
                                v-else
                                class="size-5 transition-transform group-hover:-rotate-6"
                            />
                            {{ agotado ? 'Agotado' : 'Agregar al carrito' }}
                        </button>
                    </div>

                    <!-- Confianza (sólo hechos reales) -->
                    <ul class="mt-6 grid gap-3 text-sm sm:grid-cols-2">
                        <li
                            v-if="metodosPago.length"
                            class="flex hover-lift items-start gap-3 rounded-2xl border border-border/70 bg-surface/60 p-3.5"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-surface text-brand-green-strong [&>svg]:size-[18px]"
                            >
                                <MetodoPagoIcono
                                    :clave="metodosPago[0].clave"
                                />
                            </span>
                            <span>
                                <span class="block font-semibold"
                                    >Formas de pago</span
                                >
                                <span class="text-muted-foreground">{{
                                    metodosPago.map((m) => m.nombre).join(' · ')
                                }}</span>
                            </span>
                        </li>
                        <li
                            class="flex hover-lift items-start gap-3 rounded-2xl border border-border/70 bg-surface/60 p-3.5"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-surface text-brand-green-strong"
                            >
                                <PackageCheck class="size-[18px]" />
                            </span>
                            <span>
                                <span class="block font-semibold"
                                    >Seguimiento en línea</span
                                >
                                <span class="text-muted-foreground"
                                    >Consulta el estado de tu pedido desde tu
                                    cuenta.</span
                                >
                            </span>
                        </li>
                    </ul>

                    <div
                        v-if="enlaceEnvios || enlaceDevoluciones"
                        class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm"
                    >
                        <Link
                            v-if="enlaceEnvios"
                            :href="enlaceEnvios"
                            class="inline-flex items-center gap-1.5 font-semibold text-brand-green-strong hover:underline"
                        >
                            <Truck class="size-4" /> Envíos y entregas
                        </Link>
                        <Link
                            v-if="enlaceDevoluciones"
                            :href="enlaceDevoluciones"
                            class="font-semibold text-brand-green-strong hover:underline"
                        >
                            Cambios y devoluciones
                        </Link>
                    </div>
                </div>
            </div>

            <!-- Descripción y especificaciones -->
            <section
                v-if="descripcionCompleta || especificaciones.length"
                class="mt-14 sm:mt-20"
                aria-label="Información del producto"
            >
                <Tabs
                    :default-value="
                        descripcionCompleta ? 'descripcion' : 'especificaciones'
                    "
                >
                    <TabsList
                        class="h-12 w-full justify-start gap-1 rounded-full bg-card p-1 ring-1 shadow-brand-sm ring-border/70 sm:w-fit"
                    >
                        <TabsTrigger
                            v-if="descripcionCompleta"
                            value="descripcion"
                            class="h-10 flex-1 rounded-full px-5 text-sm font-semibold sm:flex-none"
                        >
                            Descripción
                        </TabsTrigger>
                        <TabsTrigger
                            v-if="especificaciones.length"
                            value="especificaciones"
                            class="h-10 flex-1 rounded-full px-5 text-sm font-semibold sm:flex-none"
                        >
                            Especificaciones
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent
                        v-if="descripcionCompleta"
                        value="descripcion"
                        class="mt-6 animate-in duration-300 fade-in"
                    >
                        <div
                            class="max-w-3xl rounded-3xl bg-card p-6 text-[15px] leading-[1.8] whitespace-pre-line text-foreground/85 ring-1 shadow-brand-sm ring-border/70 sm:p-8"
                        >
                            {{ descripcionCompleta }}
                        </div>
                    </TabsContent>

                    <TabsContent
                        v-if="especificaciones.length"
                        value="especificaciones"
                        class="mt-6 animate-in duration-300 fade-in"
                    >
                        <dl
                            class="grid max-w-4xl overflow-hidden rounded-3xl border border-border/70 bg-card shadow-brand-sm sm:grid-cols-2"
                        >
                            <div
                                v-for="(dato, i) in especificaciones"
                                :key="`${dato.nombre}-${i}`"
                                class="flex flex-col gap-0.5 border-b border-border/70 px-5 py-4 transition-colors duration-200 hover:bg-brand-green-soft/50 sm:odd:border-r"
                            >
                                <dt
                                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ dato.nombre }}
                                </dt>
                                <dd class="text-[15px] font-medium">
                                    {{ dato.valor }}
                                </dd>
                            </div>
                        </dl>
                    </TabsContent>
                </Tabs>
            </section>

            <!-- Aviso de pagos -->
            <div class="mt-10 max-w-3xl">
                <AvisoTarjetas />
            </div>

            <!-- Relacionados -->
            <section
                v-if="relacionados.length"
                class="mt-16 sm:mt-24"
                aria-labelledby="relacionados"
            >
                <SectionHeading
                    id="relacionados"
                    antetitulo="También te puede gustar"
                    titulo="Productos relacionados"
                    :enlace="
                        producto.categoria
                            ? urls.categoria(producto.categoria.slug)
                            : null
                    "
                    texto-enlace="Ver la categoría"
                />
                <ProductRail
                    :productos="relacionados"
                    etiqueta="Productos relacionados"
                />
            </section>
        </div>

        <!-- Barra de compra fija (móvil) -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-full"
            leave-active-class="transition duration-200 ease-in"
            leave-to-class="translate-y-full"
        >
            <div
                v-if="barraVisible && puedeComprar"
                class="fixed inset-x-0 bottom-0 z-[9999] border-t bg-background/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-12px_30px_-20px_rgba(0,0,0,0.4)] backdrop-blur lg:hidden"
            >
                <div class="mx-auto flex max-w-xl items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">
                            {{ producto.nombre }}
                        </p>
                        <p class="font-display text-lg leading-tight font-bold">
                            {{ moneda(producto.precio_final) }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-12 shrink-0 items-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-primary-foreground active:scale-95 disabled:opacity-60"
                        :disabled="enviando !== null"
                        @click="comprar"
                    >
                        <Loader2
                            v-if="enviando !== null"
                            class="size-4 animate-spin"
                        />
                        <ShoppingBag v-else class="size-4" />
                        Agregar
                    </button>
                </div>
            </div>
        </Transition>
    </PublicLayout>
</template>
