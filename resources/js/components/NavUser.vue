<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const { isMobile, state } = useSidebar();

const ROLES: Record<string, string> = {
    admin: 'Administrador',
    vendedor: 'Vendedor',
    cliente: 'Cliente',
};
const rol = computed(() => ROLES[user.value?.rol as string] ?? 'Cuenta');
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="h-auto rounded-2xl border border-white/10 bg-white/6 py-2 shadow-[inset_0_1px_0_rgb(255_255_255/0.08)] group-data-[collapsible=icon]:border-0 group-data-[collapsible=icon]:bg-transparent hover:translate-x-0 hover:border-brand-green/50 hover:bg-white/10 data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground [&_.text-muted-foreground]:text-sidebar-foreground/70"
                        data-test="sidebar-menu-button"
                    >
                        <UserInfo :user="user" />
                        <span
                            class="rounded-full bg-brand-green/20 px-2 py-0.5 text-[0.6rem] font-bold tracking-wide text-brand-green uppercase"
                            >{{ rol }}</span
                        >
                        <ChevronsUpDown class="size-4 opacity-70" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                    :side="
                        isMobile
                            ? 'bottom'
                            : state === 'collapsed'
                              ? 'left'
                              : 'bottom'
                    "
                    align="end"
                    :side-offset="4"
                >
                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
