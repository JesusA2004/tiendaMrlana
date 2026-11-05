<script setup lang="ts">
import { Banknote, Building2, HandCoins, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ clave: string }>();

const componente = computed(() => {
    switch (props.clave) {
        case 'transferencia':
            return Building2;
        case 'efectivo_presencial':
            return Banknote;
        case 'efectivo_contra_entrega':
            return HandCoins;
        default:
            return Wallet;
    }
});
</script>

<template>
    <component :is="componente" aria-hidden="true" />
</template>
