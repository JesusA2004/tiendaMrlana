<script setup lang="ts">
import type { TabsListProps } from "reka-ui"
import type { HTMLAttributes } from "vue"
import { reactiveOmit } from "@vueuse/core"
import { cn } from "@/lib/utils"
import { TabsIndicator, TabsList } from "reka-ui"

const props = defineProps<TabsListProps & { class?: HTMLAttributes["class"] }>()

const delegatedProps = reactiveOmit(props, "class")
</script>

<template>
  <TabsList
    data-slot="tabs-list"
    v-bind="delegatedProps"
    :class="cn(
      'bg-muted text-muted-foreground relative isolate inline-flex h-9 w-fit items-center justify-center rounded-lg border border-border/70 p-0.75',
      props.class,
    )"
  >
    <TabsIndicator
      class="absolute top-0.75 bottom-0.75 left-0 -z-10 w-(--reka-tabs-indicator-size) translate-x-(--reka-tabs-indicator-position) rounded-md bg-card shadow-sm ring-1 ring-brand-green/40 transition-[width,translate] duration-300 ease-out"
    >
      <span class="absolute inset-x-2 bottom-0 h-0.5 rounded-full bg-brand-gradient" />
    </TabsIndicator>
    <slot />
  </TabsList>
</template>
