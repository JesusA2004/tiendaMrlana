<script setup lang="ts">
import { Truck } from 'lucide-vue-next';
import { moneda } from './formato';

/**
 * Totales calculados por el servidor. Nunca muestra "Envío gratis" sin regla:
 * si el envío no está calculado, se indica "Por cotizar" / "Se calcula en el checkout".
 */
withDefaults(
    defineProps<{
        subtotal: number;
        descuentoOfertas?: number;
        descuentoCupon?: number;
        cuponCodigo?: string | null;
        envio: number;
        envioPorCotizar: boolean;
        recogerEnTienda?: boolean;
        textoEnvioPendiente?: string;
        total: number;
        etiquetaTotal?: string;
        saldoPendiente?: number | null;
    }>(),
    {
        descuentoOfertas: 0,
        descuentoCupon: 0,
        cuponCodigo: null,
        recogerEnTienda: false,
        textoEnvioPendiente: 'Por cotizar',
        etiquetaTotal: 'Total',
        saldoPendiente: null,
    },
);
</script>

<template>
    <dl class="space-y-2 text-sm">
        <div class="flex items-center justify-between gap-4">
            <dt class="text-muted-foreground">Subtotal</dt>
            <dd class="font-medium tabular-nums">{{ moneda(subtotal) }}</dd>
        </div>
        <div
            v-if="descuentoOfertas > 0"
            class="flex items-center justify-between gap-4"
        >
            <dt class="text-muted-foreground">Ahorro en ofertas</dt>
            <dd class="font-medium text-brand-green-strong tabular-nums">
                incluido (−{{ moneda(descuentoOfertas) }})
            </dd>
        </div>
        <div
            v-if="descuentoCupon > 0"
            class="flex items-center justify-between gap-4"
        >
            <dt class="text-muted-foreground">
                Cupón
                <span
                    v-if="cuponCodigo"
                    class="ml-1 rounded bg-brand-green-soft px-1.5 py-0.5 font-mono text-xs text-brand-green-strong"
                    >{{ cuponCodigo }}</span
                >
            </dt>
            <dd class="font-medium text-brand-green-strong tabular-nums">
                −{{ moneda(descuentoCupon) }}
            </dd>
        </div>
        <div class="flex items-center justify-between gap-4">
            <dt class="flex items-center gap-1.5 text-muted-foreground">
                <Truck class="size-4" aria-hidden="true" />
                Envío
            </dt>
            <dd class="text-right font-medium tabular-nums">
                <template v-if="recogerEnTienda">Recoges en tienda</template>
                <span v-else-if="envioPorCotizar" class="text-warning">
                    {{ textoEnvioPendiente }}
                </span>
                <template v-else>{{ moneda(envio) }}</template>
            </dd>
        </div>
        <div
            class="mt-1 flex items-end justify-between gap-4 border-t border-border pt-3"
        >
            <dt class="font-display text-base font-bold">
                {{ etiquetaTotal }}
            </dt>
            <dd
                class="font-display text-2xl leading-none font-extrabold tabular-nums"
            >
                {{ moneda(total) }}
                <span class="ml-0.5 text-xs font-semibold text-muted-foreground"
                    >MXN</span
                >
            </dd>
        </div>
        <p
            v-if="envioPorCotizar && !recogerEnTienda"
            class="text-xs text-muted-foreground"
        >
            El total no incluye envío todavía.
        </p>
        <div
            v-if="saldoPendiente !== null && saldoPendiente > 0"
            class="flex items-center justify-between gap-4 rounded-lg bg-warning/10 px-3 py-2"
        >
            <dt class="font-medium text-warning">Saldo pendiente</dt>
            <dd class="font-bold text-warning tabular-nums">
                {{ moneda(saldoPendiente) }}
            </dd>
        </div>
    </dl>
</template>
