<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    BadgeCheck,
    Check,
    ClipboardCheck,
    CreditCard,
    Clock3,
    ImageOff,
    Info,
    Loader2,
    MapPin,
    Plus,
    ShieldCheck,
    Store,
    Truck,
    UserRound,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import CampoTexto from '@/components/compra/CampoTexto.vue';
import EncabezadoPaso from '@/components/compra/EncabezadoPaso.vue';
import { ESTADOS_MX } from '@/components/compra/estados';
import { moneda } from '@/components/compra/formato';
import PasosCompra from '@/components/compra/PasosCompra.vue';
import type { PasoCompra } from '@/components/compra/PasosCompra.vue';
import ResumenTotales from '@/components/compra/ResumenTotales.vue';
import type {
    CotizacionEnvio,
    CuponAplicado,
    Direccion,
    LineaCarrito,
    ResumenCompra,
} from '@/components/compra/types';
import AvisoTarjetas from '@/components/public/AvisoTarjetas.vue';
import MetodoPagoIcono from '@/components/public/MetodoPagoIcono.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import PublicLayout from '@/layouts/PublicLayout.vue';
import carrito from '@/routes/carrito';
import checkout from '@/routes/checkout';

interface MetodoCheckout {
    clave: string;
    nombre: string;
    instrucciones: string | null;
    disponible: boolean;
    motivo: string | null;
}

interface Modalidad {
    value: string;
    label: string;
    disponible: boolean;
}

const props = defineProps<{
    items: LineaCarrito[];
    cupon: CuponAplicado | null;
    cupon_error: string | null;
    resumen: ResumenCompra;
    envio: CotizacionEnvio;
    errores: string[];
    puede_comprar: boolean;
    direcciones: Direccion[];
    seleccion: {
        modalidad_entrega: string;
        direccion_id: number | null;
        nueva: boolean;
    };
    modalidades_entrega: Modalidad[];
    recoger: { direccion: string | null; horario: string | null } | null;
    metodos_pago: MetodoCheckout[];
    cliente: { nombre: string; correo: string; telefono: string | null };
    checkout_token: string;
}>();

const metodoInicial =
    props.metodos_pago.find((m) => m.disponible && m.clave === 'transferencia')
        ?.clave ??
    props.metodos_pago.find((m) => m.disponible)?.clave ??
    '';

const form = useForm({
    checkout_token: props.checkout_token,
    nombre_cliente: props.cliente.nombre ?? '',
    correo_cliente: props.cliente.correo ?? '',
    telefono_cliente: props.cliente.telefono ?? '',
    notas_cliente: '',
    modalidad_entrega: props.seleccion.modalidad_entrega,
    metodo_pago: metodoInicial,
    usar_direccion_existente: !props.seleccion.nueva,
    direccion_id: props.seleccion.direccion_id as number | null,
    alias: '',
    nombre_receptor: props.cliente.nombre ?? '',
    telefono: props.cliente.telefono ?? '',
    calle: '',
    numero_exterior: '',
    numero_interior: '',
    colonia: '',
    municipio: '',
    estado: '',
    codigo_postal: '',
    referencias: '',
});

const recalculando = ref(false);
const esDomicilio = computed(
    () => form.modalidad_entrega === 'envio_domicilio',
);
const recogerDisponible = computed(
    () =>
        props.modalidades_entrega.find((m) => m.value === 'recoger_en_tienda')
            ?.disponible ?? false,
);
const metodoElegido = computed(() =>
    props.metodos_pago.find((m) => m.clave === form.metodo_pago),
);
const piezas = computed(() => props.items.reduce((t, i) => t + i.cantidad, 0));
const envioPorCotizar = computed(
    () => esDomicilio.value && props.resumen.envio_por_cotizar,
);
const puedeConfirmar = computed(
    () =>
        props.puede_comprar &&
        !!metodoElegido.value?.disponible &&
        !form.processing &&
        !recalculando.value,
);

/** Recalcula en el servidor (petición parcial) envío, total y métodos disponibles. */
let temporizador: ReturnType<typeof setTimeout> | null = null;

function recalcular() {
    const nueva = esDomicilio.value && !form.usar_direccion_existente;
    const data: Record<string, string | number> = {
        modalidad_entrega: form.modalidad_entrega,
    };

    if (esDomicilio.value) {
        if (nueva) {
            data.nueva = 1;

            if (form.estado) {
                data.estado = form.estado;
            }

            if (/^\d{5}$/.test(form.codigo_postal)) {
                data.codigo_postal = form.codigo_postal;
            }
        } else if (form.direccion_id) {
            data.direccion_id = form.direccion_id;
        }
    }

    router.reload({
        data,
        only: [
            'items',
            'resumen',
            'envio',
            'errores',
            'puede_comprar',
            'metodos_pago',
            'seleccion',
            'cupon',
            'cupon_error',
        ],
        onStart: () => (recalculando.value = true),
        onFinish: () => (recalculando.value = false),
    });
}

function recalcularPronto() {
    if (temporizador) {
        clearTimeout(temporizador);
    }

    temporizador = setTimeout(recalcular, 450);
}

watch(() => form.modalidad_entrega, recalcular);
watch(() => [form.usar_direccion_existente, form.direccion_id], recalcular);
watch(() => form.estado, recalcularPronto);
watch(
    () => form.codigo_postal,
    (cp) => {
        if (cp.length === 0 || /^\d{5}$/.test(cp)) {
            recalcularPronto();
        }
    },
);

// Si el método elegido deja de estar disponible tras recalcular, se cambia al primero disponible.
watch(
    () => props.metodos_pago,
    (metodos) => {
        if (!metodos.find((m) => m.clave === form.metodo_pago)?.disponible) {
            form.metodo_pago = metodos.find((m) => m.disponible)?.clave ?? '';
        }
    },
);

function elegirDireccion(id: number) {
    form.usar_direccion_existente = true;
    form.direccion_id = id;
}

function usarNueva() {
    form.usar_direccion_existente = false;
    form.direccion_id = null;
}

function confirmar() {
    if (!puedeConfirmar.value) {
        return;
    }

    form.post(checkout.store.url(), {
        preserveScroll: 'errors',
        onError: () => {
            requestAnimationFrame(() => {
                document
                    .querySelector('[aria-invalid="true"]')
                    ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        },
    });
}

/* ---- Sólo presentación: progreso visual del stepper (no valida nada). ---- */
const contactoListo = computed(
    () =>
        form.nombre_cliente.trim().length > 1 &&
        /.+@.+\..+/.test(form.correo_cliente) &&
        form.telefono_cliente.replace(/\D/g, '').length >= 10,
);
const entregaLista = computed(() => {
    if (!esDomicilio.value) {
        return true;
    }

    if (form.usar_direccion_existente) {
        return !!form.direccion_id;
    }

    return (
        !!form.nombre_receptor.trim() &&
        !!form.telefono.trim() &&
        !!form.calle.trim() &&
        !!form.numero_exterior.trim() &&
        !!form.colonia.trim() &&
        !!form.municipio.trim() &&
        !!form.estado &&
        /^\d{5}$/.test(form.codigo_postal)
    );
});
const pagoListo = computed(() => !!metodoElegido.value?.disponible);

const pasosCheckout = computed<PasoCompra[]>(() => {
    const hechos = [contactoListo.value, entregaLista.value, pagoListo.value];
    const pendiente = hechos.indexOf(false);
    const actual = pendiente === -1 ? 3 : pendiente;
    const base = [
        { clave: 'contacto', titulo: 'Contacto', icono: UserRound },
        { clave: 'entrega', titulo: 'Entrega', icono: Truck },
        { clave: 'pago', titulo: 'Pago', icono: CreditCard },
        { clave: 'revision', titulo: 'Revisión', icono: ClipboardCheck },
    ];

    return base.map((paso, i) => ({
        ...paso,
        estado:
            i < 3 && hechos[i]
                ? 'hecho'
                : i === actual
                  ? 'actual'
                  : 'pendiente',
    }));
});
const progresoCheckout = computed(
    () =>
        ([contactoListo.value, entregaLista.value, pagoListo.value].filter(
            Boolean,
        ).length /
            3) *
        100,
);

function irAPaso(clave: string) {
    document
        .getElementById(`paso-${clave}`)
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function textoDireccion(d: Direccion): string {
    return `${d.calle} ${d.numero_exterior}${d.numero_interior ? ' int. ' + d.numero_interior : ''}, ${d.colonia}, ${d.municipio}, ${d.estado}, C.P. ${d.codigo_postal}`;
}
</script>

<template>
    <Head title="Finalizar compra" />

    <PublicLayout>
        <section class="relative pb-28 lg:pb-0">
            <div
                class="relative mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8"
            >
                <div
                    class="mb-6 flex animate-fade-up flex-wrap items-center justify-between gap-3"
                >
                    <div>
                        <Link
                            :href="carrito.index.url()"
                            class="group inline-flex items-center gap-1 text-xs font-semibold tracking-wide text-brand-green-strong uppercase transition-colors hover:text-brand-deep"
                        >
                            <ArrowLeft
                                class="size-3.5 transition-transform group-hover:-translate-x-0.5"
                                aria-hidden="true"
                            />
                            Carrito
                        </Link>
                        <h1
                            class="mt-1 font-display text-3xl font-extrabold tracking-tight text-brand-deep sm:text-4xl"
                        >
                            Finalizar compra
                        </h1>
                    </div>
                    <p
                        class="inline-flex items-center gap-1.5 rounded-full bg-card px-3 py-1.5 text-xs font-semibold text-brand-deep ring-1 shadow-brand-sm ring-border"
                    >
                        <ShieldCheck
                            class="size-4 text-brand-green-strong"
                            aria-hidden="true"
                        />
                        Sin cobros en línea
                    </p>
                </div>

                <div
                    class="mb-6 animate-fade-up rounded-3xl border border-border bg-card px-3 py-4 shadow-brand-sm [--enter-delay:80ms] sm:px-6"
                >
                    <PasosCompra
                        :pasos="pasosCheckout"
                        :progreso="progresoCheckout"
                        etiqueta="Pasos para finalizar la compra"
                        interactivo
                        @ir="irAPaso"
                    />
                </div>

                <form
                    class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start"
                    novalidate
                    @submit.prevent="confirmar"
                >
                    <div class="space-y-5">
                        <!-- 1. Contacto -->
                        <fieldset
                            id="paso-contacto"
                            class="scroll-mt-28 rounded-3xl border border-border bg-card p-5 shadow-brand-sm transition-shadow duration-300 focus-within:ring-2 focus-within:shadow-brand focus-within:ring-brand-green/30 sm:p-6"
                        >
                            <legend class="sr-only">Datos de contacto</legend>
                            <EncabezadoPaso
                                :numero="1"
                                titulo="Datos de contacto"
                                :hecho="contactoListo"
                            />
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <CampoTexto
                                        id="nombre_cliente"
                                        v-model="form.nombre_cliente"
                                        etiqueta="Nombre completo"
                                        autocomplete="name"
                                        requerido
                                        :error="form.errors.nombre_cliente"
                                    />
                                </div>
                                <CampoTexto
                                    id="correo_cliente"
                                    v-model="form.correo_cliente"
                                    etiqueta="Correo"
                                    tipo="email"
                                    inputmode="email"
                                    autocomplete="email"
                                    requerido
                                    ayuda="Aquí te enviaremos la confirmación."
                                    :error="form.errors.correo_cliente"
                                />
                                <CampoTexto
                                    id="telefono_cliente"
                                    v-model="form.telefono_cliente"
                                    etiqueta="Teléfono / WhatsApp"
                                    tipo="tel"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    :maxlength="20"
                                    requerido
                                    ayuda="10 dígitos."
                                    :error="form.errors.telefono_cliente"
                                />
                            </div>
                        </fieldset>

                        <!-- 2. Entrega -->
                        <fieldset
                            id="paso-entrega"
                            class="scroll-mt-28 rounded-3xl border border-border bg-card p-5 shadow-brand-sm transition-shadow duration-300 focus-within:ring-2 focus-within:shadow-brand focus-within:ring-brand-green/30 sm:p-6"
                        >
                            <legend class="sr-only">Entrega</legend>
                            <EncabezadoPaso
                                :numero="2"
                                titulo="Entrega"
                                :hecho="entregaLista"
                            />

                            <div
                                v-if="recogerDisponible"
                                class="mb-5 grid gap-3 sm:grid-cols-2"
                                role="radiogroup"
                                aria-label="Modalidad de entrega"
                            >
                                <label
                                    v-for="m in modalidades_entrega.filter(
                                        (x) => x.disponible,
                                    )"
                                    :key="m.value"
                                    class="flex cursor-pointer items-center gap-3 rounded-2xl border-2 bg-card p-4 transition-all duration-200 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring motion-reduce:transition-none"
                                    :class="
                                        form.modalidad_entrega === m.value
                                            ? 'scale-[1.01] border-brand-green bg-brand-green-soft/70 ring-2 shadow-brand-sm ring-brand-green/25'
                                            : 'border-border hover:-translate-y-0.5 hover:border-brand-green/60 hover:shadow-brand-sm'
                                    "
                                >
                                    <input
                                        v-model="form.modalidad_entrega"
                                        type="radio"
                                        name="modalidad_entrega"
                                        :value="m.value"
                                        class="sr-only"
                                    />
                                    <Truck
                                        v-if="m.value === 'envio_domicilio'"
                                        class="size-5 text-brand-green-strong"
                                        aria-hidden="true"
                                    />
                                    <Store
                                        v-else
                                        class="size-5 text-brand-green-strong"
                                        aria-hidden="true"
                                    />
                                    <span class="font-semibold">{{
                                        m.label
                                    }}</span>
                                    <Check
                                        v-if="
                                            form.modalidad_entrega === m.value
                                        "
                                        class="ml-auto size-5 animate-scale-in text-brand-green-strong"
                                        aria-hidden="true"
                                    />
                                </label>
                            </div>

                            <!-- Recoger en tienda -->
                            <div
                                v-if="!esDomicilio && recoger"
                                class="flex animate-fade-in gap-3 rounded-2xl bg-brand-teal-soft/80 p-4 text-sm"
                            >
                                <MapPin
                                    class="mt-0.5 size-5 shrink-0 text-brand-teal-strong"
                                    aria-hidden="true"
                                />
                                <div>
                                    <p class="font-semibold">
                                        Recoges en nuestra tienda
                                    </p>
                                    <p v-if="recoger.direccion">
                                        {{ recoger.direccion }}
                                    </p>
                                    <p
                                        v-if="recoger.horario"
                                        class="text-muted-foreground"
                                    >
                                        Horario: {{ recoger.horario }}
                                    </p>
                                    <p class="mt-1 text-muted-foreground">
                                        Te avisaremos por correo cuando tu
                                        pedido esté listo.
                                    </p>
                                </div>
                            </div>

                            <!-- Domicilio -->
                            <div v-if="esDomicilio" class="space-y-4">
                                <div
                                    v-if="direcciones.length"
                                    class="grid gap-3"
                                    role="radiogroup"
                                    aria-label="Direcciones guardadas"
                                >
                                    <label
                                        v-for="d in direcciones"
                                        :key="d.id"
                                        class="flex cursor-pointer gap-3 rounded-2xl border-2 bg-card p-4 transition-all duration-200 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring motion-reduce:transition-none"
                                        :class="
                                            form.usar_direccion_existente &&
                                            form.direccion_id === d.id
                                                ? 'scale-[1.01] border-brand-green bg-brand-green-soft/70 ring-2 shadow-brand-sm ring-brand-green/25'
                                                : 'border-border hover:-translate-y-0.5 hover:border-brand-green/60 hover:shadow-brand-sm'
                                        "
                                    >
                                        <input
                                            type="radio"
                                            name="direccion"
                                            class="sr-only"
                                            :checked="
                                                form.usar_direccion_existente &&
                                                form.direccion_id === d.id
                                            "
                                            @change="elegirDireccion(d.id)"
                                        />
                                        <MapPin
                                            class="mt-0.5 size-5 shrink-0 text-brand-green-strong"
                                            aria-hidden="true"
                                        />
                                        <span class="min-w-0 text-sm">
                                            <span class="font-semibold">
                                                {{ d.alias || 'Dirección' }}
                                                <span
                                                    v-if="d.predeterminada"
                                                    class="ml-1 rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground"
                                                    >Principal</span
                                                >
                                            </span>
                                            <span class="block">{{
                                                d.nombre_receptor
                                            }}</span>
                                            <span
                                                class="block text-muted-foreground"
                                                >{{ textoDireccion(d) }}</span
                                            >
                                        </span>
                                        <Check
                                            v-if="
                                                form.usar_direccion_existente &&
                                                form.direccion_id === d.id
                                            "
                                            class="ml-auto size-5 shrink-0 animate-scale-in text-brand-green-strong"
                                            aria-hidden="true"
                                        />
                                    </label>
                                    <button
                                        type="button"
                                        class="flex items-center gap-2 rounded-2xl border-2 border-dashed p-4 text-left text-sm font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        :class="
                                            !form.usar_direccion_existente
                                                ? 'scale-[1.01] border-brand-green bg-brand-green-soft/70 ring-2 shadow-brand-sm ring-brand-green/25'
                                                : 'border-border hover:-translate-y-0.5 hover:border-brand-green/60 hover:shadow-brand-sm'
                                        "
                                        :aria-pressed="
                                            !form.usar_direccion_existente
                                        "
                                        @click="usarNueva"
                                    >
                                        <Plus
                                            class="size-5 text-brand-green-strong"
                                            aria-hidden="true"
                                        />
                                        Enviar a una dirección nueva
                                    </button>
                                </div>
                                <p
                                    v-if="form.errors.direccion_id"
                                    class="text-xs font-medium text-destructive"
                                    role="alert"
                                >
                                    {{ form.errors.direccion_id }}
                                </p>

                                <div
                                    v-if="!form.usar_direccion_existente"
                                    class="grid animate-fade-up gap-4 rounded-2xl sm:grid-cols-6"
                                    :class="
                                        direcciones.length
                                            ? 'border border-border bg-surface p-4'
                                            : ''
                                    "
                                >
                                    <div class="sm:col-span-3">
                                        <CampoTexto
                                            id="nombre_receptor"
                                            v-model="form.nombre_receptor"
                                            etiqueta="¿Quién recibe?"
                                            autocomplete="name"
                                            requerido
                                            :error="form.errors.nombre_receptor"
                                        />
                                    </div>
                                    <div class="sm:col-span-3">
                                        <CampoTexto
                                            id="telefono"
                                            v-model="form.telefono"
                                            etiqueta="Teléfono de quien recibe"
                                            tipo="tel"
                                            inputmode="tel"
                                            autocomplete="tel"
                                            requerido
                                            :error="form.errors.telefono"
                                        />
                                    </div>
                                    <div class="sm:col-span-6">
                                        <CampoTexto
                                            id="calle"
                                            v-model="form.calle"
                                            etiqueta="Calle"
                                            autocomplete="address-line1"
                                            requerido
                                            :error="form.errors.calle"
                                        />
                                    </div>
                                    <div class="sm:col-span-3">
                                        <CampoTexto
                                            id="numero_exterior"
                                            v-model="form.numero_exterior"
                                            etiqueta="Núm. ext."
                                            requerido
                                            :error="form.errors.numero_exterior"
                                        />
                                    </div>
                                    <div class="sm:col-span-3">
                                        <CampoTexto
                                            id="numero_interior"
                                            v-model="form.numero_interior"
                                            etiqueta="Núm. int."
                                            :error="form.errors.numero_interior"
                                        />
                                    </div>
                                    <div class="sm:col-span-3">
                                        <CampoTexto
                                            id="colonia"
                                            v-model="form.colonia"
                                            etiqueta="Colonia"
                                            requerido
                                            :error="form.errors.colonia"
                                        />
                                    </div>
                                    <div class="sm:col-span-3">
                                        <CampoTexto
                                            id="municipio"
                                            v-model="form.municipio"
                                            etiqueta="Municipio o alcaldía"
                                            autocomplete="address-level2"
                                            requerido
                                            :error="form.errors.municipio"
                                        />
                                    </div>
                                    <div class="space-y-1.5 sm:col-span-4">
                                        <Label for="estado">Estado</Label>
                                        <select
                                            id="estado"
                                            v-model="form.estado"
                                            class="h-11 w-full rounded-md border border-input bg-card px-3 text-sm shadow-xs transition focus-visible:border-brand-green focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                            autocomplete="address-level1"
                                            required
                                            :aria-invalid="!!form.errors.estado"
                                        >
                                            <option value="" disabled>
                                                Elige tu estado
                                            </option>
                                            <option
                                                v-for="e in ESTADOS_MX"
                                                :key="e"
                                                :value="e"
                                            >
                                                {{ e }}
                                            </option>
                                        </select>
                                        <p
                                            v-if="form.errors.estado"
                                            class="text-xs font-medium text-destructive"
                                            role="alert"
                                        >
                                            {{ form.errors.estado }}
                                        </p>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <CampoTexto
                                            id="codigo_postal"
                                            v-model="form.codigo_postal"
                                            etiqueta="Código postal"
                                            inputmode="numeric"
                                            autocomplete="postal-code"
                                            :maxlength="5"
                                            requerido
                                            :error="form.errors.codigo_postal"
                                        />
                                    </div>
                                    <div class="sm:col-span-6">
                                        <CampoTexto
                                            id="referencias"
                                            v-model="form.referencias"
                                            etiqueta="Referencias para encontrar tu domicilio"
                                            placeholder="Entre calles, color de fachada…"
                                            :error="form.errors.referencias"
                                        />
                                    </div>
                                    <p
                                        class="text-xs text-muted-foreground sm:col-span-6"
                                    >
                                        Guardaremos esta dirección en tu cuenta
                                        para tus próximas compras.
                                    </p>
                                </div>

                                <!-- Envío calculado en vivo -->
                                <div
                                    class="flex items-start gap-3 rounded-2xl p-4 text-sm transition-colors duration-300"
                                    :class="
                                        envioPorCotizar
                                            ? 'bg-warning/10'
                                            : 'bg-brand-green-soft/60'
                                    "
                                    aria-live="polite"
                                >
                                    <Loader2
                                        v-if="recalculando"
                                        class="mt-0.5 size-5 shrink-0 animate-spin text-brand-green-strong"
                                        aria-hidden="true"
                                    />
                                    <Clock3
                                        v-else-if="envioPorCotizar"
                                        class="mt-0.5 size-5 shrink-0 text-warning"
                                        aria-hidden="true"
                                    />
                                    <Truck
                                        v-else
                                        class="mt-0.5 size-5 shrink-0 text-brand-green-strong"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <p class="font-semibold">
                                            <template v-if="recalculando"
                                                >Calculando envío…</template
                                            >
                                            <template
                                                v-else-if="envioPorCotizar"
                                                >Envío por cotizar</template
                                            >
                                            <template v-else
                                                >Envío:
                                                {{
                                                    moneda(resumen.envio)
                                                }}</template
                                            >
                                        </p>
                                        <p
                                            v-if="!recalculando"
                                            class="text-muted-foreground"
                                        >
                                            <template v-if="envioPorCotizar">
                                                {{
                                                    envio.mensaje ||
                                                    'El costo de envío se cotiza por separado. Te enviaremos opciones de paquetería con precio y tiempo para que elijas.'
                                                }}
                                            </template>
                                            <template
                                                v-else-if="envio.dias_estimados"
                                            >
                                                Tiempo estimado:
                                                {{ envio.dias_estimados }}.
                                            </template>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <!-- 3. Pago -->
                        <fieldset
                            id="paso-pago"
                            class="scroll-mt-28 rounded-3xl border border-border bg-card p-5 shadow-brand-sm transition-shadow duration-300 focus-within:ring-2 focus-within:shadow-brand focus-within:ring-brand-green/30 sm:p-6"
                        >
                            <legend class="sr-only">Método de pago</legend>
                            <EncabezadoPaso
                                :numero="3"
                                titulo="Método de pago"
                                :hecho="pagoListo"
                            />

                            <div
                                v-if="metodos_pago.length"
                                class="grid gap-3"
                                role="radiogroup"
                                aria-label="Método de pago"
                            >
                                <label
                                    v-for="m in metodos_pago"
                                    :key="m.clave"
                                    class="group/metodo flex gap-3 rounded-2xl border-2 bg-card p-4 transition-all duration-200 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring motion-reduce:transition-none"
                                    :class="[
                                        !m.disponible
                                            ? 'cursor-not-allowed border-border opacity-60'
                                            : 'cursor-pointer',
                                        m.disponible &&
                                        form.metodo_pago === m.clave
                                            ? 'scale-[1.01] border-brand-green bg-brand-green-soft/70 ring-2 shadow-brand-sm ring-brand-green/25'
                                            : m.disponible
                                              ? 'border-border hover:-translate-y-0.5 hover:border-brand-green/60 hover:shadow-brand-sm'
                                              : '',
                                    ]"
                                >
                                    <input
                                        v-model="form.metodo_pago"
                                        type="radio"
                                        name="metodo_pago"
                                        :value="m.clave"
                                        :disabled="!m.disponible"
                                        class="sr-only"
                                    />
                                    <span
                                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-green-soft text-brand-green-strong transition-transform duration-200 group-hover/metodo:scale-110 [&_svg]:size-5"
                                    >
                                        <MetodoPagoIcono :clave="m.clave" />
                                    </span>
                                    <span class="min-w-0 text-sm">
                                        <span class="block font-semibold">{{
                                            m.nombre
                                        }}</span>
                                        <span
                                            v-if="
                                                m.disponible && m.instrucciones
                                            "
                                            class="block text-muted-foreground"
                                            >{{ m.instrucciones }}</span
                                        >
                                        <span
                                            v-else-if="m.disponible"
                                            class="block text-muted-foreground"
                                        >
                                            {{
                                                m.clave === 'transferencia'
                                                    ? 'Te damos los datos bancarios al confirmar; subes tu comprobante y lo validamos.'
                                                    : m.clave ===
                                                        'efectivo_presencial'
                                                      ? 'Pagas en efectivo en nuestra tienda.'
                                                      : 'Pagas en efectivo al recibir tu pedido.'
                                            }}
                                        </span>
                                        <span
                                            v-if="!m.disponible && m.motivo"
                                            class="block text-muted-foreground"
                                            >{{ m.motivo }}</span
                                        >
                                    </span>
                                    <Check
                                        v-if="
                                            m.disponible &&
                                            form.metodo_pago === m.clave
                                        "
                                        class="ml-auto size-5 shrink-0 animate-scale-in text-brand-green-strong"
                                        aria-hidden="true"
                                    />
                                </label>
                            </div>
                            <p
                                v-else
                                class="rounded-2xl bg-warning/10 p-4 text-sm text-warning"
                            >
                                Por ahora no hay métodos de pago disponibles.
                                Contáctanos para completar tu compra.
                            </p>
                            <p
                                v-if="form.errors.metodo_pago"
                                class="mt-2 text-xs font-medium text-destructive"
                                role="alert"
                            >
                                {{ form.errors.metodo_pago }}
                            </p>

                            <div class="mt-4">
                                <AvisoTarjetas />
                            </div>
                        </fieldset>

                        <!-- Notas -->
                        <div
                            id="paso-notas"
                            class="scroll-mt-28 rounded-3xl border border-border bg-card p-5 shadow-brand-sm transition-shadow duration-300 focus-within:ring-2 focus-within:shadow-brand focus-within:ring-brand-green/30 sm:p-6"
                        >
                            <Label
                                for="notas_cliente"
                                class="text-sm font-medium"
                                >Notas para tu pedido
                                <span class="font-normal text-muted-foreground"
                                    >(opcional)</span
                                ></Label
                            >
                            <textarea
                                id="notas_cliente"
                                v-model="form.notas_cliente"
                                rows="3"
                                maxlength="1000"
                                class="mt-1.5 w-full rounded-md border border-input bg-card px-3 py-2 text-sm shadow-xs transition focus-visible:border-brand-green focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                placeholder="Indicaciones de entrega, horario preferido…"
                            />
                        </div>
                    </div>

                    <!-- 4. Revisión -->
                    <aside
                        id="paso-revision"
                        class="animate-fade-up scroll-mt-28 space-y-4 [--enter-delay:140ms] lg:sticky lg:top-24"
                    >
                        <div
                            class="relative overflow-hidden rounded-3xl border border-border bg-card shadow-brand"
                            :aria-busy="recalculando"
                        >
                            <div
                                class="flex items-center gap-3 bg-brand-deep-pattern px-5 py-4 text-white"
                            >
                                <span
                                    class="flex size-9 items-center justify-center rounded-full bg-brand-green font-display text-sm font-bold text-brand-ink"
                                    aria-hidden="true"
                                    >4</span
                                >
                                <h2 class="font-display text-xl font-bold">
                                    Revisa tu pedido
                                </h2>
                                <Loader2
                                    v-if="recalculando"
                                    class="ml-auto size-5 animate-spin text-brand-green"
                                    aria-label="Actualizando totales"
                                />
                            </div>
                            <div
                                class="h-0.5 w-full overflow-hidden transition-opacity duration-300"
                                :class="
                                    recalculando ? 'opacity-100' : 'opacity-0'
                                "
                                aria-hidden="true"
                            >
                                <div
                                    class="barra-carga h-full w-1/3 bg-brand-gradient"
                                />
                            </div>
                            <div class="p-5 pt-4">
                                <ul
                                    class="mb-4 max-h-72 space-y-2 overflow-y-auto pr-1 [scrollbar-width:thin]"
                                >
                                    <li
                                        v-for="item in items"
                                        :key="item.producto_id"
                                        class="flex gap-3 rounded-xl p-1 transition-colors hover:bg-brand-green-soft/60"
                                    >
                                        <span
                                            class="relative size-14 shrink-0 overflow-hidden rounded-lg bg-brand-green-soft"
                                        >
                                            <img
                                                v-if="item.imagen"
                                                :src="item.imagen"
                                                :alt="item.nombre"
                                                class="size-full object-cover"
                                                loading="lazy"
                                            />
                                            <ImageOff
                                                v-else
                                                class="m-auto mt-4 size-5 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                            <span
                                                class="absolute top-0.5 right-0.5 flex min-w-5 items-center justify-center rounded-full bg-brand-deep px-1 text-[11px] font-bold text-white shadow"
                                                >{{ item.cantidad }}</span
                                            >
                                        </span>
                                        <span class="min-w-0 flex-1 text-sm">
                                            <span
                                                class="line-clamp-2 font-medium"
                                                >{{ item.nombre }}</span
                                            >
                                            <span
                                                v-if="item.error"
                                                class="block text-xs text-destructive"
                                                >{{ item.error }}</span
                                            >
                                            <span
                                                v-else-if="
                                                    item.modalidad_venta ===
                                                    'bajo_pedido'
                                                "
                                                class="block text-xs text-brand-teal-strong"
                                                >Bajo pedido{{
                                                    item.tiempo_entrega_dias
                                                        ? ` · ${item.tiempo_entrega_dias} días aprox.`
                                                        : ''
                                                }}</span
                                            >
                                        </span>
                                        <span
                                            class="text-sm font-semibold tabular-nums"
                                            >{{ moneda(item.subtotal) }}</span
                                        >
                                    </li>
                                </ul>

                                <div
                                    :class="
                                        recalculando
                                            ? 'opacity-50 transition duration-300'
                                            : 'transition duration-300'
                                    "
                                >
                                    <ResumenTotales
                                        :subtotal="resumen.subtotal"
                                        :descuento-ofertas="
                                            resumen.descuento_ofertas
                                        "
                                        :descuento-cupon="
                                            resumen.descuento_cupon
                                        "
                                        :cupon-codigo="cupon?.codigo"
                                        :envio="resumen.envio"
                                        :envio-por-cotizar="
                                            resumen.envio_por_cotizar
                                        "
                                        :recoger-en-tienda="!esDomicilio"
                                        :total="resumen.total"
                                    />
                                </div>

                                <div
                                    v-if="envioPorCotizar"
                                    class="mt-4 flex gap-2 rounded-2xl bg-warning/10 p-3 text-sm"
                                    role="note"
                                >
                                    <Info
                                        class="mt-0.5 size-4 shrink-0 text-warning"
                                        aria-hidden="true"
                                    />
                                    <p>
                                        <strong
                                            >El costo de envío se cotiza por
                                            separado.</strong
                                        >
                                        Te enviaremos opciones de paquetería con
                                        precio y tiempo para que elijas según
                                        qué tanto te urja. No realices ningún
                                        pago hasta elegir tu envío y ver el
                                        total final.
                                    </p>
                                </div>

                                <div
                                    v-if="errores.length || cupon_error"
                                    class="mt-4 flex gap-2 rounded-2xl bg-destructive/10 p-3 text-sm text-destructive"
                                    role="alert"
                                >
                                    <AlertTriangle
                                        class="mt-0.5 size-4 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <div>
                                        <p v-for="e in errores" :key="e">
                                            {{ e }}
                                        </p>
                                        <p v-if="cupon_error">
                                            {{ cupon_error }}
                                        </p>
                                        <Link
                                            :href="carrito.index.url()"
                                            class="mt-1 inline-block font-semibold underline"
                                            >Ir al carrito para corregir</Link
                                        >
                                    </div>
                                </div>

                                <Button
                                    type="submit"
                                    size="lg"
                                    class="mt-5 h-12 w-full rounded-full text-base font-bold shadow-brand-sm hover:-translate-y-0.5 hover:shadow-brand disabled:translate-y-0"
                                    :disabled="!puedeConfirmar"
                                >
                                    <Loader2
                                        v-if="form.processing"
                                        class="animate-spin"
                                        aria-hidden="true"
                                    />
                                    {{
                                        form.processing
                                            ? 'Registrando tu pedido…'
                                            : 'Confirmar pedido'
                                    }}
                                </Button>
                                <p
                                    class="mt-3 flex items-start gap-1.5 text-xs text-muted-foreground"
                                >
                                    <ShieldCheck
                                        class="size-4 shrink-0 text-brand-green-strong"
                                        aria-hidden="true"
                                    />
                                    No cobramos nada en línea. Al confirmar
                                    apartamos tus productos y te mostramos cómo
                                    pagar.
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ piezas }} pieza{{
                                        piezas === 1 ? '' : 's'
                                    }}
                                    · método:
                                    {{ metodoElegido?.nombre ?? 'sin elegir' }}
                                </p>
                            </div>
                        </div>

                        <Link
                            :href="carrito.index.url()"
                            class="group inline-flex items-center gap-1 text-sm font-semibold text-brand-green-strong transition-colors hover:text-brand-deep"
                        >
                            <ArrowLeft
                                class="size-4 transition-transform group-hover:-translate-x-1"
                                aria-hidden="true"
                            />
                            Volver al carrito
                        </Link>
                    </aside>

                    <!-- Barra fija en móvil: total + confirmar -->
                    <div
                        class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-card/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-8px_24px_-12px_rgb(20_51_43/0.35)] backdrop-blur lg:hidden"
                    >
                        <div
                            class="mx-auto mb-2 h-1 max-w-2xl overflow-hidden rounded-full bg-brand-deep/10"
                            aria-hidden="true"
                        >
                            <div
                                class="h-full rounded-full bg-brand-gradient transition-[width] duration-700 motion-reduce:transition-none"
                                :style="{
                                    width: `${Math.max(progresoCheckout, 6)}%`,
                                }"
                            />
                        </div>
                        <div class="mx-auto flex max-w-2xl items-center gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-[11px] text-muted-foreground">
                                    Total{{
                                        envioPorCotizar ? ' (sin envío)' : ''
                                    }}
                                    · {{ piezas }} pieza{{
                                        piezas === 1 ? '' : 's'
                                    }}
                                </p>
                                <p
                                    class="flex items-center gap-1.5 font-display text-xl leading-tight font-extrabold text-brand-deep tabular-nums"
                                >
                                    {{ moneda(resumen.total) }}
                                    <Loader2
                                        v-if="recalculando"
                                        class="size-4 animate-spin text-brand-green-strong"
                                        aria-hidden="true"
                                    />
                                </p>
                            </div>
                            <Button
                                type="submit"
                                size="lg"
                                class="h-11 shrink-0 rounded-full px-5 font-bold shadow-brand-sm"
                                :disabled="!puedeConfirmar"
                            >
                                <Loader2
                                    v-if="form.processing"
                                    class="animate-spin"
                                    aria-hidden="true"
                                />
                                <BadgeCheck v-else aria-hidden="true" />
                                {{
                                    form.processing ? 'Enviando…' : 'Confirmar'
                                }}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </PublicLayout>
</template>

<style scoped>
@keyframes barra-carga {
    from {
        transform: translateX(-100%);
    }
    to {
        transform: translateX(300%);
    }
}

.barra-carga {
    animation: barra-carga 1.1s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .barra-carga {
        animation: none;
        width: 100%;
    }
}
</style>
