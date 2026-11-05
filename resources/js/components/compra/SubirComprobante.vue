<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FileText, FileUp, Loader2, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import { Button } from '@/components/ui/button';
import CampoTexto from './CampoTexto.vue';
import { tamanoArchivo } from './formato';
import type { ReglasComprobante } from './types';

/**
 * Formulario para subir el comprobante de transferencia (imagen o PDF, máx. 5 MB).
 * El servidor valida tipo real, tamaño y estado del pago.
 */
const props = defineProps<{
    url: string;
    reglas: ReglasComprobante;
    montoSugerido: number;
    reintento?: boolean;
}>();

const input = ref<HTMLInputElement | null>(null);
const errorLocal = ref<string | null>(null);
const arrastrando = ref(false);
const vistaPrevia = ref<string | null>(null);

function limpiarVista() {
    if (vistaPrevia.value) {
        URL.revokeObjectURL(vistaPrevia.value);
        vistaPrevia.value = null;
    }
}

onBeforeUnmount(limpiarVista);

const form = useForm<{
    archivo: File | null;
    referencia: string;
    fecha_transferencia: string;
    monto_declarado: string;
}>({
    archivo: null,
    referencia: '',
    fecha_transferencia: '',
    monto_declarado: props.montoSugerido ? props.montoSugerido.toFixed(2) : '',
});

const hoy = new Date().toLocaleDateString('en-CA', {
    timeZone: 'America/Mexico_City',
});

const errorArchivo = computed(() => errorLocal.value ?? form.errors.archivo);

function elegir(event: Event) {
    procesar((event.target as HTMLInputElement).files?.[0] ?? null);
}

function soltar(event: DragEvent) {
    arrastrando.value = false;
    procesar(event.dataTransfer?.files?.[0] ?? null);
}

function procesar(archivo: File | null) {
    errorLocal.value = null;
    limpiarVista();

    if (!archivo) {
        form.archivo = null;

        return;
    }

    const extension = archivo.name.split('.').pop()?.toLowerCase() ?? '';

    if (!props.reglas.extensiones.includes(extension)) {
        errorLocal.value =
            'El comprobante debe ser una imagen JPG, PNG o WEBP, o un PDF.';
        form.archivo = null;

        return;
    }

    if (archivo.size > props.reglas.max_mb * 1024 * 1024) {
        errorLocal.value = `El comprobante no puede pesar más de ${props.reglas.max_mb} MB.`;
        form.archivo = null;

        return;
    }

    form.archivo = archivo;

    if (archivo.type.startsWith('image/')) {
        vistaPrevia.value = URL.createObjectURL(archivo);
    }
}

function quitar() {
    form.archivo = null;
    limpiarVista();

    if (input.value) {
        input.value.value = '';
    }
}

function enviar() {
    if (!form.archivo || form.processing) {
        errorLocal.value = form.archivo
            ? null
            : 'Adjunta tu comprobante de pago.';

        return;
    }

    form.post(props.url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            quitar();
        },
    });
}
</script>

<template>
    <form
        class="space-y-4 rounded-3xl border border-border bg-card p-5 shadow-brand-sm sm:p-6"
        novalidate
        @submit.prevent="enviar"
    >
        <div>
            <h2 class="font-display text-lg font-bold text-brand-deep">
                {{
                    reintento
                        ? 'Envía un nuevo comprobante'
                        : '¿Ya pagaste? Sube tu comprobante'
                }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Foto o captura del comprobante (JPG, PNG, WEBP) o PDF, máximo
                {{ reglas.max_mb }} MB. Lo revisaremos y te avisaremos por
                correo.
            </p>
        </div>

        <div>
            <label
                for="archivo-comprobante"
                class="group relative flex cursor-pointer flex-col items-center justify-center gap-2 overflow-hidden rounded-2xl border-2 border-dashed px-4 py-8 text-center transition-all duration-200 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring motion-reduce:transition-none"
                :class="[
                    arrastrando
                        ? 'scale-[1.01] border-brand-green bg-brand-green-soft ring-4 shadow-brand ring-brand-green/20'
                        : errorArchivo
                          ? 'border-destructive/60 bg-destructive/5'
                          : 'border-brand-green/40 bg-surface hover:border-brand-green hover:bg-brand-green-soft/60',
                ]"
                @dragenter.prevent="arrastrando = true"
                @dragover.prevent="arrastrando = true"
                @dragleave.prevent="arrastrando = false"
                @drop.prevent="soltar"
            >
                <span
                    class="flex size-14 items-center justify-center rounded-full bg-card text-brand-green-strong shadow-brand-sm transition-transform duration-300 group-hover:-translate-y-1 motion-reduce:transition-none"
                    :class="arrastrando ? '-translate-y-1 scale-110' : ''"
                >
                    <FileUp class="size-7" aria-hidden="true" />
                </span>
                <span class="text-sm font-semibold text-brand-deep">
                    {{
                        arrastrando
                            ? 'Suelta tu comprobante aquí'
                            : 'Arrastra tu comprobante o elige un archivo'
                    }}
                </span>
                <span class="text-xs text-muted-foreground"
                    >En el celular puedes tomar una foto</span
                >
                <input
                    id="archivo-comprobante"
                    ref="input"
                    type="file"
                    class="sr-only"
                    :accept="reglas.accept"
                    :aria-invalid="!!errorArchivo"
                    aria-describedby="archivo-error"
                    @change="elegir"
                />
            </label>
            <Transition
                enter-active-class="transition duration-300 motion-reduce:transition-none"
                enter-from-class="opacity-0 -translate-y-2 scale-95"
                leave-active-class="transition duration-150 motion-reduce:transition-none"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="form.archivo"
                    class="mt-3 flex items-center gap-3 rounded-2xl border border-brand-green/40 bg-card p-2 pr-3 text-sm shadow-brand-sm"
                >
                    <span
                        class="flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-brand-green-soft text-brand-green-strong"
                    >
                        <img
                            v-if="vistaPrevia"
                            :src="vistaPrevia"
                            alt="Vista previa del comprobante"
                            class="size-full object-cover"
                        />
                        <FileText v-else class="size-6" aria-hidden="true" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-semibold">{{
                            form.archivo.name
                        }}</span>
                        <span class="text-xs text-muted-foreground"
                            >{{ tamanoArchivo(form.archivo.size) }} · listo para
                            enviar</span
                        >
                    </span>
                    <button
                        type="button"
                        class="flex size-8 items-center justify-center rounded-full text-muted-foreground transition hover:rotate-90 hover:bg-destructive/10 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label="Quitar archivo"
                        @click="quitar"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </Transition>
            <p
                v-if="errorArchivo"
                id="archivo-error"
                class="mt-1.5 text-xs font-medium text-destructive"
                role="alert"
            >
                {{ errorArchivo }}
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <CampoTexto
                id="referencia"
                v-model="form.referencia"
                etiqueta="Referencia o clave de rastreo"
                :maxlength="120"
                :error="form.errors.referencia"
            />
            <div class="space-y-1.5">
                <label for="fecha_transferencia" class="text-sm font-medium"
                    >Fecha del pago
                    <span class="font-normal text-muted-foreground"
                        >(opcional)</span
                    ></label
                >
                <input
                    id="fecha_transferencia"
                    v-model="form.fecha_transferencia"
                    type="date"
                    :max="hoy"
                    class="h-11 w-full rounded-md border border-input bg-card px-3 text-sm shadow-xs transition focus-visible:border-brand-green focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    :aria-invalid="!!form.errors.fecha_transferencia"
                />
                <p
                    v-if="form.errors.fecha_transferencia"
                    class="text-xs font-medium text-destructive"
                    role="alert"
                >
                    {{ form.errors.fecha_transferencia }}
                </p>
            </div>
            <CampoTexto
                id="monto_declarado"
                v-model="form.monto_declarado"
                etiqueta="Monto transferido"
                inputmode="decimal"
                :error="form.errors.monto_declarado"
            />
        </div>

        <div
            v-if="form.progress"
            class="h-2 overflow-hidden rounded-full bg-brand-green-soft"
            role="progressbar"
            :aria-valuenow="form.progress.percentage"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full rounded-full bg-brand-gradient transition-all duration-300"
                :style="{ width: `${form.progress.percentage ?? 0}%` }"
            />
        </div>

        <Button
            type="submit"
            size="lg"
            class="h-11 w-full rounded-full font-bold shadow-brand-sm hover:-translate-y-0.5 hover:shadow-brand sm:w-auto"
            :disabled="form.processing"
        >
            <Loader2
                v-if="form.processing"
                class="animate-spin"
                aria-hidden="true"
            />
            {{ form.processing ? 'Enviando…' : 'Enviar comprobante' }}
        </Button>
    </form>
</template>
