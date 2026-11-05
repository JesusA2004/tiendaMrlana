<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    ImagePlus,
    ShieldCheck,
    Star,
    Trash2,
    Upload,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    destroy as destruirImagen,
    ordenar,
    principal,
    store as subirImagenes,
} from '@/routes/admin/productos/imagenes';
import ConfirmarAccion from './ConfirmarAccion.vue';
import type { ImagenProducto } from './types';

const props = defineProps<{
    productoId: number;
    imagenes: ImagenProducto[];
    imagenHeredada?: string | null;
    maxMb?: number;
    maxArchivos?: number;
}>();

const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];
const maxBytes = computed(() => (props.maxMb ?? 5) * 1024 * 1024);

const lista = ref<ImagenProducto[]>([...props.imagenes]);
watch(
    () => props.imagenes,
    (nuevas) => {
        lista.value = [...nuevas];
    },
);

// ---------------------------------------------------------------- Subida
const input = ref<HTMLInputElement | null>(null);
const avisos = ref<string[]>([]);
const previas = ref<{ archivo: File; url: string }[]>([]);
const arrastrando = ref(false);

const form = useForm({
    imagenes: [] as File[],
    derechos: false,
});

function liberarPrevias(): void {
    previas.value.forEach((p) => URL.revokeObjectURL(p.url));
    previas.value = [];
}

onBeforeUnmount(liberarPrevias);

function agregarArchivos(archivos: FileList | File[] | null): void {
    avisos.value = [];

    for (const archivo of Array.from(archivos ?? [])) {
        if (!TIPOS.includes(archivo.type)) {
            avisos.value.push(`${archivo.name}: sólo JPG, PNG o WebP.`);
            continue;
        }

        if (archivo.size > maxBytes.value) {
            avisos.value.push(
                `${archivo.name}: pesa más de ${props.maxMb ?? 5} MB.`,
            );
            continue;
        }

        if (previas.value.length >= (props.maxArchivos ?? 10)) {
            avisos.value.push(
                `Máximo ${props.maxArchivos ?? 10} imágenes por subida.`,
            );
            break;
        }

        previas.value.push({ archivo, url: URL.createObjectURL(archivo) });
    }

    if (input.value) {
        input.value.value = '';
    }
}

function quitarPrevia(indice: number): void {
    const [quitada] = previas.value.splice(indice, 1);

    if (quitada) {
        URL.revokeObjectURL(quitada.url);
    }
}

function soltar(evento: DragEvent): void {
    arrastrando.value = false;
    agregarArchivos(evento.dataTransfer?.files ?? null);
}

const erroresSubida = computed(() =>
    Object.entries(form.errors)
        .filter(
            ([clave]) => clave.startsWith('imagenes') || clave === 'derechos',
        )
        .map(([, mensaje]) => mensaje),
);

function subir(): void {
    form.imagenes = previas.value.map((p) => p.archivo);
    form.post(subirImagenes(props.productoId).url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            liberarPrevias();
            form.reset();
        },
    });
}

// ---------------------------------------------------------------- Galería
const ocupado = ref(false);
const opciones = {
    preserveScroll: true,
    onStart: () => (ocupado.value = true),
    onFinish: () => (ocupado.value = false),
};

function mover(indice: number, delta: number): void {
    const destino = indice + delta;

    if (destino < 0 || destino >= lista.value.length) {
        return;
    }

    const copia = [...lista.value];
    const [item] = copia.splice(indice, 1);
    copia.splice(destino, 0, item);
    lista.value = copia;
    guardarOrden();
}

function guardarOrden(): void {
    router.put(
        ordenar(props.productoId).url,
        { ids: lista.value.map((i) => i.id) },
        opciones,
    );
}

// Arrastrar y soltar para reordenar (escritorio).
const arrastrado = ref<number | null>(null);

function alSoltarEn(indice: number): void {
    if (arrastrado.value === null || arrastrado.value === indice) {
        arrastrado.value = null;

        return;
    }

    const copia = [...lista.value];
    const [item] = copia.splice(arrastrado.value, 1);
    copia.splice(indice, 0, item);
    lista.value = copia;
    arrastrado.value = null;
    guardarOrden();
}

function hacerPrincipal(imagen: ImagenProducto): void {
    router.patch(
        principal({ producto: props.productoId, imagen: imagen.id }).url,
        {},
        opciones,
    );
}

const porEliminar = ref<ImagenProducto | null>(null);
const confirmarAbierto = ref(false);

function pedirEliminar(imagen: ImagenProducto): void {
    porEliminar.value = imagen;
    confirmarAbierto.value = true;
}

function eliminar(): void {
    if (!porEliminar.value) {
        return;
    }

    router.delete(
        destruirImagen({
            producto: props.productoId,
            imagen: porEliminar.value.id,
        }).url,
        {
            ...opciones,
            onSuccess: () => (confirmarAbierto.value = false),
        },
    );
}
</script>

<template>
    <div class="space-y-5">
        <!-- Galería actual -->
        <div v-if="lista.length" class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <figure
                v-for="(imagen, i) in lista"
                :key="imagen.id"
                class="group relative overflow-hidden rounded-xl border bg-muted/40"
                :class="[
                    imagen.principal
                        ? 'ring-2 ring-brand-green ring-offset-2 ring-offset-background'
                        : '',
                    arrastrado === i ? 'opacity-50' : '',
                ]"
                draggable="true"
                @dragstart="arrastrado = i"
                @dragover.prevent
                @drop.prevent="alSoltarEn(i)"
            >
                <img
                    :src="imagen.url ?? ''"
                    :alt="`Imagen ${i + 1} del producto`"
                    class="aspect-square w-full object-cover"
                    loading="lazy"
                />
                <span
                    v-if="imagen.principal"
                    class="absolute top-2 left-2 inline-flex items-center gap-1 rounded-full bg-brand-green px-2 py-0.5 text-[11px] font-bold text-brand-ink"
                >
                    <Star class="size-3 fill-current" /> Principal
                </span>
                <figcaption
                    class="flex items-center justify-between gap-1 border-t bg-background/95 p-1.5"
                >
                    <div class="flex">
                        <Button
                            type="button"
                            size="icon-sm"
                            variant="ghost"
                            :disabled="i === 0 || ocupado"
                            aria-label="Mover a la izquierda"
                            @click="mover(i, -1)"
                        >
                            <ArrowLeft />
                        </Button>
                        <Button
                            type="button"
                            size="icon-sm"
                            variant="ghost"
                            :disabled="i === lista.length - 1 || ocupado"
                            aria-label="Mover a la derecha"
                            @click="mover(i, 1)"
                        >
                            <ArrowRight />
                        </Button>
                    </div>
                    <div class="flex">
                        <Button
                            v-if="!imagen.principal"
                            type="button"
                            size="icon-sm"
                            variant="ghost"
                            :disabled="ocupado"
                            aria-label="Usar como principal"
                            title="Usar como principal"
                            @click="hacerPrincipal(imagen)"
                        >
                            <Star />
                        </Button>
                        <Button
                            type="button"
                            size="icon-sm"
                            variant="ghost"
                            class="text-destructive hover:text-destructive"
                            :disabled="ocupado"
                            aria-label="Eliminar imagen"
                            @click="pedirEliminar(imagen)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </figcaption>
            </figure>
        </div>

        <div
            v-else-if="imagenHeredada"
            class="flex items-center gap-3 rounded-xl border border-dashed p-3 text-sm text-muted-foreground"
        >
            <img
                :src="imagenHeredada"
                alt="Imagen actual"
                class="size-16 rounded-lg object-cover"
            />
            Imagen heredada del catálogo anterior. Sube imágenes nuevas para
            administrarlas aquí.
        </div>

        <!-- Zona de subida -->
        <div
            class="rounded-xl border-2 border-dashed p-5 text-center transition"
            :class="
                arrastrando
                    ? 'border-brand-green bg-brand-green-soft'
                    : 'border-border bg-surface/60'
            "
            @dragover.prevent="arrastrando = true"
            @dragleave.prevent="arrastrando = false"
            @drop.prevent="soltar"
        >
            <ImagePlus
                class="mx-auto mb-2 size-8 text-brand-deep dark:text-brand-green"
                aria-hidden="true"
            />
            <p class="text-sm font-semibold">
                Arrastra imágenes aquí o
                <button
                    type="button"
                    class="text-brand-green-strong underline underline-offset-2"
                    @click="input?.click()"
                >
                    selecciónalas
                </button>
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                JPG, PNG o WebP · máx. {{ maxMb ?? 5 }} MB c/u · se optimizan a
                WebP automáticamente
            </p>
            <input
                ref="input"
                type="file"
                multiple
                accept="image/jpeg,image/png,image/webp"
                class="sr-only"
                @change="
                    agregarArchivos(($event.target as HTMLInputElement).files)
                "
            />
        </div>

        <ul v-if="avisos.length" class="space-y-1 text-sm text-destructive">
            <li v-for="aviso in avisos" :key="aviso">{{ aviso }}</li>
        </ul>

        <div v-if="previas.length" class="space-y-4 rounded-xl border p-4">
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                <div
                    v-for="(previa, i) in previas"
                    :key="previa.url"
                    class="relative"
                >
                    <img
                        :src="previa.url"
                        :alt="previa.archivo.name"
                        class="aspect-square w-full rounded-lg object-cover"
                    />
                    <button
                        type="button"
                        class="absolute -top-2 -right-2 grid size-6 place-items-center rounded-full bg-foreground text-background shadow"
                        :aria-label="`Quitar ${previa.archivo.name}`"
                        @click="quitarPrevia(i)"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
            </div>

            <label
                class="flex cursor-pointer items-start gap-3 rounded-lg border border-brand-teal/40 bg-brand-teal-soft p-3 text-sm"
            >
                <Checkbox v-model="form.derechos" class="mt-0.5" />
                <span>
                    <span class="flex items-center gap-1 font-semibold">
                        <ShieldCheck class="size-4" /> Confirmo que Mr Lana
                        tiene derechos para usar estas imágenes.
                    </span>
                    <span class="mt-0.5 block text-xs text-muted-foreground">
                        Fotos propias o con licencia. No uses fotografías de
                        catálogos de otras marcas.
                    </span>
                </span>
            </label>

            <ul
                v-if="erroresSubida.length"
                class="space-y-1 text-sm text-destructive"
            >
                <li v-for="error in erroresSubida" :key="error">{{ error }}</li>
            </ul>

            <div class="flex flex-wrap justify-end gap-2">
                <Button type="button" variant="outline" @click="liberarPrevias"
                    >Descartar</Button
                >
                <Button
                    type="button"
                    :disabled="!form.derechos || form.processing"
                    @click="subir"
                >
                    <Upload />
                    Subir {{ previas.length }}
                    {{ previas.length === 1 ? 'imagen' : 'imágenes' }}
                </Button>
            </div>
            <div
                v-if="form.progress"
                class="h-1.5 overflow-hidden rounded-full bg-muted"
            >
                <div
                    class="h-full bg-brand-gradient transition-all"
                    :style="{ width: `${form.progress.percentage ?? 0}%` }"
                />
            </div>
        </div>

        <ConfirmarAccion
            v-model:open="confirmarAbierto"
            titulo="¿Eliminar esta imagen?"
            descripcion="El archivo se borrará del almacenamiento. Esta acción no se puede deshacer."
            confirmar="Eliminar"
            destructiva
            :procesando="ocupado"
            @confirmar="eliminar"
        />
    </div>
</template>
