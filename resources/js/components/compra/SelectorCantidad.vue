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

watch(
    () => props.modelValue,
    (v) => (texto.value = String(v)),
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
        class="inline-flex h-10 items-center rounded-full border border-border bg-background shadow-xs"
        role="group"
        :aria-label="etiqueta"
    >
        <button
            type="button"
            class="flex size-10 items-center justify-center rounded-full text-foreground transition hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-40"
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
            class="h-full w-10 border-0 bg-transparent text-center text-sm font-semibold tabular-nums focus:outline-none disabled:opacity-50"
            :disabled="disabled"
            :aria-label="etiqueta"
            @blur="alSalir"
            @keydown.enter.prevent="alSalir"
        />
        <button
            type="button"
            class="flex size-10 items-center justify-center rounded-full text-foreground transition hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-40"
            :disabled="disabled || modelValue >= max"
            aria-label="Agregar una pieza"
            @click="fijar(modelValue + 1)"
        >
            <Plus class="size-4" aria-hidden="true" />
        </button>
    </div>
</template>
