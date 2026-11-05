<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, toRaw } from 'vue';
import { toast } from 'vue-sonner';
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

type Flash = { success?: string | null; error?: string | null };

/**
 * Mensajes flash del servidor (`flash.success` / `flash.error`, incluidos los errores de dominio
 * que bootstrap/app.php convierte en `error`) como toasts.
 */
const mostrados = new WeakSet<object>();

function mostrarFlash(flash: Flash | undefined) {
    if (!flash) {
        return;
    }

    // El montaje inicial y el evento `success` pueden entregar el mismo flash.
    const crudo = toRaw(flash);

    if (mostrados.has(crudo)) {
        return;
    }

    mostrados.add(crudo);

    if (flash?.success) {
        toast.success(flash.success);
    }

    if (flash?.error) {
        toast.error(flash.error, { duration: 8000 });
    }
}

const page = usePage();
let quitarListener: (() => void) | undefined;

onMounted(() => {
    mostrarFlash(page.props.flash as Flash | undefined);

    quitarListener = router.on('success', (event) => {
        mostrarFlash(
            (event.detail.page.props as { flash?: Flash }).flash ?? undefined,
        );
    });
});

onBeforeUnmount(() => quitarListener?.());
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden bg-background">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Toaster rich-colors close-button />
    </AppShell>
</template>
