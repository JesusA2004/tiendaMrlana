<script setup lang="ts">
import {
    Building2,
    CalendarClock,
    Clock3,
    HandCoins,
    MapPin,
    MessageCircle,
} from 'lucide-vue-next';
import CopiarTexto from './CopiarTexto.vue';
import { agruparClabe, fechaHora, moneda } from './formato';
import type { InstruccionesPago, PedidoCliente } from './types';

/**
 * Qué tiene que hacer el cliente para pagar, según método y estado.
 * Los datos bancarios sólo llegan del servidor para el dueño; nunca se inventan.
 */
defineProps<{
    pedido: PedidoCliente;
    instrucciones: InstruccionesPago;
}>();
</script>

<template>
    <!-- Envío por cotizar: aún no se paga -->
    <section
        v-if="
            pedido.envio_por_cotizar &&
            !pedido.envio_esperando_eleccion &&
            !pedido.es_terminal
        "
        class="flex gap-3 rounded-3xl border border-warning/30 bg-warning/10 p-5"
    >
        <Clock3
            class="mt-0.5 size-6 shrink-0 text-warning"
            aria-hidden="true"
        />
        <div>
            <h2 class="font-display text-lg font-bold">
                Estamos cotizando tu envío
            </h2>
            <p class="mt-1 text-sm">
                Te enviaremos opciones de paquetería con precio y tiempo de
                entrega para que elijas la que más te convenga. No realices
                ningún pago todavía: al elegir verás el total final y cómo
                pagar.
            </p>
        </div>
    </section>

    <!-- Transferencia -->
    <section
        v-else-if="instrucciones.banco"
        class="overflow-hidden rounded-3xl border-2 border-brand-teal/60 bg-background"
        aria-labelledby="titulo-transferencia"
    >
        <div class="flex items-center gap-3 bg-brand-teal-soft/70 px-5 py-4">
            <Building2
                class="size-6 text-brand-teal-strong"
                aria-hidden="true"
            />
            <h2
                id="titulo-transferencia"
                class="font-display text-lg font-bold"
            >
                Paga por transferencia
            </h2>
        </div>

        <div v-if="instrucciones.banco.configurado" class="space-y-4 p-5">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">Banco</dt>
                    <dd class="font-semibold">
                        {{ instrucciones.banco.nombre }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Beneficiario</dt>
                    <dd class="font-semibold">
                        {{ instrucciones.banco.beneficiario }}
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-muted-foreground">CLABE interbancaria</dt>
                    <dd class="mt-1 flex flex-wrap items-center gap-2">
                        <span
                            class="rounded-lg bg-surface px-3 py-1.5 font-mono text-base font-bold tracking-wider sm:text-lg"
                            >{{ agruparClabe(instrucciones.banco.clabe) }}</span
                        >
                        <CopiarTexto
                            :valor="instrucciones.banco.clabe ?? ''"
                            etiqueta="Copiar CLABE"
                        />
                    </dd>
                </div>
                <div v-if="instrucciones.banco.cuenta">
                    <dt class="text-muted-foreground">Cuenta</dt>
                    <dd class="font-mono font-semibold">
                        {{ instrucciones.banco.cuenta }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Referencia / concepto</dt>
                    <dd class="mt-1 flex flex-wrap items-center gap-2">
                        <span class="font-mono font-bold">{{
                            instrucciones.banco.referencia
                        }}</span>
                        <CopiarTexto
                            :valor="instrucciones.banco.referencia ?? ''"
                            etiqueta="Copiar"
                        />
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Monto exacto</dt>
                    <dd class="font-display text-xl font-extrabold">
                        {{ moneda(pedido.saldo_pendiente || pedido.total) }}
                    </dd>
                </div>
            </dl>
            <p
                v-if="pedido.vence_en"
                class="flex items-center gap-2 rounded-xl bg-warning/10 px-3 py-2 text-sm"
            >
                <CalendarClock
                    class="size-4 shrink-0 text-warning"
                    aria-hidden="true"
                />
                <span
                    >Paga antes del
                    <strong>{{ fechaHora(pedido.vence_en) }}</strong> para
                    conservar tu pedido.</span
                >
            </p>
            <p
                v-if="instrucciones.banco.indicaciones"
                class="text-sm text-muted-foreground"
            >
                {{ instrucciones.banco.indicaciones }}
            </p>
            <p v-if="instrucciones.texto" class="text-sm text-muted-foreground">
                {{ instrucciones.texto }}
            </p>
        </div>
        <div v-else class="flex gap-3 p-5 text-sm">
            <MessageCircle
                class="mt-0.5 size-5 shrink-0 text-brand-teal-strong"
                aria-hidden="true"
            />
            <p>
                <strong>Te contactaremos con los datos bancarios</strong> para
                que completes tu pago. Tu referencia será el folio
                <span class="font-mono font-semibold">{{ pedido.folio }}</span
                >.
            </p>
        </div>
    </section>

    <!-- Efectivo en tienda -->
    <section
        v-else-if="instrucciones.efectivo_presencial"
        class="flex gap-3 rounded-3xl border border-brand-green/40 bg-brand-green-soft/50 p-5"
    >
        <MapPin
            class="mt-0.5 size-6 shrink-0 text-brand-green-strong"
            aria-hidden="true"
        />
        <div class="text-sm">
            <h2 class="font-display text-lg font-bold">
                Paga en efectivo en nuestra tienda
            </h2>
            <p class="mt-1">
                Menciona tu folio
                <span class="font-mono font-semibold">{{ pedido.folio }}</span>
                y paga {{ moneda(pedido.saldo_pendiente || pedido.total) }}.
            </p>
            <template v-if="instrucciones.recoger">
                <p v-if="instrucciones.recoger.direccion" class="mt-2">
                    <strong>Dirección:</strong>
                    {{ instrucciones.recoger.direccion }}
                </p>
                <p v-if="instrucciones.recoger.horario">
                    <strong>Horario:</strong>
                    {{ instrucciones.recoger.horario }}
                </p>
            </template>
            <p v-else class="mt-2 text-muted-foreground">
                Te contactaremos para indicarte dónde y cuándo pagar.
            </p>
            <p v-if="pedido.vence_en" class="mt-2 text-muted-foreground">
                Te apartamos tus productos hasta el
                <strong class="font-semibold text-foreground">{{
                    fechaHora(pedido.vence_en)
                }}</strong>
            </p>
            <p v-if="instrucciones.texto" class="mt-2 text-muted-foreground">
                {{ instrucciones.texto }}
            </p>
        </div>
    </section>

    <!-- Contra entrega -->
    <section
        v-else-if="instrucciones.contra_entrega"
        class="flex gap-3 rounded-3xl border border-brand-teal/50 bg-brand-teal-soft/50 p-5"
    >
        <HandCoins
            class="mt-0.5 size-6 shrink-0 text-brand-teal-strong"
            aria-hidden="true"
        />
        <div class="text-sm">
            <h2 class="font-display text-lg font-bold">Pagas al recibir</h2>
            <p class="mt-1">
                Ten listo
                <strong>{{ moneda(pedido.saldo_pendiente) }}</strong> en
                efectivo cuando llegue tu pedido.
            </p>
            <p v-if="instrucciones.texto" class="mt-2 text-muted-foreground">
                {{ instrucciones.texto }}
            </p>
        </div>
    </section>
</template>
