<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, ChevronRight, ImageOff, Package } from 'lucide-vue-next';
import CuentaNav from '@/components/compra/CuentaNav.vue';
import EstadoChip from '@/components/compra/EstadoChip.vue';
import { fechaLarga, moneda } from '@/components/compra/formato';
import type { EstadoInfo } from '@/components/compra/types';
import { urls } from '@/components/public/tienda';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';
import pedidosRutas from '@/routes/cliente/pedidos';

defineOptions({ layout: PublicLayout });

interface PedidoListado {
    id: number;
    folio: string;
    created_at: string | null;
    total: number;
    envio_por_cotizar: boolean;
    envio_esperando_eleccion: boolean;
    metodo_pago_label: string;
    estatus: EstadoInfo;
    estado_pago: EstadoInfo;
    estado_logistico: EstadoInfo;
    items_count: number;
    piezas: number;
    imagenes: string[];
    primer_producto: string | null;
    requiere_accion: boolean;
}

interface EnlacePaginacion {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{
    pedidos: {
        data: PedidoListado[];
        links: EnlacePaginacion[];
        total: number;
        current_page: number;
        last_page: number;
    };
    filtro: string | null;
    conteos: Record<string, number>;
}>();

const filtros = [
    { clave: null, texto: 'Todos', conteo: 'todos' },
    { clave: 'por_pagar', texto: 'Por pagar', conteo: 'por_pagar' },
    { clave: 'en_proceso', texto: 'En proceso', conteo: 'en_proceso' },
    { clave: 'entregados', texto: 'Entregados', conteo: 'entregados' },
    { clave: 'cerrados', texto: 'Cancelados y vencidos', conteo: 'cerrados' },
] as const;

function etiquetaPagina(label: string): string {
    return label
        .replace('&laquo; Previous', '‹ Anterior')
        .replace('Next &raquo;', 'Siguiente ›')
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›');
}
</script>

<template>
    <Head title="Mis pedidos" />

    <CuentaNav
        activo="pedidos"
        titulo="Mis pedidos"
        descripcion="Consulta el estado de tus compras, paga y descarga su resumen."
    />

    <div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
        <nav
            class="-mx-4 mb-5 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0"
            aria-label="Filtrar pedidos"
        >
            <Link
                v-for="f in filtros"
                :key="f.texto"
                :href="
                    pedidosRutas.index.url(
                        f.clave ? { query: { estado: f.clave } } : undefined,
                    )
                "
                preserve-scroll
                class="flex shrink-0 items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-semibold transition"
                :class="
                    filtro === f.clave
                        ? 'border-brand-green bg-brand-green text-brand-ink'
                        : 'border-border bg-background hover:border-brand-green'
                "
                :aria-current="filtro === f.clave ? 'true' : undefined"
            >
                {{ f.texto }}
                <span
                    class="rounded-full px-1.5 text-xs"
                    :class="filtro === f.clave ? 'bg-white/60' : 'bg-muted'"
                    >{{ conteos[f.conteo] ?? 0 }}</span
                >
            </Link>
        </nav>

        <div
            v-if="!pedidos.data.length"
            class="flex flex-col items-center rounded-3xl border border-dashed border-border bg-background px-6 py-14 text-center"
        >
            <span
                class="mb-4 flex size-14 items-center justify-center rounded-full bg-brand-green-soft text-brand-green-strong"
            >
                <Package class="size-7" aria-hidden="true" />
            </span>
            <h2 class="font-display text-xl font-bold">
                {{
                    filtro
                        ? 'No hay pedidos con este filtro'
                        : 'Aún no tienes pedidos'
                }}
            </h2>
            <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                Cuando compres, aquí podrás seguir tu pedido paso a paso.
            </p>
            <Button as-child class="mt-5 rounded-full">
                <Link :href="urls.catalogo">
                    Ir a la tienda
                    <ArrowRight aria-hidden="true" />
                </Link>
            </Button>
        </div>

        <ul v-else class="space-y-3">
            <li v-for="p in pedidos.data" :key="p.id">
                <Link
                    :href="pedidosRutas.show.url(p.id)"
                    class="group flex flex-col gap-4 rounded-3xl border bg-background p-4 shadow-xs transition hover:border-brand-green hover:shadow-md sm:flex-row sm:items-center sm:p-5"
                    :class="
                        p.requiere_accion
                            ? 'border-warning/50'
                            : 'border-border'
                    "
                >
                    <div class="flex shrink-0 -space-x-3">
                        <span
                            v-for="(img, i) in p.imagenes"
                            :key="i"
                            class="size-14 overflow-hidden rounded-2xl bg-surface ring-2 ring-background"
                        >
                            <img
                                :src="img"
                                alt=""
                                class="size-full object-cover"
                                loading="lazy"
                            />
                        </span>
                        <span
                            v-if="!p.imagenes.length"
                            class="flex size-14 items-center justify-center rounded-2xl bg-surface text-muted-foreground"
                        >
                            <ImageOff class="size-5" aria-hidden="true" />
                        </span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div
                            class="flex flex-wrap items-center gap-x-3 gap-y-1"
                        >
                            <p
                                class="font-display text-lg font-bold tracking-wide"
                            >
                                {{ p.folio }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ fechaLarga(p.created_at) }}
                            </p>
                        </div>
                        <p class="truncate text-sm text-muted-foreground">
                            {{ p.primer_producto }}
                            <template v-if="p.items_count > 1">
                                y {{ p.items_count - 1 }} producto{{
                                    p.items_count - 1 === 1 ? '' : 's'
                                }}
                                más</template
                            >
                            · {{ p.metodo_pago_label }}
                        </p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <EstadoChip
                                :estado="p.estado_pago"
                                prefijo="Pago:"
                            />
                            <EstadoChip
                                :estado="p.estado_logistico"
                                prefijo="Entrega:"
                            />
                        </div>
                        <p
                            v-if="p.requiere_accion"
                            class="mt-2 text-xs font-semibold text-warning"
                        >
                            {{
                                p.envio_esperando_eleccion
                                    ? 'Pendiente: elige tu opción de envío.'
                                    : 'Pendiente: realiza tu pago y sube el comprobante.'
                            }}
                        </p>
                    </div>

                    <div
                        class="flex items-center justify-between gap-3 sm:flex-col sm:items-end"
                    >
                        <p
                            class="font-display text-xl font-extrabold tabular-nums"
                        >
                            {{ moneda(p.total) }}
                            <span
                                v-if="p.envio_por_cotizar"
                                class="block text-right text-[11px] font-medium text-warning"
                                >{{
                                    p.envio_esperando_eleccion
                                        ? 'Elige tu envío'
                                        : '+ envío por cotizar'
                                }}</span
                            >
                        </p>
                        <span
                            class="inline-flex items-center gap-1 text-sm font-semibold text-brand-green-strong"
                        >
                            Ver detalle
                            <ChevronRight
                                class="size-4 transition group-hover:translate-x-0.5"
                                aria-hidden="true"
                            />
                        </span>
                    </div>
                </Link>
            </li>
        </ul>

        <nav
            v-if="pedidos.last_page > 1"
            class="mt-6 flex flex-wrap justify-center gap-1"
            aria-label="Paginación"
        >
            <template v-for="(l, i) in pedidos.links" :key="i">
                <Link
                    v-if="l.url"
                    :href="l.url"
                    preserve-scroll
                    class="min-w-9 rounded-full border px-3 py-1.5 text-center text-sm"
                    :class="
                        l.active
                            ? 'border-brand-green bg-brand-green font-bold text-brand-ink'
                            : 'border-border hover:border-brand-green'
                    "
                    :aria-current="l.active ? 'page' : undefined"
                    >{{ etiquetaPagina(l.label) }}</Link
                >
                <span
                    v-else
                    class="min-w-9 px-3 py-1.5 text-center text-sm text-muted-foreground"
                    >{{ etiquetaPagina(l.label) }}</span
                >
            </template>
        </nav>
    </div>
</template>
