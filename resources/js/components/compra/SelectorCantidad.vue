<script setup lang="ts">
import { Minus, Plus } from 'lucide-vue-next';
import { ref, watch } from 'vue';

/**
 * Selector de cantidad (+/− e input). Emite el valor final; quien lo usa decide cuándo enviar.
 */
const props = withDefaults(
    defineProps<{
        modelValue: number;
        max: number;
        min?: number;
        disabled?: boolean;
        etiqueta?: string;
    }>(),
    { min: 1, disabled: false, etiqueta: 'Cantidad' },
);

const emit = defineEmits<{ 'update:modelValue': [valor: number] }>();

const texto = ref(String(props.modelValue));
/** Dirección del último cambio para la micro-animación del número. */
const pulso = ref<'arriba' | 'abajo' | null>(null);

watch(
    () => props.modelValue,
    (v, anterior) => {
        texto.value = String(v);
        pulso.value = v > anterior ? 'arriba' : 'abajo';
        setTimeout(() => (pulso.value = null), 320);
    },
);

function fijar(valor: number) {
    const limitado = Math.min(
        Math.max(valor, props.min),
        Math.max(props.max, props.min),
    );

    texto.value = String(limitado);

    if (limitado !== props.modelValue) {
        emit('update:modelValue', limitado);
    }
}

function alSalir() {
    const n = Number.parseInt(texto.value, 10);

    fijar(Number.isNaN(n) ? props.modelValue : n);
}
</script>

<template>
    <div
        class="inline-flex h-10 items-center rounded-full border border-border bg-card shadow-xs transition-[border-color,box-shadow] duration-200 focus-within:border-brand-green focus-within:shadow-[0_0_0_3px_rgb(115_200_58/0.25)] hover:border-brand-green/60"
        role="group"
        :aria-label="etiqueta"
    >
        <button
            type="button"
            class="flex size-10 items-center justify-center rounded-full text-foreground transition duration-150 hover:bg-brand-green-soft hover:text-brand-green-strong focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:scale-90 disabled:pointer-events-none disabled:opacity-40"
            :disabled="disabled || modelValue <= min"
            aria-label="Quitar una pieza"
            @click="fijar(modelValue - 1)"
        >
            <Minus class="size-4" aria-hidden="true" />
        </button>
        <input
            v-model="texto"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="2"
            class="cantidad h-full w-10 border-0 bg-transparent text-center text-sm font-bold tabular-nums focus:outline-none disabled:opacity-50"
            :class="pulso ? `cantidad--${pulso}` : ''"
            :disabled="disabled"
            :aria-label="etiqueta"
            @blur="alSalir"
            @keydown.enter.prevent="alSalir"
        />
        <button
            type="button"
            class="flex size-10 items-center justify-center rounded-full text-foreground transition duration-150 hover:bg-brand-green-soft hover:text-brand-green-strong focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:scale-90 disabled:pointer-events-none disabled:opacity-40"
            :disabled="disabled || modelValue >= max"
            aria-label="Agregar una pieza"
            @click="fijar(modelValue + 1)"
        >
            <Plus class="size-4" aria-hidden="true" />
        </button>
    </div>
</template>

<style scoped>
@keyframes cantidad-arriba {
    0% {
        transform: translateY(6px);
        opacity: 0.2;
    }
    60% {
        transform: translateY(-2px) scale(1.15);
        opacity: 1;
    }
    100% {
        transform: none;
    }
}

@keyframes cantidad-abajo {
    0% {
        transform: translateY(-6px);
        opacity: 0.2;
    }
    60% {
        transform: translateY(2px) scale(1.15);
        opacity: 1;
    }
    100% {
        transform: none;
    }
}

.cantidad--arriba {
    animation: cantidad-arriba 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}

.cantidad--abajo {
    animation: cantidad-abajo 0.3s cubic-bezier(0.22, 1, 0.36, 1);
}

@media (prefers-reduced-motion: reduce) {
    .cantidad--arriba,
    .cantidad--abajo {
        animation: none;
    }
}
</style>
