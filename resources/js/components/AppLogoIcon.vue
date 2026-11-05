<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

import logoFull from '@/img/logo.png';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

const props = defineProps<Props>();
</script>

<template>
    <img
        v-bind="$attrs"
        :class="['h-12 w-auto object-contain', props.className]"
        :src="logoFull"
        alt="Mr Lana"
    />
</template>
