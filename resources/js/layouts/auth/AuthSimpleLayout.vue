<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, PackageSearch, Truck, Wallet } from 'lucide-vue-next';
import logo from '@/img/logo.png';
import mascota from '@/img/mascota-mr-lana.webp';

defineProps<{
    title?: string;
    description?: string;
}>();

const beneficios = [
    { icono: PackageSearch, texto: 'Sigue tus pedidos en línea' },
    { icono: Wallet, texto: 'Paga por transferencia o en efectivo' },
    { icono: Truck, texto: 'Envío cotizado con varias paqueterías' },
] as const;
</script>

<template>
    <div
        class="min-h-svh bg-brand-app lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]"
    >
        <!-- Panel de marca (escritorio) -->
        <aside
            class="relative isolate hidden overflow-hidden bg-brand-deep-pattern text-white lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16"
        >
            <div
                class="pointer-events-none absolute -top-40 -left-32 size-[420px] rounded-full bg-brand-green/25 blur-3xl"
                aria-hidden="true"
            />
            <div
                class="pointer-events-none absolute -right-24 -bottom-48 size-[520px] rounded-full bg-brand-teal/25 blur-3xl"
                aria-hidden="true"
            />

            <Link
                href="/"
                class="relative w-fit rounded-lg focus-visible:ring-[3px] focus-visible:ring-brand-green focus-visible:outline-none"
                aria-label="Mr Lana, ir a la tienda"
            >
                <img
                    :src="logo"
                    alt="Mr Lana"
                    width="508"
                    height="192"
                    class="h-12 w-auto"
                />
            </Link>

            <div class="relative max-w-md">
                <p
                    class="font-display text-4xl leading-[1.05] font-extrabold tracking-tight text-balance xl:text-5xl"
                >
                    Muebles y artículos para tu
                    <span class="text-brand-gradient">hogar</span>
                </p>
                <ul class="mt-8 stagger-children space-y-4">
                    <li
                        v-for="beneficio in beneficios"
                        :key="beneficio.texto"
                        class="flex items-center gap-3 text-base text-white/85"
                    >
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-brand-green ring-1 ring-white/15"
                        >
                            <component
                                :is="beneficio.icono"
                                class="size-5"
                                aria-hidden="true"
                            />
                        </span>
                        {{ beneficio.texto }}
                    </li>
                </ul>
            </div>

            <div class="relative flex items-end justify-between gap-6">
                <p class="max-w-[14rem] text-sm text-white/60">
                    Tu cuenta Mr Lana para comprar y dar seguimiento a tus
                    pedidos.
                </p>
                <div class="relative w-48 shrink-0 xl:w-56">
                    <div
                        class="absolute inset-x-[12%] bottom-[4%] h-[16%] rounded-[50%] bg-black/35 blur-2xl"
                        aria-hidden="true"
                    />
                    <img
                        :src="mascota"
                        alt=""
                        width="710"
                        height="848"
                        class="relative h-auto w-full animate-float drop-shadow-[0_20px_30px_rgba(0,0,0,0.35)]"
                    />
                </div>
            </div>
        </aside>

        <!-- Columna del formulario -->
        <div class="flex min-h-svh min-w-0 flex-col">
            <!-- Encabezado compacto (móvil) -->
            <header
                class="relative isolate overflow-hidden bg-brand-deep-pattern px-4 pt-6 pb-14 text-white sm:px-6 lg:hidden"
            >
                <div
                    class="pointer-events-none absolute -top-24 -right-16 size-64 rounded-full bg-brand-teal/25 blur-3xl"
                    aria-hidden="true"
                />
                <div
                    class="pointer-events-none absolute -bottom-24 -left-16 size-64 rounded-full bg-brand-green/25 blur-3xl"
                    aria-hidden="true"
                />
                <div
                    class="relative mx-auto flex w-full max-w-md items-center justify-between gap-3"
                >
                    <Link
                        href="/"
                        class="rounded-lg focus-visible:ring-[3px] focus-visible:ring-brand-green focus-visible:outline-none"
                        aria-label="Mr Lana, ir a la tienda"
                    >
                        <img
                            :src="logo"
                            alt="Mr Lana"
                            width="508"
                            height="192"
                            class="h-9 w-auto"
                        />
                    </Link>
                    <img
                        :src="mascota"
                        alt=""
                        width="710"
                        height="848"
                        class="h-14 w-auto drop-shadow-[0_8px_12px_rgba(0,0,0,0.35)]"
                    />
                </div>
            </header>

            <main
                class="flex flex-1 flex-col items-center px-4 pb-10 sm:px-6 lg:justify-center lg:py-12"
            >
                <div
                    class="relative z-10 -mt-8 w-full max-w-md animate-scale-in lg:mt-0"
                >
                    <div
                        class="relative overflow-hidden rounded-3xl border border-border/70 bg-card p-6 text-card-foreground shadow-[0_24px_60px_-30px_rgba(20,51,43,0.45)] transition-shadow duration-300 hover:shadow-[0_30px_70px_-30px_rgba(20,51,43,0.55)] sm:p-8"
                    >
                        <span
                            class="absolute inset-x-0 top-0 h-1.5 bg-brand-gradient"
                            aria-hidden="true"
                        />
                        <div class="mb-6 space-y-2 text-center sm:mb-8">
                            <h1
                                class="font-display text-2xl leading-tight font-bold tracking-tight text-balance sm:text-3xl"
                            >
                                {{ title }}
                            </h1>
                            <p
                                v-if="description"
                                class="text-sm text-pretty text-muted-foreground"
                            >
                                {{ description }}
                            </p>
                        </div>

                        <slot />
                    </div>

                    <div class="mt-6 text-center">
                        <Link
                            href="/"
                            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-card/70 hover:text-brand-green-strong focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <ArrowLeft class="size-4" aria-hidden="true" />
                            Volver a la tienda
                        </Link>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
