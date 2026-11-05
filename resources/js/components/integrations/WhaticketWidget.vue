<script setup lang="ts">
import { onMounted } from 'vue';

declare global {
    interface Window {
        WTT_API?: {
            config?: {
                token?: string;
                title?: string;
                subtitle?: string;
                position?: 'left' | 'right';
                initialMessages?: string[];
                toggleButtonEnabled?: boolean;
                toggleButtonBgColor?: string;
                isAudioEnabled?: boolean;
            };
        };
    }
}

type Position = 'left' | 'right';

const props = withDefaults(
    defineProps<{
        title?: string;
        subtitle?: string;
        position?: Position;
        initialMessages?: string[];
        toggleButtonEnabled?: boolean;
        toggleButtonBgColor?: string;
        isAudioEnabled?: boolean;
    }>(),
    {
        title: '¡Bienvenido al soporte!',
        subtitle: '¿Cómo podemos ayudar?',
        position: 'right',
        initialMessages: () => ['Hola, ¿cómo podemos ayudarte?'],
        toggleButtonEnabled: true,
        toggleButtonBgColor: '#42a5f5',
        isAudioEnabled: false,
    },
);

onMounted(() => {
    const token = import.meta.env.VITE_WHATICKET_TOKEN as string | undefined;

    if (!token) {
        console.warn('[Whaticket] Falta VITE_WHATICKET_TOKEN en .env');

        return;
    }

    window.WTT_API = {
        config: {
            token,
            title: props.title,
            subtitle: props.subtitle,
            position: props.position,
            initialMessages: props.initialMessages,
            toggleButtonEnabled: props.toggleButtonEnabled,
            toggleButtonBgColor: props.toggleButtonBgColor,
            isAudioEnabled: props.isAudioEnabled,
        },
    };

    const SCRIPT_ID = 'wtt-widget-default';

    if (document.getElementById(SCRIPT_ID)) {
        return;
    }

    const script = document.createElement('script');
    script.id = SCRIPT_ID;
    script.async = true;
    script.crossOrigin = 'anonymous';
    script.src =
        'https://public.whaticket.com/widget/production/wtt-widget-default.js';

    document.head.appendChild(script);
});
</script>

<template>
    <span class="hidden" aria-hidden="true"></span>
</template>
