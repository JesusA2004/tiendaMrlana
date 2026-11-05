<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';
import { Inbox } from 'lucide-vue-next';

defineProps<{
    title: string;
    description?: string;
    icon?: LucideIcon;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed bg-surface/60 px-6 py-12 text-center"
    >
        <span
            class="grid size-12 place-items-center rounded-2xl bg-brand-green-soft text-brand-green-strong"
        >
            <component :is="icon ?? Inbox" class="size-6" />
        </span>
        <div>
            <p class="font-display text-lg font-semibold">{{ title }}</p>
            <p
                v-if="description"
                class="mx-auto mt-1 max-w-md text-sm text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>
        <slot />
    </div>
</template>
