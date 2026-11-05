<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    ArrowRight,
    BadgeCheck,
    Clock3,
    ImageOff,
    Loader2,
    LockKeyhole,
    ShieldCheck,
    ShoppingBag,
    ShoppingCart,
    Tag,
    Trash2,
    Truck,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { moneda } from '@/components/compra/formato';
import PasosCompra from '@/components/compra/PasosCompra.vue';
import type { PasoCompra } from '@/components/compra/PasosCompra.vue';
import ResumenTotales from '@/components/compra/ResumenTotales.vue';
import SelectorCantidad from '@/components/compra/SelectorCantidad.vue';
import type {
    CotizacionEnvio,
    CuponAplicado,
    LineaCarrito,
    ResumenCompra,
} from '@/components/compra/types';
import AvisoTarjetas from '@/components/public/AvisoTarjetas.vue';
import { urls } from '@/components/public/tienda';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PublicLayout from '@/layouts/PublicLayout.vue';
import carrito from '@/routes/carrito';
import checkout from '@/routes/checkout';

const props = defineProps<{
    items: LineaCarrito[];
    cupon: CuponAplicado | null;
    cupon_error: string | null;
    resumen: ResumenCompra;
    envio: CotizacionEnvio;
    errores: string[];
    puede_comprar: boolean;
    autenticado: boolean;
}>();

const actualizando = ref<number | null>(null);
const confirmarVaciar = ref(false);
const vaciando = ref(false);

const cuponForm = useForm({ codigo: '' });

const piezas = computed(() =>
    props.items.reduce((total, item) => total + item.cantidad, 0),
);
const hayErrores = computed(
    () => props.errores.length > 0 || props.cupon_error !== null,
);
const totalEstimado = computed(
    () => props.resumen.subtotal - props.resumen.descuento_cupon,
);

const pasos: PasoCompra[] = [
    {
        clave: 'carrito',
        titulo: 'Carrito',
        icono: ShoppingCart,
        estado: 'actual',
    },
    {
        clave: 'datos',
        titulo: 'Datos y pago',
        icono: Truck,
        estado: 'pendiente',
    },
    {
        clave: 'confirmacion',
        titulo: 'Confirmación',
        icono: BadgeCheck,
        estado: 'pendiente',
    },
];

function cambiarCantidad(item: LineaCarrito, cantidad: number) {
    actualizando.value = item.producto_id;
    router.patch(
        carrito.actualizar.url(item.producto_id),
        { cantidad },
        {
            preserveScroll: true,
            onFinish: () => (actualizando.value = null),
        },
    );
}

function eliminar(item: LineaCarrito) {
    actualizando.value = item.producto_id;
    router.delete(carrito.eliminar.url(item.producto_id), {
        preserveScroll: true,
        onFinish: () => (actualizando.value = null),
    });
}

function vaciar() {
    router.delete(carrito.vaciar.url(), {
        preserveScroll: true,
        onStart: () => (vaciando.value = true),
        onFinish: () => {
            vaciando.value = false;
            confirmarVaciar.value = false;
        },
    });
}

function aplicarCupon() {
    cuponForm.codigo = cuponForm.codigo.trim().toUpperCase();
    cuponForm.post(carrito.cupon.aplicar.url(), {
        preserveScroll: true,
        onSuccess: () => cuponForm.reset(),
    });
}

function quitarCupon() {
    router.delete(carrito.cupon.quitar.url(), { preserveScroll: true });
}

function etiquetaDisponibilidad(item: LineaCarrito): string | null {
    if (item.modalidad_venta === 'bajo_pedido') {
        return item.tiempo_entrega_dias
            ? `Bajo pedido · entrega aprox. ${item.tiempo_entrega_dias} días`
            : 'Bajo pedido';
    }

    if (item.stock > 0 && item.stock <= 5) {
        return `Quedan ${item.stock} pieza${item.stock === 1 ? '' : 's'}`;
    }

    return null;
}
</script>

<template>
    <Head title="Tu carrito" />

    <PublicLayout>
        <section class="relative" :class="items.length ? 'pb-28 lg:pb-0' : ''">
            <div
                class="relative mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8"
            >
                <div
                    class="mx-auto mb-8 max-w-md motion-safe:animate-in motion-safe:duration-500 motion-safe:fade-in"
                >
                    <PasosCompra :pasos="pasos" :progreso="8" />
                </div>

                <header
                    class="mb-6 flex flex-wrap items-end justify-between gap-3 motion-safe:animate-in motion-safe:duration-500 motion-safe:fade-in motion-safe:slide-in-from-bottom-2"
                >
                    <div>
                        <h1
                            class="flex items-center gap-3 font-display text-3xl font-extrabold tracking-tight text-brand-deep sm:text-4xl"
                        >
                            Tu carrito
                            <span
                                v-if="items.length"
                                class="rounded-full bg-brand-green px-3 py-0.5 font-sans text-sm font-bold text-brand-ink tabular-nums"
                                >{{ piezas }}</span
                            >
                        </h1>
                        <p
                            v-if="items.length"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            {{ piezas }} pieza{{ piezas === 1 ? '' : 's' }} ·
                            los precios se confirman al finalizar tu compra.
                        </p>
                    </div>
                    <div v-if="items.length" class="flex items-center gap-2">
                        <Transition
                            mode="out-in"
                            enter-active-class="transition duration-200 motion-reduce:transition-none"
                            enter-from-class="opacity-0 translate-x-2"
                            leave-active-class="transition duration-150 motion-reduce:transition-none"
                            leave-to-class="opacity-0"
                        >
                            <div
                                v-if="confirmarVaciar"
                                class="flex items-center gap-2 rounded-full bg-card py-1 pr-1 pl-4 shadow-sm ring-1 ring-destructive/25"
                            >
                                <span class="text-sm font-medium"
                                    >¿Vaciar el carrito?</span
                                >
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    class="rounded-full"
                                    :disabled="vaciando"
                                    @click="vaciar"
                                >
                                    <Loader2
                                        v-if="vaciando"
                                        class="animate-spin"
                                        aria-hidden="true"
                                    />
                                    Sí, vaciar
                                </Button>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    class="rounded-full"
                                    @click="confirmarVaciar = false"
                                >
                                    Cancelar
                                </Button>
                            </div>
                            <Button
                                v-else
                                size="sm"
                                variant="ghost"
                                class="rounded-full text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive"
                                @click="confirmarVaciar = true"
                            >
                                <Trash2 aria-hidden="true" />
                                Vaciar carrito
                            </Button>
                        </Transition>
                    </div>
                </header>

                <!-- Estado vacío -->
                <div
                    v-if="!items.length"
                    class="relative flex flex-col items-center overflow-hidden rounded-3xl border border-brand-green/25 bg-card px-6 py-16 text-center shadow-sm motion-safe:animate-in motion-safe:duration-500 motion-safe:zoom-in-95 motion-safe:fade-in"
                >
                    <div
                        class="pointer-events-none absolute inset-0 bg-brand-pattern-light opacity-60"
                        aria-hidden="true"
                    />
                    <span
                        class="relative mb-5 flex size-20 items-center justify-center rounded-full bg-brand-gradient text-brand-ink shadow-lg motion-safe:animate-float"
                    >
                        <ShoppingBag class="size-9" aria-hidden="true" />
                    </span>
                    <h2
                        class="relative font-display text-2xl font-bold text-brand-deep"
                    >
                        Tu carrito está vacío
                    </h2>
                    <p
                        class="relative mt-2 max-w-sm text-sm text-muted-foreground"
                    >
                        Explora el catálogo y agrega lo que te guste. Aquí verás
                        tus productos antes de pagar.
                    </p>
                    <Button
                        as-child
                        size="lg"
                        class="group relative mt-6 rounded-full font-bold shadow-md transition hover:-translate-y-0.5 hover:shadow-lg"
                    >
                        <Link :href="urls.catalogo">
                            Ver productos
                            <ArrowRight
                                class="transition-transform group-hover:translate-x-1"
                                aria-hidden="true"
                            />
                        </Link>
                    </Button>
                </div>

                <div
                    v-else
                    class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_370px] lg:items-start"
                >
                    <!-- Líneas -->
                    <div class="min-w-0 space-y-3">
                        <Transition
                            enter-active-class="transition duration-300 motion-reduce:transition-none"
                            enter-from-class="opacity-0 -translate-y-2"
                            leave-active-class="transition duration-200 motion-reduce:transition-none"
                            leave-to-class="opacity-0"
                        >
                            <div
                                v-if="errores.length"
                                class="flex gap-3 rounded-2xl border border-destructive/30 bg-card p-4 text-sm shadow-xs"
                                role="alert"
                            >
                                <span
                                    class="flex size-9 shrink-0 items-center justify-center rounded-full bg-destructive/10"
                                >
                                    <AlertTriangle
                                        class="size-5 text-destructive"
                                        aria-hidden="true"
                                    />
                                </span>
                                <div>
                                    <p class="font-semibold text-destructive">
                                        Revisa tu carrito antes de continuar
                                    </p>
                                    <ul
                                        class="mt-1 list-disc space-y-0.5 pl-4 text-foreground"
                                    >
                                        <li v-for="e in errores" :key="e">
                                            {{ e }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </Transition>

                        <TransitionGroup
                            tag="div"
                            class="relative space-y-3"
                            enter-active-class="transition duration-400 ease-out motion-reduce:transition-none"
                            enter-from-class="opacity-0 translate-y-3"
                            leave-active-class="absolute inset-x-0 transition duration-300 ease-in motion-reduce:transition-none"
                            leave-to-class="opacity-0 -translate-x-8 scale-95"
                            move-class="transition-transform duration-300 motion-reduce:transition-none"
                        >
                            <article
                                v-for="item in items"
                                :key="item.producto_id"
                                class="group/linea relative flex hover-lift gap-3 overflow-hidden rounded-2xl border bg-card p-3 shadow-brand-sm sm:gap-4 sm:p-4"
                                :class="[
                                    item.error
                                        ? 'border-destructive/40'
                                        : 'border-border',
                                    actualizando === item.producto_id
                                        ? 'pointer-events-none'
                                        : '',
                                ]"
                                :aria-busy="actualizando === item.producto_id"
                            >
                                <span
                                    class="absolute inset-y-0 left-0 w-1 origin-top scale-y-0 bg-brand-gradient transition-transform duration-300 group-hover/linea:scale-y-100 motion-reduce:transition-none"
                                    aria-hidden="true"
                                />
                                <Link
                                    :href="urls.producto(item.slug)"
                                    class="size-20 shrink-0 overflow-hidden rounded-xl bg-brand-green-soft ring-1 ring-brand-deep/5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:size-28"
                                >
                                    <img
                                        v-if="item.imagen"
                                        :src="item.imagen"
                                        :alt="item.nombre"
                                        class="size-full object-cover transition-transform duration-500 group-hover/linea:scale-105 motion-reduce:transition-none"
                                        loading="lazy"
                                        decoding="async"
                                    />
                                    <span
                                        v-else
                                        class="flex size-full items-center justify-center text-brand-green-strong/60"
                                    >
                                        <ImageOff
                                            class="size-6"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </Link>

                                <div
                                    class="flex min-w-0 flex-1 flex-col gap-2 transition-opacity"
                                    :class="
                                        actualizando === item.producto_id
                                            ? 'opacity-50'
                                            : ''
                                    "
                                >
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <div class="min-w-0">
                                            <Link
                                                :href="urls.producto(item.slug)"
                                                class="line-clamp-2 text-sm font-semibold transition-colors hover:text-brand-green-strong sm:text-base"
                                            >
                                                {{ item.nombre }}
                                            </Link>
                                            <p
                                                v-if="item.sku"
                                                class="text-xs text-muted-foreground"
                                            >
                                                SKU {{ item.sku }}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="-mt-1 -mr-1 flex size-9 shrink-0 items-center justify-center rounded-full text-muted-foreground transition duration-200 hover:rotate-90 hover:bg-destructive/10 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:scale-90 motion-reduce:hover:rotate-0"
                                            :aria-label="`Quitar ${item.nombre} del carrito`"
                                            :disabled="
                                                actualizando ===
                                                item.producto_id
                                            "
                                            @click="eliminar(item)"
                                        >
                                            <X
                                                class="size-4"
                                                aria-hidden="true"
                                            />
                                        </button>
                                    </div>

                                    <div
                                        class="flex flex-wrap items-baseline gap-x-2 gap-y-1"
                                    >
                                        <span class="font-bold tabular-nums">{{
                                            moneda(item.precio)
                                        }}</span>
                                        <span
                                            v-if="item.tiene_oferta"
                                            class="text-xs text-muted-foreground tabular-nums line-through"
                                            >{{
                                                moneda(item.precio_original)
                                            }}</span
                                        >
                                        <span
                                            v-if="item.tiene_oferta"
                                            class="inline-flex items-center rounded-full bg-brand-green-soft px-2 py-0.5 text-[11px] font-semibold text-brand-green-strong"
                                        >
                                            <Tag
                                                class="mr-0.5 size-3"
                                                aria-hidden="true"
                                            />{{
                                                item.oferta?.nombre || 'Oferta'
                                            }}
                                        </span>
                                    </div>

                                    <p
                                        v-if="etiquetaDisponibilidad(item)"
                                        class="inline-flex w-fit items-center gap-1 rounded-full bg-brand-teal-soft px-2 py-0.5 text-xs font-medium text-brand-teal-strong"
                                    >
                                        <Clock3
                                            v-if="
                                                item.modalidad_venta ===
                                                'bajo_pedido'
                                            "
                                            class="size-3.5"
                                            aria-hidden="true"
                                        />
                                        {{ etiquetaDisponibilidad(item) }}
                                    </p>

                                    <p
                                        v-if="item.error"
                                        class="flex items-start gap-1.5 rounded-lg bg-destructive/10 px-2.5 py-1.5 text-xs font-medium text-destructive"
                                        role="alert"
                                    >
                                        <AlertTriangle
                                            class="mt-px size-3.5 shrink-0"
                                            aria-hidden="true"
                                        />
                                        {{ item.error }}
                                    </p>

                                    <div
                                        class="mt-auto flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <SelectorCantidad
                                            v-if="item.cantidad_maxima > 0"
                                            :model-value="item.cantidad"
                                            :max="
                                                Math.max(
                                                    item.cantidad_maxima,
                                                    1,
                                                )
                                            "
                                            :disabled="
                                                actualizando ===
                                                item.producto_id
                                            "
                                            :etiqueta="`Cantidad de ${item.nombre}`"
                                            @update:model-value="
                                                (v) => cambiarCantidad(item, v)
                                            "
                                        />
                                        <Button
                                            v-else
                                            size="sm"
                                            variant="outline"
                                            class="rounded-full"
                                            @click="eliminar(item)"
                                        >
                                            Quitar producto agotado
                                        </Button>
                                        <p class="text-right">
                                            <span
                                                class="block text-[11px] text-muted-foreground"
                                                >Importe</span
                                            >
                                            <Transition
                                                mode="out-in"
                                                enter-active-class="transition duration-300 motion-reduce:transition-none"
                                                enter-from-class="opacity-0 translate-y-1"
                                                leave-active-class="transition duration-150 motion-reduce:transition-none"
                                                leave-to-class="opacity-0 -translate-y-1"
                                            >
                                                <span
                                                    :key="item.subtotal"
                                                    class="block font-display text-lg font-bold text-brand-deep tabular-nums"
                                                    >{{
                                                        moneda(item.subtotal)
                                                    }}</span
                                                >
                                            </Transition>
                                        </p>
                                    </div>
                                </div>
                                <span
                                    v-if="actualizando === item.producto_id"
                                    class="absolute inset-0 flex items-center justify-center bg-card/40 backdrop-blur-[1px]"
                                >
                                    <Loader2
                                        class="size-7 animate-spin text-brand-green-strong"
                                        aria-label="Actualizando"
                                    />
                                </span>
                            </article>
                        </TransitionGroup>

                        <Link
                            :href="urls.catalogo"
                            class="group inline-flex items-center gap-1.5 rounded-full px-2 pt-2 text-sm font-semibold text-brand-green-strong transition-colors hover:text-brand-deep"
                        >
                            <ArrowLeft
                                class="size-4 transition-transform group-hover:-translate-x-1"
                                aria-hidden="true"
                            />
                            Seguir comprando
                        </Link>
                    </div>

                    <!-- Resumen -->
                    <aside
                        class="space-y-4 motion-safe:animate-in motion-safe:duration-500 motion-safe:fade-in motion-safe:slide-in-from-right-4 lg:sticky lg:top-24"
                    >
                        <div
                            class="overflow-hidden rounded-3xl border border-border bg-card shadow-brand"
                        >
                            <div
                                class="flex items-center gap-2 bg-brand-deep-pattern px-5 py-4 text-white"
                            >
                                <ShoppingBag
                                    class="size-5 text-brand-green"
                                    aria-hidden="true"
                                />
                                <h2 class="font-display text-xl font-bold">
                                    Resumen
                                </h2>
                            </div>

                            <div class="p-5">
                                <!-- Cupón -->
                                <div class="mb-5">
                                    <Transition
                                        mode="out-in"
                                        enter-active-class="transition duration-300 motion-reduce:transition-none"
                                        enter-from-class="opacity-0 scale-95"
                                        leave-active-class="transition duration-150 motion-reduce:transition-none"
                                        leave-to-class="opacity-0 scale-95"
                                    >
                                        <div
                                            v-if="cupon"
                                            class="flex items-center justify-between gap-2 rounded-xl border border-dashed border-brand-green bg-brand-green-soft px-3 py-2"
                                        >
                                            <p class="text-sm">
                                                <Tag
                                                    class="mr-1 inline size-4 text-brand-green-strong"
                                                    aria-hidden="true"
                                                />
                                                <span
                                                    class="font-mono font-bold"
                                                    >{{ cupon.codigo }}</span
                                                >
                                                <span
                                                    class="text-muted-foreground"
                                                >
                                                    · ahorras
                                                    {{
                                                        moneda(
                                                            cupon.descuento_aplicado,
                                                        )
                                                    }}</span
                                                >
                                            </p>
                                            <button
                                                type="button"
                                                class="rounded-full px-2 py-1 text-xs font-semibold text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                @click="quitarCupon"
                                            >
                                                Quitar
                                            </button>
                                        </div>
                                        <div v-else>
                                            <form
                                                class="flex gap-2"
                                                novalidate
                                                @submit.prevent="aplicarCupon"
                                            >
                                                <label
                                                    for="cupon"
                                                    class="sr-only"
                                                    >Código de cupón</label
                                                >
                                                <Input
                                                    id="cupon"
                                                    v-model="cuponForm.codigo"
                                                    placeholder="Código de cupón"
                                                    autocomplete="off"
                                                    class="h-10 rounded-full bg-card uppercase transition focus-visible:border-brand-green"
                                                    :aria-invalid="
                                                        !!cuponForm.errors
                                                            .codigo
                                                    "
                                                    aria-describedby="cupon-error"
                                                />
                                                <Button
                                                    type="submit"
                                                    variant="outline"
                                                    class="h-10 rounded-full transition hover:border-brand-green hover:bg-brand-green-soft"
                                                    :disabled="
                                                        cuponForm.processing ||
                                                        !cuponForm.codigo.trim()
                                                    "
                                                >
                                                    <Loader2
                                                        v-if="
                                                            cuponForm.processing
                                                        "
                                                        class="animate-spin"
                                                        aria-hidden="true"
                                                    />
                                                    Aplicar
                                                </Button>
                                            </form>
                                            <p
                                                v-if="cuponForm.errors.codigo"
                                                id="cupon-error"
                                                class="mt-1.5 text-xs font-medium text-destructive"
                                                role="alert"
                                            >
                                                {{ cuponForm.errors.codigo }}
                                            </p>
                                        </div>
                                    </Transition>
                                    <p
                                        v-if="cupon_error"
                                        class="mt-2 rounded-lg bg-destructive/10 px-3 py-2 text-xs font-medium text-destructive"
                                        role="alert"
                                    >
                                        {{ cupon_error }} Quita el cupón para
                                        continuar.
                                    </p>
                                </div>

                                <ResumenTotales
                                    :subtotal="resumen.subtotal"
                                    :descuento-ofertas="
                                        resumen.descuento_ofertas
                                    "
                                    :descuento-cupon="resumen.descuento_cupon"
                                    :cupon-codigo="cupon?.codigo"
                                    :envio="resumen.envio"
                                    :envio-por-cotizar="true"
                                    texto-envio-pendiente="Se calcula en el checkout"
                                    :total="totalEstimado"
                                    etiqueta-total="Total estimado"
                                />

                                <Button
                                    v-if="autenticado"
                                    as-child
                                    size="lg"
                                    class="group mt-5 h-12 w-full rounded-full text-base font-bold shadow-md transition hover:-translate-y-0.5 hover:shadow-lg"
                                    :class="
                                        !puede_comprar
                                            ? 'pointer-events-none opacity-50'
                                            : ''
                                    "
                                >
                                    <Link
                                        :href="checkout.index.url()"
                                        :aria-disabled="!puede_comprar"
                                        :tabindex="puede_comprar ? 0 : -1"
                                    >
                                        Continuar con la compra
                                        <ArrowRight
                                            class="transition-transform group-hover:translate-x-1"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </Button>
                                <template v-else>
                                    <Button
                                        as-child
                                        size="lg"
                                        class="mt-5 h-12 w-full rounded-full text-base font-bold shadow-md transition hover:-translate-y-0.5 hover:shadow-lg"
                                    >
                                        <Link :href="urls.login">
                                            <LockKeyhole aria-hidden="true" />
                                            Inicia sesión para comprar
                                        </Link>
                                    </Button>
                                    <p
                                        class="mt-2 text-center text-xs text-muted-foreground"
                                    >
                                        ¿Primera vez?
                                        <Link
                                            :href="urls.registro"
                                            class="font-semibold text-brand-green-strong hover:underline"
                                            >Crea tu cuenta</Link
                                        >
                                        — tu carrito se conserva.
                                    </p>
                                </template>
                                <p
                                    v-if="hayErrores"
                                    class="mt-2 text-center text-xs text-destructive"
                                >
                                    Corrige los avisos marcados para continuar.
                                </p>

                                <p
                                    class="mt-4 flex items-center justify-center gap-1.5 rounded-xl bg-brand-green-soft px-3 py-2 text-xs font-medium text-brand-deep"
                                >
                                    <ShieldCheck
                                        class="size-4 text-brand-green-strong"
                                        aria-hidden="true"
                                    />
                                    Pagas por transferencia o en efectivo
                                </p>
                            </div>
                        </div>

                        <AvisoTarjetas />
                    </aside>
                </div>
            </div>

            <!-- Barra fija en móvil -->
            <div
                v-if="items.length"
                class="fixed inset-x-0 bottom-0 z-40 border-t border-brand-deep/10 bg-card/95 px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] shadow-[0_-8px_24px_-12px_rgb(20_51_43/0.35)] backdrop-blur motion-safe:animate-in motion-safe:duration-500 motion-safe:slide-in-from-bottom-full lg:hidden"
            >
                <div class="mx-auto flex max-w-2xl items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] text-muted-foreground">
                            Total estimado · {{ piezas }} pieza{{
                                piezas === 1 ? '' : 's'
                            }}
                        </p>
                        <p
                            class="font-display text-xl leading-tight font-extrabold text-brand-deep tabular-nums"
                        >
                            {{ moneda(totalEstimado) }}
                        </p>
                    </div>
                    <Button
                        as-child
                        size="lg"
                        class="h-11 shrink-0 rounded-full px-5 font-bold shadow-md"
                        :class="
                            autenticado && !puede_comprar
                                ? 'pointer-events-none opacity-50'
                                : ''
                        "
                    >
                        <Link
                            :href="
                                autenticado ? checkout.index.url() : urls.login
                            "
                            :aria-disabled="autenticado && !puede_comprar"
                            :tabindex="autenticado && !puede_comprar ? -1 : 0"
                        >
                            {{ autenticado ? 'Continuar' : 'Iniciar sesión' }}
                            <ArrowRight aria-hidden="true" />
                        </Link>
                    </Button>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
