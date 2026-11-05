<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: 'Confirma tu contraseña',
        description:
            'Esta es una sección protegida. Confirma tu contraseña para continuar.',
    },
});
</script>

<template>
    <Head title="Confirmar contraseña" />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label htmlFor="password">Contraseña</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="block h-11 w-full rounded-xl"
                    required
                    autocomplete="current-password"
                    autofocus
                    placeholder="Tu contraseña"
                />

                <InputError :message="errors.password" />
            </div>

            <Button
                class="h-11 w-full rounded-full text-[15px] font-semibold"
                :disabled="processing"
                data-test="confirm-password-button"
            >
                <Spinner v-if="processing" />
                Confirmar contraseña
            </Button>
        </div>
    </Form>
</template>
