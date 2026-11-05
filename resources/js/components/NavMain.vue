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
    <SidebarGroup v-for="grupo in groups" :key="grupo.label" class="px-2 py-1">
        <SidebarGroupLabel
            class="text-[0.68rem] font-semibold tracking-[0.12em] uppercase"
            >{{ grupo.label }}</SidebarGroupLabel
        >
        <SidebarMenu>
            <SidebarMenuItem v-for="item in grupo.items" :key="item.href">
                <SidebarMenuButton
                    as-child
                    :is-active="activo(item)"
                    :tooltip="item.title"
                    class="data-[active=true]:bg-brand-green-soft data-[active=true]:font-semibold data-[active=true]:text-brand-green-strong"
                >
                    <a v-if="item.externo" :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </a>
                    <Link v-else :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
