<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Check,
    Clock3,
    ImageOff,
    Info,
    Loader2,
    MapPin,
    Plus,
    ShieldCheck,
    Store,
    Truck,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import CampoTexto from '@/components/compra/CampoTexto.vue';
import { ESTADOS_MX } from '@/components/compra/estados';
import { moneda } from '@/components/compra/formato';
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

function textoDireccion(d: Direccion): string {
    return `${d.calle} ${d.numero_exterior}${d.numero_interior ? ' int. ' + d.numero_interior : ''}, ${d.colonia}, ${d.municipio}, ${d.estado}, C.P. ${d.codigo_postal}`;
}
</script>

<template>
    <Head title="Finalizar compra" />

    <PublicLayout>
        <section class="bg-surface/60">
            <div
                class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8"
            >
                <nav
                    class="mb-6 flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    aria-label="Pasos de compra"
                >
                    <Link
                        :href="carrito.index.url()"
                        class="hover:text-foreground"
                        >1. Carrito</Link
                    >
                    <span aria-hidden="true">›</span>
                    <span class="text-brand-green-strong">2. Datos y pago</span>
                    <span aria-hidden="true">›</span>
                    <span>3. Confirmación</span>
                </nav>

                <h1
                    class="mb-6 font-display text-3xl font-extrabold tracking-tight sm:text-4xl"
                >
                    Finalizar compra
                </h1>

                <form
                    class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px] lg:items-start"
                    novalidate
                    @submit.prevent="confirmar"
                >
                    <div class="space-y-5">
                        <!-- 1. Contacto -->
                        <fieldset
                            class="rounded-3xl border border-border bg-background p-5 shadow-xs sm:p-6"
                        >
                            <legend class="sr-only">Datos de contacto</legend>
                            <h2
                                class="mb-4 flex items-center gap-3 font-display text-xl font-bold"
                                aria-hidden="true"
                            >
                                <span
                                    class="flex size-8 items-center justify-center rounded-full bg-brand-green text-sm text-brand-ink"
                                    >1</span
                                >
                                Datos de contacto
                            </h2>
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
                            class="rounded-3xl border border-border bg-background p-5 shadow-xs sm:p-6"
                        >
                            <legend class="sr-only">Entrega</legend>
                            <h2
                                class="mb-4 flex items-center gap-3 font-display text-xl font-bold"
                                aria-hidden="true"
                            >
                                <span
                                    class="flex size-8 items-center justify-center rounded-full bg-brand-green text-sm text-brand-ink"
                                    >2</span
                                >
                                Entrega
                            </h2>

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
                                    class="flex cursor-pointer items-center gap-3 rounded-2xl border-2 p-4 transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                                    :class="
                                        form.modalidad_entrega === m.value
                                            ? 'border-brand-green bg-brand-green-soft/60'
                                            : 'border-border hover:border-brand-green/50'
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
                                        class="ml-auto size-5 text-brand-green-strong"
                                        aria-hidden="true"
                                    />
                                </label>
                            </div>

                            <!-- Recoger en tienda -->
                            <div
                                v-if="!esDomicilio && recoger"
                                class="flex gap-3 rounded-2xl bg-brand-teal-soft/60 p-4 text-sm"
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
                                        class="flex cursor-pointer gap-3 rounded-2xl border-2 p-4 transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                                        :class="
                                            form.usar_direccion_existente &&
                                            form.direccion_id === d.id
                                                ? 'border-brand-green bg-brand-green-soft/60'
                                                : 'border-border hover:border-brand-green/50'
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
                                            class="ml-auto size-5 shrink-0 text-brand-green-strong"
                                            aria-hidden="true"
                                        />
                                    </label>
                                    <button
                                        type="button"
                                        class="flex items-center gap-2 rounded-2xl border-2 border-dashed p-4 text-left text-sm font-semibold transition"
                                        :class="
                                            !form.usar_direccion_existente
                                                ? 'border-brand-green bg-brand-green-soft/60'
                                                : 'border-border hover:border-brand-green/50'
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
                                    class="grid gap-4 rounded-2xl sm:grid-cols-6"
                                    :class="
                                        direcciones.length
                                            ? 'border border-border p-4'
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
                                            class="h-11 w-full rounded-md border border-input bg-background px-3 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
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
                                    class="flex items-start gap-3 rounded-2xl p-4 text-sm"
                                    :class="
                                        envioPorCotizar
                                            ? 'bg-warning/10'
                                            : 'bg-brand-green-soft/60'
                                    "
                                    aria-live="polite"
                                >
                                    <Loader2
                                        v-if="recalculando"
                                        class="mt-0.5 size-5 shrink-0 animate-spin text-muted-foreground"
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
                            class="rounded-3xl border border-border bg-background p-5 shadow-xs sm:p-6"
                        >
                            <legend class="sr-only">Método de pago</legend>
                            <h2
                                class="mb-4 flex items-center gap-3 font-display text-xl font-bold"
                                aria-hidden="true"
                            >
                                <span
                                    class="flex size-8 items-center justify-center rounded-full bg-brand-green text-sm text-brand-ink"
                                    >3</span
                                >
                                Método de pago
                            </h2>

                            <div
                                v-if="metodos_pago.length"
                                class="grid gap-3"
                                role="radiogroup"
                                aria-label="Método de pago"
                            >
                                <label
                                    v-for="m in metodos_pago"
                                    :key="m.clave"
                                    class="flex gap-3 rounded-2xl border-2 p-4 transition has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring"
                                    :class="[
                                        !m.disponible
                                            ? 'cursor-not-allowed border-border opacity-60'
                                            : 'cursor-pointer',
                                        m.disponible &&
                                        form.metodo_pago === m.clave
                                            ? 'border-brand-green bg-brand-green-soft/60'
                                            : m.disponible
                                              ? 'border-border hover:border-brand-green/50'
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
                                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-surface text-brand-green-strong [&_svg]:size-5"
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
                                        class="ml-auto size-5 shrink-0 text-brand-green-strong"
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
                            class="rounded-3xl border border-border bg-background p-5 shadow-xs sm:p-6"
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
                                class="mt-1.5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                placeholder="Indicaciones de entrega, horario preferido…"
                            />
                        </div>
                    </div>

                    <!-- 4. Revisión -->
                    <aside class="space-y-4 lg:sticky lg:top-24">
                        <div
                            class="rounded-3xl border border-border bg-background p-5 shadow-sm"
                            :aria-busy="recalculando"
                        >
                            <h2
                                class="mb-4 flex items-center gap-3 font-display text-xl font-bold"
                            >
                                <span
                                    class="flex size-8 items-center justify-center rounded-full bg-brand-green text-sm text-brand-ink"
                                    aria-hidden="true"
                                    >4</span
                                >
                                Revisa tu pedido
                            </h2>

                            <ul
                                class="mb-4 max-h-72 space-y-3 overflow-y-auto pr-1"
                            >
                                <li
                                    v-for="item in items"
                                    :key="item.producto_id"
                                    class="flex gap-3"
                                >
                                    <span
                                        class="relative size-14 shrink-0 overflow-hidden rounded-lg bg-surface"
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
                                            class="absolute top-0.5 right-0.5 flex min-w-5 items-center justify-center rounded-full bg-brand-ink px-1 text-[11px] font-bold text-white"
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
                                        ? 'opacity-50 transition-opacity'
                                        : 'transition-opacity'
                                "
                            >
                                <ResumenTotales
                                    :subtotal="resumen.subtotal"
                                    :descuento-ofertas="
                                        resumen.descuento_ofertas
                                    "
                                    :descuento-cupon="resumen.descuento_cupon"
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
                                    precio y tiempo para que elijas según qué
                                    tanto te urja. No realices ningún pago hasta
                                    elegir tu envío y ver el total final.
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
                                    <p v-for="e in errores" :key="e">{{ e }}</p>
                                    <p v-if="cupon_error">{{ cupon_error }}</p>
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
                                class="mt-5 h-12 w-full rounded-full text-base font-bold"
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
                                {{ piezas }} pieza{{ piezas === 1 ? '' : 's' }}
                                · método:
                                {{ metodoElegido?.nombre ?? 'sin elegir' }}
                            </p>
                        </div>

                        <Link
                            :href="carrito.index.url()"
                            class="inline-flex items-center gap-1 text-sm font-semibold text-brand-green-strong hover:underline"
                        >
                            <ArrowLeft class="size-4" aria-hidden="true" />
                            Volver al carrito
                        </Link>
                    </aside>
                </form>
            </div>
        </section>
    </PublicLayout>
</template>
