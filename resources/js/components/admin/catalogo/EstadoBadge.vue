<script setup lang="ts">
import { computed } from 'vue';
import { claseTono } from './formato';

const props = defineProps<{
    tono?: string | null;
    etiqueta: string;
    punto?: boolean;
}>();

const clases = computed(() => claseTono(props.tono));
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap"
        :class="clases"
    >
        <span
            v-if="punto"
            class="size-1.5 rounded-full bg-current opacity-80"
            aria-hidden="true"
        />
        {{ etiqueta }}
    </span>
</template>
