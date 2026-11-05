<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';
import type { Paginado } from './types';

/** Paginación de servidor (LengthAwarePaginator) conservando filtros (withQueryString). */
const props = defineProps<{
    pagina: Paginado<unknown>;
}>();

const numeros = computed(() => props.pagina.links.slice(1, -1));
const anterior = computed(() => props.pagina.links[0]?.url ?? null);
const siguiente = computed(
    () => props.pagina.links[props.pagina.links.length - 1]?.url ?? null,
);
</script>

<template>
    <nav
        v-if="pagina.last_page > 1 || pagina.total > 0"
        class="flex flex-col items-center justify-between gap-3 sm:flex-row"
        aria-label="Paginación"
    >
        <p class="text-sm text-muted-foreground">
            <template v-if="pagina.total > 0">
                {{ pagina.from }}–{{ pagina.to }} de
                <span class="font-semibold text-foreground">{{
                    pagina.total
                }}</span>
            </template>
        </p>
        <div v-if="pagina.last_page > 1" class="flex items-center gap-1">
            <component
                :is="anterior ? Link : 'span'"
                :href="anterior ?? undefined"
                preserve-scroll
                class="grid size-9 place-items-center rounded-lg border text-sm transition hover:bg-accent aria-disabled:pointer-events-none aria-disabled:opacity-40"
                :aria-disabled="!anterior"
                aria-label="Página anterior"
            >
                <ChevronLeft class="size-4" />
            </component>
            <template v-for="(l, i) in numeros" :key="i">
                <span
                    v-if="!l.url"
                    class="hidden px-2 text-sm text-muted-foreground sm:inline"
                    >…</span
                >
                <Link
                    v-else
                    :href="l.url"
                    preserve-scroll
                    :class="[
                        'hidden h-9 min-w-9 place-items-center rounded-lg border px-2 text-sm transition sm:grid',
                        l.active
                            ? 'border-transparent bg-primary font-semibold text-primary-foreground'
                            : 'hover:bg-accent',
                    ]"
                    :aria-current="l.active ? 'page' : undefined"
                    >{{ l.label }}</Link
                >
            </template>
            <span class="px-2 text-sm text-muted-foreground sm:hidden"
                >{{ pagina.current_page }} / {{ pagina.last_page }}</span
            >
            <component
                :is="siguiente ? Link : 'span'"
                :href="siguiente ?? undefined"
                preserve-scroll
                class="grid size-9 place-items-center rounded-lg border text-sm transition hover:bg-accent aria-disabled:pointer-events-none aria-disabled:opacity-40"
                :aria-disabled="!siguiente"
                aria-label="Página siguiente"
            >
                <ChevronRight class="size-4" />
            </component>
        </div>
    </nav>
</template>
