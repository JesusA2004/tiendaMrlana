<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    FileDown,
    PartyPopper,
} from 'lucide-vue-next';
import { computed } from 'vue';
import CopiarTexto from '@/components/compra/CopiarTexto.vue';
import ElegirEnvio from '@/components/compra/ElegirEnvio.vue';
import EstadoChip from '@/components/compra/EstadoChip.vue';
import { fechaHora, moneda } from '@/components/compra/formato';
import InstruccionesPago from '@/components/compra/InstruccionesPago.vue';
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
</script>

<template>
    <Head :title="`Pedido ${pedido.folio}`" />

    <PublicLayout>
        <section class="bg-brand-gradient">
            <div
                class="mx-auto w-full max-w-5xl px-4 py-8 text-brand-ink sm:px-6 sm:py-12 lg:px-8"
            >
                <nav
                    v-if="es_dueno"
                    class="mb-4 flex items-center gap-2 text-xs font-semibold tracking-wide uppercase opacity-80"
                    aria-label="Pasos de compra"
                >
                    <span>1. Carrito</span>
                    <span aria-hidden="true">›</span>
                    <span>2. Datos y pago</span>
                    <span aria-hidden="true">›</span>
                    <span class="underline underline-offset-4"
                        >3. Confirmación</span
                    >
                </nav>
                <div class="flex flex-wrap items-start gap-4">
                    <span
                        class="flex size-12 shrink-0 items-center justify-center rounded-full bg-white/70"
                    >
                        <PartyPopper class="size-6" aria-hidden="true" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h1
                            class="font-display text-3xl font-extrabold tracking-tight sm:text-4xl"
                        >
                            {{ titulo }}
                        </h1>
                        <p class="mt-1 text-sm sm:text-base">
                            Registrado el {{ fechaHora(pedido.created_at) }} ·
                            te enviamos los detalles a
                            <strong>{{ pedido.correo_cliente }}</strong>
                        </p>
                    </div>
                </div>

                <div
                    class="mt-6 flex flex-wrap items-center gap-4 rounded-3xl bg-white/85 p-4 shadow-sm backdrop-blur sm:p-5 dark:bg-brand-deep/80 dark:text-white"
                >
                    <div class="min-w-0">
                        <p
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Folio
                        </p>
                        <p
                            class="font-display text-2xl font-extrabold tracking-wide break-all sm:text-3xl"
                        >
                            {{ pedido.folio }}
                        </p>
                    </div>
                    <CopiarTexto
                        :valor="pedido.folio"
                        etiqueta="Copiar folio"
                    />
                    <div class="ml-auto text-right">
                        <p
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Total
                        </p>
                        <p class="font-display text-2xl font-extrabold">
                            {{ moneda(pedido.total) }}
                        </p>
                        <p
                            v-if="pedido.envio_por_cotizar"
                            class="text-xs text-warning"
                        >
                            {{
                                pedido.envio_esperando_eleccion
                                    ? '+ envío: elige tu paquetería'
                                    : '+ envío por cotizar'
                            }}
                        </p>
                    </div>
                    <div class="flex w-full flex-wrap gap-2">
                        <EstadoChip :estado="pedido.estatus" />
                        <EstadoChip
                            :estado="pedido.estado_pago"
                            prefijo="Pago:"
                        />
                    </div>
                </div>
            </div>
        </section>

        <div
            class="mx-auto w-full max-w-5xl space-y-5 px-4 py-8 sm:px-6 lg:px-8"
        >
            <div
                v-if="rechazado && es_dueno"
                class="flex gap-3 rounded-3xl border border-destructive/30 bg-destructive/5 p-5"
                role="alert"
            >
                <AlertTriangle
                    class="mt-0.5 size-6 shrink-0 text-destructive"
                    aria-hidden="true"
                />
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

            <ElegirEnvio
                v-if="pedido.envio_esperando_eleccion && !pedido.es_terminal"
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

            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline" class="rounded-full">
                    <a :href="props.urls.pdf" target="_blank" rel="noopener">
                        <FileDown aria-hidden="true" />
                        Descargar PDF
                    </a>
                </Button>
                <Button
                    v-if="props.urls.detalle"
                    as-child
                    variant="outline"
                    class="rounded-full"
                >
                    <Link :href="tiendaUrls.misPedidos">Ver mis pedidos</Link>
                </Button>
                <Button as-child class="ml-auto rounded-full">
                    <Link :href="tiendaUrls.catalogo">
                        Seguir comprando
                        <ArrowRight aria-hidden="true" />
                    </Link>
                </Button>
            </div>

            <PedidoDetalle :pedido="pedido" :instrucciones="instrucciones" />
        </div>
    </PublicLayout>
</template>
