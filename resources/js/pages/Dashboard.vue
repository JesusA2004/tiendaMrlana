<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Banknote,
    CheckCircle2,
    ClipboardList,
    Clock,
    HandCoins,
    PackageCheck,
    PackageOpen,
    PackageSearch,
    PackageX,
    Receipt,
    ScanSearch,
    Send,
    Store,
    Truck,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AnimatedNumber from '@/components/admin/AnimatedNumber.vue';
import { fechaHora } from '@/components/admin/formato';
import KpiCard from '@/components/admin/KpiCard.vue';
import {
    TONO_BARRA,
    TONO_ICONO,
    TONO_PUNTO,
    tono,
} from '@/components/admin/tonos';
import type { Opcion } from '@/components/admin/types';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inicio', href: dashboard().url }],
    },
});

type Atencion = {
    clave: string;
    titulo: string;
    detalle: string;
    href: string;
    tono: string;
    cantidad: number;
};

const props = defineProps<{
    periodo: string;
    periodos: Opcion[];
    rango: { desde: string; hasta: string };
    esAdmin: boolean;
    operacion: {
        pendientes_pago: number;
        pagos_por_revisar: number;
        por_cotizar: number;
        esperando_eleccion: number;
        por_preparar: number;
        en_preparacion: number;
        listos_envio: number;
        enviados: number;
        incidencias: number;
        entregados_periodo: number;
    };
    flujo: { clave: string; label: string; valor: number; tono: string }[];
    comercial: {
        ventas_confirmadas: number;
        pagos_confirmados: number;
        importe_creados: number;
        pedidos_creados: number;
        cancelados_periodo: number;
        saldo_contra_entrega: number;
        pedidos_contra_entrega: number;
        efectivo_por_confirmar: number;
    } | null;
    catalogo: {
        publicados: number;
        sin_existencias: number;
        pendientes_aprobacion: number;
    } | null;
    atencion: Atencion[];
}>();

const page = usePage();
const nombre = computed(
    () => (page.props.auth?.user?.name ?? '').split(' ')[0] ?? '',
);

const cargando = ref(false);

function cambiarPeriodo(valor: string) {
    if (valor === props.periodo) {
        return;
    }

    router.get(
        dashboard().url,
        { periodo: valor },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onStart: () => (cargando.value = true),
            onFinish: () => (cargando.value = false),
        },
    );
}

const etiquetaPeriodo = computed(
    () =>
        props.periodos.find((p) => p.value === props.periodo)?.label ??
        props.periodo,
);

const totalFlujo = computed(() =>
    props.flujo.reduce((suma, e) => suma + e.valor, 0),
);

const saludo = computed(() => {
    const hora = Number(
        new Intl.DateTimeFormat('es-MX', {
            hour: 'numeric',
            hour12: false,
            timeZone: 'America/Mexico_City',
        }).format(new Date()),
    );

    return hora < 12
        ? 'Buenos días'
        : hora < 19
          ? 'Buenas tardes'
          : 'Buenas noches';
});

const conversion = computed(() => {
    if (!props.comercial || props.comercial.importe_creados <= 0) {
        return null;
    }

    return Math.min(
        100,
        Math.round(
            (props.comercial.ventas_confirmadas /
                props.comercial.importe_creados) *
                100,
        ),
    );
});
</script>

<template>
    <Head title="Inicio" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <!-- Encabezado de marca -->
        <section
            class="relative overflow-hidden rounded-3xl bg-brand-deep-pattern p-5 text-white shadow-lg sm:p-7"
        >
            <div
                class="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-brand-gradient opacity-30 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-[0.16em] text-brand-green uppercase"
                    >
                        {{
                            esAdmin
                                ? 'Panel de operación'
                                : 'Panel del vendedor'
                        }}
                    </p>
                    <h1
                        class="mt-1 font-display text-3xl leading-tight font-bold sm:text-4xl"
                    >
                        {{ saludo }}{{ nombre ? `, ${nombre}` : '' }}
                    </h1>
                    <p class="mt-1 max-w-xl text-sm text-white/75">
                        <template v-if="atencion.length">
                            Tienes
                            <strong class="text-white">{{
                                atencion.reduce((s, a) => s + a.cantidad, 0)
                            }}</strong>
                            pendientes que requieren atención.
                        </template>
                        <template v-else>
                            Todo al día. No hay pendientes urgentes.
                        </template>
                    </p>
                </div>

                <div
                    class="flex w-full flex-wrap gap-1 rounded-2xl bg-white/10 p-1 backdrop-blur sm:w-auto"
                    role="radiogroup"
                    aria-label="Periodo"
                >
                    <button
                        v-for="p in periodos"
                        :key="p.value"
                        type="button"
                        role="radio"
                        :aria-checked="p.value === periodo"
                        :class="[
                            'flex-1 rounded-xl px-3 py-1.5 text-xs font-semibold transition sm:flex-none',
                            p.value === periodo
                                ? 'bg-brand-green text-brand-ink shadow'
                                : 'text-white/80 hover:bg-white/10',
                        ]"
                        @click="cambiarPeriodo(p.value)"
                    >
                        {{ p.label }}
                    </button>
                </div>
            </div>

            <!-- Ventas (sólo admin) -->
            <div
                v-if="comercial"
                :class="[
                    'relative mt-6 grid gap-4 transition-opacity sm:grid-cols-3',
                    cargando && 'opacity-60',
                ]"
            >
                <div class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/15">
                    <p class="flex items-center gap-2 text-xs text-white/75">
                        <CheckCircle2 class="size-4 text-brand-green" /> Ventas
                        confirmadas · {{ etiquetaPeriodo.toLowerCase() }}
                    </p>
                    <p class="mt-2 font-display text-3xl font-bold sm:text-4xl">
                        <AnimatedNumber
                            :value="comercial.ventas_confirmadas"
                            formato="moneda"
                        />
                    </p>
                    <p class="mt-1 text-xs text-white/70">
                        {{ comercial.pagos_confirmados }}
                        {{
                            comercial.pagos_confirmados === 1
                                ? 'pago aprobado'
                                : 'pagos aprobados'
                        }}
                        o efectivo recibido
                    </p>
                </div>
                <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                    <p class="flex items-center gap-2 text-xs text-white/75">
                        <Receipt class="size-4 text-brand-teal" /> Importe de
                        pedidos creados
                    </p>
                    <p class="mt-2 font-display text-2xl font-bold sm:text-3xl">
                        <AnimatedNumber
                            :value="comercial.importe_creados"
                            formato="moneda"
                        />
                    </p>
                    <p class="mt-1 text-xs text-white/70">
                        {{ comercial.pedidos_creados }}
                        {{
                            comercial.pedidos_creados === 1
                                ? 'pedido'
                                : 'pedidos'
                        }}
                        ·
                        {{ comercial.cancelados_periodo }}
                        {{
                            comercial.cancelados_periodo === 1
                                ? 'cancelado'
                                : 'cancelados'
                        }}. No es venta hasta confirmar el pago.
                    </p>
                </div>
                <div class="rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
                    <p class="flex items-center gap-2 text-xs text-white/75">
                        <HandCoins class="size-4 text-brand-green" /> Contra
                        entrega por cobrar
                    </p>
                    <p class="mt-2 font-display text-2xl font-bold sm:text-3xl">
                        <AnimatedNumber
                            :value="comercial.saldo_contra_entrega"
                            formato="moneda"
                        />
                    </p>
                    <p class="mt-1 text-xs text-white/70">
                        {{ comercial.pedidos_contra_entrega }}
                        {{
                            comercial.pedidos_contra_entrega === 1
                                ? 'pedido'
                                : 'pedidos'
                        }}
                        con saldo pendiente
                    </p>
                </div>
                <div
                    v-if="conversion !== null"
                    class="sm:col-span-3"
                    aria-label="Proporción cobrada del importe creado"
                >
                    <div
                        class="flex items-center justify-between text-xs text-white/70"
                    >
                        <span>Cobrado vs. importe creado en el periodo</span>
                        <span class="font-semibold text-white"
                            >{{ conversion }}%</span
                        >
                    </div>
                    <div
                        class="mt-1.5 h-2 overflow-hidden rounded-full bg-white/15"
                    >
                        <div
                            class="h-full rounded-full bg-brand-gradient transition-[width] duration-1000 ease-out"
                            :style="{ width: `${conversion}%` }"
                        />
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="flex min-w-0 flex-col gap-6">
                <!-- KPIs operativos -->
                <section aria-labelledby="kpi-operacion">
                    <h2
                        id="kpi-operacion"
                        class="mb-3 font-display text-lg font-semibold"
                    >
                        Operación ahora
                    </h2>
                    <div
                        class="grid stagger-children grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-4"
                    >
                        <KpiCard
                            v-if="esAdmin"
                            label="Pagos por revisar"
                            :value="operacion.pagos_por_revisar"
                            :icon="ScanSearch"
                            color="info"
                            hint="Comprobantes de transferencia"
                            href="/admin/pagos?tab=revision"
                        />
                        <KpiCard
                            v-if="esAdmin && comercial"
                            label="Efectivo por confirmar"
                            :value="comercial.efectivo_por_confirmar"
                            :icon="Banknote"
                            color="warning"
                            hint="Pago en tienda"
                            href="/admin/pagos?tab=efectivo"
                        />
                        <KpiCard
                            label="Pendientes de pago"
                            :value="operacion.pendientes_pago"
                            :icon="Clock"
                            color="neutral"
                            hint="Esperando al cliente"
                            href="/admin/pedidos?estatus=pendiente_pago"
                        />
                        <KpiCard
                            label="Envíos por cotizar"
                            :value="operacion.por_cotizar"
                            :icon="Wallet"
                            color="warning"
                            hint="El cliente no puede pagar aún"
                            href="/admin/envios?tab=por_cotizar"
                        />
                        <KpiCard
                            label="Esperando elección"
                            :value="operacion.esperando_eleccion"
                            :icon="Truck"
                            color="info"
                            hint="El cliente elige paquetería"
                            href="/admin/envios?tab=esperando_eleccion"
                        />
                        <KpiCard
                            label="Por preparar"
                            :value="operacion.por_preparar"
                            :icon="PackageOpen"
                            color="success"
                            hint="Pago confirmado"
                            href="/admin/envios?tab=por_preparar"
                        />
                        <KpiCard
                            label="En preparación"
                            :value="operacion.en_preparacion"
                            :icon="ClipboardList"
                            color="info"
                            href="/admin/envios?tab=en_preparacion"
                        />
                        <KpiCard
                            label="Listos para envío"
                            :value="operacion.listos_envio"
                            :icon="PackageCheck"
                            color="info"
                            hint="Falta guía o recoger"
                            href="/admin/envios?tab=listos"
                        />
                        <KpiCard
                            label="Enviados sin entregar"
                            :value="operacion.enviados"
                            :icon="Truck"
                            color="brand"
                            href="/admin/envios?tab=enviados"
                        />
                        <KpiCard
                            :label="`Entregados · ${etiquetaPeriodo.toLowerCase()}`"
                            :value="operacion.entregados_periodo"
                            :icon="Send"
                            color="success"
                            destacado
                        />
                    </div>
                </section>

                <!-- Flujo de pedidos activos -->
                <section
                    class="rounded-2xl border bg-card p-4 sm:p-5"
                    aria-labelledby="flujo-titulo"
                >
                    <div class="flex items-baseline justify-between gap-3">
                        <h2
                            id="flujo-titulo"
                            class="font-display text-lg font-semibold"
                        >
                            Flujo de pedidos activos
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            <AnimatedNumber :value="totalFlujo" /> en curso
                        </p>
                    </div>
                    <div
                        class="mt-4 flex h-3 overflow-hidden rounded-full bg-muted"
                        role="img"
                        :aria-label="
                            flujo
                                .map((e) => `${e.label}: ${e.valor}`)
                                .join(', ')
                        "
                    >
                        <div
                            v-for="e in flujo.filter((x) => x.valor > 0)"
                            :key="e.clave"
                            :class="[
                                'h-full transition-[width] duration-700 ease-out first:rounded-l-full last:rounded-r-full',
                                TONO_BARRA[tono(e.tono)],
                            ]"
                            :style="{
                                width: `${(e.valor / totalFlujo) * 100}%`,
                            }"
                        />
                    </div>
                    <ol class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <li v-for="(e, i) in flujo" :key="e.clave">
                            <Link
                                :href="`/admin/pedidos?estatus=${e.clave}`"
                                class="flex h-full flex-col gap-1 rounded-xl border p-2.5 transition hover:border-brand-teal/60 hover:bg-surface"
                            >
                                <span
                                    class="flex items-center gap-1.5 text-[0.7rem] text-muted-foreground"
                                >
                                    <span
                                        :class="[
                                            'size-2 rounded-full',
                                            TONO_PUNTO[tono(e.tono)],
                                        ]"
                                    />
                                    {{ i + 1 }}.
                                </span>
                                <span
                                    class="text-xs leading-tight font-medium"
                                    >{{ e.label }}</span
                                >
                                <span
                                    class="font-display text-xl font-bold tabular-nums"
                                    >{{ e.valor }}</span
                                >
                            </Link>
                        </li>
                    </ol>
                </section>

                <!-- Catálogo -->
                <section v-if="catalogo" aria-labelledby="kpi-catalogo">
                    <h2
                        id="kpi-catalogo"
                        class="mb-3 font-display text-lg font-semibold"
                    >
                        Catálogo
                    </h2>
                    <div
                        class="grid stagger-children grid-cols-1 gap-3 sm:grid-cols-3"
                    >
                        <KpiCard
                            label="Productos publicados"
                            :value="catalogo.publicados"
                            :icon="Store"
                            color="success"
                            hint="Visibles en la tienda"
                            href="/admin/productos"
                        />
                        <KpiCard
                            label="Publicados sin existencias"
                            :value="catalogo.sin_existencias"
                            :icon="PackageX"
                            :color="
                                catalogo.sin_existencias > 0
                                    ? 'danger'
                                    : 'neutral'
                            "
                            hint="Se muestran como agotados"
                            href="/admin/inventario"
                        />
                        <KpiCard
                            label="Pendientes de aprobación"
                            :value="catalogo.pendientes_aprobacion"
                            :icon="PackageSearch"
                            :color="
                                catalogo.pendientes_aprobacion > 0
                                    ? 'warning'
                                    : 'neutral'
                            "
                            hint="Revisa precio, imágenes y stock"
                            href="/admin/productos?estado=pendiente_aprobacion"
                        />
                    </div>
                </section>
            </div>

            <!-- Requiere atención -->
            <aside
                class="flex flex-col rounded-2xl border bg-card xl:sticky xl:top-20 xl:max-h-[calc(100vh-6rem)] xl:self-start"
                aria-labelledby="atencion-titulo"
            >
                <div class="flex items-center justify-between border-b p-4">
                    <h2
                        id="atencion-titulo"
                        class="flex items-center gap-2 font-display text-lg font-semibold"
                    >
                        <AlertTriangle class="size-5 text-warning" />
                        Requiere atención
                    </h2>
                    <span
                        class="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold tabular-nums"
                        >{{ atencion.length }}</span
                    >
                </div>
                <ul
                    v-if="atencion.length"
                    class="flex-1 divide-y overflow-y-auto"
                >
                    <li v-for="a in atencion" :key="a.clave">
                        <Link
                            :href="a.href"
                            class="group flex items-start gap-3 p-4 transition hover:bg-surface"
                        >
                            <span
                                :class="[
                                    'mt-0.5 grid size-8 shrink-0 place-items-center rounded-lg text-sm font-bold tabular-nums',
                                    TONO_ICONO[tono(a.tono)],
                                ]"
                                >{{
                                    a.cantidad > 99 ? '99+' : a.cantidad
                                }}</span
                            >
                            <span class="min-w-0 flex-1">
                                <span
                                    class="block text-sm leading-snug font-semibold"
                                    >{{ a.titulo }}</span
                                >
                                <span
                                    class="mt-0.5 block text-xs text-muted-foreground"
                                    >{{ a.detalle }}</span
                                >
                            </span>
                            <ArrowRight
                                class="mt-1 size-4 shrink-0 text-muted-foreground transition group-hover:translate-x-0.5 group-hover:text-foreground"
                            />
                        </Link>
                    </li>
                </ul>
                <div
                    v-else
                    class="flex flex-1 flex-col items-center justify-center gap-2 p-8 text-center"
                >
                    <span
                        class="grid size-12 place-items-center rounded-2xl bg-brand-green-soft text-brand-green-strong"
                    >
                        <CheckCircle2 class="size-6" />
                    </span>
                    <p class="font-display font-semibold">Sin pendientes</p>
                    <p class="text-xs text-muted-foreground">
                        Te avisaremos aquí cuando algo necesite tu revisión.
                    </p>
                </div>
                <p
                    class="border-t px-4 py-2 text-[0.7rem] text-muted-foreground"
                >
                    Actualizado {{ fechaHora(rango.hasta) }}
                </p>
            </aside>
        </div>
    </div>
</template>
