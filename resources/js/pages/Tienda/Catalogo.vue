<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    ChevronLeft,
    ChevronRight,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import CatalogFilters from '@/components/public/CatalogFilters.vue';
import ProductCard from '@/components/public/ProductCard.vue';
import ProductCardSkeleton from '@/components/public/ProductCardSkeleton.vue';
import { moneda, urls } from '@/components/public/tienda';
import type {
    CategoriaPublica,
    FiltrosCatalogo,
    MarcaPublica,
    ProductoTarjeta,
} from '@/components/public/types';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import mascota from '@/img/mascota-mr-lana.webp';
import PublicLayout from '@/layouts/PublicLayout.vue';

interface EnlacePaginacion {
    url: string | null;
    label: string;
    active: boolean;
}

const props = defineProps<{
    productos: {
        data: ProductoTarjeta[];
        meta: {
            pagina_actual: number;
            ultima_pagina: number;
            por_pagina: number;
            total: number;
            desde: number | null;
            hasta: number | null;
        };
        links: EnlacePaginacion[];
    };
    filtros: FiltrosCatalogo;
    categorias: CategoriaPublica[];
    marcas: MarcaPublica[];
    rangoPrecio: { min: number; max: number };
    categoriaActual: {
        nombre: string;
        slug: string;
        descripcion: string | null;
    } | null;
}>();

const ORDENES = [
    { valor: 'relevancia', etiqueta: 'Relevancia' },
    { valor: 'nuevos', etiqueta: 'Más nuevos' },
    { valor: 'precio_asc', etiqueta: 'Precio: menor a mayor' },
    { valor: 'precio_desc', etiqueta: 'Precio: mayor a menor' },
    { valor: 'nombre', etiqueta: 'Nombre (A-Z)' },
];

const panelMovil = ref(false);
const cargando = ref(false);
const busqueda = ref(props.filtros.q ?? '');
const orden = ref(props.filtros.orden);
const resultados = ref<HTMLElement | null>(null);

watch(
    () => props.filtros,
    (f) => {
        busqueda.value = f.q ?? '';
        orden.value = f.orden;
    },
);

const titulo = computed(() => {
    if (props.filtros.q) {
        return `Resultados para “${props.filtros.q}”`;
    }

    return props.categoriaActual?.nombre ?? 'Catálogo';
});

const nombreDe = (
    lista: { slug: string; nombre: string }[],
    slug: string | null,
) => lista.find((x) => x.slug === slug)?.nombre ?? slug ?? '';

const chips = computed(() => {
    const f = props.filtros;
    const lista: {
        clave: keyof FiltrosCatalogo | 'precio';
        etiqueta: string;
    }[] = [];

    if (f.q) {
        lista.push({ clave: 'q', etiqueta: `“${f.q}”` });
    }

    if (f.categoria) {
        lista.push({
            clave: 'categoria',
            etiqueta: nombreDe(props.categorias, f.categoria),
        });
    }

    if (f.marca) {
        lista.push({
            clave: 'marca',
            etiqueta: nombreDe(props.marcas, f.marca),
        });
    }

    if (f.disponibilidad) {
        const etiquetas = {
            en_stock: 'En existencia',
            bajo_pedido: 'Bajo pedido',
            agotado: 'Agotado',
        } as const;
        lista.push({
            clave: 'disponibilidad',
            etiqueta: etiquetas[f.disponibilidad],
        });
    }

    if (f.precio_min !== null || f.precio_max !== null) {
        const desde =
            f.precio_min !== null
                ? moneda(f.precio_min)
                : moneda(props.rangoPrecio.min);
        const hasta =
            f.precio_max !== null
                ? moneda(f.precio_max)
                : moneda(props.rangoPrecio.max);
        lista.push({ clave: 'precio', etiqueta: `${desde} – ${hasta}` });
    }

    return lista;
});

const filtrosActivos = computed(
    () => chips.value.filter((c) => c.clave !== 'q').length,
);

function parametros(f: FiltrosCatalogo): Record<string, string | number> {
    const salida: Record<string, string | number> = {};

    for (const [clave, valor] of Object.entries(f)) {
        if (
            valor === null ||
            valor === '' ||
            (clave === 'orden' && valor === 'relevancia')
        ) {
            continue;
        }

        salida[clave] = valor as string | number;
    }

    return salida;
}

function aplicar(
    parcial: Partial<FiltrosCatalogo>,
    opciones: { pagina?: number } = {},
) {
    const siguiente: FiltrosCatalogo = { ...props.filtros, ...parcial };
    const datos = parametros(siguiente);

    if (opciones.pagina && opciones.pagina > 1) {
        datos.page = opciones.pagina;
    }

    router.get(urls.catalogo, datos, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['productos', 'filtros', 'categoriaActual', 'seo'],
        onSuccess: () => {
            if (opciones.pagina) {
                resultados.value?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
            }
        },
    });
}

function quitar(clave: keyof FiltrosCatalogo | 'precio') {
    if (clave === 'precio') {
        aplicar({ precio_min: null, precio_max: null });

        return;
    }

    aplicar({ [clave]: null } as Partial<FiltrosCatalogo>);
}

function limpiarTodo() {
    router.get(urls.catalogo, {}, { preserveScroll: true, replace: true });
}

const buscarConRetraso = useDebounceFn((valor: string) => {
    const q = valor.trim();

    if (q !== (props.filtros.q ?? '')) {
        aplicar({ q: q || null });
    }
}, 450);

watch(busqueda, (valor) => buscarConRetraso(valor));
watch(orden, (valor) => {
    if (valor !== props.filtros.orden) {
        aplicar({ orden: valor });
    }
});

function irAPagina(enlace: EnlacePaginacion) {
    if (!enlace.url || enlace.active) {
        return;
    }

    const pagina = Number(
        new URL(enlace.url, window.location.origin).searchParams.get('page') ??
            1,
    );
    aplicar({}, { pagina });
}

const paginas = computed(() =>
    props.productos.links.filter((l) => /^\d+$|^\.\.\.$/.test(l.label.trim())),
);
const anterior = computed(() => props.productos.links[0] ?? null);
const siguiente = computed(
    () => props.productos.links[props.productos.links.length - 1] ?? null,
);

let quitarInicio: (() => void) | null = null;
let quitarFin: (() => void) | null = null;

onMounted(() => {
    quitarInicio = router.on('start', (evento) => {
        const url = evento.detail.visit.url;

        if (
            url.pathname === urls.catalogo &&
            evento.detail.visit.method === 'get'
        ) {
            cargando.value = true;
        }
    });
    quitarFin = router.on('finish', () => {
        cargando.value = false;
    });
});

onBeforeUnmount(() => {
    quitarInicio?.();
    quitarFin?.();
});
</script>

<template>
    <PublicLayout>
        <!-- Encabezado -->
        <section
            class="relative overflow-hidden bg-brand-deep-pattern text-white"
        >
            <div
                class="pointer-events-none absolute -top-32 -right-24 size-96 rounded-full bg-brand-teal/20 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="relative mx-auto w-full max-w-7xl px-4 pt-6 pb-10 sm:px-6 sm:pb-12 lg:px-8"
            >
                <Breadcrumb>
                    <BreadcrumbList class="text-white/70">
                        <BreadcrumbItem>
                            <BreadcrumbLink as-child class="hover:text-white">
                                <Link :href="urls.home">Inicio</Link>
                            </BreadcrumbLink>
                        </BreadcrumbItem>
                        <BreadcrumbSeparator class="text-white/40" />
                        <BreadcrumbItem>
                            <BreadcrumbLink
                                v-if="categoriaActual || filtros.q"
                                as-child
                                class="hover:text-white"
                            >
                                <Link :href="urls.catalogo">Catálogo</Link>
                            </BreadcrumbLink>
                            <BreadcrumbPage v-else class="text-white"
                                >Catálogo</BreadcrumbPage
                            >
                        </BreadcrumbItem>
                        <template v-if="categoriaActual">
                            <BreadcrumbSeparator class="text-white/40" />
                            <BreadcrumbItem>
                                <BreadcrumbPage class="text-white">{{
                                    categoriaActual.nombre
                                }}</BreadcrumbPage>
                            </BreadcrumbItem>
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>

                <div
                    class="mt-6 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"
                >
                    <div class="max-w-2xl">
                        <h1
                            class="font-display text-[2rem] leading-[1.05] font-bold tracking-tight text-balance sm:text-5xl"
                        >
                            {{ titulo }}
                        </h1>
                        <p class="mt-2 text-sm text-white/75 sm:text-base">
                            <template v-if="productos.meta.total > 0">
                                {{ productos.meta.total }}
                                {{
                                    productos.meta.total === 1
                                        ? 'producto'
                                        : 'productos'
                                }}
                                <template v-if="categoriaActual?.descripcion">
                                    ·
                                    {{ categoriaActual.descripcion }}</template
                                >
                            </template>
                            <template v-else
                                >Sin resultados con estos filtros</template
                            >
                        </p>
                    </div>

                    <form
                        class="w-full lg:max-w-md"
                        role="search"
                        @submit.prevent="
                            aplicar({ q: busqueda.trim() || null })
                        "
                    >
                        <label for="buscar-catalogo" class="sr-only"
                            >Buscar en el catálogo</label
                        >
                        <div
                            class="flex h-12 items-center gap-2 rounded-full bg-white/95 pr-2 pl-4 text-foreground shadow-lg ring-1 ring-white/20 focus-within:ring-4 focus-within:ring-brand-green/40 dark:bg-card"
                        >
                            <Search
                                class="size-[18px] text-muted-foreground"
                                aria-hidden="true"
                            />
                            <input
                                id="buscar-catalogo"
                                v-model="busqueda"
                                type="search"
                                enterkeyhint="search"
                                autocomplete="off"
                                placeholder="Nombre, modelo, SKU o descripción"
                                class="h-full min-w-0 flex-1 bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                            />
                            <button
                                v-if="busqueda"
                                type="button"
                                class="inline-flex size-8 items-center justify-center rounded-full text-muted-foreground hover:bg-muted"
                                aria-label="Borrar búsqueda"
                                @click="busqueda = ''"
                            >
                                <X class="size-4" />
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <div
            ref="resultados"
            class="mx-auto grid w-full max-w-7xl scroll-mt-24 gap-8 px-4 pt-6 sm:px-6 lg:grid-cols-[260px_1fr] lg:gap-10 lg:px-8 lg:pt-10"
        >
            <!-- Filtros de escritorio -->
            <aside class="hidden lg:block" aria-label="Filtros">
                <div
                    class="sticky top-28 max-h-[calc(100svh-8rem)] overflow-y-auto pr-2 pb-6"
                >
                    <div class="mb-6 flex items-center justify-between">
                        <h2 class="font-display text-xl font-bold">Filtros</h2>
                        <button
                            v-if="filtrosActivos > 0"
                            type="button"
                            class="text-sm font-semibold text-brand-green-strong hover:underline"
                            @click="limpiarTodo"
                        >
                            Limpiar
                        </button>
                    </div>
                    <CatalogFilters
                        :filtros="filtros"
                        :categorias="categorias"
                        :marcas="marcas"
                        :rango-precio="rangoPrecio"
                        @cambiar="aplicar"
                    />
                </div>
            </aside>

            <div class="min-w-0">
                <!-- Barra de herramientas -->
                <div
                    class="sticky top-16 z-20 -mx-4 flex items-center gap-2 border-b border-border/60 bg-background/90 px-4 py-3 backdrop-blur-md sm:-mx-6 sm:px-6 lg:static lg:mx-0 lg:border-0 lg:bg-transparent lg:px-0 lg:py-0 lg:backdrop-blur-none"
                >
                    <button
                        type="button"
                        class="inline-flex h-10 items-center gap-2 rounded-full border bg-background px-4 text-sm font-semibold hover:bg-muted lg:hidden"
                        @click="panelMovil = true"
                    >
                        <SlidersHorizontal class="size-4" />
                        Filtros
                        <span
                            v-if="filtrosActivos"
                            class="inline-flex size-5 items-center justify-center rounded-full bg-primary text-[11px] font-bold text-primary-foreground"
                            >{{ filtrosActivos }}</span
                        >
                    </button>

                    <p
                        class="hidden text-sm text-muted-foreground lg:block"
                        aria-live="polite"
                    >
                        <template v-if="productos.meta.total > 0">
                            Mostrando {{ productos.meta.desde }}–{{
                                productos.meta.hasta
                            }}
                            de
                            {{ productos.meta.total }}
                        </template>
                    </p>

                    <div class="ml-auto flex items-center gap-2">
                        <label
                            for="orden"
                            class="hidden text-sm text-muted-foreground sm:inline"
                            >Ordenar por</label
                        >
                        <NativeSelect
                            id="orden"
                            v-model="orden"
                            class="h-10 rounded-full bg-background pl-4 text-sm font-medium"
                            aria-label="Ordenar productos"
                        >
                            <NativeSelectOption
                                v-for="o in ORDENES"
                                :key="o.valor"
                                :value="o.valor"
                                >{{ o.etiqueta }}</NativeSelectOption
                            >
                        </NativeSelect>
                    </div>
                </div>

                <!-- Chips de filtros activos -->
                <TransitionGroup
                    v-if="chips.length"
                    tag="ul"
                    class="mt-4 flex flex-wrap items-center gap-2"
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0 scale-90"
                    leave-active-class="transition duration-150 ease-in absolute"
                    leave-to-class="opacity-0 scale-90"
                    move-class="transition-transform duration-200"
                    aria-label="Filtros activos"
                >
                    <li v-for="chip in chips" :key="chip.clave">
                        <button
                            type="button"
                            class="inline-flex h-8 items-center gap-1.5 rounded-full bg-brand-green-soft pr-2 pl-3 text-sm font-medium text-foreground transition-colors hover:bg-brand-green/30"
                            :aria-label="`Quitar filtro ${chip.etiqueta}`"
                            @click="quitar(chip.clave)"
                        >
                            {{ chip.etiqueta }}
                            <X class="size-3.5" aria-hidden="true" />
                        </button>
                    </li>
                    <li key="limpiar">
                        <button
                            type="button"
                            class="h-8 px-2 text-sm font-semibold text-brand-green-strong hover:underline"
                            @click="limpiarTodo"
                        >
                            Limpiar todo
                        </button>
                    </li>
                </TransitionGroup>

                <!-- Resultados -->
                <div class="mt-6" :aria-busy="cargando">
                    <ul
                        v-if="cargando"
                        class="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-4"
                        aria-label="Cargando productos"
                    >
                        <li
                            v-for="n in Math.min(productos.data.length || 6, 9)"
                            :key="n"
                        >
                            <ProductCardSkeleton />
                        </li>
                    </ul>

                    <TransitionGroup
                        v-else-if="productos.data.length"
                        tag="ul"
                        appear
                        class="grid grid-cols-2 gap-3 sm:gap-5 md:grid-cols-3 xl:grid-cols-3 2xl:grid-cols-4"
                        enter-active-class="transition duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none"
                        enter-from-class="opacity-0 translate-y-4"
                    >
                        <li
                            v-for="(producto, indice) in productos.data"
                            :key="producto.id"
                            :style="{
                                transitionDelay: `${Math.min(indice, 8) * 40}ms`,
                            }"
                        >
                            <ProductCard
                                :producto="producto"
                                :prioridad="indice < 4"
                            />
                        </li>
                    </TransitionGroup>

                    <!-- Estado vacío -->
                    <div
                        v-else
                        class="flex flex-col items-center rounded-3xl border border-dashed border-border bg-surface px-6 py-14 text-center"
                    >
                        <img
                            :src="mascota"
                            alt=""
                            width="710"
                            height="848"
                            class="h-32 w-auto animate-float"
                            loading="lazy"
                            decoding="async"
                        />
                        <h2 class="mt-6 font-display text-2xl font-bold">
                            No encontramos productos
                        </h2>
                        <p class="mt-2 max-w-md text-sm text-muted-foreground">
                            <template v-if="chips.length">
                                Prueba con otras palabras o quita algunos
                                filtros para ver más resultados.
                            </template>
                            <template v-else>
                                Aún no hay productos publicados. Vuelve pronto.
                            </template>
                        </p>
                        <button
                            v-if="chips.length"
                            type="button"
                            class="mt-6 inline-flex h-11 items-center rounded-full bg-primary px-6 text-sm font-semibold text-primary-foreground hover:bg-primary/90"
                            @click="limpiarTodo"
                        >
                            Quitar filtros
                        </button>
                    </div>

                    <!-- Paginación -->
                    <nav
                        v-if="productos.meta.ultima_pagina > 1"
                        class="mt-12 flex flex-col items-center gap-4"
                        aria-label="Paginación"
                    >
                        <ul class="flex items-center gap-1.5">
                            <li>
                                <button
                                    type="button"
                                    class="inline-flex size-10 items-center justify-center rounded-full border bg-background transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                                    :disabled="!anterior?.url"
                                    aria-label="Página anterior"
                                    @click="anterior && irAPagina(anterior)"
                                >
                                    <ChevronLeft class="size-5" />
                                </button>
                            </li>
                            <li
                                v-for="(enlace, i) in paginas"
                                :key="`${enlace.label}-${i}`"
                            >
                                <span
                                    v-if="!enlace.url"
                                    class="inline-flex size-10 items-center justify-center text-sm text-muted-foreground"
                                    >…</span
                                >
                                <button
                                    v-else
                                    type="button"
                                    class="inline-flex size-10 items-center justify-center rounded-full text-sm font-semibold transition-colors"
                                    :class="
                                        enlace.active
                                            ? 'bg-brand-ink text-white dark:bg-primary dark:text-primary-foreground'
                                            : 'hover:bg-muted'
                                    "
                                    :aria-current="
                                        enlace.active ? 'page' : undefined
                                    "
                                    :aria-label="`Página ${enlace.label}`"
                                    @click="irAPagina(enlace)"
                                >
                                    {{ enlace.label }}
                                </button>
                            </li>
                            <li>
                                <button
                                    type="button"
                                    class="inline-flex size-10 items-center justify-center rounded-full border bg-background transition-colors hover:bg-muted disabled:pointer-events-none disabled:opacity-40"
                                    :disabled="!siguiente?.url"
                                    aria-label="Página siguiente"
                                    @click="siguiente && irAPagina(siguiente)"
                                >
                                    <ChevronRight class="size-5" />
                                </button>
                            </li>
                        </ul>
                        <p class="text-xs text-muted-foreground">
                            Página {{ productos.meta.pagina_actual }} de
                            {{ productos.meta.ultima_pagina }}
                        </p>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Panel de filtros móvil -->
        <Sheet v-model:open="panelMovil">
            <SheetContent
                side="bottom"
                class="max-h-[88svh] gap-0 rounded-t-[28px] p-0"
            >
                <SheetHeader class="border-b px-5 py-4 text-left">
                    <SheetTitle class="font-display text-xl"
                        >Filtros</SheetTitle
                    >
                    <SheetDescription>
                        {{ productos.meta.total }}
                        {{
                            productos.meta.total === 1
                                ? 'resultado'
                                : 'resultados'
                        }}
                    </SheetDescription>
                </SheetHeader>
                <div class="overflow-y-auto px-5 py-6">
                    <CatalogFilters
                        :filtros="filtros"
                        :categorias="categorias"
                        :marcas="marcas"
                        :rango-precio="rangoPrecio"
                        @cambiar="aplicar"
                    />
                </div>
                <SheetFooter class="flex-row gap-2 border-t px-5 py-4">
                    <button
                        type="button"
                        class="inline-flex h-12 flex-1 items-center justify-center rounded-full border text-sm font-semibold hover:bg-muted"
                        :disabled="filtrosActivos === 0"
                        @click="limpiarTodo"
                    >
                        Limpiar
                    </button>
                    <button
                        type="button"
                        class="inline-flex h-12 flex-[2] items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground hover:bg-primary/90"
                        @click="panelMovil = false"
                    >
                        Ver {{ productos.meta.total }}
                        {{
                            productos.meta.total === 1
                                ? 'producto'
                                : 'productos'
                        }}
                    </button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    </PublicLayout>
</template>
