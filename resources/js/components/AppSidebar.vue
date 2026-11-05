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
    <Sidebar collapsible="icon" variant="inset" class="mrl-sidebar">
        <SidebarHeader class="relative gap-3 pt-3 pb-1">
            <!-- Halo decorativo de marca -->
            <span
                aria-hidden="true"
                class="pointer-events-none absolute -top-16 -left-10 size-44 rounded-full bg-brand-green/25 blur-3xl"
            />
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="h-auto justify-center p-1 group-data-[collapsible=icon]:size-10! hover:translate-x-0 hover:bg-transparent"
                    >
                        <Link :href="inicio" aria-label="Inicio del panel">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <div
                class="mx-2 h-px bg-gradient-to-r from-transparent via-white/25 to-transparent group-data-[collapsible=icon]:mx-0"
                aria-hidden="true"
            />
        </SidebarHeader>

        <SidebarContent class="gap-0 py-1">
            <NavMain :groups="grupos" />
        </SidebarContent>

        <SidebarFooter class="p-2">
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
