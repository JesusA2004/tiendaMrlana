<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Banknote,
    Check,
    ExternalLink,
    History,
    Lock,
    ScanSearch,
    Search,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import AccionDialog from '@/components/admin/AccionDialog.vue';
import CampoForm from '@/components/admin/CampoForm.vue';
import ComprobanteVisor from '@/components/admin/ComprobanteVisor.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import {
    dinero,
    fecha,
    fechaHora,
    haceCuanto,
} from '@/components/admin/formato';
import PageHeader from '@/components/admin/PageHeader.vue';
import Paginacion from '@/components/admin/Paginacion.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type {
    Etiqueta,
    Opcion,
    PagoDatos,
    Paginado,
} from '@/components/admin/types';
import { useFiltros } from '@/components/admin/useFiltros';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import { index as pagosIndex } from '@/routes/admin/pagos';
import { show as pedidoShow } from '@/routes/admin/pedidos';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pagos', href: pagosIndex().url }],
    },
});

type PagoFila = PagoDatos & {
    pedido: {
        id: number;
        folio: string;
        nombre_cliente: string | null;
        correo_cliente: string | null;
        telefono_cliente: string | null;
        total: number;
        envio: number;
        saldo_pendiente: number;
        estatus: Etiqueta | null;
        metodo_pago: string | null;
        envio_por_cotizar: boolean;
        vence_en: string | null;
    } | null;
};

const props = defineProps<{
    tab: 'revision' | 'efectivo' | 'historial';
    pagos: Paginado<PagoFila>;
    seleccionado: number | null;
    conteos: { revision: number; efectivo: number };
    filtros: { buscar: string; estatus: string; tipo: string };
    opciones: { estatus: Opcion[]; tipo: Opcion[] };
}>();

const TABS = [
    {
        value: 'revision',
        label: 'Transferencias en revisión',
        icono: ScanSearch,
    },
    { value: 'efectivo', label: 'Efectivo por confirmar', icono: Banknote },
    { value: 'historial', label: 'Historial', icono: History },
] as const;

const { filtros } = useFiltros(
    pagosIndex().url,
    { ...props.filtros },
    {
        textos: ['buscar'],
        extra: () => ({ tab: props.tab }),
    },
);

function irTab(tab: string) {
    router.get(pagosIndex().url, { tab }, { preserveScroll: true });
}

// ---------------------------------------------------------------- selección (revisión)
const seleccionId = ref<number | null>(
    props.seleccionado ?? props.pagos.data[0]?.id ?? null,
);

watch(
    () => props.pagos.data,
    (data) => {
        if (!data.some((p) => p.id === seleccionId.value)) {
            seleccionId.value = data[0]?.id ?? null;
        }
    },
);

const seleccionado = computed(
    () => props.pagos.data.find((p) => p.id === seleccionId.value) ?? null,
);
const comprobante = computed(() => seleccionado.value?.comprobantes[0] ?? null);
const diferencia = computed(() => {
    const s = seleccionado.value;
    const c = comprobante.value;

    if (!s?.pedido || !c || c.monto_declarado === null) {
        return null;
    }

    return Math.round((c.monto_declarado - s.pedido.total) * 100) / 100;
});

// ---------------------------------------------------------------- acciones
type Accion = 'aprobar' | 'rechazar' | 'efectivo';
const accion = ref<Accion | null>(null);
const objetivo = ref<PagoFila | null>(null);
const abierto = ref(false);
const form = useForm({
    notas: '',
    motivo: '',
    monto_recibido: '' as string | number,
});

function abrir(tipo: Accion, pago: PagoFila) {
    form.reset();
    form.clearErrors();
    accion.value = tipo;
    objetivo.value = pago;

    if (tipo === 'efectivo') {
        form.monto_recibido = pago.pedido?.saldo_pendiente || pago.monto || '';
    }

    abierto.value = true;
}

function ejecutar() {
    if (!objetivo.value || !accion.value) {
        return;
    }

    const id = objetivo.value.id;
    const destino = {
        aprobar: { url: `/admin/pagos/${id}/aprobar`, campos: ['notas'] },
        rechazar: { url: `/admin/pagos/${id}/rechazar`, campos: ['motivo'] },
        efectivo: {
            url: `/admin/pagos/${id}/confirmar-efectivo`,
            campos: ['monto_recibido', 'notas'],
        },
    }[accion.value];

    form.transform((d) =>
        Object.fromEntries(
            destino.campos.map((c) => [c, (d as Record<string, unknown>)[c]]),
        ),
    ).post(destino.url, {
        preserveScroll: true,
        onSuccess: () => (abierto.value = false),
    });
}

const titulos: Record<
    Accion,
    { titulo: string; descripcion: string; boton: string }
> = {
    aprobar: {
        titulo: 'Aprobar transferencia',
        descripcion:
            'Verifica en tu banca que el depósito llegó. El pedido pasará a confirmado y podrá prepararse.',
        boton: 'Aprobar pago',
    },
    rechazar: {
        titulo: 'Rechazar comprobante',
        descripcion:
            'El cliente verá el motivo y podrá subir un comprobante nuevo.',
        boton: 'Rechazar',
    },
    efectivo: {
        titulo: 'Confirmar efectivo recibido',
        descripcion:
            'Registra el monto que recibiste. Debe cubrir el saldo pendiente del pedido.',
        boton: 'Confirmar efectivo',
    },
};
</script>

<template>
    <Head title="Pagos" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            eyebrow="Cobranza"
            title="Pagos"
            description="Revisa comprobantes de transferencia, confirma pagos en efectivo y consulta el historial. Los pagos con tarjeta anteriores se conservan sólo como consulta."
        />

        <nav
            class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0"
            aria-label="Secciones de pagos"
        >
            <div class="flex w-max gap-2 rounded-2xl bg-muted p-1">
                <button
                    v-for="t in TABS"
                    :key="t.value"
                    type="button"
                    :aria-current="tab === t.value ? 'page' : undefined"
                    :class="[
                        'flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium whitespace-nowrap transition',
                        tab === t.value
                            ? 'bg-background shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    ]"
                    @click="irTab(t.value)"
                >
                    <component :is="t.icono" class="size-4" />
                    {{ t.label }}
                    <span
                        v-if="t.value !== 'historial'"
                        :class="[
                            'grid min-w-5 place-items-center rounded-full px-1.5 text-[0.7rem] font-bold tabular-nums',
                            conteos[t.value] > 0
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-background text-muted-foreground',
                        ]"
                        >{{ conteos[t.value] }}</span
                    >
                </button>
            </div>
        </nav>

        <div class="flex flex-col gap-2 sm:flex-row">
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filtros.buscar"
                    type="search"
                    class="pl-9"
                    placeholder="Folio, cliente, correo o referencia…"
                    aria-label="Buscar pagos"
                />
            </div>
            <template v-if="tab === 'historial'">
                <NativeSelect
                    v-model="filtros.estatus"
                    class="w-full sm:w-48"
                    aria-label="Estatus"
                >
                    <NativeSelectOption value=""
                        >Todos los estatus</NativeSelectOption
                    >
                    <NativeSelectOption
                        v-for="o in opciones.estatus"
                        :key="o.value"
                        :value="o.value"
                        >{{ o.label }}</NativeSelectOption
                    >
                </NativeSelect>
                <NativeSelect
                    v-model="filtros.tipo"
                    class="w-full sm:w-48"
                    aria-label="Método"
                >
                    <NativeSelectOption value=""
                        >Todos los métodos</NativeSelectOption
                    >
                    <NativeSelectOption
                        v-for="o in opciones.tipo"
                        :key="o.value"
                        :value="o.value"
                        >{{ o.label }}</NativeSelectOption
                    >
                </NativeSelect>
            </template>
        </div>

        <!-- ============================================================ revisión -->
        <template v-if="tab === 'revision'">
            <EmptyState
                v-if="pagos.data.length === 0"
                :icon="ScanSearch"
                title="Sin comprobantes por revisar"
                description="Cuando un cliente suba su comprobante de transferencia aparecerá aquí."
            />
            <div
                v-else
                class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)] xl:grid-cols-[20rem_minmax(0,1fr)]"
            >
                <ul
                    class="flex gap-2 overflow-x-auto pb-1 lg:max-h-[75vh] lg:flex-col lg:overflow-y-auto lg:pb-0"
                    aria-label="Comprobantes"
                >
                    <li
                        v-for="pago in pagos.data"
                        :key="pago.id"
                        class="w-64 shrink-0 lg:w-auto"
                    >
                        <button
                            type="button"
                            :aria-pressed="pago.id === seleccionId"
                            :class="[
                                'w-full rounded-2xl border p-3 text-left transition',
                                pago.id === seleccionId
                                    ? 'border-brand-teal bg-brand-teal-soft/60 ring-1 ring-brand-teal'
                                    : 'bg-card hover:border-brand-teal/50',
                            ]"
                            @click="seleccionId = pago.id"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span class="font-semibold">{{
                                    pago.pedido?.folio
                                }}</span>
                                <span
                                    class="font-display font-bold tabular-nums"
                                    >{{ dinero(pago.pedido?.total) }}</span
                                >
                            </div>
                            <p class="truncate text-sm">
                                {{ pago.pedido?.nombre_cliente }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Enviado
                                {{
                                    haceCuanto(
                                        pago.comprobantes[0]?.created_at ??
                                            pago.created_at,
                                    )
                                }}
                                <span v-if="pago.comprobantes.length > 1">
                                    ·
                                    {{ pago.comprobantes.length }}
                                    archivos</span
                                >
                            </p>
                        </button>
                    </li>
                    <li v-if="pagos.last_page > 1" class="shrink-0 lg:pt-2">
                        <Paginacion :pagina="pagos" />
                    </li>
                </ul>

                <section
                    v-if="seleccionado"
                    class="grid min-w-0 gap-4 xl:grid-cols-[minmax(0,1fr)_17rem]"
                >
                    <ComprobanteVisor
                        v-if="comprobante"
                        :comprobante="comprobante"
                        alto="h-[65vh] min-h-96"
                    />
                    <p
                        v-else
                        class="rounded-2xl border p-6 text-sm text-muted-foreground"
                    >
                        Sin archivo adjunto.
                    </p>

                    <div class="flex flex-col gap-4">
                        <div class="rounded-2xl border bg-card p-4">
                            <p class="text-xs text-muted-foreground">Pedido</p>
                            <Link
                                v-if="seleccionado.pedido"
                                :href="pedidoShow(seleccionado.pedido.id).url"
                                class="inline-flex items-center gap-1 font-display text-lg font-bold text-brand-green-strong hover:underline"
                                >{{ seleccionado.pedido.folio }}
                                <ExternalLink class="size-3.5"
                            /></Link>
                            <p class="text-sm">
                                {{ seleccionado.pedido?.nombre_cliente }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ seleccionado.pedido?.correo_cliente }}
                            </p>
                        </div>

                        <dl
                            class="grid gap-3 rounded-2xl border bg-card p-4 text-sm"
                        >
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Monto esperado
                                </dt>
                                <dd
                                    class="font-display text-2xl font-bold tabular-nums"
                                >
                                    {{ dinero(seleccionado.pedido?.total) }}
                                </dd>
                                <dd
                                    v-if="(seleccionado.pedido?.envio ?? 0) > 0"
                                    class="text-xs text-muted-foreground"
                                >
                                    Incluye envío
                                    {{ dinero(seleccionado.pedido?.envio) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Monto declarado
                                </dt>
                                <dd
                                    class="font-display text-xl font-bold tabular-nums"
                                >
                                    {{
                                        comprobante?.monto_declarado != null
                                            ? dinero(
                                                  comprobante.monto_declarado,
                                              )
                                            : '—'
                                    }}
                                </dd>
                                <dd
                                    v-if="diferencia !== null"
                                    :class="[
                                        'mt-1 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                        diferencia === 0
                                            ? 'bg-success/10 text-success'
                                            : 'bg-warning/10 text-warning',
                                    ]"
                                >
                                    {{
                                        diferencia === 0
                                            ? 'Coincide'
                                            : `Diferencia ${dinero(diferencia)}`
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Referencia
                                </dt>
                                <dd class="font-medium break-all">
                                    {{
                                        comprobante?.referencia ??
                                        seleccionado.referencia_cliente ??
                                        '—'
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted-foreground">
                                    Fecha de transferencia
                                </dt>
                                <dd class="font-medium">
                                    {{
                                        fecha(comprobante?.fecha_transferencia)
                                    }}
                                </dd>
                            </div>
                            <div
                                v-if="seleccionado.pedido?.envio_por_cotizar"
                                class="rounded-xl bg-warning/10 p-2 text-xs text-warning"
                            >
                                El envío aún no está cotizado; cotízalo antes de
                                aprobar.
                            </div>
                        </dl>

                        <div class="grid gap-2">
                            <Button
                                class="h-11"
                                @click="abrir('aprobar', seleccionado)"
                            >
                                <Check class="size-4" /> Aprobar pago
                            </Button>
                            <Button
                                variant="outline"
                                class="h-11 text-destructive hover:text-destructive"
                                @click="abrir('rechazar', seleccionado)"
                            >
                                <XCircle class="size-4" /> Rechazar
                            </Button>
                        </div>
                    </div>
                </section>
            </div>
        </template>

        <!-- ============================================================ efectivo -->
        <template v-else-if="tab === 'efectivo'">
            <EmptyState
                v-if="pagos.data.length === 0"
                :icon="Banknote"
                title="Sin efectivo pendiente"
                description="Los pedidos con pago en tienda o contra entrega aparecerán aquí hasta que confirmes el cobro."
            />
            <ul
                v-else
                class="grid stagger-children gap-3 md:grid-cols-2 2xl:grid-cols-3"
            >
                <li
                    v-for="pago in pagos.data"
                    :key="pago.id"
                    class="flex hover-lift flex-col gap-3 rounded-2xl border bg-card p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <Link
                                v-if="pago.pedido"
                                :href="pedidoShow(pago.pedido.id).url"
                                class="font-semibold text-brand-green-strong hover:underline"
                                >{{ pago.pedido.folio }}</Link
                            >
                            <p class="truncate text-sm">
                                {{ pago.pedido?.nombre_cliente }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ pago.pedido?.telefono_cliente }}
                            </p>
                        </div>
                        <StatusBadge :etiqueta="pago.tipo" />
                    </div>
                    <div class="flex items-end justify-between gap-2">
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Por cobrar
                            </p>
                            <p
                                class="font-display text-2xl font-bold tabular-nums"
                            >
                                {{
                                    dinero(
                                        pago.pedido?.saldo_pendiente ||
                                            pago.monto,
                                    )
                                }}
                            </p>
                        </div>
                        <StatusBadge :etiqueta="pago.pedido?.estatus ?? null" />
                    </div>
                    <p
                        v-if="pago.pedido?.envio_por_cotizar"
                        class="rounded-xl bg-warning/10 p-2 text-xs text-warning"
                    >
                        Primero cotiza el envío del pedido.
                    </p>
                    <Button
                        :disabled="pago.pedido?.envio_por_cotizar"
                        @click="abrir('efectivo', pago)"
                    >
                        <Banknote class="size-4" /> Confirmar efectivo
                    </Button>
                </li>
            </ul>
            <Paginacion :pagina="pagos" />
        </template>

        <!-- ============================================================ historial -->
        <template v-else>
            <EmptyState
                v-if="pagos.data.length === 0"
                :icon="History"
                title="Sin pagos"
                description="No hay pagos que coincidan."
            />
            <template v-else>
                <div
                    class="hidden overflow-hidden rounded-2xl border bg-card md:block"
                >
                    <table class="w-full table-hover text-sm">
                        <thead
                            class="bg-surface text-left text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Pago</th>
                                <th class="px-4 py-3">Pedido</th>
                                <th class="px-4 py-3">Método</th>
                                <th class="px-4 py-3">Estatus</th>
                                <th class="px-4 py-3">Revisión</th>
                                <th class="px-4 py-3 text-right">Monto</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="pago in pagos.data"
                                :key="pago.id"
                                class="hover:bg-surface/60"
                            >
                                <td class="px-4 py-3">
                                    <p class="font-medium">#{{ pago.id }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ fechaHora(pago.created_at) }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <Link
                                        v-if="pago.pedido"
                                        :href="pedidoShow(pago.pedido.id).url"
                                        class="font-semibold text-brand-green-strong hover:underline"
                                        >{{ pago.pedido.folio }}</Link
                                    >
                                    <p
                                        class="max-w-48 truncate text-xs text-muted-foreground"
                                    >
                                        {{ pago.pedido?.nombre_cliente }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        {{ pago.tipo?.label ?? '—' }}
                                        <Lock
                                            v-if="pago.es_historico"
                                            class="size-3.5 text-muted-foreground"
                                            aria-label="Histórico, sólo lectura"
                                        />
                                    </span>
                                    <p
                                        v-if="pago.referencia_externa"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ pago.referencia_externa }}
                                    </p>
                                </td>
                                <td class="px-4 py-3">
                                    <StatusBadge :etiqueta="pago.estatus" />
                                    <p
                                        v-if="pago.motivo_rechazo"
                                        class="mt-1 max-w-56 truncate text-xs text-destructive"
                                        :title="pago.motivo_rechazo"
                                    >
                                        {{ pago.motivo_rechazo }}
                                    </p>
                                </td>
                                <td
                                    class="px-4 py-3 text-xs text-muted-foreground"
                                >
                                    <template v-if="pago.revisado_por"
                                        >{{ pago.revisado_por }}<br />{{
                                            fechaHora(pago.revisado_en)
                                        }}</template
                                    >
                                    <template v-else>—</template>
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-semibold tabular-nums"
                                >
                                    {{ dinero(pago.monto) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <ul class="grid gap-3 md:hidden">
                    <li
                        v-for="pago in pagos.data"
                        :key="pago.id"
                        class="rounded-2xl border bg-card p-4"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <Link
                                    v-if="pago.pedido"
                                    :href="pedidoShow(pago.pedido.id).url"
                                    class="font-semibold text-brand-green-strong"
                                    >{{ pago.pedido.folio }}</Link
                                >
                                <p class="text-xs text-muted-foreground">
                                    {{ fechaHora(pago.created_at) }}
                                </p>
                            </div>
                            <p
                                class="font-display text-lg font-bold tabular-nums"
                            >
                                {{ dinero(pago.monto) }}
                            </p>
                        </div>
                        <div
                            class="mt-2 flex flex-wrap items-center gap-2 text-xs"
                        >
                            <StatusBadge :etiqueta="pago.estatus" />
                            <span class="text-muted-foreground">{{
                                pago.tipo?.label
                            }}</span>
                            <Lock
                                v-if="pago.es_historico"
                                class="size-3.5 text-muted-foreground"
                            />
                        </div>
                    </li>
                </ul>
                <Paginacion :pagina="pagos" />
            </template>
        </template>
    </div>

    <AccionDialog
        v-if="accion && objetivo"
        v-model:open="abierto"
        :title="titulos[accion].titulo"
        :description="titulos[accion].descripcion"
        :confirm-label="titulos[accion].boton"
        :destructivo="accion === 'rechazar'"
        :procesando="form.processing"
        :deshabilitado="accion === 'rechazar' && form.motivo.trim().length < 5"
        @confirmar="ejecutar"
    >
        <div
            class="flex items-center justify-between rounded-xl bg-surface p-3 text-sm"
        >
            <span
                >{{ objetivo.pedido?.folio }} ·
                {{ objetivo.pedido?.nombre_cliente }}</span
            >
            <span class="font-display text-lg font-bold">{{
                dinero(objetivo.pedido?.total)
            }}</span>
        </div>
        <CampoForm
            v-if="accion === 'efectivo'"
            label="Monto recibido (MXN)"
            for="p-monto"
            :error="form.errors.monto_recibido"
            :ayuda="`Saldo pendiente: ${dinero(objetivo.pedido?.saldo_pendiente)}`"
            requerido
        >
            <Input
                id="p-monto"
                v-model="form.monto_recibido"
                type="number"
                min="0.01"
                step="0.01"
                inputmode="decimal"
                required
            />
        </CampoForm>
        <CampoForm
            v-if="accion === 'rechazar'"
            label="Motivo del rechazo"
            for="p-motivo"
            :error="form.errors.motivo"
            ayuda="El cliente verá este motivo. Ej.: «El monto no coincide», «La imagen no es legible»."
            requerido
        >
            <Textarea
                id="p-motivo"
                v-model="form.motivo"
                rows="3"
                maxlength="500"
                required
            />
        </CampoForm>
        <CampoForm
            v-else
            label="Notas internas (opcional)"
            for="p-notas"
            :error="form.errors.notas"
        >
            <Textarea
                id="p-notas"
                v-model="form.notas"
                rows="2"
                maxlength="500"
            />
        </CampoForm>
    </AccionDialog>
</template>
