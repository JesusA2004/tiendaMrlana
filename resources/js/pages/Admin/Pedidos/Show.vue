<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';
import {
    AlertOctagon,
    ArrowLeft,
    Ban,
    Banknote,
    Check,
    CheckCircle2,
    ClipboardCheck,
    FileText,
    Lock,
    Mail,
    MapPin,
    MessageSquarePlus,
    Package,
    PackageCheck,
    Phone,
    Printer,
    Receipt,
    RotateCcw,
    Store,
    Truck,
    Wallet,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AccionDialog from '@/components/admin/AccionDialog.vue';
import CampoForm from '@/components/admin/CampoForm.vue';
import ComprobanteVisor from '@/components/admin/ComprobanteVisor.vue';
import CotizadorEnvio from '@/components/admin/CotizadorEnvio.vue';
import {
    dinero,
    fecha,
    fechaHora,
    haceCuanto,
    hoyMexico,
} from '@/components/admin/formato';
import OpcionesEnvioLista from '@/components/admin/OpcionesEnvioLista.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { TONO_PUNTO, tono } from '@/components/admin/tonos';
import type {
    AccionesPedido,
    Comprobante,
    OpcionEnvio,
    PagoDatos,
    PaqueteriaOpcion,
    PedidoFila,
} from '@/components/admin/types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { index as pedidosIndex } from '@/routes/admin/pedidos';

type Historial = {
    id: number;
    tipo: string;
    estatus: string | null;
    estatus_label: string | null;
    estatus_color: string;
    estado_anterior: string | null;
    comentario: string | null;
    visible_cliente: boolean;
    usuario: string | null;
    usuario_rol: string | null;
    created_at: string | null;
};

type PedidoDetalle = PedidoFila & {
    descuento: number;
    impuesto: number;
    cupon_codigo: string | null;
    notas_cliente: string | null;
    motivo_cancelacion: string | null;
    cancelado_por: string | null;
    cancelado_en: string | null;
    envio_cotizado_por: string | null;
    envio_cotizado_en: string | null;
    pagado_en: string | null;
    preparando_en: string | null;
    listo_envio_en: string | null;
    entregado_en: string | null;
    regla_envio: { nombre: string; dias_estimados: string | null } | null;
    cuenta: { id: number; name: string; email: string } | null;
    direccion: {
        nombre_receptor: string | null;
        telefono: string | null;
        linea1: string;
        colonia: string | null;
        municipio: string | null;
        estado: string | null;
        codigo_postal: string | null;
        referencias: string | null;
    } | null;
    items: {
        id: number;
        nombre: string;
        sku: string | null;
        cantidad: number;
        precio_unitario: number;
        subtotal: number;
        imagen: string | null;
        producto_id: number | null;
    }[];
    pagos: PagoDatos[];
    historial: Historial[];
    opciones_envio: OpcionEnvio[];
    url_rastreo: string | null;
};

const props = defineProps<{
    pedido: PedidoDetalle;
    pagoActualId: number | null;
    acciones: AccionesPedido;
    paqueterias: PaqueteriaOpcion[];
    puedeGestionarPaqueterias: boolean;
    documentos: { pdf: string | null; hoja_empaque: string | null };
}>();

// ---------------------------------------------------------------- cotización de envío
const esperandoEleccion = computed(
    () => props.pedido.envio_estado?.value === 'opciones_enviadas',
);
const cotizadorAbierto = ref(
    props.acciones.cotizar_envio && props.pedido.opciones_envio.length === 0,
);
const modoCotizador = computed<'nuevo' | 'reemplazar' | 'recotizar'>(() =>
    props.acciones.recotizar_envio
        ? 'recotizar'
        : props.pedido.opciones_envio.length
          ? 'reemplazar'
          : 'nuevo',
);
const mostrarCotizacion = computed(
    () =>
        props.acciones.cotizar_envio ||
        props.acciones.recotizar_envio ||
        props.pedido.opciones_envio.length > 0,
);

function irACotizacion() {
    cotizadorAbierto.value = true;
    document
        .getElementById('cotizacion-envio')
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Pedidos', href: pedidosIndex().url }],
    },
});

const page = usePage();
const puedeRevisarPagos = computed(() =>
    Boolean(
        (page.props.auth as { can?: Record<string, boolean> }).can
            ?.revisar_pagos,
    ),
);

const p = computed(() => props.pedido);
const pagoActual = computed(
    () => props.pedido.pagos.find((x) => x.id === props.pagoActualId) ?? null,
);
const esRecoger = computed(
    () => p.value.modalidad_entrega?.value === 'recoger_en_tienda',
);
const esContraEntrega = computed(
    () => p.value.metodo_pago?.value === 'efectivo_contra_entrega',
);

// ---------------------------------------------------------------- comprobantes
const comprobantes = computed<(Comprobante & { pago: PagoDatos })[]>(() =>
    props.pedido.pagos.flatMap((pago) =>
        pago.comprobantes.map((c) => ({ ...c, pago })),
    ),
);
const comprobanteVisible = ref<number | null>(
    comprobantes.value[0]?.id ?? null,
);
const comprobanteActual = computed(
    () =>
        comprobantes.value.find((c) => c.id === comprobanteVisible.value) ??
        null,
);

// ---------------------------------------------------------------- etapas del flujo
type Etapa = {
    clave: string;
    label: string;
    icono: LucideIcon;
    hecho: boolean;
    en: string | null;
};

const etapas = computed<Etapa[]>(() => {
    const x = p.value;
    const log = x.estado_logistico?.value ?? 'pendiente';
    const pagado =
        ['aprobado', 'reembolsado'].includes(x.estado_pago?.value ?? '') ||
        (x.estado_pago?.value === 'contra_entrega' && log === 'entregado');
    const orden = [
        'pendiente',
        'en_preparacion',
        'listo_envio',
        'listo_recoger',
        'enviado',
        'entregado',
    ];
    const nivel = Math.max(0, orden.indexOf(log));

    const lista: Etapa[] = [
        {
            clave: 'creado',
            label: 'Pedido recibido',
            icono: Receipt,
            hecho: true,
            en: x.created_at,
        },
    ];

    if (x.envio_estado?.value !== 'calculado') {
        lista.push({
            clave: 'cotizado',
            label:
                x.envio_estado?.value === 'opciones_enviadas'
                    ? 'Cliente elige envío'
                    : 'Envío cotizado',
            icono: Wallet,
            hecho: x.envio_estado?.value === 'cotizado',
            en: x.envio_cotizado_en,
        });
    }

    lista.push(
        {
            clave: 'pago',
            label: esContraEntrega.value
                ? 'Pago contra entrega'
                : 'Pago confirmado',
            icono: Banknote,
            hecho: pagado || (esContraEntrega.value && nivel >= 1),
            en: x.pagado_en,
        },
        {
            clave: 'preparacion',
            label: 'En preparación',
            icono: Package,
            hecho: nivel >= 1,
            en: x.preparando_en,
        },
        {
            clave: 'listo',
            label: esRecoger.value ? 'Listo para recoger' : 'Listo para envío',
            icono: PackageCheck,
            hecho: nivel >= 2 && log !== 'incidencia',
            en: x.listo_envio_en,
        },
    );

    if (!esRecoger.value) {
        lista.push({
            clave: 'enviado',
            label: 'Enviado',
            icono: Truck,
            hecho: nivel >= 4,
            en: x.enviado_en,
        });
    }

    lista.push({
        clave: 'entregado',
        label: 'Entregado',
        icono: CheckCircle2,
        hecho: log === 'entregado',
        en: x.entregado_en,
    });

    return lista;
});

const progreso = computed(() => {
    const hechas = etapas.value.filter((e) => e.hecho).length;

    return Math.round(
        ((hechas - 1) / Math.max(1, etapas.value.length - 1)) * 100,
    );
});

const terminal = computed(() =>
    ['cancelado', 'vencido', 'reembolsado'].includes(
        p.value.estatus?.value ?? '',
    ),
);

// ---------------------------------------------------------------- acciones
// La cotización de envío tiene su propia sección (formulario de opciones).
type ClaveAccion =
    | 'aprobar_pago'
    | 'rechazar_pago'
    | 'confirmar_efectivo'
    | 'iniciar_preparacion'
    | 'marcar_listo_envio'
    | 'marcar_listo_recoger'
    | 'registrar_envio'
    | 'confirmar_entrega'
    | 'registrar_incidencia'
    | 'cancelar'
    | 'reembolsar';

type DefAccion = {
    label: string;
    titulo: string;
    descripcion: string;
    icono: LucideIcon;
    variante: 'default' | 'outline' | 'destructive' | 'secondary';
    destructivo?: boolean;
    url: () => string;
    campos: (keyof typeof form)[];
    principal?: boolean;
};

const form = useForm({
    monto: '' as string | number,
    nota: '',
    notas: '',
    motivo: '',
    monto_recibido: '' as string | number,
    paqueteria: '',
    guia: '',
    fecha_envio: hoyMexico(),
    confirmo: false,
});

const base = `/admin/pedidos/${props.pedido.id}`;
const pagoUrl = (accion: string) =>
    `/admin/pagos/${props.pagoActualId}/${accion}`;

const DEF: Record<ClaveAccion, DefAccion> = {
    aprobar_pago: {
        label: 'Aprobar pago',
        titulo: 'Aprobar pago',
        descripcion:
            'Confirma que el dinero ya está en la cuenta. El pedido pasará a confirmado.',
        icono: Check,
        variante: 'default',
        principal: true,
        url: () => pagoUrl('aprobar'),
        campos: ['notas'],
    },
    rechazar_pago: {
        label: 'Rechazar comprobante',
        titulo: 'Rechazar comprobante',
        descripcion:
            'El cliente verá el motivo y podrá subir un comprobante nuevo.',
        icono: XCircle,
        variante: 'outline',
        destructivo: true,
        url: () => pagoUrl('rechazar'),
        campos: ['motivo'],
    },
    confirmar_efectivo: {
        label: 'Confirmar efectivo',
        titulo: 'Confirmar efectivo recibido',
        descripcion:
            'Registra el monto que recibiste. Debe cubrir el saldo pendiente.',
        icono: Banknote,
        variante: 'default',
        principal: true,
        url: () => pagoUrl('confirmar-efectivo'),
        campos: ['monto_recibido', 'notas'],
    },
    iniciar_preparacion: {
        label: 'Iniciar preparación',
        titulo: 'Iniciar preparación',
        descripcion: 'Avisaremos al cliente que su pedido se está preparando.',
        icono: Package,
        variante: 'default',
        principal: true,
        url: () => `${base}/preparacion`,
        campos: [],
    },
    marcar_listo_envio: {
        label: 'Listo para envío',
        titulo: 'Marcar listo para envío',
        descripcion: 'El paquete está empacado y espera a la paquetería.',
        icono: PackageCheck,
        variante: 'default',
        principal: true,
        url: () => `${base}/listo-envio`,
        campos: [],
    },
    marcar_listo_recoger: {
        label: 'Listo para recoger',
        titulo: 'Listo para recoger en tienda',
        descripcion: 'Avisaremos al cliente que ya puede pasar por su pedido.',
        icono: Store,
        variante: 'default',
        principal: true,
        url: () => `${base}/listo-recoger`,
        campos: [],
    },
    registrar_envio: {
        label: 'Registrar envío',
        titulo: 'Registrar envío',
        descripcion:
            'Captura la paquetería y el número de guía; el cliente podrá rastrear su paquete.',
        icono: Truck,
        variante: 'default',
        principal: true,
        url: () => `${base}/envio`,
        campos: ['paqueteria', 'guia', 'fecha_envio'],
    },
    confirmar_entrega: {
        label: 'Confirmar entrega',
        titulo: 'Confirmar entrega',
        descripcion: 'El cliente ya tiene su pedido.',
        icono: CheckCircle2,
        variante: 'default',
        principal: true,
        url: () => `${base}/entrega`,
        campos: ['monto_recibido', 'notas'],
    },
    registrar_incidencia: {
        label: 'Registrar incidencia',
        titulo: 'Registrar incidencia',
        descripcion:
            'Paquete dañado, extraviado, dirección incorrecta… Queda en el historial interno.',
        icono: AlertOctagon,
        variante: 'outline',
        url: () => `${base}/incidencia`,
        campos: ['nota'],
    },
    cancelar: {
        label: 'Cancelar pedido',
        titulo: 'Cancelar pedido',
        descripcion:
            'Se liberarán las existencias y el cupón. Esta acción no se puede deshacer.',
        icono: Ban,
        variante: 'outline',
        destructivo: true,
        url: () => `${base}/cancelar`,
        campos: ['motivo', 'confirmo'],
    },
    reembolsar: {
        label: 'Registrar reembolso',
        titulo: 'Registrar reembolso manual',
        descripcion:
            'Úsalo cuando ya devolviste el dinero al cliente fuera de la plataforma.',
        icono: RotateCcw,
        variante: 'outline',
        destructivo: true,
        url: () => `${base}/reembolso`,
        campos: ['nota'],
    },
};

const disponibles = computed(() =>
    (Object.keys(DEF) as ClaveAccion[]).filter((k) => props.acciones[k]),
);
const principales = computed(() =>
    disponibles.value.filter((k) => DEF[k].principal),
);
const secundarias = computed(() =>
    disponibles.value.filter((k) => !DEF[k].principal),
);

const accion = ref<ClaveAccion | null>(null);
const dialogoAbierto = ref(false);

const cobraEnEntrega = computed(
    () =>
        esContraEntrega.value &&
        p.value.estado_pago?.value === 'contra_entrega' &&
        puedeRevisarPagos.value,
);

function abrir(clave: ClaveAccion) {
    form.reset();
    form.clearErrors();
    accion.value = clave;

    if (
        clave === 'confirmar_efectivo' ||
        (clave === 'confirmar_entrega' && cobraEnEntrega.value)
    ) {
        form.monto_recibido = p.value.saldo_pendiente || '';
    }

    if (clave === 'registrar_envio') {
        form.paqueteria = p.value.paqueteria ?? '';
        form.guia = p.value.numero_guia ?? '';
    }

    dialogoAbierto.value = true;
}

function ejecutar() {
    if (!accion.value) {
        return;
    }

    const def = DEF[accion.value];

    form.transform((datos) => {
        const salida: Record<string, unknown> = {};

        for (const campo of def.campos) {
            if (campo === 'confirmo') {
                continue;
            }

            if (
                campo === 'monto_recibido' &&
                accion.value === 'confirmar_entrega' &&
                !cobraEnEntrega.value
            ) {
                continue;
            }

            salida[campo as string] = (datos as Record<string, unknown>)[
                campo as string
            ];
        }

        return salida;
    }).post(def.url(), {
        preserveScroll: true,
        onSuccess: () => {
            dialogoAbierto.value = false;
        },
    });
}

const deshabilitarConfirmacion = computed(() => {
    if (accion.value === 'cancelar') {
        return !form.confirmo || form.motivo.trim().length < 5;
    }

    if (accion.value === 'rechazar_pago') {
        return form.motivo.trim().length < 5;
    }

    return false;
});

// ---------------------------------------------------------------- notas
const notaForm = useForm({ nota: '', visible_cliente: false });

function agregarNota() {
    notaForm.post(`${base}/notas`, {
        preserveScroll: true,
        onSuccess: () => notaForm.reset(),
    });
}

const historialFiltro = ref<'todo' | 'notas' | 'cliente'>('todo');
const historialVisible = computed(() =>
    props.pedido.historial.filter((h) =>
        historialFiltro.value === 'notas'
            ? h.tipo === 'nota'
            : historialFiltro.value === 'cliente'
              ? h.visible_cliente
              : true,
    ),
);

const ICONO_TIPO: Record<string, LucideIcon> = {
    pago: Banknote,
    logistico: Package,
    envio: Truck,
    nota: MessageSquarePlus,
    sistema: ClipboardCheck,
    general: Receipt,
};

const diferenciaDeclarada = computed(() => {
    const c = comprobanteActual.value;

    if (!c || c.monto_declarado === null) {
        return null;
    }

    return Math.round((c.monto_declarado - p.value.total) * 100) / 100;
});
</script>

<template>
    <Head :title="`Pedido ${pedido.folio}`" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <!-- Encabezado -->
        <header
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div class="min-w-0">
                <Link
                    :href="pedidosIndex().url"
                    class="mb-2 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-4" /> Pedidos
                </Link>
                <div class="flex flex-wrap items-center gap-3">
                    <h1
                        class="font-display text-2xl font-bold tracking-tight sm:text-3xl"
                    >
                        {{ pedido.folio }}
                    </h1>
                    <StatusBadge :etiqueta="pedido.estatus" size="md" />
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Creado {{ fechaHora(pedido.created_at) }} ·
                    {{ pedido.metodo_pago?.label ?? 'Sin método' }} ·
                    {{ pedido.modalidad_entrega?.label }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button v-if="documentos.pdf" variant="outline" as-child>
                    <a :href="documentos.pdf" target="_blank" rel="noopener">
                        <FileText class="size-4" /> PDF del pedido
                    </a>
                </Button>
                <Button
                    v-if="documentos.hoja_empaque"
                    variant="outline"
                    as-child
                >
                    <a
                        :href="documentos.hoja_empaque"
                        target="_blank"
                        rel="noopener"
                    >
                        <Printer class="size-4" /> Hoja de empaque
                    </a>
                </Button>
            </div>
        </header>

        <Alert
            v-if="pedido.estatus?.value === 'cancelado'"
            variant="destructive"
        >
            <Ban class="size-4" />
            <AlertTitle>Pedido cancelado</AlertTitle>
            <AlertDescription>
                {{ pedido.motivo_cancelacion ?? 'Sin motivo registrado.' }}
                <span v-if="pedido.cancelado_por">
                    · por {{ pedido.cancelado_por }}</span
                >
                <span v-if="pedido.cancelado_en">
                    · {{ fechaHora(pedido.cancelado_en) }}</span
                >
                <template v-if="pedido.estado_pago?.value === 'aprobado'">
                    <br />Tenía un pago aprobado: registra el reembolso manual
                    cuando devuelvas el dinero.
                </template>
            </AlertDescription>
        </Alert>
        <Alert
            v-else-if="pedido.envio_estado?.value === 'por_cotizar'"
            class="border-warning/40 bg-warning/5"
        >
            <Wallet class="size-4 text-warning" />
            <AlertTitle>Envío por cotizar</AlertTitle>
            <AlertDescription>
                El cliente no puede pagar hasta que le envíes opciones de
                paquetería para
                {{ pedido.direccion?.municipio }},
                {{ pedido.direccion?.estado }}.
                <button
                    v-if="acciones.cotizar_envio"
                    type="button"
                    class="font-semibold text-brand-green-strong underline-offset-2 hover:underline"
                    @click="irACotizacion"
                >
                    Cotizar ahora
                </button>
            </AlertDescription>
        </Alert>
        <Alert v-else-if="esperandoEleccion" class="border-info/40 bg-info/5">
            <Truck class="size-4 text-info" />
            <AlertTitle>Esperando elección del cliente</AlertTitle>
            <AlertDescription>
                Le enviamos {{ pedido.opciones_envio.length }}
                {{ pedido.opciones_envio.length === 1 ? 'opción' : 'opciones' }}
                de envío. Cuando elija, verá el total final y las instrucciones
                de pago.
            </AlertDescription>
        </Alert>
        <Alert
            v-else-if="pedido.estado_logistico?.value === 'incidencia'"
            variant="destructive"
        >
            <AlertOctagon class="size-4" />
            <AlertTitle>Pedido con incidencia</AlertTitle>
            <AlertDescription
                >Revisa el historial para ver el detalle y da
                seguimiento.</AlertDescription
            >
        </Alert>

        <!-- Progreso del flujo -->
        <section
            v-if="!terminal"
            class="rounded-2xl border bg-card p-4 sm:p-5"
            aria-label="Avance del pedido"
        >
            <div class="relative">
                <div
                    class="absolute top-4 right-4 left-4 h-0.5 bg-muted"
                    aria-hidden="true"
                >
                    <div
                        class="h-full bg-brand-gradient transition-[width] duration-1000 ease-out"
                        :style="{ width: `${progreso}%` }"
                    />
                </div>
                <ol class="relative flex justify-between gap-1">
                    <li
                        v-for="e in etapas"
                        :key="e.clave"
                        class="flex w-0 flex-1 flex-col items-center gap-1.5 text-center"
                    >
                        <span
                            :class="[
                                'grid size-8 place-items-center rounded-full border-2 transition',
                                e.hecho
                                    ? 'border-transparent bg-brand-green text-brand-ink'
                                    : 'border-border bg-background text-muted-foreground',
                            ]"
                        >
                            <component :is="e.icono" class="size-4" />
                        </span>
                        <span
                            :class="[
                                'hidden text-xs leading-tight sm:block',
                                e.hecho
                                    ? 'font-semibold'
                                    : 'text-muted-foreground',
                            ]"
                            >{{ e.label }}</span
                        >
                        <span
                            v-if="e.hecho && e.en"
                            class="hidden text-[0.68rem] text-muted-foreground md:block"
                            >{{ haceCuanto(e.en) }}</span
                        >
                    </li>
                </ol>
            </div>
            <p class="mt-3 text-center text-xs text-muted-foreground sm:hidden">
                {{ etapas.filter((e) => e.hecho).at(-1)?.label }} ·
                {{ etapas.filter((e) => e.hecho).length }}/{{ etapas.length }}
            </p>
        </section>

        <div
            class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem] xl:grid-cols-[minmax(0,1fr)_22rem]"
        >
            <!-- Columna principal -->
            <div class="flex min-w-0 flex-col gap-5">
                <!-- Cotización de envío con varias paqueterías -->
                <section
                    v-if="mostrarCotizacion"
                    id="cotizacion-envio"
                    class="scroll-mt-24 overflow-hidden rounded-2xl border bg-card"
                    aria-labelledby="titulo-cotizacion"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b bg-brand-teal-soft/40 px-4 py-3"
                    >
                        <div class="min-w-0">
                            <h2
                                id="titulo-cotizacion"
                                class="flex items-center gap-2 font-display text-lg font-semibold"
                            >
                                <Truck class="size-5 text-brand-teal-strong" />
                                Cotización de envío
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                <template
                                    v-if="
                                        pedido.envio_estado?.value ===
                                        'por_cotizar'
                                    "
                                    >Agrega una o varias opciones; el cliente
                                    elige según precio y urgencia.</template
                                >
                                <template v-else-if="esperandoEleccion"
                                    >Opciones enviadas
                                    {{ haceCuanto(pedido.envio_cotizado_en) }}
                                    <span v-if="pedido.envio_cotizado_por">
                                        por
                                        {{ pedido.envio_cotizado_por }}</span
                                    >. Puedes reemplazarlas mientras el cliente
                                    no elija.</template
                                >
                                <template v-else
                                    >El cliente eligió
                                    <strong>{{ pedido.paqueteria }}</strong>
                                    por {{ dinero(pedido.envio) }}.</template
                                >
                            </p>
                        </div>
                        <StatusBadge :etiqueta="pedido.envio_estado" />
                    </div>

                    <div class="grid gap-4 p-4">
                        <OpcionesEnvioLista
                            v-if="pedido.opciones_envio.length"
                            :opciones="pedido.opciones_envio"
                        />

                        <template
                            v-if="
                                acciones.cotizar_envio ||
                                acciones.recotizar_envio
                            "
                        >
                            <CotizadorEnvio
                                v-if="cotizadorAbierto"
                                :pedido-id="pedido.id"
                                :paqueterias="paqueterias"
                                :modo="modoCotizador"
                                :previas="pedido.opciones_envio"
                                :puede-gestionar-paqueterias="
                                    puedeGestionarPaqueterias
                                "
                                @enviado="cotizadorAbierto = false"
                                @cancelar="cotizadorAbierto = false"
                            />
                            <div v-else class="flex flex-wrap gap-2">
                                <Button
                                    :variant="
                                        modoCotizador === 'nuevo'
                                            ? 'default'
                                            : 'outline'
                                    "
                                    @click="cotizadorAbierto = true"
                                >
                                    <Wallet class="size-4" />
                                    {{
                                        modoCotizador === 'nuevo'
                                            ? 'Agregar opciones de envío'
                                            : modoCotizador === 'reemplazar'
                                              ? 'Reemplazar opciones'
                                              : 'Re-cotizar envío'
                                    }}
                                </Button>
                                <p
                                    v-if="modoCotizador === 'recotizar'"
                                    class="self-center text-xs text-muted-foreground"
                                >
                                    Sólo mientras no haya pago en revisión ni
                                    preparación; pide un motivo.
                                </p>
                            </div>
                        </template>
                    </div>
                </section>

                <!-- Productos y totales -->
                <section class="overflow-hidden rounded-2xl border bg-card">
                    <h2
                        class="border-b px-4 py-3 font-display text-lg font-semibold"
                    >
                        Productos
                    </h2>
                    <ul class="divide-y">
                        <li
                            v-for="item in pedido.items"
                            :key="item.id"
                            class="flex items-center gap-3 px-4 py-3"
                        >
                            <img
                                v-if="item.imagen"
                                :src="item.imagen"
                                :alt="item.nombre"
                                class="size-12 shrink-0 rounded-xl border object-cover sm:size-14"
                                loading="lazy"
                            />
                            <span
                                v-else
                                class="grid size-12 shrink-0 place-items-center rounded-xl border bg-surface text-muted-foreground sm:size-14"
                            >
                                <Package class="size-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="line-clamp-2 font-medium break-words">
                                    {{ item.nombre }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    <span
                                        v-if="item.sku"
                                        class="block break-all sm:inline"
                                        >SKU {{ item.sku
                                        }}<span class="max-sm:hidden"> ·</span>
                                    </span>
                                    {{ item.cantidad }} ×
                                    {{ dinero(item.precio_unitario) }}
                                </p>
                            </div>
                            <p class="font-semibold tabular-nums">
                                {{ dinero(item.subtotal) }}
                            </p>
                        </li>
                    </ul>
                    <dl
                        class="space-y-1.5 border-t bg-surface/60 px-4 py-3 text-sm"
                    >
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Subtotal</dt>
                            <dd class="tabular-nums">
                                {{ dinero(pedido.subtotal) }}
                            </dd>
                        </div>
                        <div
                            v-if="pedido.descuento > 0"
                            class="flex justify-between"
                        >
                            <dt class="text-muted-foreground">
                                Descuento<span v-if="pedido.cupon_codigo">
                                    (cupón {{ pedido.cupon_codigo }})</span
                                >
                            </dt>
                            <dd class="text-success tabular-nums">
                                −{{ dinero(pedido.descuento) }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Envío</dt>
                            <dd class="tabular-nums">
                                <span
                                    v-if="
                                        pedido.envio_estado?.value ===
                                        'por_cotizar'
                                    "
                                    class="font-medium text-warning"
                                    >Por cotizar</span
                                >
                                <span
                                    v-else-if="esperandoEleccion"
                                    class="font-medium text-info"
                                    >Esperando elección</span
                                >
                                <template v-else>{{
                                    pedido.envio > 0
                                        ? dinero(pedido.envio)
                                        : 'Sin costo'
                                }}</template>
                            </dd>
                        </div>
                        <div
                            class="flex justify-between border-t pt-2 text-base font-semibold"
                        >
                            <dt>Total</dt>
                            <dd class="font-display text-xl tabular-nums">
                                {{ dinero(pedido.total) }}
                            </dd>
                        </div>
                        <div
                            v-if="pedido.saldo_pendiente > 0"
                            class="flex justify-between text-sm"
                        >
                            <dt class="text-muted-foreground">
                                Saldo pendiente
                            </dt>
                            <dd class="font-semibold text-warning tabular-nums">
                                {{ dinero(pedido.saldo_pendiente) }}
                            </dd>
                        </div>
                    </dl>
                </section>

                <!-- Pagos y comprobantes -->
                <section class="overflow-hidden rounded-2xl border bg-card">
                    <div
                        class="flex items-center justify-between border-b px-4 py-3"
                    >
                        <h2 class="font-display text-lg font-semibold">
                            Pagos y comprobantes
                        </h2>
                        <StatusBadge :etiqueta="pedido.estado_pago" />
                    </div>
                    <p
                        v-if="pedido.pagos.length === 0"
                        class="p-4 text-sm text-muted-foreground"
                    >
                        Sin pagos registrados.
                    </p>
                    <ul v-else class="divide-y">
                        <li
                            v-for="pago in pedido.pagos"
                            :key="pago.id"
                            class="flex flex-wrap items-start justify-between gap-3 px-4 py-3"
                        >
                            <div class="min-w-0">
                                <p
                                    class="flex flex-wrap items-center gap-2 font-medium"
                                >
                                    {{ pago.tipo?.label ?? 'Pago' }}
                                    <StatusBadge :etiqueta="pago.estatus" />
                                    <span
                                        v-if="pago.es_historico"
                                        class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-[0.68rem] text-muted-foreground"
                                        ><Lock class="size-3" /> Histórico</span
                                    >
                                </p>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    #{{ pago.id }} ·
                                    {{ fechaHora(pago.created_at) }}
                                    <span v-if="pago.referencia_cliente">
                                        · Ref.
                                        {{ pago.referencia_cliente }}</span
                                    >
                                    <span v-if="pago.referencia_externa">
                                        · {{ pago.referencia_externa }}</span
                                    >
                                </p>
                                <p
                                    v-if="pago.revisado_por"
                                    class="mt-0.5 text-xs text-muted-foreground"
                                >
                                    Revisado por {{ pago.revisado_por }} ·
                                    {{ fechaHora(pago.revisado_en) }}
                                </p>
                                <p
                                    v-if="pago.motivo_rechazo"
                                    class="mt-1 text-xs font-medium text-destructive"
                                >
                                    Motivo de rechazo: {{ pago.motivo_rechazo }}
                                </p>
                                <p
                                    v-if="pago.notas_internas"
                                    class="mt-1 text-xs whitespace-pre-line text-muted-foreground"
                                >
                                    Nota interna: {{ pago.notas_internas }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold tabular-nums">
                                    {{ dinero(pago.monto) }}
                                </p>
                                <p
                                    v-if="pago.monto_recibido !== null"
                                    class="text-xs text-muted-foreground tabular-nums"
                                >
                                    Recibido {{ dinero(pago.monto_recibido) }}
                                </p>
                            </div>
                        </li>
                    </ul>

                    <div v-if="comprobantes.length" class="border-t p-4">
                        <div
                            v-if="comprobantes.length > 1"
                            class="mb-3 flex flex-wrap gap-2"
                        >
                            <button
                                v-for="(c, i) in comprobantes"
                                :key="c.id"
                                type="button"
                                :class="[
                                    'rounded-full border px-3 py-1 text-xs font-medium transition',
                                    comprobanteVisible === c.id
                                        ? 'border-transparent bg-brand-deep text-white'
                                        : 'hover:bg-accent',
                                ]"
                                @click="comprobanteVisible = c.id"
                            >
                                Comprobante {{ comprobantes.length - i }} ·
                                {{ c.estado }}
                            </button>
                        </div>
                        <div
                            v-if="comprobanteActual"
                            class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_14rem]"
                        >
                            <ComprobanteVisor
                                :comprobante="comprobanteActual"
                                alto="h-[28rem]"
                            />
                            <dl
                                class="grid content-start gap-3 rounded-2xl border bg-surface/60 p-4 text-sm"
                            >
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Total esperado
                                    </dt>
                                    <dd
                                        class="font-display text-xl font-bold tabular-nums"
                                    >
                                        {{ dinero(pedido.total) }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Monto declarado
                                    </dt>
                                    <dd class="font-semibold tabular-nums">
                                        {{
                                            comprobanteActual.monto_declarado !==
                                            null
                                                ? dinero(
                                                      comprobanteActual.monto_declarado,
                                                  )
                                                : '—'
                                        }}
                                    </dd>
                                    <dd
                                        v-if="diferenciaDeclarada !== null"
                                        :class="[
                                            'mt-0.5 text-xs font-medium',
                                            diferenciaDeclarada === 0
                                                ? 'text-success'
                                                : 'text-warning',
                                        ]"
                                    >
                                        {{
                                            diferenciaDeclarada === 0
                                                ? 'Coincide con el total'
                                                : `Diferencia ${dinero(diferenciaDeclarada)}`
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Referencia
                                    </dt>
                                    <dd class="font-medium break-all">
                                        {{
                                            comprobanteActual.referencia ?? '—'
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Fecha de transferencia
                                    </dt>
                                    <dd class="font-medium">
                                        {{
                                            fecha(
                                                comprobanteActual.fecha_transferencia,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </section>

                <!-- Historial -->
                <section class="rounded-2xl border bg-card">
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3"
                    >
                        <h2 class="font-display text-lg font-semibold">
                            Historial
                        </h2>
                        <div
                            class="flex gap-1 rounded-xl bg-muted p-1 text-xs"
                            role="tablist"
                        >
                            <button
                                v-for="f in [
                                    { v: 'todo', l: 'Todo' },
                                    { v: 'notas', l: 'Notas' },
                                    { v: 'cliente', l: 'Visible al cliente' },
                                ] as const"
                                :key="f.v"
                                type="button"
                                role="tab"
                                :aria-selected="historialFiltro === f.v"
                                :class="[
                                    'rounded-lg px-2.5 py-1 font-medium transition',
                                    historialFiltro === f.v
                                        ? 'bg-background shadow-sm'
                                        : 'text-muted-foreground',
                                ]"
                                @click="historialFiltro = f.v"
                            >
                                {{ f.l }}
                            </button>
                        </div>
                    </div>

                    <form
                        v-if="acciones.agregar_nota"
                        class="grid gap-2 border-b p-4"
                        @submit.prevent="agregarNota"
                    >
                        <Label for="nota-nueva" class="sr-only"
                            >Nueva nota</Label
                        >
                        <Textarea
                            id="nota-nueva"
                            v-model="notaForm.nota"
                            rows="2"
                            maxlength="1000"
                            placeholder="Agrega una nota para el equipo o para el cliente…"
                        />
                        <p
                            v-if="notaForm.errors.nota"
                            class="text-xs text-destructive"
                        >
                            {{ notaForm.errors.nota }}
                        </p>
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <label class="flex items-center gap-2 text-sm">
                                <Switch
                                    :model-value="notaForm.visible_cliente"
                                    @update:model-value="
                                        (v: boolean) =>
                                            (notaForm.visible_cliente = v)
                                    "
                                />
                                <span>{{
                                    notaForm.visible_cliente
                                        ? 'Visible para el cliente'
                                        : 'Nota interna'
                                }}</span>
                            </label>
                            <Button
                                type="submit"
                                size="sm"
                                :disabled="
                                    notaForm.processing || !notaForm.nota.trim()
                                "
                            >
                                <MessageSquarePlus class="size-4" /> Agregar
                                nota
                            </Button>
                        </div>
                    </form>

                    <ol class="relative px-4 py-4">
                        <li
                            v-if="historialVisible.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            Sin movimientos.
                        </li>
                        <li
                            v-for="(h, i) in historialVisible"
                            :key="h.id"
                            class="relative flex gap-3 pb-5 last:pb-0"
                        >
                            <span
                                v-if="i < historialVisible.length - 1"
                                class="absolute top-8 bottom-0 left-4 w-px bg-border"
                                aria-hidden="true"
                            />
                            <span
                                :class="[
                                    'relative grid size-8 shrink-0 place-items-center rounded-full border bg-background',
                                    h.tipo === 'nota' &&
                                        !h.visible_cliente &&
                                        'border-dashed',
                                ]"
                            >
                                <component
                                    :is="ICONO_TIPO[h.tipo] ?? Receipt"
                                    class="size-4 text-muted-foreground"
                                />
                            </span>
                            <div class="min-w-0 flex-1 pt-0.5">
                                <div
                                    class="flex flex-wrap items-center gap-x-2 gap-y-1"
                                >
                                    <span
                                        v-if="h.tipo !== 'nota'"
                                        class="flex items-center gap-1.5 text-sm font-semibold"
                                    >
                                        <span
                                            :class="[
                                                'size-2 rounded-full',
                                                TONO_PUNTO[
                                                    tono(h.estatus_color)
                                                ],
                                            ]"
                                        />
                                        {{ h.estatus_label }}
                                    </span>
                                    <span v-else class="text-sm font-semibold"
                                        >Nota</span
                                    >
                                    <span
                                        :class="[
                                            'rounded-full px-1.5 py-0.5 text-[0.65rem] font-medium',
                                            h.visible_cliente
                                                ? 'bg-brand-teal-soft text-brand-teal-strong'
                                                : 'bg-muted text-muted-foreground',
                                        ]"
                                        >{{
                                            h.visible_cliente
                                                ? 'Cliente la ve'
                                                : 'Interna'
                                        }}</span
                                    >
                                </div>
                                <p
                                    v-if="h.comentario"
                                    class="mt-1 text-sm whitespace-pre-line"
                                >
                                    {{ h.comentario }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ fechaHora(h.created_at) }}
                                    <span v-if="h.usuario">
                                        · {{ h.usuario }}</span
                                    >
                                    <span v-else> · Sistema</span>
                                </p>
                            </div>
                        </li>
                    </ol>
                </section>
            </div>

            <!-- Columna lateral -->
            <aside
                class="flex flex-col gap-5 lg:sticky lg:top-20 lg:self-start"
            >
                <!-- Acciones -->
                <section class="rounded-2xl border bg-card p-4">
                    <h2 class="font-display text-lg font-semibold">
                        Siguiente paso
                    </h2>
                    <div
                        v-if="
                            principales.length ||
                            (acciones.cotizar_envio && !esperandoEleccion)
                        "
                        class="mt-3 grid gap-2"
                    >
                        <Button
                            v-if="acciones.cotizar_envio && !esperandoEleccion"
                            class="h-11 justify-start"
                            @click="irACotizacion"
                        >
                            <Wallet class="size-4" /> Cotizar envío
                        </Button>
                        <Button
                            v-for="clave in principales"
                            :key="clave"
                            class="h-11 justify-start"
                            @click="abrir(clave)"
                        >
                            <component :is="DEF[clave].icono" class="size-4" />
                            {{ DEF[clave].label }}
                        </Button>
                    </div>
                    <p v-else class="mt-2 text-sm text-muted-foreground">
                        {{
                            terminal
                                ? 'Este pedido está cerrado.'
                                : esperandoEleccion
                                  ? 'Esperando que el cliente elija su envío.'
                                  : pedido.estatus?.value === 'pendiente_pago'
                                    ? 'Esperando el pago del cliente.'
                                    : pedido.estatus?.value === 'entregado'
                                      ? 'Pedido entregado. No hay más pasos pendientes.'
                                      : 'No hay acciones disponibles para tu rol en este momento.'
                        }}
                    </p>
                    <div
                        v-if="secundarias.length"
                        class="mt-3 grid gap-2 border-t pt-3"
                    >
                        <Button
                            v-for="clave in secundarias"
                            :key="clave"
                            :variant="DEF[clave].variante"
                            size="sm"
                            :class="[
                                'justify-start',
                                DEF[clave].destructivo &&
                                    'text-destructive hover:text-destructive',
                            ]"
                            @click="abrir(clave)"
                        >
                            <component :is="DEF[clave].icono" class="size-4" />
                            {{ DEF[clave].label }}
                        </Button>
                    </div>
                </section>

                <!-- Cliente -->
                <section class="rounded-2xl border bg-card p-4">
                    <h2 class="font-display text-lg font-semibold">Cliente</h2>
                    <p class="mt-2 font-medium">{{ pedido.nombre_cliente }}</p>
                    <ul class="mt-2 grid gap-1.5 text-sm">
                        <li
                            v-if="pedido.correo_cliente"
                            class="flex items-center gap-2"
                        >
                            <Mail class="size-4 text-muted-foreground" />
                            <a
                                :href="`mailto:${pedido.correo_cliente}`"
                                class="truncate hover:underline"
                                >{{ pedido.correo_cliente }}</a
                            >
                        </li>
                        <li
                            v-if="pedido.telefono_cliente"
                            class="flex items-center gap-2"
                        >
                            <Phone class="size-4 text-muted-foreground" />
                            <a
                                :href="`tel:${pedido.telefono_cliente}`"
                                class="hover:underline"
                                >{{ pedido.telefono_cliente }}</a
                            >
                        </li>
                    </ul>
                    <p
                        v-if="
                            pedido.cuenta &&
                            pedido.cuenta.email !== pedido.correo_cliente
                        "
                        class="mt-2 text-xs text-muted-foreground"
                    >
                        Cuenta: {{ pedido.cuenta.name }} ({{
                            pedido.cuenta.email
                        }})
                    </p>
                    <div
                        v-if="pedido.notas_cliente"
                        class="mt-3 rounded-xl bg-surface p-3 text-sm"
                    >
                        <p class="text-xs font-semibold text-muted-foreground">
                            Notas del cliente
                        </p>
                        <p class="mt-1 whitespace-pre-line">
                            {{ pedido.notas_cliente }}
                        </p>
                    </div>
                </section>

                <!-- Entrega -->
                <section class="rounded-2xl border bg-card p-4">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="font-display text-lg font-semibold">
                            Entrega
                        </h2>
                        <StatusBadge :etiqueta="pedido.estado_logistico" />
                    </div>
                    <div
                        v-if="esRecoger"
                        class="mt-2 flex items-start gap-2 text-sm"
                    >
                        <Store class="mt-0.5 size-4 text-muted-foreground" />
                        <p>El cliente recoge en tienda.</p>
                    </div>
                    <address
                        v-else-if="pedido.direccion"
                        class="mt-2 flex items-start gap-2 text-sm not-italic"
                    >
                        <MapPin
                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                        />
                        <span>
                            <span
                                v-if="pedido.direccion.nombre_receptor"
                                class="block font-medium"
                                >{{ pedido.direccion.nombre_receptor }}</span
                            >
                            {{ pedido.direccion.linea1 }}<br />
                            {{ pedido.direccion.colonia }},
                            {{ pedido.direccion.municipio }}<br />
                            {{ pedido.direccion.estado }} · CP
                            {{ pedido.direccion.codigo_postal }}
                            <span
                                v-if="pedido.direccion.telefono"
                                class="block text-muted-foreground"
                                >Tel. {{ pedido.direccion.telefono }}</span
                            >
                            <span
                                v-if="pedido.direccion.referencias"
                                class="mt-1 block text-xs text-muted-foreground"
                                >Ref.: {{ pedido.direccion.referencias }}</span
                            >
                        </span>
                    </address>
                    <dl class="mt-3 grid gap-2 border-t pt-3 text-sm">
                        <div
                            v-if="pedido.regla_envio"
                            class="flex justify-between gap-2"
                        >
                            <dt class="text-muted-foreground">Regla</dt>
                            <dd class="text-right">
                                {{ pedido.regla_envio.nombre }}
                            </dd>
                        </div>
                        <div
                            v-if="pedido.regla_envio?.dias_estimados"
                            class="flex justify-between gap-2"
                        >
                            <dt class="text-muted-foreground">
                                Tiempo estimado
                            </dt>
                            <dd class="text-right">
                                {{ pedido.regla_envio.dias_estimados }}
                            </dd>
                        </div>
                        <div
                            v-if="pedido.envio_cotizado_por"
                            class="flex justify-between gap-2"
                        >
                            <dt class="text-muted-foreground">Cotizó</dt>
                            <dd class="text-right">
                                {{ pedido.envio_cotizado_por }}
                            </dd>
                        </div>
                        <div
                            v-if="pedido.numero_guia"
                            class="flex justify-between gap-2"
                        >
                            <dt class="text-muted-foreground">Guía</dt>
                            <dd class="text-right font-medium">
                                {{ pedido.paqueteria }} ·
                                {{ pedido.numero_guia }}
                                <a
                                    v-if="pedido.url_rastreo"
                                    :href="pedido.url_rastreo"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="block text-xs font-semibold text-brand-green-strong hover:underline"
                                    >Rastrear</a
                                >
                            </dd>
                        </div>
                        <div
                            v-else-if="pedido.paqueteria"
                            class="flex justify-between gap-2"
                        >
                            <dt class="text-muted-foreground">Paquetería</dt>
                            <dd class="text-right font-medium">
                                {{ pedido.paqueteria }}
                            </dd>
                        </div>
                        <div
                            v-if="pedido.fecha_envio"
                            class="flex justify-between gap-2"
                        >
                            <dt class="text-muted-foreground">
                                Fecha de envío
                            </dt>
                            <dd class="text-right">
                                {{ fecha(pedido.fecha_envio) }}
                            </dd>
                        </div>
                        <div
                            v-if="
                                pedido.vence_en &&
                                pedido.estatus?.value === 'pendiente_pago'
                            "
                            class="flex justify-between gap-2"
                        >
                            <dt class="text-muted-foreground">Vence</dt>
                            <dd class="text-right">
                                {{ fechaHora(pedido.vence_en) }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </div>
    </div>

    <!-- Diálogo de acción -->
    <AccionDialog
        v-if="accion"
        v-model:open="dialogoAbierto"
        :title="DEF[accion].titulo"
        :description="DEF[accion].descripcion"
        :confirm-label="DEF[accion].label"
        :destructivo="DEF[accion].destructivo"
        :procesando="form.processing"
        :deshabilitado="deshabilitarConfirmacion"
        @confirmar="ejecutar"
    >
        <template v-if="accion === 'aprobar_pago' && pagoActual">
            <div
                class="grid grid-cols-2 gap-3 rounded-xl bg-surface p-3 text-sm"
            >
                <div>
                    <p class="text-xs text-muted-foreground">
                        Total del pedido
                    </p>
                    <p class="font-display text-lg font-bold">
                        {{ dinero(pedido.total) }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-muted-foreground">
                        Declarado por el cliente
                    </p>
                    <p class="font-display text-lg font-bold">
                        {{
                            pagoActual.comprobantes[0]?.monto_declarado != null
                                ? dinero(
                                      pagoActual.comprobantes[0]
                                          .monto_declarado,
                                  )
                                : '—'
                        }}
                    </p>
                </div>
            </div>
        </template>

        <CampoForm
            v-if="DEF[accion].campos.includes('monto')"
            label="Costo de envío (MXN)"
            for="f-monto"
            :error="form.errors.monto"
            requerido
        >
            <Input
                id="f-monto"
                v-model="form.monto"
                type="number"
                min="0"
                step="0.01"
                inputmode="decimal"
                required
            />
        </CampoForm>

        <CampoForm
            v-if="
                DEF[accion].campos.includes('monto_recibido') &&
                (accion === 'confirmar_efectivo' || cobraEnEntrega)
            "
            label="Monto recibido (MXN)"
            for="f-recibido"
            :error="form.errors.monto_recibido"
            :ayuda="`Saldo pendiente: ${dinero(pedido.saldo_pendiente)}`"
            requerido
        >
            <Input
                id="f-recibido"
                v-model="form.monto_recibido"
                type="number"
                min="0"
                step="0.01"
                inputmode="decimal"
                required
            />
        </CampoForm>
        <p
            v-if="
                accion === 'confirmar_entrega' &&
                esContraEntrega &&
                pedido.estado_pago?.value === 'contra_entrega' &&
                !cobraEnEntrega
            "
            class="rounded-xl bg-warning/10 p-3 text-sm text-warning"
        >
            Este pedido es contra entrega y aún no se confirma el efectivo. Un
            administrador debe registrar el cobro.
        </p>

        <template v-if="DEF[accion].campos.includes('paqueteria')">
            <CampoForm
                label="Paquetería"
                for="f-paq"
                :error="form.errors.paqueteria"
                :ayuda="
                    pedido.opciones_envio.some((o) => o.seleccionada)
                        ? 'Por defecto, la que eligió el cliente.'
                        : undefined
                "
                requerido
            >
                <Input
                    id="f-paq"
                    v-model="form.paqueteria"
                    list="paqueterias"
                    maxlength="80"
                    required
                    autocomplete="off"
                />
                <datalist id="paqueterias">
                    <option
                        v-for="n in paqueterias"
                        :key="n.id"
                        :value="n.nombre"
                    />
                </datalist>
            </CampoForm>
            <CampoForm
                label="Número de guía"
                for="f-guia"
                :error="form.errors.guia"
                requerido
            >
                <Input
                    id="f-guia"
                    v-model="form.guia"
                    maxlength="120"
                    required
                    autocomplete="off"
                />
            </CampoForm>
            <CampoForm
                label="Fecha de envío"
                for="f-fecha"
                :error="form.errors.fecha_envio"
            >
                <Input
                    id="f-fecha"
                    v-model="form.fecha_envio"
                    type="date"
                    :max="hoyMexico()"
                />
            </CampoForm>
        </template>

        <CampoForm
            v-if="DEF[accion].campos.includes('motivo')"
            :label="
                accion === 'cancelar'
                    ? 'Motivo de la cancelación'
                    : 'Motivo del rechazo'
            "
            for="f-motivo"
            :error="form.errors.motivo"
            :ayuda="
                accion === 'rechazar_pago'
                    ? 'El cliente verá este motivo. Mínimo 5 caracteres.'
                    : 'Mínimo 5 caracteres.'
            "
            requerido
        >
            <Textarea
                id="f-motivo"
                v-model="form.motivo"
                rows="3"
                maxlength="500"
                required
            />
        </CampoForm>

        <CampoForm
            v-if="DEF[accion].campos.includes('nota')"
            :label="
                accion === 'registrar_incidencia'
                    ? 'Descripción de la incidencia'
                    : 'Nota'
            "
            for="f-nota"
            :error="form.errors.nota"
            :requerido="accion === 'registrar_incidencia'"
        >
            <Textarea
                id="f-nota"
                v-model="form.nota"
                rows="3"
                maxlength="1000"
            />
        </CampoForm>

        <CampoForm
            v-if="DEF[accion].campos.includes('notas')"
            label="Notas internas (opcional)"
            for="f-notas"
            :error="form.errors.notas"
        >
            <Textarea
                id="f-notas"
                v-model="form.notas"
                rows="2"
                maxlength="500"
            />
        </CampoForm>

        <label
            v-if="accion === 'cancelar'"
            class="flex items-start gap-2 rounded-xl border border-destructive/30 bg-destructive/5 p-3 text-sm"
        >
            <Checkbox
                :model-value="form.confirmo"
                class="mt-0.5"
                @update:model-value="(v) => (form.confirmo = v === true)"
            />
            <span>
                Entiendo que el pedido {{ pedido.folio }} se cancelará y se
                liberarán sus existencias.
                <template v-if="pedido.estado_pago?.value === 'aprobado'">
                    <strong>Tiene un pago aprobado:</strong> deberás devolver el
                    dinero y registrar el reembolso.
                </template>
            </span>
        </label>
    </AccionDialog>
</template>
