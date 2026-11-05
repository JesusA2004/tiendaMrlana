<script setup lang="ts">
import { Check, Copy } from 'lucide-vue-next';
import { onBeforeUnmount, ref } from 'vue';

/**
 * Botón para copiar un valor (folio, CLABE, referencia) con confirmación accesible.
 */
const props = withDefaults(
    defineProps<{
        valor: string;
        etiqueta?: string;
        compacto?: boolean;
    }>(),
    { etiqueta: 'Copiar', compacto: false },
);

const copiado = ref(false);
let temporizador: ReturnType<typeof setTimeout> | null = null;

async function copiar() {
    try {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(props.valor);
        } else {
            const area = document.createElement('textarea');
            area.value = props.valor;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            document.body.removeChild(area);
        }

        copiado.value = true;

        if (temporizador) {
            clearTimeout(temporizador);
        }

        temporizador = setTimeout(() => (copiado.value = false), 2000);
    } catch {
        copiado.value = false;
    }
}

onBeforeUnmount(() => {
    if (temporizador) {
        clearTimeout(temporizador);
    }
});
</script>

<template>
    <button
        type="button"
        class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-border bg-background font-semibold text-foreground transition hover:border-brand-green hover:bg-brand-green-soft focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        :class="compacto ? 'size-8 justify-center' : 'h-8 px-3 text-xs'"
        :aria-label="`${etiqueta}: ${valor}`"
        @click="copiar"
    >
        <Check
            v-if="copiado"
            class="size-4 text-brand-green-strong"
            aria-hidden="true"
        />
        <Copy v-else class="size-4" aria-hidden="true" />
        <span v-if="!compacto">{{ copiado ? 'Copiado' : etiqueta }}</span>
        <span class="sr-only" aria-live="polite">{{
            copiado ? 'Copiado al portapapeles' : ''
        }}</span>
    </button>
</template>
