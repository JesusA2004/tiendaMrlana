<script setup lang="ts">
import { Check } from 'lucide-vue-next';

/**
 * Encabezado numerado de cada sección del checkout; el número cambia a ✓ al completarse.
 */
withDefaults(
    defineProps<{
        numero: number;
        titulo: string;
        hecho?: boolean;
        descripcion?: string;
        oculto?: boolean;
    }>(),
    { hecho: false, descripcion: undefined, oculto: true },
);
</script>

<template>
    <div class="mb-5 flex items-center gap-3">
        <span
            class="relative flex size-9 shrink-0 items-center justify-center rounded-full font-display text-sm font-bold transition-all duration-300 motion-reduce:transition-none"
            :class="
                hecho
                    ? 'bg-brand-green text-brand-ink shadow-[0_0_0_4px_rgb(115_200_58/0.2)]'
                    : 'bg-brand-deep text-white'
            "
            aria-hidden="true"
        >
            <Transition
                mode="out-in"
                enter-active-class="transition duration-300 ease-out motion-reduce:transition-none"
                enter-from-class="scale-0 -rotate-45 opacity-0"
                leave-active-class="transition duration-100 motion-reduce:transition-none"
                leave-to-class="scale-0 opacity-0"
            >
                <Check v-if="hecho" class="size-4" stroke-width="3" />
                <span v-else>{{ numero }}</span>
            </Transition>
        </span>
        <div class="min-w-0 flex-1">
            <h2
                class="font-display text-xl leading-tight font-bold text-brand-deep"
                :aria-hidden="oculto ? 'true' : undefined"
            >
                {{ titulo }}
            </h2>
            <p v-if="descripcion" class="text-xs text-muted-foreground">
                {{ descripcion }}
            </p>
        </div>
        <span
            v-if="hecho"
            class="hidden rounded-full bg-brand-green-soft px-2.5 py-1 text-[11px] font-semibold text-brand-green-strong motion-safe:animate-in motion-safe:zoom-in-90 motion-safe:fade-in sm:inline-flex"
            aria-hidden="true"
            >Listo</span
        >
    </div>
</template>
