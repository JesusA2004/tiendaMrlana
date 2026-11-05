<script setup lang="ts">
import { useIntersectionObserver } from '@vueuse/core';
import { onBeforeUnmount, ref } from 'vue';
import { entero, prefiereMenosMovimiento } from './tienda';

const props = withDefaults(
    defineProps<{ valor: number; duracion?: number }>(),
    { duracion: 1400 },
);

const el = ref<HTMLElement | null>(null);
const mostrado = ref(entero(props.valor));
let iniciado = false;
let cuadro = 0;

function animar() {
    if (iniciado) {
        return;
    }

    iniciado = true;

    if (prefiereMenosMovimiento() || props.valor <= 0) {
        mostrado.value = entero(props.valor);

        return;
    }

    const inicio = performance.now();
    const paso = (ahora: number) => {
        const t = Math.min(1, (ahora - inicio) / props.duracion);
        const suave = 1 - Math.pow(1 - t, 3);
        mostrado.value = entero(props.valor * suave);

        if (t < 1) {
            cuadro = requestAnimationFrame(paso);
        }
    };

    mostrado.value = '0';
    cuadro = requestAnimationFrame(paso);
}

const { stop } = useIntersectionObserver(
    el,
    ([entrada]) => {
        if (entrada?.isIntersecting) {
            animar();
            stop();
        }
    },
    { threshold: 0.4 },
);

onBeforeUnmount(() => cancelAnimationFrame(cuadro));
</script>

<template>
    <span ref="el" class="tabular-nums">{{ mostrado }}</span>
</template>
