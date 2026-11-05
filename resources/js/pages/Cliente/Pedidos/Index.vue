<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowRight,
    ChevronRight,
    ImageOff,
    Package,
} from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
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

/** Skeleton verde mientras se cambia de filtro o de página (sólo presentación). */
const cargando = ref(false);
let quitarInicio: (() => void) | null = null;
let quitarFin: (() => void) | null = null;

onMounted(() => {
    quitarInicio = router.on('start', (evento) => {
        const visita = evento.detail.visit;

        if (
            visita.method === 'get' &&
            visita.url.pathname === window.location.pathname
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
            class="-mx-4 mb-5 flex gap-2 overflow-x-auto px-4 pt-1 pb-2 sm:mx-0 sm:flex-wrap sm:px-0"
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
                class="flex shrink-0 items-center gap-1.5 rounded-full border px-4 py-2 text-sm font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none active:scale-95"
                :class="
                    filtro === f.clave
                        ? 'border-brand-deep bg-brand-deep text-white shadow-brand-sm'
                        : 'border-border bg-card text-foreground hover:-translate-y-0.5 hover:border-brand-green hover:bg-brand-green-soft hover:shadow-brand-sm'
                "
                :aria-current="filtro === f.clave ? 'true' : undefined"
            >
                {{ f.texto }}
                <span
                    class="min-w-5 rounded-full px-1.5 text-center text-xs tabular-nums transition-colors"
                    :class="
                        filtro === f.clave
                            ? 'bg-brand-green text-brand-ink'
                            : 'bg-brand-green-soft text-brand-green-strong'
                    "
                    >{{ conteos[f.conteo] ?? 0 }}</span
                >
            </Link>
        </nav>

        <ul
            v-if="cargando"
            class="space-y-3"
            aria-busy="true"
            aria-label="Cargando pedidos"
        >
            <li
                v-for="n in 3"
                :key="n"
                class="flex items-center gap-4 rounded-3xl border border-border bg-card p-4 shadow-brand-sm sm:p-5"
            >
                <span class="size-14 shrink-0 skeleton-shimmer rounded-2xl" />
                <span class="flex-1 space-y-2">
                    <span
                        class="block h-4 w-1/3 skeleton-shimmer rounded-full"
                    />
                    <span
                        class="block h-3 w-2/3 skeleton-shimmer rounded-full"
                    />
                    <span
                        class="block h-5 w-1/2 skeleton-shimmer rounded-full"
                    />
                </span>
                <span
                    class="hidden h-6 w-20 skeleton-shimmer rounded-full sm:block"
                />
            </li>
        </ul>

        <div
            v-else-if="!pedidos.data.length"
            class="flex animate-scale-in flex-col items-center rounded-3xl border border-border bg-card px-6 py-14 text-center shadow-brand-sm"
        >
            <span
                class="mb-4 flex size-16 items-center justify-center rounded-full bg-brand-gradient text-brand-ink shadow-brand-sm motion-safe:animate-float"
            >
                <Package class="size-7" aria-hidden="true" />
            </span>
            <h2 class="font-display text-xl font-bold text-brand-deep">
                {{
                    filtro
                        ? 'No hay pedidos con este filtro'
                        : 'Aún no tienes pedidos'
                }}
            </h2>
            <p class="mt-1 max-w-sm text-sm text-muted-foreground">
                Cuando compres, aquí podrás seguir tu pedido paso a paso.
            </p>
            <Button as-child class="group mt-5 rounded-full font-bold">
                <Link :href="urls.catalogo">
                    Ir a la tienda
                    <ArrowRight
                        class="transition-transform group-hover:translate-x-1"
                        aria-hidden="true"
                    />
                </Link>
            </Button>
        </div>

        <ul v-else class="stagger-children space-y-3">
            <li v-for="p in pedidos.data" :key="p.id">
                <Link
                    :href="pedidosRutas.show.url(p.id)"
                    class="group relative flex hover-lift flex-col gap-4 overflow-hidden rounded-3xl border bg-card p-4 shadow-brand-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:flex-row sm:items-center sm:p-5"
                    :class="
                        p.requiere_accion
                            ? 'border-warning/50'
                            : 'border-border'
                    "
                >
                    <span
                        class="absolute inset-y-0 left-0 w-1.5 transition-all duration-300 group-hover:w-2"
                        :class="
                            p.requiere_accion
                                ? 'bg-warning'
                                : 'bg-brand-gradient opacity-0 group-hover:opacity-100'
                        "
                        aria-hidden="true"
                    />
                    <div class="flex shrink-0 -space-x-3">
                        <span
                            v-for="(img, i) in p.imagenes"
                            :key="i"
                            class="size-14 overflow-hidden rounded-2xl bg-brand-green-soft ring-2 ring-card transition-transform duration-300 group-hover:-translate-y-0.5"
                        >
                            <img
                                :src="img"
                                alt=""
                                class="size-full object-cover transition-transform duration-500 group-hover:scale-110"
                                loading="lazy"
                            />
                        </span>
                        <span
                            v-if="!p.imagenes.length"
                            class="flex size-14 items-center justify-center rounded-2xl bg-brand-green-soft text-brand-green-strong"
                        >
                            <ImageOff class="size-5" aria-hidden="true" />
                        </span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div
                            class="flex flex-wrap items-center gap-x-3 gap-y-1"
                        >
                            <p
                                class="font-display text-lg font-bold tracking-wide text-brand-deep transition-colors group-hover:text-brand-green-strong"
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
                            class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-warning/10 px-2.5 py-1 text-xs font-semibold text-warning"
                        >
                            <AlertCircle class="size-3.5" aria-hidden="true" />
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
                            class="font-display text-xl font-extrabold text-brand-deep tabular-nums"
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
                            class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-sm font-semibold text-brand-green-strong transition-colors group-hover:bg-brand-green-soft"
                        >
                            Ver detalle
                            <ChevronRight
                                class="size-4 transition-transform group-hover:translate-x-1"
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
                    class="min-w-9 rounded-full border px-3 py-1.5 text-center text-sm transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        l.active
                            ? 'border-brand-deep bg-brand-deep font-bold text-white'
                            : 'border-border bg-card hover:-translate-y-0.5 hover:border-brand-green hover:bg-brand-green-soft'
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
