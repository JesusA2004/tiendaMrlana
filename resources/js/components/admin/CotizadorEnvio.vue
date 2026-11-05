<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import {
    LoaderCircle,
    Plus,
    Send,
    Sparkles,
    Trash2,
    Truck,
} from 'lucide-vue-next';
import { computed } from 'vue';
import CampoForm from '@/components/admin/CampoForm.vue';
import { dinero } from '@/components/admin/formato';
import type { OpcionEnvio, PaqueteriaOpcion } from '@/components/admin/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index as paqueteriasIndex } from '@/routes/admin/paqueterias';
import { opcionesEnvio } from '@/routes/admin/pedidos';

/**
 * Formulario dinámico de opciones de envío (una fila por paquetería/servicio).
 * modo: nuevo (por cotizar) · reemplazar (el cliente aún no elige) · recotizar (ya eligió; motivo obligatorio).
 */
const props = withDefaults(
    defineProps<{
        pedidoId: number;
        paqueterias: PaqueteriaOpcion[];
        modo?: 'nuevo' | 'reemplazar' | 'recotizar';
        previas?: OpcionEnvio[];
        puedeGestionarPaqueterias?: boolean;
        compacto?: boolean;
    }>(),
    {
        modo: 'nuevo',
        previas: () => [],
        puedeGestionarPaqueterias: false,
        compacto: false,
    },
);

const emit = defineEmits<{ enviado: []; cancelar: [] }>();

type Fila = {
    clave: number;
    paqueteria_id: number | string;
    servicio: string;
    costo: number | string;
    dias_min: number | string;
    dias_max: number | string;
    tiempo_texto: string;
    notas: string;
    recomendada: boolean;
};

let siguiente = 1;

function filaVacia(parcial: Partial<Fila> = {}): Fila {
    return {
        clave: siguiente++,
        paqueteria_id:
            props.paqueterias.length === 1 ? props.paqueterias[0].id : '',
        servicio: '',
        costo: '',
        dias_min: '',
        dias_max: '',
        tiempo_texto: '',
        notas: '',
        recomendada: false,
        ...parcial,
    };
}

const activas = computed(() => new Set(props.paqueterias.map((p) => p.id)));

// Al reemplazar se parte de las opciones enviadas (sólo con paqueterías aún activas).
const iniciales = props.previas
    .filter((o) => o.paqueteria_id && activas.value.has(o.paqueteria_id))
    .map((o) =>
        filaVacia({
            paqueteria_id: o.paqueteria_id ?? '',
            servicio: o.servicio,
            costo: o.costo,
            dias_min: o.dias_min ?? '',
            dias_max: o.dias_max ?? '',
            tiempo_texto: o.tiempo_texto ?? '',
            notas: o.notas ?? '',
            recomendada: o.recomendada,
        }),
    );

const form = useForm({
    opciones: iniciales.length ? iniciales : [filaVacia()],
    motivo: '',
});

const MAX = 8;

function agregar() {
    if (form.opciones.length < MAX) {
        form.opciones.push(filaVacia());
    }
}

function quitar(i: number) {
    form.opciones.splice(i, 1);
}

function recomendar(i: number) {
    form.opciones.forEach((f, j) => {
        f.recomendada = j === i ? !f.recomendada : false;
    });
}

function error(i: number, campo: string): string | undefined {
    return (form.errors as Record<string, string>)[`opciones.${i}.${campo}`];
}

const costos = computed(() =>
    form.opciones
        .map((f) => Number(f.costo))
        .filter((c) => !Number.isNaN(c) && String(c) !== ''),
);
const rango = computed(() =>
    costos.value.length
        ? { min: Math.min(...costos.value), max: Math.max(...costos.value) }
        : null,
);

const motivoRequerido = computed(() => props.modo === 'recotizar');
const listo = computed(
    () =>
        form.opciones.length > 0 &&
        form.opciones.every(
            (f) =>
                f.paqueteria_id !== '' &&
                f.servicio.trim() !== '' &&
                f.costo !== '' &&
                Number(f.costo) >= 0,
        ) &&
        (!motivoRequerido.value || form.motivo.trim().length >= 5),
);

const SERVICIOS = ['Estándar', 'Express', 'Económico', 'Día siguiente'];

function enviar() {
    form.transform((d) => ({
        motivo: d.motivo || null,
        opciones: d.opciones.map((f) => ({
            paqueteria_id:
                f.paqueteria_id === '' ? null : Number(f.paqueteria_id),
            servicio: f.servicio,
            costo: f.costo === '' ? null : Number(f.costo),
            dias_min: f.dias_min === '' ? null : Number(f.dias_min),
            dias_max: f.dias_max === '' ? null : Number(f.dias_max),
            tiempo_texto: f.tiempo_texto || null,
            notas: f.notas || null,
            recomendada: f.recomendada,
        })),
    })).post(opcionesEnvio(props.pedidoId).url, {
        preserveScroll: true,
        onSuccess: () => emit('enviado'),
    });
}
</script>

<template>
    <!-- Sin catálogo de paqueterías -->
    <div
        v-if="paqueterias.length === 0"
        class="flex flex-col items-start gap-3 rounded-2xl border border-dashed border-warning/50 bg-warning/5 p-4 text-sm"
    >
        <p class="flex items-center gap-2 font-semibold">
            <Truck class="size-4 text-warning" /> Aún no hay paqueterías activas
        </p>
        <p class="text-muted-foreground">
            Para cotizar con opciones primero da de alta las paqueterías con las
            que trabajas (nombre y, si quieres, su enlace de rastreo).
        </p>
        <Button v-if="puedeGestionarPaqueterias" size="sm" as-child>
            <Link :href="paqueteriasIndex().url"
                ><Plus class="size-4" /> Dar de alta paqueterías</Link
            >
        </Button>
        <p v-else class="text-xs text-muted-foreground">
            Pide a un administrador que las agregue en Administración →
            Paqueterías.
        </p>
    </div>

    <form v-else class="grid gap-4" @submit.prevent="enviar">
        <ol class="grid gap-3">
            <li
                v-for="(f, i) in form.opciones"
                :key="f.clave"
                :class="[
                    'relative rounded-2xl border p-3 transition sm:p-4',
                    f.recomendada
                        ? 'border-brand-green bg-brand-green-soft/40 shadow-sm'
                        : 'bg-background',
                ]"
            >
                <div class="mb-3 flex items-center gap-2">
                    <span
                        class="grid size-7 shrink-0 place-items-center rounded-full bg-brand-deep text-xs font-bold text-white"
                        aria-hidden="true"
                        >{{ i + 1 }}</span
                    >
                    <p class="text-sm font-semibold">Opción {{ i + 1 }}</p>
                    <span
                        v-if="
                            rango &&
                            rango.min < rango.max &&
                            Number(f.costo) === rango.min &&
                            f.costo !== ''
                        "
                        class="rounded-full bg-success/12 px-2 py-0.5 text-[0.68rem] font-semibold text-success"
                        >Más económica</span
                    >
                    <div class="ml-auto flex items-center gap-1">
                        <Button
                            type="button"
                            size="sm"
                            :variant="f.recomendada ? 'default' : 'ghost'"
                            class="h-8"
                            :aria-pressed="f.recomendada"
                            @click="recomendar(i)"
                        >
                            <Sparkles class="size-3.5" />
                            {{ f.recomendada ? 'Recomendada' : 'Recomendar' }}
                        </Button>
                        <Button
                            v-if="form.opciones.length > 1"
                            type="button"
                            size="icon"
                            variant="ghost"
                            class="size-8 text-muted-foreground hover:text-destructive"
                            :aria-label="`Quitar opción ${i + 1}`"
                            @click="quitar(i)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                </div>

                <div
                    :class="[
                        'grid gap-3',
                        compacto
                            ? 'sm:grid-cols-2'
                            : 'sm:grid-cols-2 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_8rem]',
                    ]"
                >
                    <CampoForm
                        label="Paquetería"
                        :for="`op-paq-${f.clave}`"
                        :error="error(i, 'paqueteria_id')"
                        requerido
                    >
                        <NativeSelect
                            :id="`op-paq-${f.clave}`"
                            v-model="f.paqueteria_id"
                            class="w-full"
                            required
                        >
                            <option value="" disabled>Elige…</option>
                            <option
                                v-for="p in paqueterias"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.nombre }}
                            </option>
                        </NativeSelect>
                    </CampoForm>
                    <CampoForm
                        label="Servicio"
                        :for="`op-serv-${f.clave}`"
                        :error="error(i, 'servicio')"
                        requerido
                    >
                        <Input
                            :id="`op-serv-${f.clave}`"
                            v-model="f.servicio"
                            :list="`servicios-${f.clave}`"
                            maxlength="120"
                            placeholder="Estándar, Express…"
                            autocomplete="off"
                            required
                        />
                        <datalist :id="`servicios-${f.clave}`">
                            <option
                                v-for="s in SERVICIOS"
                                :key="s"
                                :value="s"
                            />
                        </datalist>
                    </CampoForm>
                    <CampoForm
                        label="Costo (MXN)"
                        :for="`op-costo-${f.clave}`"
                        :error="error(i, 'costo')"
                        requerido
                    >
                        <Input
                            :id="`op-costo-${f.clave}`"
                            v-model="f.costo"
                            type="number"
                            min="0"
                            step="0.01"
                            inputmode="decimal"
                            required
                        />
                    </CampoForm>
                </div>

                <div
                    class="mt-3 grid gap-3 sm:grid-cols-[repeat(2,minmax(0,6.5rem))_minmax(0,1fr)]"
                >
                    <CampoForm
                        label="Días mín."
                        :for="`op-min-${f.clave}`"
                        :error="error(i, 'dias_min')"
                    >
                        <Input
                            :id="`op-min-${f.clave}`"
                            v-model="f.dias_min"
                            type="number"
                            min="0"
                            max="365"
                            inputmode="numeric"
                        />
                    </CampoForm>
                    <CampoForm
                        label="Días máx."
                        :for="`op-max-${f.clave}`"
                        :error="error(i, 'dias_max')"
                    >
                        <Input
                            :id="`op-max-${f.clave}`"
                            v-model="f.dias_max"
                            type="number"
                            min="0"
                            max="365"
                            inputmode="numeric"
                        />
                    </CampoForm>
                    <CampoForm
                        label="o tiempo en texto"
                        :for="`op-tiempo-${f.clave}`"
                        :error="error(i, 'tiempo_texto')"
                        ayuda="Si lo llenas, se muestra en lugar de los días."
                    >
                        <Input
                            :id="`op-tiempo-${f.clave}`"
                            v-model="f.tiempo_texto"
                            maxlength="120"
                            placeholder="p. ej. Mismo día en zona metropolitana"
                        />
                    </CampoForm>
                </div>

                <CampoForm
                    class="mt-3"
                    label="Nota para el cliente (opcional)"
                    :for="`op-notas-${f.clave}`"
                    :error="error(i, 'notas')"
                >
                    <Input
                        :id="`op-notas-${f.clave}`"
                        v-model="f.notas"
                        maxlength="500"
                        placeholder="p. ej. Entrega en ocurre, incluye seguro…"
                    />
                </CampoForm>
            </li>
        </ol>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="form.opciones.length >= MAX"
                @click="agregar"
            >
                <Plus class="size-4" /> Agregar opción
            </Button>
            <p class="text-xs text-muted-foreground">
                {{ form.opciones.length }}/{{ MAX }} ·
                <template v-if="rango">
                    de {{ dinero(rango.min) }} a {{ dinero(rango.max) }}
                </template>
                <template v-else>captura los costos</template>
            </p>
        </div>

        <CampoForm
            v-if="modo !== 'nuevo'"
            :label="
                modo === 'recotizar'
                    ? 'Motivo de la nueva cotización'
                    : 'Motivo del cambio (opcional)'
            "
            for="op-motivo"
            :error="form.errors.motivo"
            :ayuda="
                modo === 'recotizar'
                    ? 'El cliente ya había elegido; verá este motivo y tendrá que elegir de nuevo. Mínimo 5 caracteres.'
                    : 'El cliente verá este motivo junto con las nuevas opciones.'
            "
            :requerido="modo === 'recotizar'"
        >
            <Textarea
                id="op-motivo"
                v-model="form.motivo"
                rows="2"
                maxlength="300"
            />
        </CampoForm>

        <div
            class="flex flex-col-reverse gap-2 border-t pt-3 sm:flex-row sm:items-center sm:justify-end"
        >
            <Button
                type="button"
                variant="ghost"
                :disabled="form.processing"
                @click="emit('cancelar')"
                >Cancelar</Button
            >
            <Button type="submit" :disabled="!listo || form.processing">
                <LoaderCircle
                    v-if="form.processing"
                    class="size-4 animate-spin"
                />
                <Send v-else class="size-4" />
                {{
                    modo === 'nuevo'
                        ? 'Enviar opciones al cliente'
                        : 'Reemplazar y avisar al cliente'
                }}
            </Button>
        </div>
    </form>
</template>
