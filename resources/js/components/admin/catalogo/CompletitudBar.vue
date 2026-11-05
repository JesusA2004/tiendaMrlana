<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    valor: number;
    compacta?: boolean;
}>();

const color = computed(() => {
    if (props.valor >= 100) {
        return 'bg-brand-green';
    }

    if (props.valor >= 50) {
        return 'bg-brand-teal';
    }

    return 'bg-amber-500';
});
</script>

<template>
    <div
        class="flex items-center gap-2"
        :title="`Listo para publicar: ${valor}%`"
    >
        <div
            class="h-2 flex-1 overflow-hidden rounded-full bg-muted"
            :class="compacta ? 'min-w-16' : 'min-w-24'"
            role="progressbar"
            :aria-valuenow="valor"
            aria-valuemin="0"
            aria-valuemax="100"
            aria-label="Completitud para publicar"
        >
            <div
                class="h-full rounded-full transition-all duration-500"
                :class="color"
                :style="{ width: `${Math.max(4, valor)}%` }"
            />
        </div>
        <span class="w-9 text-right text-xs font-semibold tabular-nums"
            >{{ valor }}%</span
        >
    </div>
</template>
