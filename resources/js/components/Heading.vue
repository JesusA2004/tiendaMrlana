<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header :class="variant === 'small' ? 'space-y-1' : 'mb-8 space-y-1'">
        <h2
            :class="
                variant === 'small'
                    ? 'font-display text-lg leading-tight font-bold tracking-tight'
                    : 'font-display text-2xl leading-tight font-extrabold tracking-tight sm:text-3xl'
            "
        >
            {{ title }}
        </h2>
        <p v-if="description" class="text-sm text-pretty text-muted-foreground">
            {{ description }}
        </p>
    </header>
</template>
