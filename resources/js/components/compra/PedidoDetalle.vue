<script setup lang="ts">
import {
    Download,
    ExternalLink,
    FileText,
    ImageOff,
    MapPin,
    Package,
    Store,
    Truck,
} from 'lucide-vue-next';
import { computed } from 'vue';
import CopiarTexto from './CopiarTexto.vue';
import EstadoChip from './EstadoChip.vue';
import { fechaHora, fechaSimple, moneda, tamanoArchivo } from './formato';
import LineaTiempo from './LineaTiempo.vue';
import ResumenTotales from './ResumenTotales.vue';
import type { InstruccionesPago, PedidoCliente } from './types';

/**
 * Detalle del pedido para el cliente: estado (pago y logística por separado),
 * productos, entrega, comprobantes e historial visible.
 */
const props = defineProps<{
    pedido: PedidoCliente;
    instrucciones?: InstruccionesPago | null;
}>();

const recoger = computed(
    () => props.pedido.modalidad_entrega === 'recoger_en_tienda',
);
const etiquetaComprobante: Record<string, string> = {
    recibido: 'En revisión',
    aprobado: 'Aprobado',
    rechazado: 'Rechazado',
};
</script>

<template>
    <div class="space-y-5">
        <!-- Estado -->
        <section
            class="rounded-3xl border border-border bg-background p-5 shadow-xs sm:p-6"
            aria-labelledby="titulo-estado"
        >
            <div class="mb-5 flex flex-wrap items-center gap-2">
                <h2
                    id="titulo-estado"
                    class="mr-auto font-display text-lg font-bold"
                >
                    Estado del pedido
                </h2>
                <EstadoChip :estado="pedido.estatus" />
            </div>
            <div class="grid gap-6 sm:grid-cols-2">
                <LineaTiempo titulo="Pago" :pasos="pedido.linea_pago" />
                <LineaTiempo
                    :titulo="recoger ? 'Entrega en tienda' : 'Envío'"
                    :pasos="pedido.linea_logistica"
                />
            </div>
            <p
                v-if="pedido.motivo_cancelacion && pedido.es_terminal"
                class="mt-4 rounded-xl bg-destructive/10 px-3 py-2 text-sm text-destructive"
            >
                <strong>Motivo:</strong> {{ pedido.motivo_cancelacion }}
            </p>
        </section>

        <!-- Entrega -->
        <section
            class="grid gap-4 rounded-3xl border border-border bg-background p-5 shadow-xs sm:grid-cols-2 sm:p-6"
            aria-label="Entrega"
        >
            <div class="flex gap-3">
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-green-soft text-brand-green-strong"
                >
                    <Store v-if="recoger" class="size-5" aria-hidden="true" />
                    <MapPin v-else class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0 text-sm">
                    <h3 class="font-semibold">
                        {{ pedido.modalidad_entrega_label }}
                    </h3>
                    <template v-if="pedido.direccion">
                        <p>{{ pedido.direccion.nombre_receptor }}</p>
                        <p class="text-muted-foreground">
                            {{ pedido.direccion.calle }}
                            {{ pedido.direccion.numero_exterior
                            }}<template v-if="pedido.direccion.numero_interior">
                                int.
                                {{ pedido.direccion.numero_interior }}</template
                            >, {{ pedido.direccion.colonia }},
                            {{ pedido.direccion.municipio }},
                            {{ pedido.direccion.estado }}, C.P.
                            {{ pedido.direccion.codigo_postal }}
                        </p>
                        <p class="text-muted-foreground">
                            Tel. {{ pedido.direccion.telefono }}
                        </p>
                    </template>
                    <p
                        v-else-if="instrucciones?.recoger?.direccion"
                        class="text-muted-foreground"
                    >
                        {{ instrucciones.recoger.direccion }}
                    </p>
                </div>
            </div>
            <div class="flex gap-3">
                <span
                    class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-teal-soft text-brand-teal-strong"
                >
                    <Truck class="size-5" aria-hidden="true" />
                </span>
                <div class="min-w-0 text-sm">
                    <h3 class="font-semibold">Seguimiento</h3>
                    <template v-if="pedido.numero_guia || pedido.paqueteria">
                        <p v-if="pedido.paqueteria">{{ pedido.paqueteria }}</p>
                        <p
                            v-if="pedido.numero_guia"
                            class="mt-1 flex flex-wrap items-center gap-2"
                        >
                            <span class="font-mono font-semibold">{{
                                pedido.numero_guia
                            }}</span>
                            <CopiarTexto
                                :valor="pedido.numero_guia"
                                etiqueta="Copiar guía"
                                compacto
                            />
                        </p>
                        <p
                            v-if="pedido.fecha_envio"
                            class="text-muted-foreground"
                        >
                            Enviado el {{ fechaSimple(pedido.fecha_envio) }}
                        </p>
                        <a
                            v-if="pedido.url_rastreo"
                            :href="pedido.url_rastreo"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-brand-green px-3 py-1.5 text-xs font-bold text-brand-ink transition hover:brightness-95"
                        >
                            <ExternalLink class="size-3.5" aria-hidden="true" />
                            Rastrear mi envío
                        </a>
                        <p
                            v-else-if="!pedido.numero_guia"
                            class="text-muted-foreground"
                        >
                            Te compartiremos la guía cuando salga tu pedido.
                        </p>
                    </template>
                    <p v-else class="text-muted-foreground">
                        {{
                            recoger
                                ? 'Te avisaremos cuando esté listo para recoger.'
                                : 'Te compartiremos la paquetería y la guía cuando salga tu pedido.'
                        }}
                    </p>
                    <p
                        v-if="pedido.entregado_en"
                        class="mt-1 font-medium text-brand-green-strong"
                    >
                        Entregado el {{ fechaHora(pedido.entregado_en) }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Productos -->
        <section
            class="rounded-3xl border border-border bg-background p-5 shadow-xs sm:p-6"
            aria-labelledby="titulo-productos"
        >
            <h2
                id="titulo-productos"
                class="mb-4 flex items-center gap-2 font-display text-lg font-bold"
            >
                <Package
                    class="size-5 text-brand-green-strong"
                    aria-hidden="true"
                />
                Productos
            </h2>
            <ul class="divide-y divide-border">
                <li
                    v-for="item in pedido.items"
                    :key="item.id"
                    class="flex gap-3 py-3 first:pt-0"
                >
                    <span
                        class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-surface"
                    >
                        <img
                            v-if="item.imagen"
                            :src="item.imagen"
                            :alt="item.nombre"
                            class="size-full object-cover"
                            loading="lazy"
                        />
                        <ImageOff
                            v-else
                            class="size-5 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </span>
                    <div class="min-w-0 flex-1 text-sm">
                        <p class="font-medium">{{ item.nombre }}</p>
                        <p class="text-muted-foreground">
                            {{ item.cantidad }} ×
                            {{ moneda(item.precio_unitario) }}
                        </p>
                    </div>
                    <p class="text-sm font-semibold tabular-nums">
                        {{ moneda(item.subtotal) }}
                    </p>
                </li>
            </ul>
            <div
                class="mt-4 border-t border-border pt-4 sm:ml-auto sm:max-w-sm"
            >
                <ResumenTotales
                    :subtotal="pedido.subtotal"
                    :descuento-ofertas="pedido.ahorro_ofertas"
                    :descuento-cupon="pedido.descuento_cupon"
                    :cupon-codigo="pedido.cupon_codigo"
                    :envio="pedido.envio"
                    :envio-por-cotizar="pedido.envio_por_cotizar"
                    :texto-envio-pendiente="
                        pedido.envio_esperando_eleccion
                            ? 'Por elegir'
                            : 'Por cotizar'
                    "
                    :recoger-en-tienda="recoger"
                    :total="pedido.total"
                    :saldo-pendiente="
                        pedido.metodo_pago === 'efectivo_contra_entrega' &&
                        !pedido.es_terminal
                            ? pedido.saldo_pendiente
                            : null
                    "
                />
                <p class="mt-2 text-xs text-muted-foreground">
                    Pago: {{ pedido.metodo_pago_label }}
                </p>
            </div>
        </section>

        <!-- Comprobantes -->
        <section
            v-if="pedido.comprobantes.length"
            class="rounded-3xl border border-border bg-background p-5 shadow-xs sm:p-6"
            aria-labelledby="titulo-comprobantes"
        >
            <h2
                id="titulo-comprobantes"
                class="mb-3 font-display text-lg font-bold"
            >
                Comprobantes enviados
            </h2>
            <ul class="space-y-2">
                <li
                    v-for="c in pedido.comprobantes"
                    :key="c.id"
                    class="flex items-start gap-3 rounded-2xl bg-surface/70 p-3 text-sm"
                >
                    <FileText
                        class="size-5 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ c.nombre }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ fechaHora(c.created_at) }} ·
                            {{ tamanoArchivo(c.tamano) }}
                            <template v-if="c.referencia">
                                · Ref. {{ c.referencia }}</template
                            >
                            <template v-if="c.monto_declarado !== null">
                                · {{ moneda(c.monto_declarado) }}</template
                            >
                        </p>
                        <p
                            v-if="c.motivo_rechazo"
                            class="mt-1 text-xs font-medium text-destructive"
                        >
                            Motivo: {{ c.motivo_rechazo }}
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="{
                                    'bg-info/12 text-info':
                                        c.estado === 'recibido',
                                    'bg-success/12 text-success':
                                        c.estado === 'aprobado',
                                    'bg-destructive/10 text-destructive':
                                        c.estado === 'rechazado',
                                }"
                                >{{
                                    etiquetaComprobante[c.estado] ?? c.estado
                                }}</span
                            >
                            <a
                                :href="c.url"
                                class="inline-flex items-center gap-1 rounded-full border border-border px-3 py-1 text-xs font-semibold hover:border-brand-green"
                            >
                                <Download class="size-3.5" aria-hidden="true" />
                                Descargar
                            </a>
                        </div>
                    </div>
                </li>
            </ul>
        </section>

        <!-- Historial -->
        <section
            v-if="pedido.historial.length"
            class="rounded-3xl border border-border bg-background p-5 shadow-xs sm:p-6"
            aria-labelledby="titulo-historial"
        >
            <h2
                id="titulo-historial"
                class="mb-3 font-display text-lg font-bold"
            >
                Historial
            </h2>
            <ol class="space-y-3 border-l-2 border-border pl-4">
                <li
                    v-for="h in pedido.historial"
                    :key="h.id"
                    class="relative text-sm"
                >
                    <span
                        class="absolute top-1.5 -left-[21px] size-2.5 rounded-full bg-brand-teal ring-4 ring-background"
                        aria-hidden="true"
                    />
                    <p class="font-medium">{{ h.comentario || h.estatus }}</p>
                    <p class="text-xs text-muted-foreground">
                        {{ fechaHora(h.created_at) }}
                    </p>
                </li>
            </ol>
        </section>
    </div>
</template>
