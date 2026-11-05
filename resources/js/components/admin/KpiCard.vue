<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';
import { ArrowUpRight } from 'lucide-vue-next';
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import AnimatedNumber from './AnimatedNumber.vue';
import { TONO_ICONO, tono } from './tonos';

/** Tarjeta KPI con contador animado; si trae href es un enlace a la bandeja correspondiente. */
const props = withDefaults(
    defineProps<{
        label: string;
        value: number;
        formato?: 'numero' | 'moneda';
        hint?: string;
        icon?: LucideIcon;
        color?: string;
        href?: string;
        destacado?: boolean;
    }>(),
    {
        formato: 'numero',
        hint: '',
        icon: undefined,
        color: 'neutral',
        href: undefined,
        destacado: false,
    },
);

const t = computed(() => tono(props.color));
const urgente = computed(
    () => props.value > 0 && ['warning', 'danger', 'info'].includes(t.value),
);
</script>

<template>
    <component
        :is="href ? Link : 'div'"
        :href="href"
        :class="
            cn(
                'group relative flex min-h-[7.5rem] hover-lift flex-col justify-between gap-3 overflow-hidden rounded-2xl border bg-card p-4 shadow-brand-sm',
                href && 'cursor-pointer focus-brand',
                destacado &&
                    'border-transparent bg-brand-deep-pattern text-white shadow-lg',
            )
        "
    >
        <div class="flex items-start justify-between gap-3">
            <p
                :class="[
                    'text-sm leading-snug font-medium',
                    destacado ? 'text-white/80' : 'text-muted-foreground',
                ]"
            >
                {{ label }}
            </p>
            <span
                v-if="icon"
                :class="[
                    'grid size-9 shrink-0 place-items-center rounded-xl transition-transform duration-300 group-hover:scale-110 group-hover:rotate-[-6deg]',
                    destacado ? 'bg-white/15 text-brand-green' : TONO_ICONO[t],
                ]"
            >
                <component :is="icon" class="size-4.5" />
            </span>
        </div>
        <div>
            <p
                :class="[
                    'font-display text-3xl leading-none font-bold tracking-tight',
                    destacado && 'text-white',
                ]"
            >
                <AnimatedNumber :value="value" :formato="formato" />
            </p>
            <p
                v-if="hint"
                :class="[
                    'mt-1.5 flex items-center gap-1 text-xs',
                    destacado ? 'text-white/70' : 'text-muted-foreground',
                ]"
            >
                <span
                    v-if="urgente && !destacado"
                    :class="[
                        'size-1.5 rounded-full',
                        t === 'danger'
                            ? 'bg-destructive'
                            : t === 'warning'
                              ? 'bg-warning'
                              : 'bg-info',
                    ]"
                />
                {{ hint }}
            </p>
        </div>
        <ArrowUpRight
            v-if="href"
            class="absolute right-3 bottom-3 size-4 opacity-0 transition group-hover:opacity-60"
        />
    </component>
</template>
