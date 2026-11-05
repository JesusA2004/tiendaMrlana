<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';
import type { EnlacePaginacion } from './types';

const props = defineProps<{
    links: EnlacePaginacion[];
    from: number | null;
    to: number | null;
    total: number;
    etiqueta?: string;
}>();

const paginas = computed(() => props.links.slice(1, -1));
const anterior = computed(() => props.links[0]);
const siguiente = computed(() => props.links[props.links.length - 1]);
</script>

<template>
    <nav
        v-if="total > 0"
        class="flex flex-col items-center justify-between gap-3 sm:flex-row"
        aria-label="Paginación"
    >
        <p class="text-sm text-muted-foreground">
            Mostrando
            <span class="font-semibold text-foreground"
                >{{ from }}–{{ to }}</span
            >
            de
            <span class="font-semibold text-foreground">{{ total }}</span>
            {{ etiqueta ?? 'registros' }}
        </p>

        <div v-if="links.length > 3" class="flex items-center gap-1">
            <component
                :is="anterior?.url ? Link : 'span'"
                :href="anterior?.url ?? undefined"
                preserve-scroll
                class="inline-flex size-9 items-center justify-center rounded-md border text-sm transition-all duration-200 hover:-translate-y-0.5"
                :class="
                    anterior?.url
                        ? 'hover:border-brand-green hover:bg-brand-green-soft hover:text-brand-green-strong'
                        : 'pointer-events-none opacity-40'
                "
                aria-label="Página anterior"
            >
                <ChevronLeft class="size-4" />
            </component>

            <template v-for="(link, i) in paginas" :key="i">
                <span
                    v-if="!link.url"
                    class="hidden px-2 text-sm text-muted-foreground sm:inline"
                    >…</span
                >
                <Link
                    v-else
                    :href="link.url"
                    preserve-scroll
                    class="hidden size-9 items-center justify-center rounded-md border text-sm font-medium transition-all duration-200 hover:-translate-y-0.5 sm:inline-flex"
                    :class="
                        link.active
                            ? 'border-brand-deep bg-brand-deep text-white'
                            : 'hover:border-brand-green hover:bg-brand-green-soft hover:text-brand-green-strong'
                    "
                    :aria-current="link.active ? 'page' : undefined"
                >
                    {{ link.label }}
                </Link>
            </template>

            <component
                :is="siguiente?.url ? Link : 'span'"
                :href="siguiente?.url ?? undefined"
                preserve-scroll
                class="inline-flex size-9 items-center justify-center rounded-md border text-sm transition-all duration-200 hover:-translate-y-0.5"
                :class="
                    siguiente?.url
                        ? 'hover:border-brand-green hover:bg-brand-green-soft hover:text-brand-green-strong'
                        : 'pointer-events-none opacity-40'
                "
                aria-label="Página siguiente"
            >
                <ChevronRight class="size-4" />
            </component>
        </div>
    </nav>
</template>
