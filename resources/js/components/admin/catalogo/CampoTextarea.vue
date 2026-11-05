<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    class?: HTMLAttributes['class'];
    rows?: number;
    maxlength?: number;
    invalido?: boolean;
}>();

const modelo = defineModel<string | null>({ default: '' });
</script>

<template>
    <div class="relative">
        <textarea
            v-model="modelo"
            :rows="rows ?? 4"
            :maxlength="maxlength"
            :aria-invalid="invalido || undefined"
            :class="
                cn(
                    'w-full min-w-0 rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 aria-invalid:border-destructive dark:bg-input/30',
                    props.class,
                )
            "
        />
        <span
            v-if="maxlength"
            class="pointer-events-none absolute right-2 bottom-2 text-[11px] text-muted-foreground tabular-nums"
            >{{ (modelo ?? '').length }}/{{ maxlength }}</span
        >
    </div>
</template>
