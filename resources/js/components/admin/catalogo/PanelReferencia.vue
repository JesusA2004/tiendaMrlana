<script setup lang="ts">
import { EyeOff, ExternalLink, Info } from 'lucide-vue-next';
import { formatoFecha, formatoMoneda } from './formato';
import type { ReferenciaProducto } from './types';

defineProps<{
    referencia: ReferenciaProducto;
    modelo?: string | null;
}>();
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-dashed border-brand-teal/50 bg-brand-teal-soft/50"
        aria-labelledby="panel-referencia"
    >
        <header
            class="flex items-center gap-2 border-b border-brand-teal/30 px-4 py-3"
        >
            <EyeOff class="size-4 text-brand-teal-strong" aria-hidden="true" />
            <h2
                id="panel-referencia"
                class="text-sm font-bold text-brand-teal-strong"
            >
                Información de referencia (no pública)
            </h2>
        </header>

        <dl class="grid grid-cols-2 gap-x-3 gap-y-3 px-4 py-4 text-sm">
            <div class="col-span-2">
                <dt class="text-xs text-muted-foreground">
                    Precio de referencia
                </dt>
                <dd class="font-display text-2xl font-bold">
                    {{ formatoMoneda(referencia.precio) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Consultado el</dt>
                <dd class="font-medium">
                    {{ formatoFecha(referencia.consultada_en, false) }}
                </dd>
            </div>
            <div>
                <dt class="text-xs text-muted-foreground">Código / modelo</dt>
                <dd class="font-medium break-all">
                    {{ referencia.codigo ?? '—' }}
                    <span v-if="modelo" class="text-muted-foreground"
                        >· {{ modelo }}</span
                    >
                </dd>
            </div>
            <div class="col-span-2">
                <dt class="text-xs text-muted-foreground">Fuente</dt>
                <dd class="font-medium">{{ referencia.fuente ?? '—' }}</dd>
            </div>
            <div v-if="referencia.url" class="col-span-2">
                <dt class="sr-only">Enlace</dt>
                <dd>
                    <a
                        :href="referencia.url"
                        target="_blank"
                        rel="noopener noreferrer nofollow"
                        class="inline-flex items-center gap-1 text-sm font-semibold break-all text-brand-teal-strong underline underline-offset-2"
                    >
                        Ver ficha de referencia
                        <ExternalLink class="size-3.5 shrink-0" />
                    </a>
                </dd>
            </div>
        </dl>

        <p
            class="flex gap-2 border-t border-brand-teal/30 bg-background/60 px-4 py-3 text-xs leading-relaxed text-muted-foreground"
        >
            <Info class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            Precio público de otra tienda consultado como referencia de mercado.
            NO es el precio de venta de Mr Lana: define precio, existencias y
            tiempos con tu proveedor. No se muestra al público y no implica
            relación comercial con la marca de referencia.
        </p>
    </section>
</template>
