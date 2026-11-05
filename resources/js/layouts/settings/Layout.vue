<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Palette, ShieldCheck, UserRound } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Mi perfil',
        href: editProfile(),
        icon: UserRound,
    },
    {
        title: 'Seguridad',
        href: editSecurity(),
        icon: ShieldCheck,
    },
    {
        title: 'Apariencia',
        href: editAppearance(),
        icon: Palette,
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6 sm:px-6">
        <Heading
            title="Mi cuenta"
            description="Administra tu perfil, tu seguridad y la apariencia del sitio."
        />

        <div class="flex flex-col gap-6 lg:flex-row lg:gap-10">
            <aside class="w-full lg:w-56 lg:shrink-0">
                <nav
                    class="flex flex-wrap gap-2 lg:flex-col lg:gap-1"
                    aria-label="Configuración de la cuenta"
                >
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        :aria-current="
                            isCurrentOrParentUrl(item.href) ? 'page' : undefined
                        "
                        :class="[
                            'inline-flex h-10 items-center gap-2.5 rounded-full px-4 text-sm font-medium transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none lg:w-full',
                            isCurrentOrParentUrl(item.href)
                                ? 'bg-brand-green-soft font-semibold text-brand-green-strong'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                        ]"
                    >
                        <component
                            :is="item.icon"
                            class="size-4 shrink-0"
                            aria-hidden="true"
                        />
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <div class="min-w-0 flex-1 md:max-w-2xl">
                <section class="space-y-6">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
