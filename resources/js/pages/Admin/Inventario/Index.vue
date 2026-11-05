<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Boxes,
    History,
    PackageX,
    Plus,
    TrendingDown,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AjusteInventarioDialog from '@/components/admin/catalogo/AjusteInventarioDialog.vue';
import EstadoBadge from '@/components/admin/catalogo/EstadoBadge.vue';
import {
    formatoFecha,
    visitarConFiltros,
} from '@/components/admin/catalogo/formato';
import Paginacion from '@/components/admin/catalogo/Paginacion.vue';
import type {
    MovimientoInventario,
    OpcionEnum,
    Paginado,
    ProductoInventario,
} from '@/components/admin/catalogo/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { index as inventarioIndex } from '@/routes/admin/inventario';
import { edit as editarProducto } from '@/routes/admin/productos';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inventario', href: inventarioIndex().url }],
    },
});

interface Filtros {
    producto_id: number | null;
    tipo: string;
    desde: string;
    hasta: string;
}

const props = defineProps<{
    movimientos: Paginado<MovimientoInventario>;
    filtros: Filtros;
    tipos: OpcionEnum[];
    productos: ProductoInventario[];
    resumen: {
        productos_inventario: number;
        unidades: number;
        agotados: number;
        bajo_minimo: number;
        movimientos_hoy: number;
    };
    bajo_minimo: ProductoInventario[];
    agotados: ProductoInventario[];
}>();

const filtros = ref<Filtros>({ ...props.filtros });

watch(
    () => props.filtros,
    (nuevos) => {
        filtros.value = { ...nuevos };
    },
);

function aplicar(): void {
    visitarConFiltros(inventarioIndex().url, { ...filtros.value });
}

function limpiar(): void {
    filtros.value = { producto_id: null, tipo: '', desde: '', hasta: '' };
    aplicar();
}

const hayFiltros = computed(
    () =>
        !!filtros.value.producto_id ||
        !!filtros.value.tipo ||
        !!filtros.value.desde ||
        !!filtros.value.hasta,
);

const tarjetas = computed(() => [
    {
        etiqueta: 'Productos con inventario',
        valor: props.resumen.productos_inventario,
        icono: Boxes,
        clase: 'text-brand-deep dark:text-brand-green',
    },
    {
        etiqueta: 'Unidades en existencia',
        valor: props.resumen.unidades,
        icono: Boxes,
        clase: 'text-brand-teal-strong',
    },
    {
        etiqueta: 'Publicados agotados',
        valor: props.resumen.agotados,
        icono: PackageX,
        clase: 'text-destructive',
    },
    {
        etiqueta: 'Bajo mínimo',
        valor: props.resumen.bajo_minimo,
        icono: TrendingDown,
        clase: 'text-amber-600',
    },
    {
        etiqueta: 'Movimientos hoy',
        valor: props.resumen.movimientos_hoy,
        icono: History,
        clase: 'text-brand-green-strong',
    },
]);

const ajusteAbierto = ref(false);
const productoAjuste = ref<ProductoInventario | null>(null);

function ajustar(producto: ProductoInventario | null = null): void {
    productoAjuste.value = producto;
    ajusteAbierto.value = true;
}
</script>

<template>
    <Head title="Inventario" />

    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-6">
        <header
            class="flex flex-col gap-4 rounded-2xl bg-brand-deep-pattern px-5 py-6 text-white md:flex-row md:items-end md:justify-between md:px-8"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-brand-green uppercase"
                >
                    Catálogo
                </p>
                <h1 class="font-display text-3xl font-bold md:text-4xl">
                    Inventario
                </h1>
                <p class="mt-1 max-w-xl text-sm text-white/75">
                    Bitácora de entradas, salidas, ajustes y reservas por
                    pedido. Las existencias sólo cambian con un movimiento
                    registrado.
                </p>
            </div>
            <Button @click="ajustar()"><Plus /> Nuevo movimiento</Button>
        </header>

        <dl class="grid grid-cols-2 gap-3 md:grid-cols-5">
            <div
                v-for="t in tarjetas"
                :key="t.etiqueta"
                class="rounded-xl border bg-card p-4"
            >
                <component :is="t.icono" class="size-5" :class="t.clase" />
                <dt class="mt-2 text-xs text-muted-foreground">
                    {{ t.etiqueta }}
                </dt>
                <dd class="font-display text-2xl font-bold tabular-nums">
                    {{ t.valor.toLocaleString('es-MX') }}
                </dd>
            </div>
        </dl>

        <!-- Alertas -->
        <div
            v-if="bajo_minimo.length || agotados.length"
            class="grid gap-4 lg:grid-cols-2"
        >
            <section
                v-for="grupo in [
                    {
                        titulo: 'Bajo mínimo',
                        items: bajo_minimo,
                        tono: 'text-amber-600',
                    },
                    {
                        titulo: 'Publicados agotados',
                        items: agotados,
                        tono: 'text-destructive',
                    },
                ].filter((g) => g.items.length)"
                :key="grupo.titulo"
                class="rounded-xl border bg-card p-4"
            >
                <h2 class="flex items-center gap-2 font-semibold">
                    <AlertTriangle class="size-4" :class="grupo.tono" />
                    {{ grupo.titulo }}
                </h2>
                <ul class="mt-3 divide-y">
                    <li
                        v-for="item in grupo.items"
                        :key="item.id"
                        class="flex items-center gap-3 py-2"
                    >
                        <img
                            v-if="item.imagen"
                            :src="item.imagen"
                            :alt="item.nombre"
                            class="size-10 rounded-md object-cover"
                            loading="lazy"
                        />
                        <div
                            v-else
                            class="grid size-10 place-items-center rounded-md bg-muted"
                        >
                            <Boxes class="size-4 text-muted-foreground" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="editarProducto(item.id).url"
                                class="line-clamp-1 text-sm font-medium hover:text-brand-green-strong"
                                >{{ item.nombre }}</Link
                            >
                            <p class="text-xs text-muted-foreground">
                                {{ item.sku }} · mínimo {{ item.stock_minimo }}
                            </p>
                        </div>
                        <span
                            class="font-display text-lg font-bold tabular-nums"
                            :class="grupo.tono"
                            >{{ item.stock }}</span
                        >
                        <Button
                            size="sm"
                            variant="outline"
                            @click="ajustar(item)"
                            >Ajustar</Button
                        >
                    </li>
                </ul>
            </section>
        </div>

        <!-- Filtros -->
        <section
            class="grid gap-3 rounded-xl border bg-card p-4 sm:grid-cols-2 lg:grid-cols-5"
            aria-label="Filtros de movimientos"
        >
            <div class="grid gap-1.5 sm:col-span-2">
                <Label for="filtro-producto" class="text-xs">Producto</Label>
                <NativeSelect
                    id="filtro-producto"
                    v-model="filtros.producto_id"
                    class="w-full"
                    @change="aplicar"
                >
                    <NativeSelectOption :value="null"
                        >Todos los productos</NativeSelectOption
                    >
                    <NativeSelectOption
                        v-for="p in productos"
                        :key="p.id"
                        :value="p.id"
                        >{{ p.nombre }} · {{ p.sku }}</NativeSelectOption
                    >
                </NativeSelect>
            </div>
            <div class="grid gap-1.5">
                <Label for="filtro-tipo" class="text-xs">Tipo</Label>
                <NativeSelect
                    id="filtro-tipo"
                    v-model="filtros.tipo"
                    class="w-full"
                    @change="aplicar"
                >
                    <NativeSelectOption value="">Todos</NativeSelectOption>
                    <NativeSelectOption
                        v-for="t in tipos"
                        :key="t.value"
                        :value="t.value"
                        >{{ t.label }}</NativeSelectOption
                    >
                </NativeSelect>
            </div>
            <div class="grid gap-1.5">
                <Label for="filtro-desde" class="text-xs">Desde</Label>
                <Input
                    id="filtro-desde"
                    v-model="filtros.desde"
                    type="date"
                    :max="filtros.hasta || undefined"
                    @change="aplicar"
                />
            </div>
            <div class="grid gap-1.5">
                <Label for="filtro-hasta" class="text-xs">Hasta</Label>
                <Input
                    id="filtro-hasta"
                    v-model="filtros.hasta"
                    type="date"
                    :min="filtros.desde || undefined"
                    @change="aplicar"
                />
            </div>
            <div v-if="hayFiltros" class="sm:col-span-2 lg:col-span-5">
                <Button variant="ghost" size="sm" @click="limpiar"
                    >Limpiar filtros</Button
                >
            </div>
        </section>

        <!-- Movimientos -->
        <div
            v-if="!movimientos.data.length"
            class="rounded-2xl border border-dashed bg-surface/60 px-6 py-14 text-center"
        >
            <History class="mx-auto size-10 text-brand-green" />
            <p class="mt-3 font-display text-xl font-semibold">
                Sin movimientos
            </p>
            <p class="mt-1 text-sm text-muted-foreground">
                Registra una entrada para empezar la bitácora.
            </p>
        </div>

        <div
            v-else
            class="hidden overflow-hidden rounded-xl border bg-card md:block"
        >
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/50 text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3 text-right">Cantidad</th>
                        <th class="px-4 py-3 text-right">Existencia</th>
                        <th class="px-4 py-3">Motivo</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="m in movimientos.data" :key="m.id">
                        <td
                            class="px-4 py-3 whitespace-nowrap text-muted-foreground"
                        >
                            {{ formatoFecha(m.fecha) }}
                        </td>
                        <td class="px-4 py-3">
                            <Link
                                v-if="m.producto"
                                :href="editarProducto(m.producto.id).url"
                                class="font-medium hover:text-brand-green-strong"
                                >{{ m.producto.nombre }}</Link
                            >
                            <p class="text-xs text-muted-foreground">
                                {{ m.producto?.sku }}
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <EstadoBadge
                                :tono="m.tipo.color"
                                :etiqueta="m.tipo.label"
                            />
                        </td>
                        <td
                            class="px-4 py-3 text-right font-semibold tabular-nums"
                            :class="
                                m.cantidad < 0
                                    ? 'text-destructive'
                                    : 'text-brand-green-strong'
                            "
                        >
                            {{ m.cantidad > 0 ? '+' : '' }}{{ m.cantidad }}
                        </td>
                        <td
                            class="px-4 py-3 text-right whitespace-nowrap tabular-nums"
                        >
                            <span class="text-muted-foreground">{{
                                m.stock_anterior
                            }}</span>
                            →
                            <span class="font-semibold">{{
                                m.stock_nuevo
                            }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <p class="line-clamp-2">{{ m.motivo ?? '—' }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ m.usuario ?? 'Sistema' }}
                                <span v-if="m.pedido">
                                    · Pedido {{ m.pedido.folio }}</span
                                >
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ul v-if="movimientos.data.length" class="grid gap-3 md:hidden">
            <li
                v-for="m in movimientos.data"
                :key="m.id"
                class="rounded-xl border bg-card p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="line-clamp-1 font-semibold">
                            {{ m.producto?.nombre ?? '—' }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatoFecha(m.fecha) }}
                        </p>
                    </div>
                    <span
                        class="font-display text-xl font-bold tabular-nums"
                        :class="
                            m.cantidad < 0
                                ? 'text-destructive'
                                : 'text-brand-green-strong'
                        "
                        >{{ m.cantidad > 0 ? '+' : '' }}{{ m.cantidad }}</span
                    >
                </div>
                <div class="mt-2 flex items-center justify-between gap-2">
                    <EstadoBadge
                        :tono="m.tipo.color"
                        :etiqueta="m.tipo.label"
                    />
                    <span class="text-sm tabular-nums"
                        >{{ m.stock_anterior }} →
                        <strong>{{ m.stock_nuevo }}</strong></span
                    >
                </div>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ m.motivo ?? '—' }} · {{ m.usuario ?? 'Sistema' }}
                </p>
            </li>
        </ul>

        <Paginacion
            :links="movimientos.links"
            :from="movimientos.from"
            :to="movimientos.to"
            :total="movimientos.total"
            etiqueta="movimientos"
        />
    </div>

    <AjusteInventarioDialog
        v-model:open="ajusteAbierto"
        :producto="productoAjuste"
        :productos="productos"
    />
</template>
