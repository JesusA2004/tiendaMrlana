<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { useSidebar } from '@/components/ui/sidebar';

const { state } = useSidebar();
</script>

<template>
    <div class="flex w-full items-center">
        <div
            :class="[
                'flex items-center justify-center overflow-hidden',
                state === 'expanded'
                    ? 'h-12 w-auto rounded-xl bg-white px-3 py-1 shadow-sm'
                    : 'aspect-square size-12 rounded-md bg-white text-sidebar-primary-foreground',
            ]"
        >
            <AppLogoIcon />
        </div>
    </div>
</template>
