<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div
        class="flex h-full flex-col overflow-hidden rounded-2xl border border-border/70 bg-card"
        aria-hidden="true"
    >
        <Skeleton class="aspect-[4/5] w-full rounded-none" />
        <div class="flex flex-1 flex-col gap-2 p-4">
            <Skeleton class="h-3 w-1/3" />
            <Skeleton class="h-4 w-11/12" />
            <Skeleton class="h-4 w-2/3" />
            <div class="mt-auto flex items-end justify-between pt-3">
                <Skeleton class="h-6 w-24" />
                <Skeleton class="size-10 rounded-full" />
            </div>
        </div>
    </div>
</template>
