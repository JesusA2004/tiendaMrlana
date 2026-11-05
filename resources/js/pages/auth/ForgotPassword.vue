<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CircleCheck } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: '¿Olvidaste tu contraseña?',
        description:
            'Escribe tu correo y te enviaremos un enlace para restablecerla.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Recuperar contraseña" />

    <div
        v-if="status"
        role="status"
        class="mb-6 flex items-start gap-2 rounded-2xl border border-brand-green/40 bg-brand-green-soft px-4 py-3 text-sm font-medium text-brand-green-strong"
    >
        <CircleCheck class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <span>{{ status }}</span>
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    placeholder="tucorreo@ejemplo.com"
                    class="h-11 rounded-xl"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="mt-6">
                <Button
                    class="h-11 w-full rounded-full text-[15px] font-semibold"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    Enviar enlace de recuperación
                </Button>
            </div>
        </Form>

        <div class="text-center text-sm text-muted-foreground">
            ¿Ya la recordaste?
            <TextLink :href="login()">Inicia sesión</TextLink>
        </div>
    </div>
</template>
