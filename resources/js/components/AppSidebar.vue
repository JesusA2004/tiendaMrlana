<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { PermisosNav } from '@/composables/useRoleNavigation';
import { useRoleNavigation } from '@/composables/useRoleNavigation';

const page = usePage();

const grupos = computed(() =>
    useRoleNavigation(
        page.props.auth?.user?.rol ?? 'cliente',
        (page.props.auth as { can?: PermisosNav } | undefined)?.can ?? {},
    ),
);

const inicio = computed(() =>
    (page.props.auth as { can?: PermisosNav } | undefined)?.can?.operar_pedidos
        ? '/dashboard'
        : '/mi-cuenta/pedidos',
);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="inicio" aria-label="Inicio del panel">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :groups="grupos" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
