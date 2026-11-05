<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Store } from 'lucide-vue-next';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();

const rol = computed(() => {
    switch (page.props.auth?.user?.rol) {
        case 'admin':
            return {
                label: 'Administración',
                clase: 'bg-brand-deep text-white',
            };
        case 'vendedor':
            return {
                label: 'Vendedor',
                clase: 'bg-brand-teal-soft text-brand-teal-strong',
            };
        default:
            return null;
    }
});
</script>

<template>
    <header
        class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b border-sidebar-border/70 bg-background/85 px-4 backdrop-blur transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 supports-[backdrop-filter]:bg-background/70 md:rounded-t-xl"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <span class="h-4 w-px bg-border" aria-hidden="true" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>
        <div class="flex items-center gap-2">
            <span
                v-if="rol"
                :class="[
                    'hidden rounded-full px-2.5 py-1 text-[0.7rem] font-semibold tracking-wide uppercase sm:inline-flex',
                    rol.clase,
                ]"
                >{{ rol.label }}</span
            >
            <a
                href="/"
                class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1.5 text-xs font-medium transition hover:border-brand-green hover:bg-brand-green-soft"
            >
                <Store class="size-3.5" />
                <span class="hidden sm:inline">Ver tienda</span>
            </a>
        </div>
    </header>
</template>
