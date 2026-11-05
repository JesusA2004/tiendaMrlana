<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ShieldCheck, UserRound } from 'lucide-vue-next';
import Heading from '@/components/Heading.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
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
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6 sm:px-6">
        <Heading
            title="Mi cuenta"
            description="Administra tu perfil y la seguridad de tu cuenta."
        />

        <div class="flex flex-col gap-6 lg:flex-row lg:gap-10">
            <aside class="w-full lg:w-56 lg:shrink-0">
                <nav
                    class="flex stagger-children flex-wrap gap-2 lg:sticky lg:top-20 lg:flex-col lg:gap-1.5"
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
                            'group inline-flex h-10 items-center gap-2.5 rounded-xl px-4 text-sm font-medium transition-[background-color,color,box-shadow,translate] duration-200 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none lg:w-full',
                            isCurrentOrParentUrl(item.href)
                                ? 'bg-brand-gradient font-semibold text-brand-ink shadow-brand-sm'
                                : 'bg-card/60 text-muted-foreground hover:translate-x-0.5 hover:bg-card hover:text-brand-deep hover:shadow-brand-sm',
                        ]"
                    >
                        <component
                            :is="item.icon"
                            class="size-4 shrink-0 transition-transform duration-200 group-hover:scale-110"
                            aria-hidden="true"
                        />
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <div class="min-w-0 flex-1 md:max-w-3xl">
                <section
                    class="animate-fade-up space-y-6 surface-card p-5 sm:p-8"
                >
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
