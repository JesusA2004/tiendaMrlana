<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavGroup, NavLink } from '@/composables/useRoleNavigation';

const props = defineProps<{
    groups: NavGroup[];
}>();

const page = usePage();

const actual = computed(() => {
    const url = new URL(page.url, 'http://localhost');

    return { path: url.pathname, query: url.search.replace(/^\?/, '') };
});

/** Enlaces con query (p. ej. «Aprobación de catálogo») ganan sobre su ruta base. */
const conQueryActivo = computed(() =>
    props.groups.flatMap((g) => g.items).some((i) => i.query && esActivo(i)),
);

function esActivo(item: NavLink): boolean {
    const destino = new URL(item.href, 'http://localhost');

    if (item.query) {
        return (
            actual.value.path === destino.pathname &&
            actual.value.query.split('&').includes(item.query)
        );
    }

    if (item.activoEn) {
        return actual.value.path.startsWith(item.activoEn);
    }

    return actual.value.path === destino.pathname;
}

function activo(item: NavLink): boolean {
    if (!item.query && conQueryActivo.value) {
        const destino = new URL(item.href, 'http://localhost');

        if (destino.pathname === actual.value.path) {
            return false;
        }
    }

    return esActivo(item);
}
</script>

<template>
    <SidebarGroup
        v-for="(grupo, gi) in groups"
        :key="grupo.label"
        class="animate-fade-up px-2 py-2"
        :style="{ '--enter-delay': `${gi * 60}ms` }"
    >
        <SidebarGroupLabel
            class="mb-0.5 h-6 px-2 text-[0.65rem] font-bold tracking-[0.16em] text-brand-green/90 uppercase"
            >{{ grupo.label }}</SidebarGroupLabel
        >
        <SidebarMenu class="gap-0.5">
            <SidebarMenuItem v-for="item in grupo.items" :key="item.href">
                <SidebarMenuButton
                    as-child
                    :is-active="activo(item)"
                    :tooltip="item.title"
                    class="group/nav relative h-9 rounded-xl text-sidebar-foreground/85 hover:bg-white/8 hover:text-white data-[active=true]:bg-brand-gradient data-[active=true]:font-semibold data-[active=true]:text-brand-ink data-[active=true]:shadow-[0_8px_20px_-10px_rgb(115_200_58/0.9)] data-[active=true]:hover:translate-x-0 data-[active=true]:hover:text-brand-ink [&>svg]:transition-[color,filter,transform] [&>svg]:duration-200 hover:[&>svg]:scale-110 hover:[&>svg]:text-brand-green hover:[&>svg]:drop-shadow-[0_0_6px_rgb(115_200_58/0.85)] data-[active=true]:[&>svg]:text-brand-ink data-[active=true]:[&>svg]:drop-shadow-none"
                >
                    <a v-if="item.externo" :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </a>
                    <Link v-else :href="item.href">
                        <span
                            aria-hidden="true"
                            :class="[
                                'absolute top-1/2 left-1 h-4 w-1 -translate-y-1/2 rounded-full bg-brand-deep/70 transition-[scale,opacity] duration-300 ease-out group-data-[collapsible=icon]:hidden',
                                activo(item)
                                    ? 'scale-y-100 opacity-100'
                                    : 'scale-y-0 opacity-0',
                            ]"
                        />
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
