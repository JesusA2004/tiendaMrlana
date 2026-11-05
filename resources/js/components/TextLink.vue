<script setup lang="ts">
import type { LinkComponentBaseProps, Method } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';

type Props = {
    href: LinkComponentBaseProps['href'];
    tabindex?: number;
    method?: Method;
    as?: string;
};

defineProps<Props>();
</script>

<template>
    <Link
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        class="rounded-sm font-medium text-brand-green-strong underline decoration-brand-green/40 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
    >
        <slot />
    </Link>
</template>
