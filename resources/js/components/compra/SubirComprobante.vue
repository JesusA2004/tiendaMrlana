<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FileUp, Loader2, Paperclip, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
    const archivo = (event.target as HTMLInputElement).files?.[0] ?? null;

    errorLocal.value = null;

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
}

function quitar() {
    form.archivo = null;

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
        class="space-y-4 rounded-3xl border border-border bg-background p-5 shadow-xs sm:p-6"
        novalidate
        @submit.prevent="enviar"
    >
        <div>
            <h2 class="font-display text-lg font-bold">
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
                class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed px-4 py-6 text-center transition hover:border-brand-green hover:bg-brand-green-soft/40 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                :class="
                    errorArchivo ? 'border-destructive/60' : 'border-border'
                "
            >
                <FileUp
                    class="size-7 text-brand-green-strong"
                    aria-hidden="true"
                />
                <span class="text-sm font-semibold"
                    >Elegir archivo<span
                        class="font-normal text-muted-foreground"
                    >
                        o tomar foto</span
                    ></span
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
            <div
                v-if="form.archivo"
                class="mt-2 flex items-center gap-2 rounded-xl bg-surface px-3 py-2 text-sm"
            >
                <Paperclip class="size-4 shrink-0" aria-hidden="true" />
                <span class="min-w-0 flex-1 truncate">{{
                    form.archivo.name
                }}</span>
                <span class="text-xs text-muted-foreground">{{
                    tamanoArchivo(form.archivo.size)
                }}</span>
                <button
                    type="button"
                    class="flex size-7 items-center justify-center rounded-full hover:bg-muted"
                    aria-label="Quitar archivo"
                    @click="quitar"
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>
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
                    class="h-11 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
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
            class="h-2 overflow-hidden rounded-full bg-muted"
            role="progressbar"
            :aria-valuenow="form.progress.percentage"
            aria-valuemin="0"
            aria-valuemax="100"
        >
            <div
                class="h-full bg-brand-gradient transition-all"
                :style="{ width: `${form.progress.percentage ?? 0}%` }"
            />
        </div>

        <Button
            type="submit"
            size="lg"
            class="h-11 w-full rounded-full font-bold sm:w-auto"
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
