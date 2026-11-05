<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import { TONO_BADGE, TONO_PUNTO, tono } from './tonos';
import type { Etiqueta } from './types';

/** Badge de estado con punto de color. Acepta la etiqueta {value,label,color} del backend. */
const props = withDefaults(
    defineProps<{
        etiqueta?: Etiqueta | null;
        label?: string;
        color?: string;
        size?: 'sm' | 'md';
        pulso?: boolean;
        class?: string;
    }>(),
    {
        etiqueta: null,
        label: undefined,
        color: undefined,
        size: 'sm',
        pulso: false,
        class: '',
    },
);

const t = computed(() => tono(props.color ?? props.etiqueta?.color));
const texto = computed(() => props.label ?? props.etiqueta?.label ?? '—');
</script>

<template>
    <span
        :class="
            cn(
                'inline-flex items-center gap-1.5 rounded-full border font-medium whitespace-nowrap',
                size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-sm',
                TONO_BADGE[t],
                props.class,
            )
        "
    >
        <span class="relative flex size-1.5 shrink-0">
            <span
                v-if="pulso"
                :class="[
                    'absolute inline-flex size-full animate-ping rounded-full opacity-60',
                    TONO_PUNTO[t],
                ]"
            />
            <span
                :class="[
                    'relative inline-flex size-1.5 rounded-full',
                    TONO_PUNTO[t],
                ]"
            />
        </span>
        {{ texto }}
    </span>
</template>
