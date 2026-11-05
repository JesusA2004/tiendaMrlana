<script setup lang="ts">
/** Encabezado de página del panel: título Baloo 2, descripción y acciones. */
defineProps<{
    title: string;
    description?: string;
    eyebrow?: string;
}>();
</script>

<template>
    <header
        class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
    >
        <div class="min-w-0">
            <p
                v-if="eyebrow"
                class="mb-1 text-xs font-semibold tracking-[0.14em] text-brand-green-strong uppercase"
            >
                {{ eyebrow }}
            </p>
            <h1
                class="font-display text-2xl leading-tight font-bold tracking-tight sm:text-3xl"
            >
                {{ title }}
            </h1>
            <p
                v-if="description"
                class="mt-1 max-w-2xl text-sm text-muted-foreground"
            >
                {{ description }}
            </p>
            <slot name="meta" />
        </div>
        <div
            v-if="$slots.default"
            class="flex flex-wrap items-center gap-2 sm:justify-end"
        >
            <slot />
        </div>
    </header>
</template>
