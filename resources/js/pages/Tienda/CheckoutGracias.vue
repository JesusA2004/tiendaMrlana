<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    BadgeCheck,
    FileDown,
    Package,
    ShoppingCart,
    Truck,
} from 'lucide-vue-next';
import { computed } from 'vue';
import CopiarTexto from '@/components/compra/CopiarTexto.vue';
import ElegirEnvio from '@/components/compra/ElegirEnvio.vue';
import EstadoChip from '@/components/compra/EstadoChip.vue';
import { fechaHora, moneda } from '@/components/compra/formato';
import InstruccionesPago from '@/components/compra/InstruccionesPago.vue';
import PasosCompra from '@/components/compra/PasosCompra.vue';
import type { PasoCompra } from '@/components/compra/PasosCompra.vue';
import PedidoDetalle from '@/components/compra/PedidoDetalle.vue';
import SubirComprobante from '@/components/compra/SubirComprobante.vue';
import type {
    InstruccionesPago as Instrucciones,
    PedidoCliente,
    ReglasComprobante,
} from '@/components/compra/types';
import { urls as tiendaUrls } from '@/components/public/tienda';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';

const props = defineProps<{
    pedido: PedidoCliente;
    instrucciones: Instrucciones;
    es_dueno: boolean;
    urls: {
        pdf: string;
        detalle: string | null;
        comprobante: string | null;
        elegir_envio: string | null;
    };
    comprobante_reglas: ReglasComprobante;
}>();

const rechazado = computed(
    () =>
        props.pedido.estado_pago.value === 'rechazado' &&
        !!props.pedido.pago?.motivo_rechazo,
);

const titulo = computed(() => {
    if (props.pedido.es_terminal) {
        return `Pedido ${props.pedido.estatus.label.toLowerCase()}`;
    }

    return props.es_dueno ? '¡Gracias por tu pedido!' : 'Pedido del cliente';
});

const celebrar = computed(() => props.es_dueno && !props.pedido.es_terminal);

const pasos: PasoCompra[] = [
    {
        clave: 'carrito',
        titulo: 'Carrito',
        icono: ShoppingCart,
        estado: 'hecho',
    },
    { clave: 'datos', titulo: 'Datos y pago', icono: Truck, estado: 'hecho' },
    {
        clave: 'confirmacion',
        titulo: 'Confirmación',
        icono: BadgeCheck,
        estado: 'hecho',
    },
];

/** Confeti CSS ligero: posiciones/colores deterministas (sin dependencias). */
const COLORES = ['#73c83a', '#64cbd4', '#ffffff', '#e3b23c', '#1f4d3a'];
const confeti = Array.from({ length: 28 }, (_, i) => ({
    left: `${(i * 37) % 100}%`,
    delay: `${(i % 7) * 0.12}s`,
    duration: `${2.4 + ((i * 13) % 10) / 10}s`,
    color: COLORES[i % COLORES.length],
    rotate: `${(i * 47) % 360}deg`,
    size: i % 3 === 0 ? '10px' : '7px',
    round: i % 4 === 0,
}));
</script>

<template>
    <Head :title="`Pedido ${pedido.folio}`" />

    <PublicLayout>
        <section
            class="relative overflow-hidden bg-brand-deep-pattern text-white"
        >
            <div
                class="pointer-events-none absolute -top-24 -right-24 size-80 rounded-full bg-brand-green/30 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute -bottom-32 -left-20 size-80 rounded-full bg-brand-teal/25 blur-3xl"
                aria-hidden="true"
            />

            <!-- Confeti -->
            <div
                v-if="celebrar"
                class="confeti pointer-events-none absolute inset-0 overflow-hidden"
                aria-hidden="true"
            >
                <span
                    v-for="(c, i) in confeti"
                    :key="i"
                    class="confeti__pieza"
                    :style="{
                        left: c.left,
                        animationDelay: c.delay,
                        animationDuration: c.duration,
                        backgroundColor: c.color,
                        width: c.size,
                        height: c.round ? c.size : `calc(${c.size} * 1.6)`,
                        borderRadius: c.round ? '9999px' : '2px',
                        '--giro': c.rotate,
                    }"
                />
            </div>

            <div
                class="relative mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8"
            >
                <div v-if="es_dueno" class="mx-auto mb-8 max-w-md">
                    <PasosCompra :pasos="pasos" tono="oscuro" :progreso="100" />
                </div>

                <div
                    class="flex animate-fade-up flex-col items-center text-center"
                >
                    <span
                        v-if="!pedido.es_terminal"
                        class="check-exito relative mb-4 flex size-20 items-center justify-center rounded-full bg-brand-gradient text-brand-ink shadow-[0_0_0_10px_rgb(115_200_58/0.18)]"
                        aria-hidden="true"
                    >
                        <svg
                            viewBox="0 0 24 24"
                            class="size-10"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="3"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >
                            <path
                                class="check-exito__trazo"
                                d="M5 12.5l4.5 4.5L19 7.5"
                            />
                        </svg>
                    </span>
                    <span
                        v-else
                        class="mb-4 flex size-20 items-center justify-center rounded-full bg-white/15 text-white ring-1 ring-white/30"
                        aria-hidden="true"
                    >
                        <Package class="size-9" />
                    </span>
                    <h1
                        class="font-display text-3xl font-extrabold tracking-tight text-balance sm:text-5xl"
                    >
                        {{ titulo }}
                    </h1>
                    <p class="mt-2 max-w-xl text-sm text-white/80 sm:text-base">
                        Registrado el {{ fechaHora(pedido.created_at) }} · te
                        enviamos los detalles a
                        <strong class="text-white">{{
                            pedido.correo_cliente
                        }}</strong>
                    </p>
                </div>

                <!-- Folio destacado -->
                <div
                    class="mx-auto mt-8 max-w-3xl animate-scale-in rounded-3xl bg-card p-1 text-foreground shadow-2xl [animation-delay:150ms]"
                >
                    <div
                        class="grid gap-4 rounded-[1.35rem] border-2 border-dashed border-brand-green/50 p-4 sm:grid-cols-[1fr_auto] sm:items-center sm:p-6"
                    >
                        <div class="min-w-0">
                            <p
                                class="text-xs font-bold tracking-[0.2em] text-brand-green-strong uppercase"
                            >
                                Tu folio
                            </p>
                            <div class="mt-1 flex flex-wrap items-center gap-3">
                                <p
                                    class="font-display text-3xl font-extrabold tracking-wide break-all text-brand-deep sm:text-4xl"
                                >
                                    {{ pedido.folio }}
                                </p>
                                <CopiarTexto
                                    :valor="pedido.folio"
                                    etiqueta="Copiar folio"
                                />
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <EstadoChip :estado="pedido.estatus" />
                                <EstadoChip
                                    :estado="pedido.estado_pago"
                                    prefijo="Pago:"
                                />
                            </div>
                        </div>
                        <div
                            class="rounded-2xl bg-brand-soft-gradient px-5 py-4 text-left sm:text-right"
                        >
                            <p
                                class="text-xs font-bold tracking-wider text-brand-deep/80 uppercase"
                            >
                                Total
                            </p>
                            <p
                                class="font-display text-3xl leading-tight font-extrabold text-brand-deep tabular-nums"
                            >
                                {{ moneda(pedido.total) }}
                            </p>
                            <p
                                v-if="pedido.envio_por_cotizar"
                                class="text-xs font-semibold text-warning"
                            >
                                {{
                                    pedido.envio_esperando_eleccion
                                        ? '+ envío: elige tu paquetería'
                                        : '+ envío por cotizar'
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div
            class="mx-auto w-full max-w-5xl space-y-5 px-4 py-8 sm:px-6 lg:px-8"
        >
            <div
                v-if="rechazado && es_dueno"
                class="flex animate-fade-up gap-3 rounded-3xl border border-destructive/30 bg-card p-5 shadow-brand-sm"
                role="alert"
            >
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10"
                >
                    <AlertTriangle
                        class="size-5 text-destructive"
                        aria-hidden="true"
                    />
                </span>
                <div class="text-sm">
                    <h2 class="font-display text-lg font-bold text-destructive">
                        No pudimos validar tu pago
                    </h2>
                    <p class="mt-1">
                        <strong>Motivo:</strong>
                        {{ pedido.pago?.motivo_rechazo }}
                    </p>
                    <p class="mt-1 text-muted-foreground">
                        Revisa los datos y envía un nuevo comprobante.
                    </p>
                </div>
            </div>

            <div class="stagger-children space-y-5">
                <ElegirEnvio
                    v-if="
                        pedido.envio_esperando_eleccion && !pedido.es_terminal
                    "
                    :pedido="pedido"
                    :url="props.urls.elegir_envio"
                />

                <InstruccionesPago
                    v-if="es_dueno"
                    :pedido="pedido"
                    :instrucciones="instrucciones"
                />

                <SubirComprobante
                    v-if="
                        instrucciones.puede_subir_comprobante &&
                        props.urls.comprobante
                    "
                    :url="props.urls.comprobante"
                    :reglas="comprobante_reglas"
                    :monto-sugerido="pedido.saldo_pendiente || pedido.total"
                    :reintento="pedido.estado_pago.value === 'rechazado'"
                />

                <div
                    class="flex flex-wrap gap-2 rounded-3xl border border-border bg-card p-3 shadow-brand-sm"
                >
                    <Button
                        as-child
                        variant="outline"
                        class="group rounded-full hover:border-brand-green hover:bg-brand-green-soft"
                    >
                        <a
                            :href="props.urls.pdf"
                            target="_blank"
                            rel="noopener"
                        >
                            <FileDown
                                class="transition-transform group-hover:translate-y-0.5"
                                aria-hidden="true"
                            />
                            Descargar PDF
                        </a>
                    </Button>
                    <Button
                        v-if="props.urls.detalle"
                        as-child
                        variant="outline"
                        class="rounded-full hover:border-brand-green hover:bg-brand-green-soft"
                    >
                        <Link :href="tiendaUrls.misPedidos">
                            <Package aria-hidden="true" />
                            Ver mis pedidos
                        </Link>
                    </Button>
                    <Button
                        as-child
                        class="group ml-auto rounded-full font-bold"
                    >
                        <Link :href="tiendaUrls.catalogo">
                            Seguir comprando
                            <ArrowRight
                                class="transition-transform group-hover:translate-x-1"
                                aria-hidden="true"
                            />
                        </Link>
                    </Button>
                </div>

                <PedidoDetalle
                    :pedido="pedido"
                    :instrucciones="instrucciones"
                />
            </div>
        </div>
    </PublicLayout>
</template>

<style scoped>
.confeti__pieza {
    position: absolute;
    top: -16px;
    opacity: 0;
    animation-name: confeti-caer;
    animation-timing-function: cubic-bezier(0.25, 0.6, 0.45, 1);
    animation-iteration-count: 1;
    animation-fill-mode: forwards;
}

@keyframes confeti-caer {
    0% {
        opacity: 1;
        transform: translate3d(0, 0, 0) rotate(0deg);
    }
    80% {
        opacity: 1;
    }
    100% {
        opacity: 0;
        transform: translate3d(24px, 420px, 0) rotate(var(--giro, 360deg));
    }
}

.check-exito {
    animation: check-pop 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

.check-exito__trazo {
    stroke-dasharray: 24;
    stroke-dashoffset: 24;
    animation: check-trazo 0.45s 0.35s ease-out forwards;
}

@keyframes check-pop {
    from {
        transform: scale(0.4);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes check-trazo {
    to {
        stroke-dashoffset: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .confeti {
        display: none;
    }

    .check-exito,
    .check-exito__trazo {
        animation: none;
        stroke-dashoffset: 0;
    }
}
</style>
