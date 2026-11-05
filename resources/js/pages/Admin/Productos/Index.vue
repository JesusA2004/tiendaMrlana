<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    Boxes,
    CheckCircle2,
    ClipboardCheck,
    DownloadCloud,
    ExternalLink,
    ImageOff,
    MoreHorizontal,
    PackagePlus,
    Pause,
    Pencil,
    Search,
    Send,
    Trash2,
    Zap,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AjusteInventarioDialog from '@/components/admin/catalogo/AjusteInventarioDialog.vue';
import CompletitudBar from '@/components/admin/catalogo/CompletitudBar.vue';
import ConfirmarAccion from '@/components/admin/catalogo/ConfirmarAccion.vue';
import EdicionRapidaDialog from '@/components/admin/catalogo/EdicionRapidaDialog.vue';
import EstadoBadge from '@/components/admin/catalogo/EstadoBadge.vue';
import {
    formatoMoneda,
    visitarConFiltros,
} from '@/components/admin/catalogo/formato';
import Paginacion from '@/components/admin/catalogo/Paginacion.vue';
import type {
    OpcionCatalogo,
    OpcionEnum,
    Paginado,
    ProductoFila,
    ProductoInventario,
} from '@/components/admin/catalogo/types';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { index as inventarioIndex } from '@/routes/admin/inventario';
import {
    create,
    destroy,
    edit,
    importar,
    index as productosIndex,
} from '@/routes/admin/productos';
import { enviar, pausar, publicar } from '@/routes/admin/productos/publicacion';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Productos', href: productosIndex().url }],
    },
});

interface Filtros {
    q: string;
    estado: string;
    categoria_id: number | null;
    marca_id: number | null;
    modalidad: string;
    stock: string;
    orden: string;
}

const props = defineProps<{
    productos: Paginado<ProductoFila>;
    filtros: Filtros;
    conteos: Record<string, number>;
    resumen: {
        agotados: number;
        bajo_minimo: number;
        importados_pendientes: number;
    };
    categorias: OpcionCatalogo[];
    marcas: OpcionCatalogo[];
    estados: OpcionEnum[];
    modalidades: OpcionEnum[];
}>();

const filtros = ref<Filtros>({ ...props.filtros });

watch(
    () => props.filtros,
    (nuevos) => {
        filtros.value = { ...nuevos };
    },
);

function aplicar(): void {
    visitarConFiltros(productosIndex().url, { ...filtros.value });
}

const buscar = useDebounceFn(aplicar, 350);

function cambiarEstado(estado: string): void {
    filtros.value.estado = estado;
    aplicar();
}

function limpiar(): void {
    filtros.value = {
        q: '',
        estado: filtros.value.estado,
        categoria_id: null,
        marca_id: null,
        modalidad: '',
        stock: '',
        orden: 'recientes',
    };
    aplicar();
}

const hayFiltros = computed(
    () =>
        !!filtros.value.q ||
        !!filtros.value.categoria_id ||
        !!filtros.value.marca_id ||
        !!filtros.value.modalidad ||
        !!filtros.value.stock,
);

const esCola = computed(() => props.filtros.estado === 'pendiente_aprobacion');

const pestanas = computed(() => [
    { value: '', label: 'Todos', total: props.conteos.todos ?? 0 },
    ...props.estados.map((e) => ({
        value: e.value,
        label: e.value === 'pendiente_aprobacion' ? 'Por aprobar' : e.label,
        total: props.conteos[e.value] ?? 0,
    })),
]);

const modalidadLabel = (valor: string) =>
    props.modalidades.find((m) => m.value === valor)?.label ?? valor;

// ------------------------------------------------------------- Acciones
const procesando = ref(false);
const opcionesVisita = {
    preserveScroll: true,
    onStart: () => (procesando.value = true),
    onFinish: () => (procesando.value = false),
};

function aprobar(p: ProductoFila): void {
    router.post(publicar(p.id).url, {}, opcionesVisita);
}

function pausarProducto(p: ProductoFila): void {
    router.post(pausar(p.id).url, {}, opcionesVisita);
}

function enviarAprobacion(p: ProductoFila): void {
    router.post(enviar(p.id).url, {}, opcionesVisita);
}

const rapidoAbierto = ref(false);
const productoRapido = ref<ProductoFila | null>(null);

function editarRapido(p: ProductoFila): void {
    productoRapido.value = p;
    rapidoAbierto.value = true;
}

const ajusteAbierto = ref(false);
const productoAjuste = ref<ProductoInventario | null>(null);

function ajustar(p: ProductoFila): void {
    productoAjuste.value = {
        id: p.id,
        nombre: p.nombre,
        sku: p.sku,
        stock: p.stock,
        stock_minimo: p.stock_minimo,
    };
    ajusteAbierto.value = true;
}

const eliminarAbierto = ref(false);
const productoEliminar = ref<ProductoFila | null>(null);

function pedirEliminar(p: ProductoFila): void {
    productoEliminar.value = p;
    eliminarAbierto.value = true;
}

function eliminar(): void {
    if (!productoEliminar.value) {
        return;
    }

    router.delete(destroy(productoEliminar.value.id).url, {
        ...opcionesVisita,
        onSuccess: () => (eliminarAbierto.value = false),
    });
}

const importarAbierto = ref(false);

function importarCatalogo(): void {
    router.post(
        importar().url,
        {},
        {
            ...opcionesVisita,
            onSuccess: () => (importarAbierto.value = false),
        },
    );
}

function stockClase(p: ProductoFila): string {
    if (p.modalidad_venta === 'bajo_pedido') {
        return 'text-brand-teal-strong';
    }

    if (p.stock <= 0) {
        return 'text-destructive';
    }

    if (p.stock_minimo > 0 && p.stock <= p.stock_minimo) {
        return 'text-amber-600';
    }

    return 'text-foreground';
}
</script>

<template>
    <Head title="Productos" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6">
        <!-- Encabezado -->
        <header
            class="relative overflow-hidden rounded-2xl bg-brand-deep-pattern px-5 py-6 text-white shadow-sm md:px-8"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.2em] text-brand-green uppercase"
                    >
                        Catálogo
                    </p>
                    <h1 class="font-display text-3xl font-bold md:text-4xl">
                        {{ esCola ? 'Cola de aprobación' : 'Productos' }}
                    </h1>
                    <p class="mt-1 max-w-xl text-sm text-white/75">
                        {{
                            esCola
                                ? 'Completa precio, categoría, imágenes y existencias para aprobar y publicar cada producto.'
                                : 'Administra fichas, precios, imágenes y publicación. Las existencias se ajustan con movimientos de inventario.'
                        }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        as-child
                        variant="outline"
                        class="border-white/30 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                    >
                        <Link :href="inventarioIndex().url"
                            ><Boxes /> Inventario</Link
                        >
                    </Button>
                    <Button as-child>
                        <Link :href="create().url"
                            ><PackagePlus /> Nuevo producto</Link
                        >
                    </Button>
                </div>
            </div>

            <dl class="mt-6 grid grid-cols-3 gap-3 text-center sm:max-w-lg">
                <div class="rounded-xl bg-white/10 px-3 py-2">
                    <dt class="text-[11px] text-white/70">Por aprobar</dt>
                    <dd class="font-display text-2xl font-bold">
                        {{ conteos.pendiente_aprobacion ?? 0 }}
                    </dd>
                </div>
                <div class="rounded-xl bg-white/10 px-3 py-2">
                    <dt class="text-[11px] text-white/70">Agotados</dt>
                    <dd class="font-display text-2xl font-bold">
                        {{ resumen.agotados }}
                    </dd>
                </div>
                <div class="rounded-xl bg-white/10 px-3 py-2">
                    <dt class="text-[11px] text-white/70">Bajo mínimo</dt>
                    <dd class="font-display text-2xl font-bold">
                        {{ resumen.bajo_minimo }}
                    </dd>
                </div>
            </dl>
        </header>

        <!-- Aviso de la cola -->
        <div
            v-if="esCola"
            class="flex flex-col gap-3 rounded-xl border border-brand-teal/40 bg-brand-teal-soft/60 p-4 text-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="flex gap-2">
                <ClipboardCheck
                    class="mt-0.5 size-4 shrink-0 text-brand-teal-strong"
                />
                <span>
                    <strong>{{ resumen.importados_pendientes }}</strong>
                    productos vienen del catálogo de muebles 2026. Sus precios
                    de referencia NO son precios de venta y no se muestran al
                    público; las fotos del PDF no se usan: sube imágenes
                    propias.
                </span>
            </p>
            <Button
                variant="outline"
                size="sm"
                class="shrink-0"
                @click="importarAbierto = true"
            >
                <DownloadCloud /> Reimportar catálogo
            </Button>
        </div>

        <!-- Pestañas de estado -->
        <nav
            class="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 md:mx-0 md:px-0"
            aria-label="Estado de publicación"
        >
            <button
                v-for="tab in pestanas"
                :key="tab.value"
                type="button"
                class="inline-flex shrink-0 items-center gap-2 rounded-full border px-4 py-1.5 text-sm font-medium transition"
                :class="
                    filtros.estado === tab.value
                        ? 'border-brand-deep bg-brand-deep text-white'
                        : 'bg-background hover:border-brand-green'
                "
                :aria-pressed="filtros.estado === tab.value"
                @click="cambiarEstado(tab.value)"
            >
                {{ tab.label }}
                <span
                    class="rounded-full px-1.5 text-xs tabular-nums"
                    :class="
                        filtros.estado === tab.value
                            ? 'bg-white/20'
                            : 'bg-muted text-muted-foreground'
                    "
                    >{{ tab.total }}</span
                >
            </button>
        </nav>

        <!-- Filtros -->
        <section
            class="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-6"
            aria-label="Filtros"
        >
            <div class="relative sm:col-span-2">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filtros.q"
                    type="search"
                    class="pl-9"
                    placeholder="Nombre, SKU, modelo o código…"
                    aria-label="Buscar productos"
                    @input="buscar"
                />
            </div>
            <NativeSelect
                v-model="filtros.categoria_id"
                class="w-full"
                aria-label="Categoría"
                @change="aplicar"
            >
                <NativeSelectOption :value="null"
                    >Todas las categorías</NativeSelectOption
                >
                <NativeSelectOption
                    v-for="c in categorias"
                    :key="c.id"
                    :value="c.id"
                    >{{ c.nombre }}</NativeSelectOption
                >
            </NativeSelect>
            <NativeSelect
                v-model="filtros.marca_id"
                class="w-full"
                aria-label="Marca"
                @change="aplicar"
            >
                <NativeSelectOption :value="null"
                    >Todas las marcas</NativeSelectOption
                >
                <NativeSelectOption
                    v-for="m in marcas"
                    :key="m.id"
                    :value="m.id"
                    >{{ m.nombre }}</NativeSelectOption
                >
            </NativeSelect>
            <NativeSelect
                v-model="filtros.modalidad"
                class="w-full"
                aria-label="Modalidad"
                @change="aplicar"
            >
                <NativeSelectOption value="">Toda modalidad</NativeSelectOption>
                <NativeSelectOption
                    v-for="m in modalidades"
                    :key="m.value"
                    :value="m.value"
                    >{{ m.label }}</NativeSelectOption
                >
            </NativeSelect>
            <NativeSelect
                v-model="filtros.stock"
                class="w-full"
                aria-label="Existencias"
                @change="aplicar"
            >
                <NativeSelectOption value=""
                    >Cualquier stock</NativeSelectOption
                >
                <NativeSelectOption value="agotado"
                    >Agotados</NativeSelectOption
                >
                <NativeSelectOption value="bajo_minimo"
                    >Bajo mínimo</NativeSelectOption
                >
            </NativeSelect>
            <div
                class="flex items-center justify-between gap-2 sm:col-span-2 lg:col-span-6"
            >
                <div class="flex items-center gap-2 text-sm">
                    <label for="orden" class="text-muted-foreground"
                        >Ordenar por</label
                    >
                    <NativeSelect
                        id="orden"
                        v-model="filtros.orden"
                        @change="aplicar"
                    >
                        <NativeSelectOption value="recientes"
                            >Más recientes</NativeSelectOption
                        >
                        <NativeSelectOption value="nombre"
                            >Nombre</NativeSelectOption
                        >
                        <NativeSelectOption value="precio"
                            >Precio</NativeSelectOption
                        >
                        <NativeSelectOption value="stock"
                            >Existencias</NativeSelectOption
                        >
                    </NativeSelect>
                </div>
                <Button
                    v-if="hayFiltros"
                    variant="ghost"
                    size="sm"
                    @click="limpiar"
                    >Limpiar filtros</Button
                >
            </div>
        </section>

        <!-- Vacío -->
        <div
            v-if="!productos.data.length"
            class="rounded-2xl border border-dashed bg-surface/60 px-6 py-14 text-center"
        >
            <Boxes class="mx-auto size-10 text-brand-green" />
            <p class="mt-3 font-display text-xl font-semibold">
                No hay productos con estos filtros
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                Ajusta la búsqueda o crea un producto nuevo.
            </p>
        </div>

        <!-- Tabla (escritorio) -->
        <div
            v-if="productos.data.length"
            class="hidden overflow-hidden rounded-xl border bg-card lg:block"
        >
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Precio</th>
                        <th class="px-4 py-3 text-right">Existencia</th>
                        <th class="px-4 py-3">
                            {{ esCola ? 'Listo para publicar' : 'Categoría' }}
                        </th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr
                        v-for="p in productos.data"
                        :key="p.id"
                        class="transition hover:bg-muted/30"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-lg border bg-muted"
                                >
                                    <img
                                        v-if="p.imagen"
                                        :src="p.imagen"
                                        :alt="p.nombre"
                                        class="size-full object-cover"
                                        loading="lazy"
                                    />
                                    <ImageOff
                                        v-else
                                        class="size-5 text-muted-foreground"
                                    />
                                </div>
                                <div class="min-w-0">
                                    <Link
                                        :href="edit(p.id).url"
                                        class="line-clamp-1 font-semibold hover:text-brand-green-strong"
                                        >{{ p.nombre }}</Link
                                    >
                                    <p class="text-xs text-muted-foreground">
                                        {{ p.sku }}
                                        <span v-if="p.codigo_referencia"
                                            >· Ref.
                                            {{ p.codigo_referencia }}</span
                                        >
                                        <span
                                            v-if="p.destacado"
                                            class="ml-1 font-semibold text-brand-green-strong"
                                            >★ Destacado</span
                                        >
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <EstadoBadge
                                :tono="p.estado.color"
                                :etiqueta="p.estado.label"
                                punto
                            />
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            <span
                                v-if="p.precio !== null"
                                class="font-semibold"
                                >{{ formatoMoneda(p.precio) }}</span
                            >
                            <span
                                v-else
                                class="text-xs text-amber-700 dark:text-amber-400"
                                >Sin precio</span
                            >
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            <span
                                v-if="p.modalidad_venta === 'bajo_pedido'"
                                class="text-xs font-semibold"
                                :class="stockClase(p)"
                                >{{ modalidadLabel(p.modalidad_venta) }}</span
                            >
                            <span
                                v-else
                                class="font-semibold"
                                :class="stockClase(p)"
                            >
                                {{ p.stock }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <div v-if="esCola" class="max-w-48">
                                <CompletitudBar
                                    :valor="p.completitud"
                                    compacta
                                />
                                <p
                                    v-if="p.faltantes.length"
                                    class="mt-1 line-clamp-1 text-xs text-muted-foreground"
                                    :title="p.faltantes.join(' ')"
                                >
                                    Falta: {{ p.faltantes.join(' ') }}
                                </p>
                            </div>
                            <span v-else class="text-muted-foreground">{{
                                p.categoria ?? 'Sin categoría'
                            }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1">
                                <Button
                                    v-if="
                                        p.estado.value !== 'publicado' &&
                                        p.faltantes.length === 0
                                    "
                                    size="sm"
                                    :disabled="procesando"
                                    @click="aprobar(p)"
                                >
                                    <CheckCircle2 /> Publicar
                                </Button>
                                <Button
                                    v-else-if="esCola"
                                    size="sm"
                                    variant="outline"
                                    @click="editarRapido(p)"
                                >
                                    <Zap /> Rápida
                                </Button>
                                <Button
                                    as-child
                                    size="icon-sm"
                                    variant="ghost"
                                    aria-label="Editar"
                                >
                                    <Link :href="edit(p.id).url"
                                        ><Pencil
                                    /></Link>
                                </Button>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button
                                            size="icon-sm"
                                            variant="ghost"
                                            aria-label="Más acciones"
                                        >
                                            <MoreHorizontal />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent
                                        align="end"
                                        class="w-52"
                                    >
                                        <DropdownMenuItem
                                            @click="editarRapido(p)"
                                        >
                                            <Zap /> Edición rápida
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="ajustar(p)">
                                            <Boxes /> Ajustar inventario
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="p.estado.value === 'borrador'"
                                            @click="enviarAprobacion(p)"
                                        >
                                            <Send /> Enviar a aprobación
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="
                                                p.estado.value === 'publicado'
                                            "
                                            @click="pausarProducto(p)"
                                        >
                                            <Pause /> Pausar
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            v-if="p.url_publica"
                                            as="a"
                                            :href="p.url_publica"
                                            target="_blank"
                                            rel="noopener"
                                        >
                                            <ExternalLink /> Ver en tienda
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem
                                            variant="destructive"
                                            @click="pedirEliminar(p)"
                                        >
                                            <Trash2 />
                                            {{
                                                p.tiene_pedidos
                                                    ? 'Pausar (tiene pedidos)'
                                                    : 'Eliminar'
                                            }}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Tarjetas (móvil/tablet) -->
        <ul
            v-if="productos.data.length"
            class="grid gap-3 sm:grid-cols-2 lg:hidden"
        >
            <li
                v-for="p in productos.data"
                :key="p.id"
                class="flex flex-col gap-3 rounded-xl border bg-card p-4"
            >
                <div class="flex gap-3">
                    <div
                        class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-lg border bg-muted"
                    >
                        <img
                            v-if="p.imagen"
                            :src="p.imagen"
                            :alt="p.nombre"
                            class="size-full object-cover"
                            loading="lazy"
                        />
                        <ImageOff v-else class="size-5 text-muted-foreground" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <Link
                            :href="edit(p.id).url"
                            class="line-clamp-2 font-semibold"
                            >{{ p.nombre }}</Link
                        >
                        <p class="text-xs text-muted-foreground">{{ p.sku }}</p>
                        <div class="mt-1.5">
                            <EstadoBadge
                                :tono="p.estado.color"
                                :etiqueta="p.estado.label"
                                punto
                            />
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Precio</dt>
                        <dd class="font-semibold">
                            {{
                                p.precio !== null
                                    ? formatoMoneda(p.precio)
                                    : 'Sin precio'
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Existencia
                        </dt>
                        <dd class="font-semibold" :class="stockClase(p)">
                            {{
                                p.modalidad_venta === 'bajo_pedido'
                                    ? modalidadLabel(p.modalidad_venta)
                                    : p.stock
                            }}
                        </dd>
                    </div>
                </dl>

                <div v-if="p.estado.value !== 'publicado'">
                    <CompletitudBar :valor="p.completitud" />
                    <ul
                        v-if="p.faltantes.length"
                        class="mt-1 list-inside list-disc text-xs text-muted-foreground"
                    >
                        <li v-for="f in p.faltantes" :key="f">{{ f }}</li>
                    </ul>
                </div>

                <div class="mt-auto flex flex-wrap gap-2">
                    <Button
                        v-if="
                            p.estado.value !== 'publicado' &&
                            p.faltantes.length === 0
                        "
                        size="sm"
                        :disabled="procesando"
                        @click="aprobar(p)"
                    >
                        <CheckCircle2 /> Publicar
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        @click="editarRapido(p)"
                    >
                        <Zap /> Rápida
                    </Button>
                    <Button size="sm" variant="outline" as-child>
                        <Link :href="edit(p.id).url"><Pencil /> Editar</Link>
                    </Button>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button
                                size="sm"
                                variant="ghost"
                                aria-label="Más acciones"
                            >
                                <MoreHorizontal />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-52">
                            <DropdownMenuItem @click="ajustar(p)">
                                <Boxes /> Ajustar inventario
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="p.estado.value === 'borrador'"
                                @click="enviarAprobacion(p)"
                            >
                                <Send /> Enviar a aprobación
                            </DropdownMenuItem>
                            <DropdownMenuItem
                                v-if="p.estado.value === 'publicado'"
                                @click="pausarProducto(p)"
                            >
                                <Pause /> Pausar
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                @click="pedirEliminar(p)"
                            >
                                <Trash2 />
                                {{
                                    p.tiene_pedidos
                                        ? 'Pausar (tiene pedidos)'
                                        : 'Eliminar'
                                }}
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </li>
        </ul>

        <Paginacion
            :links="productos.links"
            :from="productos.from"
            :to="productos.to"
            :total="productos.total"
            etiqueta="productos"
        />
    </div>

    <EdicionRapidaDialog
        v-model:open="rapidoAbierto"
        :producto="productoRapido"
        :categorias="categorias"
        :modalidades="modalidades"
    />

    <AjusteInventarioDialog
        v-model:open="ajusteAbierto"
        :producto="productoAjuste"
    />

    <ConfirmarAccion
        v-model:open="eliminarAbierto"
        :titulo="
            productoEliminar?.tiene_pedidos
                ? '¿Pausar este producto?'
                : '¿Eliminar este producto?'
        "
        :descripcion="
            productoEliminar?.tiene_pedidos
                ? 'Tiene pedidos registrados, así que no se puede eliminar: se pausará y dejará de mostrarse en la tienda.'
                : 'Se eliminarán el producto, sus imágenes y su bitácora de inventario. Esta acción no se puede deshacer.'
        "
        :confirmar="productoEliminar?.tiene_pedidos ? 'Pausar' : 'Eliminar'"
        destructiva
        :procesando="procesando"
        @confirmar="eliminar"
    />

    <ConfirmarAccion
        v-model:open="importarAbierto"
        titulo="¿Reimportar el catálogo de muebles?"
        descripcion="Crea los modelos que falten y actualiza sólo los datos de referencia. No cambia precios, existencias, imágenes, estados ni textos que ya editaste."
        confirmar="Reimportar"
        :procesando="procesando"
        @confirmar="importarCatalogo"
    />
</template>
