<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, ArrowLeft, FileDown } from 'lucide-vue-next';
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
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/PublicLayout.vue';
import pedidosRutas from '@/routes/cliente/pedidos';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    pedido: PedidoCliente;
    instrucciones: Instrucciones;
    urls: { pdf: string; comprobante: string; elegir_envio: string };
    comprobante_reglas: ReglasComprobante;
}>();

const rechazado = computed(
    () =>
        props.pedido.estado_pago.value === 'rechazado' &&
        !!props.pedido.pago?.motivo_rechazo,
);
</script>

<template>
    <Head :title="`Pedido ${pedido.folio}`" />

    <section class="relative overflow-hidden bg-brand-deep-pattern text-white">
        <div
            class="pointer-events-none absolute -top-24 -right-20 size-72 rounded-full bg-brand-green/25 blur-3xl"
            aria-hidden="true"
        />
        <div
            class="relative mx-auto w-full max-w-5xl animate-fade-up px-4 py-8 sm:px-6 lg:px-8"
        >
            <Link
                :href="pedidosRutas.index.url()"
                class="group inline-flex items-center gap-1 rounded-full py-1 pr-3 pl-2 text-sm font-semibold text-white/80 transition hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:outline-none"
            >
                <ArrowLeft
                    class="size-4 transition-transform group-hover:-translate-x-1"
                    aria-hidden="true"
                />
                Mis pedidos
            </Link>
            <div class="mt-4 flex flex-wrap items-end gap-4">
                <div class="min-w-0">
                    <p
                        class="text-xs font-bold tracking-[0.2em] text-brand-green uppercase"
                    >
                        Pedido
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1
                            class="font-display text-3xl font-extrabold tracking-wide break-all sm:text-4xl"
                        >
                            {{ pedido.folio }}
                        </h1>
                        <CopiarTexto
                            :valor="pedido.folio"
                            etiqueta="Copiar folio"
                            tono="oscuro"
                        />
                    </div>
                    <p class="mt-1 text-sm text-white/75">
                        {{ fechaHora(pedido.created_at) }} ·
                        {{ pedido.metodo_pago_label }}
                    </p>
                </div>
                <div
                    class="ml-auto rounded-2xl bg-white/10 px-4 py-2 text-right ring-1 ring-white/15"
                >
                    <p class="text-xs text-white/75 uppercase">Total</p>
                    <p
                        class="font-display text-3xl font-extrabold tabular-nums"
                    >
                        {{ moneda(pedido.total) }}
                    </p>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <EstadoChip
                    :estado="pedido.estatus"
                    class="bg-white! text-brand-ink!"
                />
                <Button
                    as-child
                    size="sm"
                    variant="outline"
                    class="group ml-auto rounded-full border-white/40 bg-transparent text-white hover:-translate-y-0.5 hover:border-white hover:bg-white/10 hover:text-white"
                >
                    <a :href="props.urls.pdf" target="_blank" rel="noopener">
                        <FileDown
                            class="transition-transform group-hover:translate-y-0.5"
                            aria-hidden="true"
                        />
                        Descargar PDF
                    </a>
                </Button>
            </div>
        </div>
    </section>

    <div
        class="mx-auto w-full max-w-5xl stagger-children space-y-5 px-4 py-8 sm:px-6 lg:px-8"
    >
        <div
            v-if="rechazado"
            class="flex gap-3 rounded-3xl border border-destructive/30 bg-card p-5 shadow-brand-sm"
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
                    <strong>Motivo:</strong> {{ pedido.pago?.motivo_rechazo }}
                </p>
            </div>
        </div>

        <ElegirEnvio
            v-if="pedido.envio_esperando_eleccion && !pedido.es_terminal"
            :pedido="pedido"
            :url="props.urls.elegir_envio"
        />

        <InstruccionesPago :pedido="pedido" :instrucciones="instrucciones" />

        <SubirComprobante
            v-if="instrucciones.puede_subir_comprobante"
            :url="props.urls.comprobante"
            :reglas="comprobante_reglas"
            :monto-sugerido="pedido.saldo_pendiente || pedido.total"
            :reintento="pedido.estado_pago.value === 'rechazado'"
        />

        <PedidoDetalle :pedido="pedido" :instrucciones="instrucciones" />
    </div>
</template>
