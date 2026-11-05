<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Banknote,
    CircleAlert,
    MapPin,
    Pencil,
    Plus,
    Trash2,
    Truck,
    Waypoints,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CampoForm from '@/components/admin/CampoForm.vue';
import ConfirmarAccion from '@/components/admin/ConfirmarAccion.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import { dinero } from '@/components/admin/formato';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Etiqueta, Opcion } from '@/components/admin/types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    destroy as reglaDestroy,
    store as reglaStore,
    update as reglaUpdate,
} from '@/routes/admin/reglas-envio';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Configuración', href: '/admin/configuraciones' },
            { title: 'Reglas de envío', href: '/admin/reglas-envio' },
        ],
    },
});

type Regla = {
    id: number;
    nombre: string;
    tipo: Etiqueta;
    estados: string[];
    cp_prefijos: string[];
    monto: number | null;
    gratis_desde: number | null;
    dias_estimados: string | null;
    permite_contra_entrega: boolean;
    activo: boolean;
    prioridad: number;
    notas: string | null;
    pedidos_count: number;
};

const props = defineProps<{
    reglas: Regla[];
    tipos: Opcion[];
    estadosMexico: string[];
}>();

const activas = computed(() => props.reglas.filter((r) => r.activo));
const hayDomicilio = computed(() =>
    activas.value.some((r) => r.tipo.value !== 'recoger_en_tienda'),
);

// ---------------------------------------------------------------- formulario
const abierto = ref(false);
const editando = ref<Regla | null>(null);
const cpTexto = ref('');

const form = useForm({
    nombre: '',
    tipo: 'tarifa_fija',
    estados: [] as string[],
    cp_prefijos: [] as string[],
    monto: '' as number | string,
    gratis_desde: '' as number | string,
    dias_estimados: '',
    permite_contra_entrega: false,
    prioridad: 0 as number | string,
    activo: true,
    notas: '',
});

const esTarifa = computed(() => form.tipo === 'tarifa_fija');
const esRecoger = computed(() => form.tipo === 'recoger_en_tienda');

const erroresCp = computed(
    () =>
        Object.entries(form.errors)
            .filter(([k]) => k.startsWith('cp_prefijos'))
            .map(([, v]) => v)[0],
);
const erroresEstados = computed(
    () =>
        Object.entries(form.errors)
            .filter(([k]) => k.startsWith('estados'))
            .map(([, v]) => v)[0],
);

function nueva() {
    editando.value = null;
    form.reset();
    form.clearErrors();
    cpTexto.value = '';
    abierto.value = true;
}

function editar(r: Regla) {
    editando.value = r;
    form.clearErrors();
    form.nombre = r.nombre;
    form.tipo = r.tipo.value;
    form.estados = [...r.estados];
    form.cp_prefijos = [...r.cp_prefijos];
    form.monto = r.monto ?? '';
    form.gratis_desde = r.gratis_desde ?? '';
    form.dias_estimados = r.dias_estimados ?? '';
    form.permite_contra_entrega = r.permite_contra_entrega;
    form.prioridad = r.prioridad;
    form.activo = r.activo;
    form.notas = r.notas ?? '';
    cpTexto.value = r.cp_prefijos.join(', ');
    abierto.value = true;
}

function alternarEstado(e: string) {
    const i = form.estados.indexOf(e);

    if (i >= 0) {
        form.estados.splice(i, 1);
    } else {
        form.estados.push(e);
    }
}

function enviar() {
    const datos = form.transform((d) => ({
        ...d,
        cp_prefijos: cpTexto.value
            .split(/[\s,;]+/)
            .map((s) => s.trim())
            .filter((s) => s !== ''),
        monto: d.monto === '' ? null : Number(d.monto),
        gratis_desde: d.gratis_desde === '' ? null : Number(d.gratis_desde),
        prioridad: d.prioridad === '' ? 0 : Number(d.prioridad),
        dias_estimados: d.dias_estimados || null,
        notas: d.notas || null,
    }));

    const opciones = {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
        },
    };

    if (editando.value) {
        datos.put(reglaUpdate(editando.value.id).url, opciones);
    } else {
        datos.post(reglaStore().url, opciones);
    }
}

function eliminar(r: Regla) {
    router.delete(reglaDestroy(r.id).url, { preserveScroll: true });
}

function cobertura(r: Regla): string {
    if (r.tipo.value === 'recoger_en_tienda') {
        return 'En tienda';
    }

    const partes: string[] = [];
    partes.push(
        r.estados.length === 0
            ? 'Todos los estados'
            : r.estados.length <= 3
              ? r.estados.join(', ')
              : `${r.estados.slice(0, 3).join(', ')} y ${r.estados.length - 3} más`,
    );

    if (r.cp_prefijos.length > 0) {
        partes.push(`CP ${r.cp_prefijos.join(', ')}`);
    }

    return partes.join(' · ');
}

function costo(r: Regla): string {
    switch (r.tipo.value) {
        case 'tarifa_fija':
            return r.monto === 0 ? 'Sin costo' : dinero(r.monto);
        case 'recoger_en_tienda':
            return 'Sin costo';
        default:
            return 'Se cotiza por pedido';
    }
}
</script>

<template>
    <Head title="Reglas de envío" />

    <div class="flex w-full flex-col gap-6 p-4 md:p-6">
        <PageHeader
            eyebrow="Administración"
            title="Reglas de envío"
            description="Define cuánto cuesta enviar según el estado o código postal del cliente. Se aplica la regla activa de mayor prioridad que coincida."
        >
            <Button @click="nueva">
                <Plus class="size-4" /> Nueva regla
            </Button>
        </PageHeader>

        <Alert v-if="!hayDomicilio" class="border-warning/40 bg-warning/5">
            <CircleAlert class="size-4 text-warning" />
            <AlertTitle>Sin reglas de envío a domicilio activas</AlertTitle>
            <AlertDescription>
                Mientras no haya reglas activas, todo pedido con envío a
                domicilio queda «por cotizar»: el equipo captura el costo y
                después el cliente paga.
            </AlertDescription>
        </Alert>
        <Alert v-else class="border-info/30 bg-info/5">
            <Waypoints class="size-4 text-info" />
            <AlertDescription>
                Si la dirección del cliente no coincide con ninguna regla
                activa, el pedido queda «por cotizar». Nunca se anuncia envío
                gratis sin un monto «gratis desde».
            </AlertDescription>
        </Alert>

        <EmptyState
            v-if="reglas.length === 0"
            title="Aún no hay reglas de envío"
            description="Crea una tarifa fija por zona, una regla de cotización manual o la opción de recoger en tienda."
            :icon="Truck"
        >
            <Button @click="nueva"
                ><Plus class="size-4" /> Crear la primera regla</Button
            >
        </EmptyState>

        <div
            v-else
            class="grid stagger-children gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
        >
            <article
                v-for="r in reglas"
                :key="r.id"
                :class="[
                    'flex hover-lift flex-col gap-4 rounded-2xl border bg-card p-4',
                    !r.activo && 'opacity-70',
                ]"
            >
                <header class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="truncate font-display text-lg font-semibold">
                            {{ r.nombre }}
                        </h2>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <StatusBadge :etiqueta="r.tipo" />
                            <StatusBadge
                                :label="r.activo ? 'Activa' : 'Inactiva'"
                                :color="r.activo ? 'success' : 'neutral'"
                            />
                            <StatusBadge
                                v-if="r.permite_contra_entrega"
                                label="Contra entrega"
                                color="brand"
                            />
                        </div>
                    </div>
                    <span
                        class="shrink-0 rounded-lg bg-surface px-2 py-1 text-xs font-semibold text-muted-foreground tabular-nums"
                        title="Prioridad"
                        >P{{ r.prioridad }}</span
                    >
                </header>

                <dl class="grid gap-2 text-sm">
                    <div class="flex items-start gap-2">
                        <Banknote
                            class="mt-0.5 size-4 shrink-0 text-brand-green-strong"
                        />
                        <div>
                            <dt class="sr-only">Costo</dt>
                            <dd class="font-semibold">{{ costo(r) }}</dd>
                            <dd
                                v-if="r.gratis_desde"
                                class="text-xs text-muted-foreground"
                            >
                                Gratis desde {{ dinero(r.gratis_desde) }}
                            </dd>
                        </div>
                    </div>
                    <div class="flex items-start gap-2">
                        <MapPin
                            class="mt-0.5 size-4 shrink-0 text-brand-teal-strong"
                        />
                        <div>
                            <dt class="sr-only">Cobertura</dt>
                            <dd>{{ cobertura(r) }}</dd>
                        </div>
                    </div>
                    <div v-if="r.dias_estimados" class="flex items-start gap-2">
                        <Truck
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <dd>{{ r.dias_estimados }}</dd>
                    </div>
                    <p
                        v-if="r.notas"
                        class="text-xs whitespace-pre-line text-muted-foreground"
                    >
                        {{ r.notas }}
                    </p>
                </dl>

                <footer
                    class="mt-auto flex items-center justify-between gap-2 border-t pt-3"
                >
                    <span class="text-xs text-muted-foreground">
                        {{ r.pedidos_count }} pedidos
                    </span>
                    <div class="flex gap-1">
                        <Button variant="ghost" size="sm" @click="editar(r)">
                            <Pencil class="size-4" /> Editar
                        </Button>
                        <ConfirmarAccion
                            :title="
                                r.pedidos_count > 0
                                    ? 'Desactivar regla'
                                    : 'Eliminar regla'
                            "
                            :description="
                                r.pedidos_count > 0
                                    ? 'Esta regla ya se aplicó en pedidos; se desactivará para conservar el historial.'
                                    : `Se eliminará «${r.nombre}». Esta acción no se puede deshacer.`
                            "
                            :confirm-label="
                                r.pedidos_count > 0 ? 'Desactivar' : 'Eliminar'
                            "
                            @confirmar="eliminar(r)"
                        >
                            <template #trigger>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-destructive"
                                    :aria-label="`Eliminar ${r.nombre}`"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </template>
                        </ConfirmarAccion>
                    </div>
                </footer>
            </article>
        </div>
    </div>

    <Dialog v-model:open="abierto">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-2xl">
            <form class="grid gap-5" @submit.prevent="enviar">
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">
                        {{
                            editando
                                ? 'Editar regla de envío'
                                : 'Nueva regla de envío'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Los montos se suman al pedido; el cliente nunca puede
                        modificarlos.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <CampoForm
                        label="Nombre"
                        for="re-nombre"
                        requerido
                        :error="form.errors.nombre"
                    >
                        <Input
                            id="re-nombre"
                            v-model="form.nombre"
                            maxlength="120"
                            placeholder="P. ej. Morelos y CDMX"
                        />
                    </CampoForm>
                    <CampoForm
                        label="Tipo"
                        for="re-tipo"
                        requerido
                        :error="form.errors.tipo"
                    >
                        <NativeSelect
                            id="re-tipo"
                            v-model="form.tipo"
                            class="w-full"
                        >
                            <option
                                v-for="t in tipos"
                                :key="t.value"
                                :value="t.value"
                            >
                                {{ t.label }}
                            </option>
                        </NativeSelect>
                    </CampoForm>

                    <template v-if="esTarifa">
                        <CampoForm
                            label="Costo de envío"
                            for="re-monto"
                            requerido
                            :error="form.errors.monto"
                            ayuda="Puede ser 0."
                        >
                            <Input
                                id="re-monto"
                                v-model="form.monto"
                                type="number"
                                min="0"
                                step="0.01"
                            />
                        </CampoForm>
                        <CampoForm
                            label="Gratis desde (opcional)"
                            for="re-gratis"
                            :error="form.errors.gratis_desde"
                            ayuda="Sólo si el negocio lo define: subtotal a partir del cual el envío es gratis."
                        >
                            <Input
                                id="re-gratis"
                                v-model="form.gratis_desde"
                                type="number"
                                min="1"
                                step="0.01"
                            />
                        </CampoForm>
                    </template>
                    <p
                        v-else
                        class="rounded-xl bg-surface p-3 text-sm text-muted-foreground sm:col-span-2"
                    >
                        {{
                            esRecoger
                                ? 'Recoger en tienda no tiene costo. La dirección y el horario se configuran en Configuración › Recoger en tienda.'
                                : 'Cotización manual: el pedido queda «por cotizar» y el equipo captura el costo antes de que el cliente pague.'
                        }}
                    </p>

                    <CampoForm
                        label="Tiempo estimado"
                        for="re-dias"
                        :error="form.errors.dias_estimados"
                    >
                        <Input
                            id="re-dias"
                            v-model="form.dias_estimados"
                            maxlength="60"
                            placeholder="3 a 5 días hábiles"
                        />
                    </CampoForm>
                    <CampoForm
                        label="Prioridad"
                        for="re-prio"
                        requerido
                        :error="form.errors.prioridad"
                        ayuda="Mayor número = se evalúa primero."
                    >
                        <Input
                            id="re-prio"
                            v-model="form.prioridad"
                            type="number"
                            min="0"
                            max="1000"
                        />
                    </CampoForm>
                </div>

                <template v-if="!esRecoger">
                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label>Estados que cubre</Label>
                            <button
                                v-if="form.estados.length > 0"
                                type="button"
                                class="text-xs font-medium text-brand-green-strong hover:underline"
                                @click="form.estados = []"
                            >
                                Quitar selección
                            </button>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{
                                form.estados.length === 0
                                    ? 'Sin selección: aplica a todos los estados.'
                                    : `${form.estados.length} estados seleccionados`
                            }}
                        </p>
                        <div
                            class="flex max-h-44 flex-wrap gap-1.5 overflow-y-auto rounded-xl border p-2"
                        >
                            <button
                                v-for="e in estadosMexico"
                                :key="e"
                                type="button"
                                :aria-pressed="form.estados.includes(e)"
                                :class="[
                                    'rounded-full border px-2.5 py-1 text-xs transition',
                                    form.estados.includes(e)
                                        ? 'border-brand-green bg-brand-green-soft font-semibold text-brand-green-strong'
                                        : 'hover:bg-accent',
                                ]"
                                @click="alternarEstado(e)"
                            >
                                {{ e }}
                            </button>
                        </div>
                        <p
                            v-if="erroresEstados"
                            class="text-xs text-destructive"
                        >
                            {{ erroresEstados }}
                        </p>
                    </div>

                    <CampoForm
                        label="Prefijos de código postal (opcional)"
                        for="re-cp"
                        :error="erroresCp"
                        ayuda="Separados por coma o espacio, de 1 a 5 dígitos (p. ej. 62, 621). Vacío = todos."
                    >
                        <Input
                            id="re-cp"
                            v-model="cpTexto"
                            inputmode="numeric"
                            placeholder="62, 621, 01000"
                        />
                    </CampoForm>

                    <label
                        class="flex items-center justify-between gap-3 rounded-xl border p-3"
                    >
                        <span>
                            <span class="block text-sm font-medium"
                                >Permite pago contra entrega</span
                            >
                            <span class="block text-xs text-muted-foreground"
                                >Además debe estar activo el método «Efectivo
                                contra entrega».</span
                            >
                        </span>
                        <Switch
                            :model-value="form.permite_contra_entrega"
                            @update:model-value="
                                (v: boolean) =>
                                    (form.permite_contra_entrega = v)
                            "
                        />
                    </label>
                </template>

                <label
                    class="flex items-center justify-between gap-3 rounded-xl border p-3"
                >
                    <span class="text-sm font-medium">Regla activa</span>
                    <Switch
                        :model-value="form.activo"
                        @update:model-value="(v: boolean) => (form.activo = v)"
                    />
                </label>

                <CampoForm
                    label="Notas internas"
                    for="re-notas"
                    :error="form.errors.notas"
                >
                    <Textarea
                        id="re-notas"
                        v-model="form.notas"
                        rows="2"
                        maxlength="1000"
                    />
                </CampoForm>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="abierto = false"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="form.processing">
                        {{ editando ? 'Guardar cambios' : 'Crear regla' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
