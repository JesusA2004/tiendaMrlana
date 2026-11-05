<script setup lang="ts">
import {
    BadgeCheck,
    Banknote,
    Clock3,
    Headset,
    HeartHandshake,
    Home,
    MapPin,
    PackageCheck,
    PackageSearch,
    ShieldCheck,
    Sparkles,
    Store,
    Truck,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';

/** Ícono por nombre (config `home.beneficios[].icono`); desconocido → destello. */
const props = defineProps<{ nombre?: string | null }>();

const ICONOS = {
    'badge-check': BadgeCheck,
    banknote: Banknote,
    clock: Clock3,
    headset: Headset,
    'heart-handshake': HeartHandshake,
    home: Home,
    'map-pin': MapPin,
    'package-check': PackageCheck,
    'package-search': PackageSearch,
    'shield-check': ShieldCheck,
    sparkles: Sparkles,
    store: Store,
    truck: Truck,
    wallet: Wallet,
} as const;

const componente = computed(
    () =>
        ICONOS[(props.nombre ?? '').toLowerCase() as keyof typeof ICONOS] ??
        Sparkles,
);
</script>

<template>
    <component :is="componente" aria-hidden="true" />
</template>
