<script setup lang="ts">
import {
    Download,
    ExternalLink,
    FileText,
    ZoomIn,
    ZoomOut,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { fecha, fechaHora, tamanoArchivo } from './formato';
import type { Comprobante } from './types';

/**
 * Visor embebido de comprobantes privados (ruta protegida admin.comprobantes.show).
 * Imagen con zoom o PDF en iframe del mismo origen.
 */
const props = withDefaults(
    defineProps<{
        comprobante: Comprobante;
        alto?: string;
    }>(),
    { alto: 'h-[60vh] min-h-80' },
);

const zoom = ref(1);
const errorCarga = ref(false);

watch(
    () => props.comprobante.id,
    () => {
        zoom.value = 1;
        errorCarga.value = false;
    },
);
</script>

<template>
    <figure class="flex flex-col overflow-hidden rounded-2xl border bg-card">
        <div
            class="flex flex-wrap items-center justify-between gap-2 border-b bg-surface/70 px-3 py-2"
        >
            <figcaption class="min-w-0 text-xs text-muted-foreground">
                <p class="truncate font-medium text-foreground">
                    {{ comprobante.nombre_original }}
                </p>
                <p>
                    {{ tamanoArchivo(comprobante.tamano) }} · subido
                    {{ fechaHora(comprobante.created_at) }}
                </p>
            </figcaption>
            <div class="flex items-center gap-1">
                <template v-if="!comprobante.es_pdf">
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        aria-label="Alejar"
                        :disabled="zoom <= 0.5"
                        @click="zoom = Math.max(0.5, zoom - 0.25)"
                    >
                        <ZoomOut class="size-4" />
                    </Button>
                    <span class="w-10 text-center text-xs tabular-nums"
                        >{{ Math.round(zoom * 100) }}%</span
                    >
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8"
                        aria-label="Acercar"
                        :disabled="zoom >= 3"
                        @click="zoom = Math.min(3, zoom + 0.25)"
                    >
                        <ZoomIn class="size-4" />
                    </Button>
                </template>
                <Button variant="ghost" size="icon" class="size-8" as-child>
                    <a
                        :href="comprobante.url"
                        target="_blank"
                        rel="noopener"
                        aria-label="Abrir en otra pestaña"
                    >
                        <ExternalLink class="size-4" />
                    </a>
                </Button>
                <Button variant="ghost" size="icon" class="size-8" as-child>
                    <a :href="comprobante.descargar_url" aria-label="Descargar">
                        <Download class="size-4" />
                    </a>
                </Button>
            </div>
        </div>

        <div :class="['relative overflow-auto bg-muted/40', alto]">
            <iframe
                v-if="comprobante.es_pdf"
                :src="comprobante.url"
                class="size-full"
                :title="`Comprobante ${comprobante.nombre_original}`"
            />
            <div
                v-else-if="!errorCarga"
                class="grid min-h-full place-items-center p-3"
            >
                <img
                    :src="comprobante.url"
                    :alt="`Comprobante ${comprobante.nombre_original}`"
                    class="max-w-full origin-top rounded-lg shadow-sm transition-transform"
                    :style="{ transform: `scale(${zoom})` }"
                    @error="errorCarga = true"
                />
            </div>
            <div
                v-else
                class="grid size-full place-items-center p-6 text-center text-sm text-muted-foreground"
            >
                <div>
                    <FileText class="mx-auto mb-2 size-8" />
                    No se pudo mostrar el archivo. Descárgalo para revisarlo.
                </div>
            </div>
        </div>

        <dl
            v-if="
                comprobante.referencia ||
                comprobante.fecha_transferencia ||
                comprobante.monto_declarado !== null
            "
            class="grid grid-cols-3 gap-2 border-t px-3 py-2 text-xs"
        >
            <div>
                <dt class="text-muted-foreground">Referencia</dt>
                <dd class="font-medium break-all">
                    {{ comprobante.referencia ?? '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Fecha declarada</dt>
                <dd class="font-medium">
                    {{ fecha(comprobante.fecha_transferencia) }}
                </dd>
            </div>
            <div>
                <dt class="text-muted-foreground">Estado</dt>
                <dd class="font-medium capitalize">{{ comprobante.estado }}</dd>
            </div>
        </dl>
    </figure>
</template>
