<script setup lang="ts">
import {
    ChevronLeft,
    ChevronRight,
    ImageOff,
    Maximize2,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Galería del detalle: imagen principal con zoom al pasar el cursor (escritorio),
 * deslizamiento táctil, miniaturas y visor a pantalla completa.
 */
const props = defineProps<{ imagenes: string[]; nombre: string }>();

const actual = ref(0);
const zoom = ref(false);
const origen = ref('50% 50%');
const visor = ref(false);
let inicioX: number | null = null;

const total = computed(() => props.imagenes.length);
const imagen = computed(() => props.imagenes[actual.value] ?? null);

function ir(indice: number) {
    if (!total.value) {
        return;
    }

    actual.value = (indice + total.value) % total.value;
}

function mover(e: PointerEvent) {
    if (e.pointerType !== 'mouse') {
        return;
    }

    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;
    origen.value = `${x}% ${y}%`;
}

function alTocar(e: PointerEvent) {
    inicioX = e.clientX;
}

function alSoltar(e: PointerEvent) {
    if (inicioX === null) {
        return;
    }

    const delta = e.clientX - inicioX;
    inicioX = null;

    if (Math.abs(delta) > 40 && total.value > 1) {
        ir(actual.value + (delta < 0 ? 1 : -1));
    }
}
</script>

<template>
    <div class="flex flex-col gap-3 lg:flex-row-reverse lg:gap-4">
        <!-- Imagen principal -->
        <div class="relative min-w-0 flex-1">
            <div
                class="group relative aspect-square cursor-zoom-in overflow-hidden rounded-3xl bg-card ring-1 shadow-brand-sm ring-border/70 select-none"
                @pointerenter="zoom = true"
                @pointerleave="zoom = false"
                @pointermove="mover"
                @pointerdown="alTocar"
                @pointerup="alSoltar"
                @click="imagen && (visor = true)"
            >
                <Transition
                    mode="out-in"
                    enter-active-class="transition-[opacity,filter] duration-300 ease-out motion-reduce:transition-none"
                    enter-from-class="opacity-0 blur-sm"
                    leave-active-class="transition-opacity duration-150"
                    leave-to-class="opacity-0"
                >
                    <img
                        v-if="imagen"
                        :key="imagen"
                        :src="imagen"
                        :alt="`${nombre} — imagen ${actual + 1} de ${total}`"
                        class="size-full object-contain transition-transform duration-200 ease-out motion-reduce:transition-none"
                        :class="zoom ? 'lg:scale-[1.8]' : 'scale-100'"
                        :style="{ transformOrigin: origen }"
                        draggable="false"
                        fetchpriority="high"
                    />
                    <div
                        v-else
                        class="flex size-full flex-col items-center justify-center gap-2 bg-brand-pattern-light text-muted-foreground"
                    >
                        <ImageOff class="size-10" aria-hidden="true" />
                        <span class="text-sm">Imagen no disponible</span>
                    </div>
                </Transition>

                <button
                    v-if="imagen"
                    type="button"
                    class="absolute right-3 bottom-3 inline-flex size-10 items-center justify-center rounded-full bg-background/90 text-foreground shadow-md backdrop-blur transition-transform hover:scale-105"
                    aria-label="Ver imagen en pantalla completa"
                    @click.stop="visor = true"
                >
                    <Maximize2 class="size-4" />
                </button>

                <template v-if="total > 1">
                    <button
                        type="button"
                        class="absolute top-1/2 left-3 inline-flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-background/90 shadow-md backdrop-blur transition-opacity lg:opacity-0 lg:group-hover:opacity-100"
                        aria-label="Imagen anterior"
                        @click.stop="ir(actual - 1)"
                    >
                        <ChevronLeft class="size-5" />
                    </button>
                    <button
                        type="button"
                        class="absolute top-1/2 right-3 inline-flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-background/90 shadow-md backdrop-blur transition-opacity lg:opacity-0 lg:group-hover:opacity-100"
                        aria-label="Imagen siguiente"
                        @click.stop="ir(actual + 1)"
                    >
                        <ChevronRight class="size-5" />
                    </button>
                    <span
                        class="absolute top-3 left-3 rounded-full bg-background/90 px-2.5 py-1 text-xs font-semibold tabular-nums shadow-sm lg:hidden"
                        >{{ actual + 1 }} / {{ total }}</span
                    >
                </template>
            </div>
        </div>

        <!-- Miniaturas -->
        <ul
            v-if="total > 1"
            class="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:none] lg:max-h-[560px] lg:w-20 lg:flex-col lg:overflow-y-auto lg:pb-0 [&::-webkit-scrollbar]:hidden"
            aria-label="Miniaturas"
        >
            <li v-for="(src, indice) in imagenes" :key="src" class="shrink-0">
                <button
                    type="button"
                    class="block size-16 overflow-hidden rounded-2xl bg-card ring-offset-2 ring-offset-background transition-[box-shadow,opacity,transform] duration-200 hover:-translate-y-0.5 motion-reduce:hover:translate-y-0 sm:size-20"
                    :class="
                        indice === actual
                            ? 'ring-2 ring-brand-green'
                            : 'opacity-70 hover:opacity-100'
                    "
                    :aria-label="`Ver imagen ${indice + 1}`"
                    :aria-current="indice === actual ? 'true' : undefined"
                    @click="ir(indice)"
                >
                    <img
                        :src="src"
                        alt=""
                        class="size-full object-cover"
                        loading="lazy"
                        decoding="async"
                    />
                </button>
            </li>
        </ul>

        <!-- Visor -->
        <Dialog v-model:open="visor">
            <DialogContent
                class="flex h-[92svh] w-[96vw] max-w-[96vw] flex-col gap-0 border-0 bg-black/95 p-0 text-white sm:max-w-[96vw]"
                :show-close-button="false"
            >
                <DialogTitle class="sr-only">{{ nombre }}</DialogTitle>
                <DialogDescription class="sr-only">
                    Imagen {{ actual + 1 }} de {{ total }}
                </DialogDescription>
                <div class="flex items-center justify-between p-3">
                    <span class="text-sm text-white/70 tabular-nums"
                        >{{ actual + 1 }} / {{ total }}</span
                    >
                    <DialogClose
                        class="inline-flex size-10 items-center justify-center rounded-full bg-white/10 hover:bg-white/20"
                        aria-label="Cerrar"
                    >
                        <X class="size-5" />
                    </DialogClose>
                </div>
                <div
                    class="relative flex min-h-0 flex-1 items-center justify-center p-2"
                    @pointerdown="alTocar"
                    @pointerup="alSoltar"
                >
                    <img
                        v-if="imagen"
                        :src="imagen"
                        :alt="nombre"
                        class="max-h-full max-w-full object-contain"
                        decoding="async"
                        draggable="false"
                    />
                    <template v-if="total > 1">
                        <button
                            type="button"
                            class="absolute top-1/2 left-3 inline-flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 hover:bg-white/20"
                            aria-label="Imagen anterior"
                            @click="ir(actual - 1)"
                        >
                            <ChevronLeft class="size-6" />
                        </button>
                        <button
                            type="button"
                            class="absolute top-1/2 right-3 inline-flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 hover:bg-white/20"
                            aria-label="Imagen siguiente"
                            @click="ir(actual + 1)"
                        >
                            <ChevronRight class="size-6" />
                        </button>
                    </template>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
