<script setup lang="ts">
import { Download, Share, SquarePlus, X } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import {
    consumirEventoInstalacion,
    eventoInstalacion,
    inicioSesionNavegacion,
    suscribirInstalacion,
} from './pwa';

/**
 * Invitación discreta a instalar la PWA.
 *  - Android/Chrome: usa el evento `beforeinstallprompt`.
 *  - iOS Safari: muestra instrucciones "Compartir → Agregar a inicio".
 * Se muestra tras unos segundos de navegación y se pospone 14 días al cerrarla.
 */
const CLAVE = 'mrlana:pwa-pospuesto';
const DIAS = 14;
const RETRASO_MS = 25_000;

const visible = ref(false);
const modo = ref<'android' | 'ios' | null>(null);
let temporizador: number | undefined;
let desuscribir: (() => void) | null = null;

function pospuesto(): boolean {
    try {
        const valor = Number(localStorage.getItem(CLAVE) ?? 0);

        return Date.now() - valor < DIAS * 86_400_000;
    } catch {
        return false;
    }
}

function posponer() {
    try {
        localStorage.setItem(CLAVE, String(Date.now()));
    } catch {
        // Almacenamiento no disponible (modo privado): sólo se oculta.
    }

    visible.value = false;
}

function instalada(): boolean {
    return (
        window.matchMedia('(display-mode: standalone)').matches ||
        (navigator as Navigator & { standalone?: boolean }).standalone === true
    );
}

function esIosSafari(): boolean {
    const ua = navigator.userAgent;
    const ios =
        /iphone|ipad|ipod/i.test(ua) ||
        (ua.includes('Macintosh') && navigator.maxTouchPoints > 1);

    return ios && /safari/i.test(ua) && !/crios|fxios|edgios/i.test(ua);
}

function evaluar() {
    window.clearTimeout(temporizador);

    if (instalada() || pospuesto()) {
        visible.value = false;

        return;
    }

    if (eventoInstalacion()) {
        modo.value = 'android';
    } else if (esIosSafari()) {
        modo.value = 'ios';
    } else {
        modo.value = null;
        visible.value = false;

        return;
    }

    const restante = Math.max(
        0,
        RETRASO_MS - (Date.now() - inicioSesionNavegacion),
    );
    temporizador = window.setTimeout(() => {
        visible.value = true;
    }, restante);
}

async function instalar() {
    const e = consumirEventoInstalacion();

    if (!e) {
        visible.value = false;

        return;
    }

    await e.prompt();
    const eleccion = await e.userChoice;

    if (eleccion.outcome === 'dismissed') {
        posponer();
    } else {
        visible.value = false;
    }
}

onMounted(() => {
    desuscribir = suscribirInstalacion(evaluar);
    evaluar();
});

onBeforeUnmount(() => {
    window.clearTimeout(temporizador);
    desuscribir?.();
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-500 ease-[cubic-bezier(0.22,1,0.36,1)]"
        enter-from-class="opacity-0 translate-y-6"
        leave-active-class="transition duration-200 ease-in"
        leave-to-class="opacity-0 translate-y-4"
    >
        <aside
            v-if="visible && modo"
            class="fixed bottom-3 left-3 z-[9999] w-[calc(100%-1.5rem)] max-w-sm rounded-2xl border border-white/10 bg-brand-deep-pattern p-4 pr-11 text-white shadow-2xl sm:bottom-5 sm:left-5"
            role="dialog"
            aria-labelledby="pwa-titulo"
        >
            <button
                type="button"
                class="absolute top-2.5 right-2.5 inline-flex size-8 items-center justify-center rounded-full text-white/70 hover:bg-white/10 hover:text-white"
                aria-label="Ahora no"
                @click="posponer"
            >
                <X class="size-4" />
            </button>
            <div class="flex items-start gap-3">
                <img
                    src="/icons/icon-192.png"
                    alt=""
                    class="size-12 shrink-0 rounded-xl ring-1 ring-white/15"
                    width="48"
                    height="48"
                />
                <div class="min-w-0">
                    <p
                        id="pwa-titulo"
                        class="font-display text-base leading-tight font-semibold"
                    >
                        Lleva Mr Lana en tu pantalla de inicio
                    </p>
                    <p
                        v-if="modo === 'android'"
                        class="mt-1 text-sm text-white/75"
                    >
                        Abre la tienda más rápido, como una app.
                    </p>
                    <p
                        v-else
                        class="mt-1 text-sm leading-relaxed text-white/80"
                    >
                        Toca
                        <Share
                            class="mx-0.5 inline size-4 -translate-y-px text-brand-teal"
                            aria-label="Compartir"
                        />
                        <strong class="font-semibold">Compartir</strong> y
                        después
                        <SquarePlus
                            class="mx-0.5 inline size-4 -translate-y-px text-brand-teal"
                            aria-hidden="true"
                        />
                        <strong class="font-semibold">Agregar a inicio</strong>.
                    </p>
                    <button
                        v-if="modo === 'android'"
                        type="button"
                        class="mt-3 inline-flex h-10 items-center gap-2 rounded-full bg-primary px-4 text-sm font-semibold text-primary-foreground transition-transform hover:scale-[1.02] active:scale-95"
                        @click="instalar"
                    >
                        <Download class="size-4" /> Instalar
                    </button>
                </div>
            </div>
        </aside>
    </Transition>
</template>
