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
        /** `oscuro` para usarlo sobre fondos verde profundo. */
        tono?: 'claro' | 'oscuro';
    }>(),
    { etiqueta: 'Copiar', compacto: false, tono: 'claro' },
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
        class="relative inline-flex shrink-0 items-center gap-1.5 rounded-full border font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none active:scale-95 motion-reduce:transition-none"
        :class="[
            compacto ? 'size-8 justify-center' : 'h-8 px-3 text-xs',
            copiado
                ? 'border-brand-green bg-brand-green text-brand-ink shadow-[0_0_0_4px_rgb(115_200_58/0.28)]'
                : tono === 'oscuro'
                  ? 'border-white/35 bg-white/10 text-white hover:border-white/70 hover:bg-white/20'
                  : 'border-border bg-card text-foreground hover:-translate-y-px hover:border-brand-green hover:bg-brand-green-soft hover:shadow-sm',
        ]"
        :aria-label="`${etiqueta}: ${valor}`"
        @click="copiar"
    >
        <span class="relative grid size-4 place-items-center">
            <Transition
                mode="out-in"
                enter-active-class="transition duration-300 ease-out motion-reduce:transition-none"
                enter-from-class="scale-0 -rotate-90 opacity-0"
                leave-active-class="transition duration-100 ease-in motion-reduce:transition-none"
                leave-to-class="scale-50 opacity-0"
            >
                <Check v-if="copiado" class="size-4" aria-hidden="true" />
                <Copy v-else class="size-4" aria-hidden="true" />
            </Transition>
        </span>
        <span v-if="!compacto">{{ copiado ? '¡Copiado!' : etiqueta }}</span>
        <span class="sr-only" aria-live="polite">{{
            copiado ? 'Copiado al portapapeles' : ''
        }}</span>
    </button>
</template>
