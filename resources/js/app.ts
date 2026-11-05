import { createInertiaApp } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    // La tienda pública (SeoHead.vue) ya manda el <title> completo con la marca ("… | Mr Lana Tienda").
    title: (title) =>
        !title
            ? appName
            : title.includes(' | ')
              ? title
              : `${title} - ${appName}`,
    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name === 'Dashboard':
            case name.startsWith('Admin/'):
            case name.startsWith('Cliente/'):
            case name.startsWith('Vendedor/'):
                return AppLayout;
            default:
                return null;
        }
    },
    progress: {
        color: '#73C83A',
    },
});

// This will listen for flash toast data from the server...
initializeFlashToast();
