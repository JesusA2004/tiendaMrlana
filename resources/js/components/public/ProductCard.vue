<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Clock3, ImageOff, Loader2, ShoppingBag } from 'lucide-vue-next';
import { computed } from 'vue';
import { moneda, porcentajeDescuento, urls } from './tienda';
import type { ProductoTarjeta } from './types';
import { useAgregarAlCarrito } from './useAgregarAlCarrito';

const props = withDefaults(
    defineProps<{
        producto: ProductoTarjeta;
        prioridad?: boolean;
    }>(),
    { prioridad: false },
);

const { enviando, agregar } = useAgregarAlCarrito();

const href = computed(() => urls.producto(props.producto.slug));
const descuento = computed(() =>
    porcentajeDescuento(
        props.producto.precio_original,
        props.producto.precio_final,
    ),
);
const agotado = computed(() => props.producto.disponibilidad === 'agotado');
const ultimas = computed(
    () =>
        props.producto.disponibilidad === 'en_stock' &&
        props.producto.stock !== null &&
        props.producto.stock <= 3,
);
const cargando = computed(() => enviando.value === props.producto.id);
</script>

<template>
    <article
        class="group/card relative flex h-full flex-col overflow-hidden rounded-2xl border border-border/70 bg-card transition-[box-shadow,transform,border-color] duration-300 ease-out hover:-translate-y-1 hover:border-brand-green/40 hover:shadow-[0_18px_40px_-18px_rgba(20,51,43,0.35)] motion-reduce:transform-none motion-reduce:transition-none"
    >
        <div class="relative aspect-[4/5] overflow-hidden bg-surface">
            <img
                v-if="producto.imagen"
                :src="producto.imagen"
                :alt="producto.nombre"
                :loading="prioridad ? 'eager' : 'lazy'"
                :fetchpriority="prioridad ? 'high' : 'auto'"
                decoding="async"
                class="size-full object-cover transition-transform duration-700 ease-out group-hover/card:scale-[1.06] motion-reduce:transition-none motion-reduce:group-hover/card:scale-100"
                :class="{ 'opacity-60 grayscale-[35%]': agotado }"
            />
            <div
                v-else
                class="flex size-full flex-col items-center justify-center gap-2 bg-brand-pattern-light text-muted-foreground"
            >
                <ImageOff class="size-8" aria-hidden="true" />
                <span class="text-xs">Imagen no disponible</span>
            </div>

            <!-- Insignias -->
            <div
                class="pointer-events-none absolute top-3 left-3 flex flex-col items-start gap-1.5"
            >
                <span
                    v-if="producto.tiene_oferta && descuento > 0"
                    class="rounded-full bg-brand-ink px-2.5 py-1 text-[11px] font-bold tracking-wide text-white shadow-sm dark:bg-white dark:text-brand-ink"
                >
                    −{{ descuento }}%
                </span>
                <span
                    v-if="producto.nuevo && !producto.tiene_oferta"
                    class="rounded-full bg-white/95 px-2.5 py-1 text-[11px] font-semibold text-brand-deep shadow-sm"
                >
                    Nuevo
                </span>
            </div>

            <span
                v-if="agotado"
                class="absolute inset-x-3 bottom-3 rounded-full bg-white/95 py-1.5 text-center text-xs font-semibold text-foreground shadow-sm dark:bg-black/80"
            >
                Agotado
            </span>
            <span
                v-else-if="producto.disponibilidad === 'bajo_pedido'"
                class="absolute bottom-3 left-3 inline-flex items-center gap-1 rounded-full bg-brand-teal-soft/95 px-2.5 py-1 text-[11px] font-semibold text-brand-teal-strong shadow-sm backdrop-blur"
            >
                <Clock3 class="size-3" aria-hidden="true" />
                Bajo pedido
            </span>
        </div>

        <div class="flex flex-1 flex-col gap-1.5 p-3.5 sm:p-4">
            <p
                v-if="producto.categoria || producto.marca"
                class="truncate text-[11px] font-semibold tracking-[0.08em] text-muted-foreground uppercase"
            >
                {{ producto.marca?.nombre ?? producto.categoria?.nombre }}
            </p>

            <h3
                class="line-clamp-2 font-sans text-sm leading-snug font-semibold text-foreground sm:text-[15px]"
            >
                <Link
                    :href="href"
                    class="after:absolute after:inset-0 after:content-[''] focus-visible:outline-none"
                    prefetch="hover"
                >
                    {{ producto.nombre }}
                </Link>
            </h3>

            <p
                v-if="
                    producto.disponibilidad === 'bajo_pedido' &&
                    producto.tiempo_entrega_dias
                "
                class="text-xs text-muted-foreground"
            >
                Entrega estimada en {{ producto.tiempo_entrega_dias }} días
            </p>
            <p v-else-if="ultimas" class="text-xs font-medium text-warning">
                {{
                    producto.stock === 1
                        ? 'Última pieza'
                        : `Últimas ${producto.stock} piezas`
                }}
            </p>

            <div
                class="mt-auto flex flex-wrap items-end justify-between gap-2 pt-2"
            >
                <div class="shrink-0">
                    <p
                        class="font-display text-base leading-none font-bold whitespace-nowrap text-foreground min-[380px]:text-lg sm:text-xl"
                    >
                        {{ moneda(producto.precio_final) }}
                    </p>
                    <p
                        v-if="producto.tiene_oferta && producto.precio_original"
                        class="mt-1 text-xs text-muted-foreground line-through"
                    >
                        {{ moneda(producto.precio_original) }}
                    </p>
                </div>

                <button
                    type="button"
                    class="relative z-10 ml-auto inline-flex size-10 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-sm transition-[transform,background-color,opacity] duration-200 hover:scale-105 hover:bg-brand-teal focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none active:scale-95 disabled:cursor-not-allowed disabled:bg-muted disabled:text-muted-foreground disabled:hover:scale-100"
                    :disabled="agotado || cargando"
                    :aria-label="
                        agotado
                            ? `${producto.nombre} está agotado`
                            : `Agregar ${producto.nombre} al carrito`
                    "
                    @click="agregar(producto.id, 1)"
                >
                    <Loader2
                        v-if="cargando"
                        class="size-4 animate-spin"
                        aria-hidden="true"
                    />
                    <ShoppingBag
                        v-else
                        class="size-[18px]"
                        aria-hidden="true"
                    />
                </button>
            </div>
        </div>
    </article>
</template>
