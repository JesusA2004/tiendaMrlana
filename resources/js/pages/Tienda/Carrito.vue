<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    Clock3,
    ImageOff,
    Loader2,
    LockKeyhole,
    ShieldCheck,
    ShoppingBag,
    Tag,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { moneda } from '@/components/compra/formato';
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
        <section class="bg-surface/60">
            <div
                class="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-10 lg:px-8"
            >
                <nav
                    class="mb-6 flex items-center gap-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                    aria-label="Pasos de compra"
                >
                    <span class="text-brand-green-strong">1. Carrito</span>
                    <span aria-hidden="true">›</span>
                    <span>2. Datos y pago</span>
                    <span aria-hidden="true">›</span>
                    <span>3. Confirmación</span>
                </nav>

                <header
                    class="mb-6 flex flex-wrap items-end justify-between gap-3"
                >
                    <div>
                        <h1
                            class="font-display text-3xl font-extrabold tracking-tight sm:text-4xl"
                        >
                            Tu carrito
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
                        <template v-if="confirmarVaciar">
                            <span class="text-sm">¿Vaciar el carrito?</span>
                            <Button
                                size="sm"
                                variant="destructive"
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
                                @click="confirmarVaciar = false"
                            >
                                Cancelar
                            </Button>
                        </template>
                        <Button
                            v-else
                            size="sm"
                            variant="ghost"
                            class="text-muted-foreground"
                            @click="confirmarVaciar = true"
                        >
                            <Trash2 aria-hidden="true" />
                            Vaciar carrito
                        </Button>
                    </div>
                </header>

                <!-- Estado vacío -->
                <div
                    v-if="!items.length"
                    class="flex flex-col items-center rounded-3xl border border-dashed border-border bg-background px-6 py-16 text-center"
                >
                    <span
                        class="mb-4 flex size-16 items-center justify-center rounded-full bg-brand-green-soft text-brand-green-strong"
                    >
                        <ShoppingBag class="size-8" aria-hidden="true" />
                    </span>
                    <h2 class="font-display text-2xl font-bold">
                        Tu carrito está vacío
                    </h2>
                    <p class="mt-2 max-w-sm text-sm text-muted-foreground">
                        Explora el catálogo y agrega lo que te guste. Aquí verás
                        tus productos antes de pagar.
                    </p>
                    <Button as-child size="lg" class="mt-6 rounded-full">
                        <Link :href="urls.catalogo">
                            Ver productos
                            <ArrowRight aria-hidden="true" />
                        </Link>
                    </Button>
                </div>

                <div
                    v-else
                    class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px] lg:items-start"
                >
                    <!-- Líneas -->
                    <div class="space-y-3">
                        <div
                            v-if="errores.length"
                            class="flex gap-3 rounded-2xl border border-destructive/30 bg-destructive/5 p-4 text-sm"
                            role="alert"
                        >
                            <AlertTriangle
                                class="mt-0.5 size-5 shrink-0 text-destructive"
                                aria-hidden="true"
                            />
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

                        <article
                            v-for="item in items"
                            :key="item.producto_id"
                            class="relative flex gap-3 rounded-2xl border bg-background p-3 shadow-xs transition sm:gap-4 sm:p-4"
                            :class="[
                                item.error
                                    ? 'border-destructive/40'
                                    : 'border-border',
                                actualizando === item.producto_id
                                    ? 'opacity-60'
                                    : '',
                            ]"
                            :aria-busy="actualizando === item.producto_id"
                        >
                            <Link
                                :href="urls.producto(item.slug)"
                                class="size-20 shrink-0 overflow-hidden rounded-xl bg-surface sm:size-28"
                            >
                                <img
                                    v-if="item.imagen"
                                    :src="item.imagen"
                                    :alt="item.nombre"
                                    class="size-full object-cover"
                                    loading="lazy"
                                    decoding="async"
                                />
                                <span
                                    v-else
                                    class="flex size-full items-center justify-center text-muted-foreground"
                                >
                                    <ImageOff
                                        class="size-6"
                                        aria-hidden="true"
                                    />
                                </span>
                            </Link>

                            <div class="flex min-w-0 flex-1 flex-col gap-2">
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <div class="min-w-0">
                                        <Link
                                            :href="urls.producto(item.slug)"
                                            class="line-clamp-2 text-sm font-semibold hover:text-brand-green-strong sm:text-base"
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
                                        class="-mt-1 -mr-1 flex size-9 shrink-0 items-center justify-center rounded-full text-muted-foreground transition hover:bg-destructive/10 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        :aria-label="`Quitar ${item.nombre} del carrito`"
                                        :disabled="
                                            actualizando === item.producto_id
                                        "
                                        @click="eliminar(item)"
                                    >
                                        <X class="size-4" aria-hidden="true" />
                                    </button>
                                </div>

                                <div
                                    class="flex flex-wrap items-baseline gap-x-2"
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
                                        class="rounded-full bg-brand-green-soft px-2 py-0.5 text-[11px] font-semibold text-brand-green-strong"
                                    >
                                        <Tag
                                            class="mr-0.5 inline size-3"
                                            aria-hidden="true"
                                        />{{ item.oferta?.nombre || 'Oferta' }}
                                    </span>
                                </div>

                                <p
                                    v-if="etiquetaDisponibilidad(item)"
                                    class="flex items-center gap-1 text-xs font-medium text-brand-teal-strong"
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
                                        :max="Math.max(item.cantidad_maxima, 1)"
                                        :disabled="
                                            actualizando === item.producto_id
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
                                        <span
                                            class="font-display text-lg font-bold tabular-nums"
                                            >{{ moneda(item.subtotal) }}</span
                                        >
                                    </p>
                                </div>
                            </div>
                            <Loader2
                                v-if="actualizando === item.producto_id"
                                class="absolute top-1/2 left-1/2 size-6 -translate-1/2 animate-spin text-brand-green-strong"
                                aria-label="Actualizando"
                            />
                        </article>

                        <Link
                            :href="urls.catalogo"
                            class="inline-flex items-center gap-1 pt-2 text-sm font-semibold text-brand-green-strong hover:underline"
                        >
                            ← Seguir comprando
                        </Link>
                    </div>

                    <!-- Resumen -->
                    <aside class="space-y-4 lg:sticky lg:top-24">
                        <div
                            class="rounded-3xl border border-border bg-background p-5 shadow-sm"
                        >
                            <h2 class="mb-4 font-display text-xl font-bold">
                                Resumen
                            </h2>

                            <!-- Cupón -->
                            <div class="mb-5">
                                <div
                                    v-if="cupon"
                                    class="flex items-center justify-between gap-2 rounded-xl border border-brand-green/40 bg-brand-green-soft px-3 py-2"
                                >
                                    <p class="text-sm">
                                        <Tag
                                            class="mr-1 inline size-4 text-brand-green-strong"
                                            aria-hidden="true"
                                        />
                                        <span class="font-mono font-bold">{{
                                            cupon.codigo
                                        }}</span>
                                        <span class="text-muted-foreground">
                                            · ahorras
                                            {{
                                                moneda(cupon.descuento_aplicado)
                                            }}</span
                                        >
                                    </p>
                                    <button
                                        type="button"
                                        class="text-xs font-semibold text-muted-foreground underline-offset-2 hover:text-destructive hover:underline"
                                        @click="quitarCupon"
                                    >
                                        Quitar
                                    </button>
                                </div>
                                <template v-else>
                                    <form
                                        class="flex gap-2"
                                        novalidate
                                        @submit.prevent="aplicarCupon"
                                    >
                                        <label for="cupon" class="sr-only"
                                            >Código de cupón</label
                                        >
                                        <Input
                                            id="cupon"
                                            v-model="cuponForm.codigo"
                                            placeholder="Código de cupón"
                                            autocomplete="off"
                                            class="h-10 rounded-full uppercase"
                                            :aria-invalid="
                                                !!cuponForm.errors.codigo
                                            "
                                            aria-describedby="cupon-error"
                                        />
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            class="h-10 rounded-full"
                                            :disabled="
                                                cuponForm.processing ||
                                                !cuponForm.codigo.trim()
                                            "
                                        >
                                            <Loader2
                                                v-if="cuponForm.processing"
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
                                </template>
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
                                :descuento-ofertas="resumen.descuento_ofertas"
                                :descuento-cupon="resumen.descuento_cupon"
                                :cupon-codigo="cupon?.codigo"
                                :envio="resumen.envio"
                                :envio-por-cotizar="true"
                                texto-envio-pendiente="Se calcula en el checkout"
                                :total="
                                    resumen.subtotal - resumen.descuento_cupon
                                "
                                etiqueta-total="Total estimado"
                            />

                            <Button
                                v-if="autenticado"
                                as-child
                                size="lg"
                                class="mt-5 h-12 w-full rounded-full text-base font-bold"
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
                                    <ArrowRight aria-hidden="true" />
                                </Link>
                            </Button>
                            <template v-else>
                                <Button
                                    as-child
                                    size="lg"
                                    class="mt-5 h-12 w-full rounded-full text-base font-bold"
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
                                class="mt-4 flex items-center justify-center gap-1.5 text-xs text-muted-foreground"
                            >
                                <ShieldCheck
                                    class="size-4 text-brand-green-strong"
                                    aria-hidden="true"
                                />
                                Pagas por transferencia o en efectivo
                            </p>
                        </div>

                        <AvisoTarjetas />
                    </aside>
                </div>
            </div>
        </section>
    </PublicLayout>
</template>
