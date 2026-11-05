<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Check } from 'lucide-vue-next';
import type { Component } from 'vue';
import { computed } from 'vue';

/**
 * Stepper visual del flujo de compra con barra de progreso animada.
 * Sólo presentación: cada paso puede ser enlace (`href`) o emitir `ir`.
 */
export interface PasoCompra {
    clave: string;
    titulo: string;
    icono?: Component;
    estado: 'hecho' | 'actual' | 'pendiente';
    href?: string;
}

const props = withDefaults(
    defineProps<{
        pasos: PasoCompra[];
        /** 0–100. Si se omite se calcula con los pasos hechos. */
        progreso?: number | null;
        etiqueta?: string;
        tono?: 'claro' | 'oscuro';
        interactivo?: boolean;
    }>(),
    {
        progreso: null,
        etiqueta: 'Pasos de compra',
        tono: 'claro',
        interactivo: false,
    },
);

const emit = defineEmits<{ ir: [clave: string] }>();

const avance = computed(() => {
    if (props.progreso !== null) {
        return Math.min(100, Math.max(0, props.progreso));
    }

    const total = props.pasos.length - 1;
    const hechos = props.pasos.filter((p) => p.estado === 'hecho').length;

    return total > 0 ? Math.min(100, (hechos / total) * 100) : 100;
});

const oscuro = computed(() => props.tono === 'oscuro');
</script>

<template>
    <nav :aria-label="etiqueta" class="w-full">
        <ol class="relative flex items-start justify-between">
            <!-- Pista + progreso (entre el centro del primer y último círculo) -->
            <span
                class="absolute top-[18px] right-[calc(50%/var(--n))] left-[calc(50%/var(--n))] h-1 -translate-y-1/2 overflow-hidden rounded-full"
                :class="oscuro ? 'bg-white/20' : 'bg-brand-deep/10'"
                :style="{ '--n': pasos.length }"
                aria-hidden="true"
            >
                <span
                    class="block h-full rounded-full bg-brand-gradient transition-[width] duration-700 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none"
                    :style="{ width: `${avance}%` }"
                />
            </span>

            <li
                v-for="(paso, i) in pasos"
                :key="paso.clave"
                class="relative z-10 flex min-w-0 flex-1 flex-col items-center"
            >
                <component
                    :is="paso.href ? Link : interactivo ? 'button' : 'span'"
                    :href="paso.href"
                    :type="!paso.href && interactivo ? 'button' : undefined"
                    class="group flex flex-col items-center gap-1.5 rounded-xl px-1 text-center focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :class="
                        paso.href || interactivo
                            ? 'cursor-pointer'
                            : 'cursor-default'
                    "
                    :aria-current="
                        paso.estado === 'actual' ? 'step' : undefined
                    "
                    @click="
                        !paso.href && interactivo
                            ? emit('ir', paso.clave)
                            : null
                    "
                >
                    <span
                        class="relative flex size-9 items-center justify-center rounded-full text-sm font-bold transition-all duration-300 motion-reduce:transition-none"
                        :class="[
                            paso.estado === 'hecho'
                                ? 'bg-brand-green text-brand-ink shadow-sm'
                                : paso.estado === 'actual'
                                  ? 'scale-110 bg-brand-deep text-white shadow-md ring-4 ring-brand-green/45'
                                  : oscuro
                                    ? 'border-2 border-white/40 bg-brand-deep-2 text-white/80'
                                    : 'border-2 border-brand-deep/15 bg-card text-muted-foreground',
                            paso.href || interactivo
                                ? 'group-hover:-translate-y-0.5 group-hover:shadow-md'
                                : '',
                        ]"
                    >
                        <Transition
                            mode="out-in"
                            enter-active-class="transition duration-300 ease-out motion-reduce:transition-none"
                            enter-from-class="scale-0 opacity-0"
                            leave-active-class="transition duration-100 motion-reduce:transition-none"
                            leave-to-class="scale-0 opacity-0"
                        >
                            <Check
                                v-if="paso.estado === 'hecho'"
                                class="size-4"
                                stroke-width="3"
                                aria-hidden="true"
                            />
                            <component
                                :is="paso.icono"
                                v-else-if="paso.icono"
                                class="size-4"
                                aria-hidden="true"
                            />
                            <span v-else>{{ i + 1 }}</span>
                        </Transition>
                        <span
                            v-if="paso.estado === 'actual'"
                            class="absolute inset-0 rounded-full bg-brand-green/40 motion-safe:animate-ping"
                            style="animation-duration: 2.4s"
                            aria-hidden="true"
                        />
                    </span>
                    <span
                        class="max-w-[6.5rem] text-[11px] leading-tight font-semibold transition-colors sm:text-xs"
                        :class="
                            paso.estado === 'pendiente'
                                ? oscuro
                                    ? 'text-white/70'
                                    : 'text-muted-foreground'
                                : oscuro
                                  ? 'text-white'
                                  : 'text-brand-deep'
                        "
                    >
                        {{ paso.titulo }}
                        <span class="sr-only">
                            ({{
                                paso.estado === 'hecho'
                                    ? 'completado'
                                    : paso.estado === 'actual'
                                      ? 'paso actual'
                                      : 'pendiente'
                            }})
                        </span>
                    </span>
                </component>
            </li>
        </ol>
    </nav>
</template>
