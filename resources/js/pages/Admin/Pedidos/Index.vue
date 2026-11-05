<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ChevronRight,
    ClipboardList,
    MapPin,
    Search,
    SlidersHorizontal,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import { dinero, fechaHora, haceCuanto } from '@/components/admin/formato';
import PageHeader from '@/components/admin/PageHeader.vue';
import Paginacion from '@/components/admin/Paginacion.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { TONO_PUNTO, tono } from '@/components/admin/tonos';
import type { Opcion, Paginado, PedidoFila } from '@/components/admin/types';
import { useFiltros } from '@/components/admin/useFiltros';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    index as pedidosIndex,
    show as pedidoShow,
} from '@/routes/admin/pedidos';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pedidos', href: pedidosIndex().url }],
    },
});

const props = defineProps<{
    pedidos: Paginado<PedidoFila>;
    filtros: {
        buscar: string;
        estatus: string;
        pago: string;
        logistico: string;
        metodo: string;
    };
    opciones: {
        estatus: Opcion[];
        estado_pago: Opcion[];
        estado_logistico: Opcion[];
        metodo_pago: Opcion[];
    };
    conteos: Record<string, number>;
    totalSinEstatus: number;
}>();

const { filtros, limpiar } = useFiltros(
    pedidosIndex().url,
    { ...props.filtros },
    {
        textos: ['buscar'],
    },
);

const mostrarFiltros = ref(
    Boolean(
        props.filtros.pago || props.filtros.logistico || props.filtros.metodo,
    ),
);

const filtrosActivos = computed(
    () =>
        [filtros.pago, filtros.logistico, filtros.metodo].filter(Boolean)
            .length,
);

const hayFiltros = computed(
    () =>
        filtrosActivos.value > 0 || Boolean(filtros.buscar || filtros.estatus),
);
</script>

<template>
    <Head title="Pedidos" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            eyebrow="Operación"
            title="Pedidos"
            description="Busca por folio, cliente o correo. Abre un pedido para ver sus pagos, comprobantes, historial y acciones disponibles."
        />

        <!-- Chips de estatus general -->
        <div class="-mx-4 overflow-x-auto px-4 pb-1 md:mx-0 md:px-0">
            <div class="flex w-max gap-2">
                <button
                    type="button"
                    :class="[
                        'flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition',
                        !filtros.estatus
                            ? 'border-transparent bg-brand-deep text-white'
                            : 'hover:bg-accent',
                    ]"
                    @click="filtros.estatus = ''"
                >
                    Todos
                    <span class="text-xs tabular-nums opacity-75">{{
                        totalSinEstatus
                    }}</span>
                </button>
                <button
                    v-for="e in opciones.estatus"
                    :key="e.value"
                    type="button"
                    :class="[
                        'flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium whitespace-nowrap transition',
                        filtros.estatus === e.value
                            ? 'border-transparent bg-brand-deep text-white'
                            : 'hover:bg-accent',
                        !(conteos[e.value] ?? 0) &&
                            filtros.estatus !== e.value &&
                            'opacity-55',
                    ]"
                    @click="
                        filtros.estatus =
                            filtros.estatus === e.value ? '' : e.value
                    "
                >
                    <span
                        :class="[
                            'size-2 rounded-full',
                            TONO_PUNTO[tono(e.color)],
                        ]"
                    />
                    {{ e.label }}
                    <span class="text-xs tabular-nums opacity-75">{{
                        conteos[e.value] ?? 0
                    }}</span>
                </button>
            </div>
        </div>

        <!-- Búsqueda y filtros -->
        <div class="flex flex-col gap-3 rounded-2xl border bg-card p-3">
            <div class="flex gap-2">
                <div class="relative flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="filtros.buscar"
                        type="search"
                        placeholder="Folio, nombre, correo o teléfono…"
                        class="pl-9"
                        aria-label="Buscar pedidos"
                    />
                </div>
                <Button
                    variant="outline"
                    :aria-expanded="mostrarFiltros"
                    @click="mostrarFiltros = !mostrarFiltros"
                >
                    <SlidersHorizontal class="size-4" />
                    <span class="hidden sm:inline">Filtros</span>
                    <span
                        v-if="filtrosActivos"
                        class="grid size-5 place-items-center rounded-full bg-primary text-[0.65rem] font-bold text-primary-foreground"
                        >{{ filtrosActivos }}</span
                    >
                </Button>
                <Button v-if="hayFiltros" variant="ghost" @click="limpiar">
                    <X class="size-4" />
                    <span class="hidden sm:inline">Limpiar</span>
                </Button>
            </div>
            <div v-if="mostrarFiltros" class="grid gap-3 sm:grid-cols-3">
                <label
                    class="grid gap-1 text-xs font-medium text-muted-foreground"
                >
                    Estado de pago
                    <NativeSelect v-model="filtros.pago" class="w-full">
                        <NativeSelectOption value="">Todos</NativeSelectOption>
                        <NativeSelectOption
                            v-for="o in opciones.estado_pago"
                            :key="o.value"
                            :value="o.value"
                            >{{ o.label }}</NativeSelectOption
                        >
                    </NativeSelect>
                </label>
                <label
                    class="grid gap-1 text-xs font-medium text-muted-foreground"
                >
                    Estado logístico
                    <NativeSelect v-model="filtros.logistico" class="w-full">
                        <NativeSelectOption value="">Todos</NativeSelectOption>
                        <NativeSelectOption
                            v-for="o in opciones.estado_logistico"
                            :key="o.value"
                            :value="o.value"
                            >{{ o.label }}</NativeSelectOption
                        >
                    </NativeSelect>
                </label>
                <label
                    class="grid gap-1 text-xs font-medium text-muted-foreground"
                >
                    Método de pago
                    <NativeSelect v-model="filtros.metodo" class="w-full">
                        <NativeSelectOption value="">Todos</NativeSelectOption>
                        <NativeSelectOption
                            v-for="o in opciones.metodo_pago"
                            :key="o.value"
                            :value="o.value"
                            >{{ o.label }}</NativeSelectOption
                        >
                    </NativeSelect>
                </label>
            </div>
        </div>

        <EmptyState
            v-if="pedidos.data.length === 0"
            :icon="ClipboardList"
            title="Sin pedidos"
            :description="
                hayFiltros
                    ? 'Ningún pedido coincide con la búsqueda o los filtros.'
                    : 'Cuando los clientes compren, sus pedidos aparecerán aquí.'
            "
        >
            <Button v-if="hayFiltros" variant="outline" @click="limpiar"
                >Quitar filtros</Button
            >
        </EmptyState>

        <template v-else>
            <!-- Tabla (escritorio) -->
            <div
                class="hidden overflow-hidden rounded-2xl border bg-card md:block"
            >
                <table class="w-full table-hover text-sm">
                    <thead
                        class="bg-surface text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3">Pedido</th>
                            <th class="px-4 py-3">Cliente</th>
                            <th class="px-4 py-3">Estatus</th>
                            <th class="px-4 py-3">Pago</th>
                            <th class="px-4 py-3">Logística</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="w-8 px-2 py-3">
                                <span class="sr-only">Abrir</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="p in pedidos.data"
                            :key="p.id"
                            class="group relative transition hover:bg-surface/70"
                        >
                            <td class="px-4 py-3 align-top">
                                <Link
                                    :href="pedidoShow(p.id).url"
                                    class="font-semibold text-brand-green-strong after:absolute after:inset-0 hover:underline"
                                    >{{ p.folio }}</Link
                                >
                                <p
                                    class="mt-0.5 text-xs text-muted-foreground"
                                    :title="fechaHora(p.created_at)"
                                >
                                    {{ haceCuanto(p.created_at) }} ·
                                    {{ p.items_count }}
                                    {{
                                        p.items_count === 1
                                            ? 'artículo'
                                            : 'artículos'
                                    }}
                                </p>
                            </td>
                            <td class="max-w-56 px-4 py-3 align-top">
                                <p class="truncate font-medium">
                                    {{ p.nombre_cliente }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ p.correo_cliente }}
                                </p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <StatusBadge :etiqueta="p.estatus" />
                                <p
                                    v-if="
                                        p.modalidad_entrega?.value ===
                                        'recoger_en_tienda'
                                    "
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    Recoge en tienda
                                </p>
                                <p
                                    v-else-if="p.destino"
                                    class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
                                >
                                    <MapPin class="size-3" />{{ p.destino }}
                                </p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <StatusBadge :etiqueta="p.estado_pago" />
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ p.metodo_pago?.label ?? 'Sin método' }}
                                </p>
                            </td>
                            <td class="px-4 py-3 align-top">
                                <StatusBadge :etiqueta="p.estado_logistico" />
                                <p
                                    v-if="
                                        p.envio_estado?.value === 'por_cotizar'
                                    "
                                    class="mt-1 text-xs font-medium text-warning"
                                >
                                    Envío por cotizar
                                </p>
                                <p
                                    v-else-if="p.numero_guia"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ p.paqueteria }} · {{ p.numero_guia }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-right align-top">
                                <p class="font-semibold tabular-nums">
                                    {{ dinero(p.total) }}
                                </p>
                                <p
                                    v-if="p.saldo_pendiente > 0"
                                    class="text-xs text-muted-foreground tabular-nums"
                                >
                                    Saldo {{ dinero(p.saldo_pendiente) }}
                                </p>
                            </td>
                            <td class="px-2 py-3 align-middle">
                                <ChevronRight
                                    class="size-4 text-muted-foreground transition group-hover:translate-x-0.5"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Tarjetas (móvil) -->
            <ul class="grid stagger-children gap-3 md:hidden">
                <li v-for="p in pedidos.data" :key="p.id">
                    <Link
                        :href="pedidoShow(p.id).url"
                        class="block rounded-2xl border bg-card p-4 transition active:scale-[0.99]"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p
                                    class="font-semibold text-brand-green-strong"
                                >
                                    {{ p.folio }}
                                </p>
                                <p class="truncate text-sm">
                                    {{ p.nombre_cliente }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ haceCuanto(p.created_at) }}
                                </p>
                            </div>
                            <p
                                class="font-display text-lg font-bold tabular-nums"
                            >
                                {{ dinero(p.total) }}
                            </p>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5">
                            <StatusBadge :etiqueta="p.estatus" />
                            <StatusBadge :etiqueta="p.estado_pago" />
                            <StatusBadge
                                v-if="p.estado_logistico?.value !== 'pendiente'"
                                :etiqueta="p.estado_logistico"
                            />
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ p.metodo_pago?.label ?? 'Sin método' }} ·
                            {{ p.modalidad_entrega?.label }}
                        </p>
                    </Link>
                </li>
            </ul>

            <Paginacion :pagina="pedidos" />
        </template>
    </div>
</template>
