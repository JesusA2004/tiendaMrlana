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
        class="flex gap-4 rounded-3xl border border-warning/30 bg-card p-5 shadow-brand-sm"
    >
        <span
            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-warning/12"
        >
            <Clock3
                class="size-6 text-warning motion-safe:animate-pulse"
                aria-hidden="true"
            />
        </span>
        <div>
            <h2 class="font-display text-lg font-bold text-brand-deep">
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

    <!-- Transferencia: tarjeta destacada verde profundo -->
    <section
        v-else-if="instrucciones.banco"
        class="relative overflow-hidden rounded-3xl bg-brand-deep-pattern text-white shadow-brand"
        aria-labelledby="titulo-transferencia"
    >
        <div
            class="pointer-events-none absolute -top-20 -right-16 size-56 rounded-full bg-brand-green/25 blur-3xl"
            aria-hidden="true"
        />
        <div
            class="relative flex items-center gap-3 border-b border-white/10 px-5 py-4 sm:px-6"
        >
            <span
                class="flex size-11 items-center justify-center rounded-full bg-brand-green text-brand-ink shadow-md"
            >
                <Building2 class="size-5" aria-hidden="true" />
            </span>
            <div>
                <h2
                    id="titulo-transferencia"
                    class="font-display text-xl font-bold"
                >
                    Paga por transferencia
                </h2>
                <p class="text-xs text-white/75">
                    Copia los datos con un toque y transfiere desde tu banco.
                </p>
            </div>
        </div>

        <div
            v-if="instrucciones.banco.configurado"
            class="relative space-y-4 p-5 sm:p-6"
        >
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div class="rounded-2xl bg-white/8 p-3 ring-1 ring-white/10">
                    <dt class="text-xs text-white/70">Banco</dt>
                    <dd class="font-semibold">
                        {{ instrucciones.banco.nombre }}
                    </dd>
                </div>
                <div class="rounded-2xl bg-white/8 p-3 ring-1 ring-white/10">
                    <dt class="text-xs text-white/70">Beneficiario</dt>
                    <dd class="font-semibold">
                        {{ instrucciones.banco.beneficiario }}
                    </dd>
                </div>
                <div
                    class="rounded-2xl bg-white/10 p-3 ring-1 ring-brand-green/40 transition-colors hover:bg-white/15 sm:col-span-2"
                >
                    <dt class="text-xs text-white/70">CLABE interbancaria</dt>
                    <dd class="mt-1 flex flex-wrap items-center gap-3">
                        <span
                            class="font-mono text-lg font-bold tracking-wider text-white sm:text-2xl"
                            >{{ agruparClabe(instrucciones.banco.clabe) }}</span
                        >
                        <CopiarTexto
                            :valor="instrucciones.banco.clabe ?? ''"
                            etiqueta="Copiar CLABE"
                            tono="oscuro"
                        />
                    </dd>
                </div>
                <div
                    v-if="instrucciones.banco.cuenta"
                    class="rounded-2xl bg-white/8 p-3 ring-1 ring-white/10"
                >
                    <dt class="text-xs text-white/70">Cuenta</dt>
                    <dd class="font-mono font-semibold">
                        {{ instrucciones.banco.cuenta }}
                    </dd>
                </div>
                <div class="rounded-2xl bg-white/8 p-3 ring-1 ring-white/10">
                    <dt class="text-xs text-white/70">Referencia / concepto</dt>
                    <dd class="mt-1 flex flex-wrap items-center gap-2">
                        <span class="font-mono font-bold">{{
                            instrucciones.banco.referencia
                        }}</span>
                        <CopiarTexto
                            :valor="instrucciones.banco.referencia ?? ''"
                            etiqueta="Copiar"
                            tono="oscuro"
                        />
                    </dd>
                </div>
                <div
                    class="rounded-2xl bg-brand-gradient p-3 text-brand-ink shadow-md"
                >
                    <dt class="text-xs font-semibold text-brand-ink/80">
                        Monto exacto
                    </dt>
                    <dd
                        class="font-display text-2xl font-extrabold tabular-nums"
                    >
                        {{ moneda(pedido.saldo_pendiente || pedido.total) }}
                    </dd>
                </div>
            </dl>
            <p
                v-if="pedido.vence_en"
                class="flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-sm text-brand-ink"
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
                class="text-sm text-white/80"
            >
                {{ instrucciones.banco.indicaciones }}
            </p>
            <p v-if="instrucciones.texto" class="text-sm text-white/80">
                {{ instrucciones.texto }}
            </p>
        </div>
        <div v-else class="relative flex gap-3 p-5 text-sm sm:p-6">
            <MessageCircle
                class="mt-0.5 size-5 shrink-0 text-brand-green"
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
        class="relative flex gap-4 overflow-hidden rounded-3xl border border-brand-green/40 bg-card p-5 shadow-brand-sm sm:p-6"
    >
        <span
            class="absolute inset-y-0 left-0 w-1.5 bg-brand-gradient"
            aria-hidden="true"
        />
        <span
            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-green-soft"
        >
            <MapPin class="size-6 text-brand-green-strong" aria-hidden="true" />
        </span>
        <div class="text-sm">
            <h2 class="font-display text-lg font-bold text-brand-deep">
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
        class="relative flex gap-4 overflow-hidden rounded-3xl border border-brand-teal/50 bg-card p-5 shadow-brand-sm sm:p-6"
    >
        <span
            class="absolute inset-y-0 left-0 w-1.5 bg-brand-teal"
            aria-hidden="true"
        />
        <span
            class="flex size-11 shrink-0 items-center justify-center rounded-full bg-brand-teal-soft"
        >
            <HandCoins
                class="size-6 text-brand-teal-strong"
                aria-hidden="true"
            />
        </span>
        <div class="text-sm">
            <h2 class="font-display text-lg font-bold text-brand-deep">
                Pagas al recibir
            </h2>
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
