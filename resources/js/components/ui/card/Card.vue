<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import { cn } from "@/lib/utils"

const props = defineProps<{
  class?: HTMLAttributes["class"]
  interactive?: boolean
}>()
</script>

<template>
  <div
    data-slot="card"
    :class="
      cn(
        'bg-card text-card-foreground flex flex-col gap-6 rounded-xl border border-border py-6 shadow-brand-sm transition-[box-shadow,border-color,transform] duration-200 hover:border-brand-green/40',
        props.interactive && 'hover-lift cursor-pointer',
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
