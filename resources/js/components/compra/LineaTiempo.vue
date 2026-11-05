<script setup lang="ts">
import { Check, Circle, X } from 'lucide-vue-next';
import type { PasoLinea } from './types';

/**
 * Línea de tiempo de estado (pago o logística). Sin gráficas: pasos con progreso.
 */
defineProps<{
    titulo: string;
    pasos: PasoLinea[];
}>();
</script>

<template>
    <div>
        <h3
            class="mb-3 text-xs font-bold tracking-wider text-muted-foreground uppercase"
        >
            {{ titulo }}
        </h3>
        <ol class="relative space-y-0">
            <li
                v-for="(paso, i) in pasos"
                :key="paso.clave"
                class="relative flex animate-fade-up gap-3 pb-4 last:pb-0"
                :style="{ '--enter-delay': `${i * 110}ms` }"
            >
                <span
                    v-if="i < pasos.length - 1"
                    class="absolute top-7 left-[13px] h-[calc(100%-1.5rem)] w-0.5 overflow-hidden rounded-full bg-border"
                    aria-hidden="true"
                >
                    <span
                        v-if="paso.estado === 'hecho'"
                        class="linea-relleno block h-full w-full bg-brand-green"
                        :style="{ animationDelay: `${i * 110 + 200}ms` }"
                    />
                </span>
                <span
                    class="relative z-10 flex size-7 shrink-0 items-center justify-center rounded-full ring-4 ring-card transition-transform duration-200 hover:scale-110"
                    :class="{
                        'bg-brand-green text-brand-ink':
                            paso.estado === 'hecho',
                        'bg-brand-teal text-brand-ink shadow-[0_0_0_4px_rgb(100_203_212/0.3)] motion-safe:animate-pulse':
                            paso.estado === 'actual',
                        'border-2 border-border bg-card text-muted-foreground':
                            paso.estado === 'pendiente',
                        'bg-destructive text-white': paso.estado === 'error',
                    }"
                    aria-hidden="true"
                >
                    <Check v-if="paso.estado === 'hecho'" class="size-4" />
                    <X v-else-if="paso.estado === 'error'" class="size-4" />
                    <Circle v-else class="size-2.5 fill-current" />
                </span>
                <div class="min-w-0 pt-0.5">
                    <p
                        class="text-sm leading-snug"
                        :class="{
                            'font-semibold text-foreground':
                                paso.estado === 'actual' ||
                                paso.estado === 'error',
                            'text-foreground': paso.estado === 'hecho',
                            'text-muted-foreground':
                                paso.estado === 'pendiente',
                        }"
                    >
                        {{ paso.titulo }}
                    </p>
                    <p
                        v-if="paso.estado === 'actual'"
                        class="text-xs text-brand-teal-strong"
                    >
                        En curso
                    </p>
                    <span class="sr-only">
                        {{
                            {
                                hecho: 'completado',
                                actual: 'en curso',
                                pendiente: 'pendiente',
                                error: 'con problema',
                            }[paso.estado]
                        }}
                    </span>
                </div>
            </li>
        </ol>
    </div>
</template>

<style scoped>
.linea-relleno {
    transform-origin: top;
    animation: linea-crecer 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes linea-crecer {
    from {
        transform: scaleY(0);
    }
    to {
        transform: scaleY(1);
    }
}

@media (prefers-reduced-motion: reduce) {
    .linea-relleno {
        animation: none;
    }
}
</style>
