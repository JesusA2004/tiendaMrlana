<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    BadgeCheck,
    CircleDollarSign,
    Clock3,
    LoaderCircle,
    Rocket,
    Sparkles,
    Truck,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { moneda } from './formato';
import type { OpcionEnvioCliente, PedidoCliente } from './types';

/**
 * Opciones de envío cotizadas para que el cliente elija (precio vs. urgencia).
 * Sin `url` (no es el dueño) se muestran sólo para consulta.
 */
const props = defineProps<{
    pedido: PedidoCliente;
    url?: string | null;
}>();

const opciones = computed(() => props.pedido.opciones_envio);
const recomendada = opciones.value.find((o) => o.recomendada);
const elegida = ref<number | null>(recomendada?.id ?? null);
const actual = computed(
    () => opciones.value.find((o) => o.id === elegida.value) ?? null,
);
const subtotalSinEnvio = computed(
    () => props.pedido.total - props.pedido.envio,
);

const form = useForm({ opcion_id: null as number | null });

function confirmar() {
    if (!props.url || !actual.value) {
        return;
    }

    form.opcion_id = actual.value.id;
    form.post(props.url, { preserveScroll: true });
}

function totalCon(o: OpcionEnvioCliente): number {
    return Math.round((subtotalSinEnvio.value + o.costo) * 100) / 100;
}
</script>

<template>
    <section
        class="overflow-hidden rounded-3xl border border-border bg-card shadow-brand"
        aria-labelledby="titulo-elegir-envio"
    >
        <div
            class="flex flex-wrap items-center gap-3 bg-brand-deep-pattern px-5 py-4 text-white"
        >
            <span
                class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-green text-brand-ink"
            >
                <Truck class="size-5" aria-hidden="true" />
            </span>
            <div class="min-w-0 flex-1">
                <h2
                    id="titulo-elegir-envio"
                    class="font-display text-xl font-bold"
                >
                    Tu cotización de envío está lista
                </h2>
                <p class="text-sm text-white/80">
                    Elige la paquetería que mejor te acomode según el precio y
                    qué tanto te urge.
                </p>
            </div>
        </div>

        <div class="p-4 sm:p-5">
            <div
                role="radiogroup"
                aria-label="Opciones de envío"
                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"
            >
                <button
                    v-for="o in opciones"
                    :key="o.id"
                    type="button"
                    role="radio"
                    :aria-checked="elegida === o.id"
                    :disabled="!url"
                    :class="[
                        'group relative flex flex-col gap-2 rounded-2xl border-2 bg-card p-4 text-left transition-all duration-300 ease-[cubic-bezier(0.22,1,0.36,1)] focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none motion-reduce:transition-none',
                        elegida === o.id
                            ? 'z-10 scale-[1.03] border-brand-green bg-brand-green-soft/60 ring-4 shadow-brand ring-brand-green/25'
                            : 'border-border hover:-translate-y-1 hover:border-brand-green/60 hover:shadow-brand-sm',
                        !url && 'cursor-default hover:translate-y-0',
                    ]"
                    @click="elegida = o.id"
                >
                    <span class="flex min-h-5 flex-wrap gap-1">
                        <span
                            v-if="o.recomendada"
                            class="inline-flex items-center gap-1 rounded-full bg-brand-green px-2 py-0.5 text-[0.7rem] font-bold text-brand-ink"
                            ><Sparkles class="size-3" aria-hidden="true" />
                            Recomendada</span
                        >
                        <span
                            v-if="o.mas_economica"
                            class="inline-flex items-center gap-1 rounded-full bg-success/12 px-2 py-0.5 text-[0.7rem] font-semibold text-success"
                            ><CircleDollarSign
                                class="size-3"
                                aria-hidden="true"
                            />
                            Más económica</span
                        >
                        <span
                            v-if="o.mas_rapida"
                            class="inline-flex items-center gap-1 rounded-full bg-info/12 px-2 py-0.5 text-[0.7rem] font-semibold text-info"
                            ><Rocket class="size-3" aria-hidden="true" /> Más
                            rápida</span
                        >
                    </span>
                    <span class="font-display text-lg leading-tight font-bold">
                        {{ o.paqueteria }}
                    </span>
                    <span class="text-sm text-muted-foreground">{{
                        o.servicio
                    }}</span>
                    <span
                        v-if="o.tiempo"
                        class="flex items-center gap-1.5 text-sm font-medium"
                    >
                        <Clock3
                            class="size-4 text-brand-teal-strong"
                            aria-hidden="true"
                        />
                        {{ o.tiempo }}
                    </span>
                    <span v-if="o.notas" class="text-xs text-muted-foreground">
                        {{ o.notas }}
                    </span>
                    <span
                        class="mt-auto flex items-end justify-between gap-2 border-t border-dashed pt-2"
                    >
                        <span class="text-xs text-muted-foreground">Envío</span>
                        <span
                            class="font-display text-2xl font-extrabold tabular-nums"
                            >{{ moneda(o.costo) }}</span
                        >
                    </span>
                    <BadgeCheck
                        v-if="elegida === o.id"
                        class="absolute top-3 right-3 size-6 animate-scale-in text-brand-green-strong"
                        aria-hidden="true"
                    />
                </button>
            </div>

            <div
                v-if="url"
                class="mt-4 flex flex-col gap-3 rounded-2xl bg-brand-soft-gradient p-4 sm:flex-row sm:items-center"
            >
                <div class="min-w-0 flex-1 text-sm">
                    <template v-if="actual">
                        <p>
                            Total con
                            <strong>{{ actual.paqueteria }}</strong
                            >:
                            <span
                                class="font-display text-xl font-extrabold text-brand-deep tabular-nums"
                                >{{ moneda(totalCon(actual)) }}</span
                            >
                        </p>
                        <p class="text-xs text-muted-foreground">
                            Productos {{ moneda(subtotalSinEnvio) }} + envío
                            {{ moneda(actual.costo) }}. Después de elegir te
                            mostramos cómo pagar.
                        </p>
                    </template>
                    <p v-else class="text-muted-foreground">
                        Toca una opción para ver tu total final.
                    </p>
                    <p
                        v-if="form.errors.opcion_id"
                        class="mt-1 text-xs font-medium text-destructive"
                        role="alert"
                    >
                        {{ form.errors.opcion_id }}
                    </p>
                </div>
                <Button
                    size="lg"
                    class="rounded-full font-bold shadow-brand-sm hover:-translate-y-0.5 hover:shadow-brand"
                    :disabled="!actual || form.processing"
                    @click="confirmar"
                >
                    <LoaderCircle
                        v-if="form.processing"
                        class="size-4 animate-spin"
                    />
                    Elegir este envío
                </Button>
            </div>
            <p v-else class="mt-3 text-xs text-muted-foreground">
                El cliente debe elegir una opción desde su cuenta.
            </p>
        </div>
    </section>
</template>
