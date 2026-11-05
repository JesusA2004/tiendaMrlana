<script setup lang="ts">
import { CheckCircle2, Clock3, Sparkles } from 'lucide-vue-next';
import { dinero, fechaHora } from '@/components/admin/formato';
import type { OpcionEnvio } from '@/components/admin/types';

/** Opciones de envío enviadas al cliente (panel, sólo lectura). Resalta la elegida. */
defineProps<{ opciones: OpcionEnvio[]; compacto?: boolean }>();
</script>

<template>
    <ul
        :class="['grid gap-2', compacto ? '' : 'sm:grid-cols-2 xl:grid-cols-3']"
    >
        <li
            v-for="o in opciones"
            :key="o.id"
            :class="[
                'relative flex flex-col gap-1 rounded-2xl border p-3 text-sm',
                o.seleccionada
                    ? 'border-brand-green bg-brand-green-soft/50 ring-2 ring-brand-green/40'
                    : 'bg-background',
            ]"
        >
            <div class="flex items-start justify-between gap-2">
                <p class="min-w-0 font-semibold">
                    {{ o.paqueteria }}
                    <span class="font-normal text-muted-foreground"
                        >· {{ o.servicio }}</span
                    >
                </p>
                <p class="font-display text-base font-bold tabular-nums">
                    {{ dinero(o.costo) }}
                </p>
            </div>
            <p
                v-if="o.tiempo"
                class="flex items-center gap-1 text-xs text-muted-foreground"
            >
                <Clock3 class="size-3" /> {{ o.tiempo }}
            </p>
            <p v-if="o.notas" class="text-xs text-muted-foreground">
                {{ o.notas }}
            </p>
            <div class="mt-1 flex flex-wrap gap-1">
                <span
                    v-if="o.seleccionada"
                    class="inline-flex items-center gap-1 rounded-full bg-brand-green px-2 py-0.5 text-[0.68rem] font-bold text-brand-ink"
                    ><CheckCircle2 class="size-3" /> Elegida
                    {{ fechaHora(o.seleccionada_en) }}</span
                >
                <span
                    v-if="o.recomendada"
                    class="inline-flex items-center gap-1 rounded-full bg-brand-teal-soft px-2 py-0.5 text-[0.68rem] font-semibold text-brand-teal-strong"
                    ><Sparkles class="size-3" /> Recomendada</span
                >
                <span
                    v-if="o.mas_economica"
                    class="rounded-full bg-success/12 px-2 py-0.5 text-[0.68rem] font-semibold text-success"
                    >Más económica</span
                >
                <span
                    v-if="o.mas_rapida"
                    class="rounded-full bg-info/12 px-2 py-0.5 text-[0.68rem] font-semibold text-info"
                    >Más rápida</span
                >
            </div>
        </li>
    </ul>
</template>
