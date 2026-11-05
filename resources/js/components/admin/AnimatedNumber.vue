<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { dinero, entero } from './formato';

/** Contador animado (ease-out). Respeta prefers-reduced-motion. */
const props = withDefaults(
    defineProps<{
        value: number;
        formato?: 'numero' | 'moneda';
        duracion?: number;
    }>(),
    { formato: 'numero', duracion: 900 },
);

const mostrado = ref(0);
let frame = 0;

function animar(desde: number, hasta: number) {
    cancelAnimationFrame(frame);

    const reducido =
        typeof window !== 'undefined' &&
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    if (reducido || desde === hasta) {
        mostrado.value = hasta;

        return;
    }

    const inicio = performance.now();

    const paso = (ahora: number) => {
        const t = Math.min(1, (ahora - inicio) / props.duracion);
        const e = 1 - Math.pow(1 - t, 3);
        mostrado.value = desde + (hasta - desde) * e;

        if (t < 1) {
            frame = requestAnimationFrame(paso);
        }
    };

    frame = requestAnimationFrame(paso);
}

onMounted(() => animar(0, props.value));
watch(
    () => props.value,
    (nuevo, viejo) => animar(viejo ?? 0, nuevo),
);
</script>

<template>
    <span class="tabular-nums">{{
        formato === 'moneda' ? dinero(mostrado) : entero(Math.round(mostrado))
    }}</span>
</template>
