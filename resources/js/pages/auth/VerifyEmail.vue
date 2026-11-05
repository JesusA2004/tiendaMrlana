<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CircleCheck, MailCheck } from 'lucide-vue-next';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Verifica tu correo',
        description:
            'Te enviamos un enlace de verificación. Ábrelo desde tu correo para activar tu cuenta.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Verificación de correo" />

    <div class="mb-6 flex justify-center" aria-hidden="true">
        <span
            class="flex size-14 items-center justify-center rounded-2xl bg-brand-green-soft text-brand-green-strong"
        >
            <MailCheck class="size-7" />
        </span>
    </div>

    <div
        v-if="status === 'verification-link-sent'"
        role="status"
        class="mb-6 flex items-start gap-2 rounded-2xl border border-brand-green/40 bg-brand-green-soft px-4 py-3 text-sm font-medium text-brand-green-strong"
    >
        <CircleCheck class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <span>
            Te enviamos un nuevo enlace de verificación al correo con el que te
            registraste.
        </span>
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-5 text-center"
        v-slot="{ processing }"
    >
        <Button
            :disabled="processing"
            class="h-11 w-full rounded-full text-[15px] font-semibold"
        >
            <Spinner v-if="processing" />
            Reenviar correo de verificación
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Cerrar sesión
        </TextLink>
    </Form>
</template>
