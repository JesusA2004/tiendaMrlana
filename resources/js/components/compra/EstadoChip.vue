<script setup lang="ts">
import { computed } from 'vue';
import { clasesTono } from './formato';
import type { EstadoInfo } from './types';

const props = defineProps<{
    estado: EstadoInfo | null | undefined;
    prefijo?: string;
}>();

const clases = computed(() => clasesTono(props.estado?.color));
</script>

<template>
    <span
        v-if="estado"
        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset"
        :class="clases"
    >
        <span class="size-1.5 rounded-full bg-current" aria-hidden="true" />
        <span v-if="prefijo" class="font-medium opacity-80">{{ prefijo }}</span>
        {{ estado.label }}
    </span>
</template>
