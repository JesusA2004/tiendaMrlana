<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { CircleCheck } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Bienvenido de vuelta',
        description: 'Inicia sesión para ver tu cuenta y tus pedidos.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <Head title="Iniciar sesión" />

    <div
        v-if="status"
        role="status"
        class="mb-6 flex items-start gap-2 rounded-2xl border border-brand-green/40 bg-brand-green-soft px-4 py-3 text-sm font-medium text-brand-green-strong"
    >
        <CircleCheck class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
        <span>{{ status }}</span>
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="space-y-6"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="tucorreo@ejemplo.com"
                    class="h-11 rounded-xl"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div
                    class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1"
                >
                    <Label for="password">Contraseña</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        ¿Olvidaste tu contraseña?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    placeholder="Tu contraseña"
                    class="h-11 rounded-xl"
                />
                <InputError :message="errors.password" />
            </div>

            <Label for="remember" class="flex w-fit items-center gap-3">
                <Checkbox id="remember" name="remember" :tabindex="3" />
                <span>Recordarme</span>
            </Label>

            <Button
                type="submit"
                class="mt-1 h-11 w-full rounded-full text-[15px] font-semibold"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Iniciar sesión
            </Button>
        </div>

        <div
            class="text-center text-sm text-muted-foreground"
            v-if="canRegister"
        >
            ¿No tienes cuenta?
            <TextLink :href="register()" :tabindex="5">Regístrate</TextLink>
        </div>
    </Form>
</template>
