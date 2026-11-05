<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { MapPin, Package, UserRound } from 'lucide-vue-next';

/**
 * Navegación de "Mi cuenta" en las páginas del cliente (sobre PublicLayout).
 */
defineProps<{
    activo: 'pedidos' | 'direcciones';
    titulo: string;
    descripcion?: string;
}>();

const enlaces = [
    {
        clave: 'pedidos',
        texto: 'Mis pedidos',
        href: '/mi-cuenta/pedidos',
        icono: Package,
    },
    {
        clave: 'direcciones',
        texto: 'Direcciones',
        href: '/mi-cuenta/direcciones',
        icono: MapPin,
    },
    {
        clave: 'perfil',
        texto: 'Mi perfil',
        href: '/settings/profile',
        icono: UserRound,
    },
] as const;
</script>

<template>
    <section class="relative overflow-hidden bg-brand-deep-pattern text-white">
        <div
            class="pointer-events-none absolute -top-24 -right-20 size-72 rounded-full bg-brand-green/25 blur-3xl"
            aria-hidden="true"
        />
        <div
            class="relative mx-auto w-full max-w-5xl animate-fade-up px-4 pt-8 pb-0 sm:px-6 lg:px-8"
        >
            <p
                class="text-xs font-bold tracking-[0.2em] text-brand-green uppercase"
            >
                Mi cuenta
            </p>
            <h1
                class="mt-1 font-display text-3xl font-extrabold tracking-tight sm:text-4xl"
            >
                {{ titulo }}
            </h1>
            <p v-if="descripcion" class="mt-1 text-sm text-white/75">
                {{ descripcion }}
            </p>
            <nav
                class="-mx-4 mt-6 flex gap-1 overflow-x-auto px-4 sm:mx-0 sm:px-0"
                aria-label="Secciones de mi cuenta"
            >
                <Link
                    v-for="e in enlaces"
                    :key="e.clave"
                    :href="e.href"
                    class="group flex shrink-0 items-center gap-2 rounded-t-2xl px-4 py-3 text-sm font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-brand-green focus-visible:outline-none"
                    :class="
                        activo === e.clave
                            ? 'bg-background text-brand-deep shadow-[0_-6px_16px_-8px_rgb(0_0_0/0.3)]'
                            : 'text-white/80 hover:-translate-y-0.5 hover:bg-white/10 hover:text-white'
                    "
                    :aria-current="activo === e.clave ? 'page' : undefined"
                >
                    <component
                        :is="e.icono"
                        class="size-4 transition-transform group-hover:scale-110"
                        aria-hidden="true"
                    />
                    {{ e.texto }}
                </Link>
            </nav>
        </div>
    </section>
</template>
