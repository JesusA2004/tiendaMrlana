<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { edit } from '@/routes/security';
import { disable, enable } from '@/routes/two-factor';

type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

withDefaults(defineProps<Props>(), {
    canManageTwoFactor: false,
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Seguridad',
                href: edit(),
            },
        ],
    },
});

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => clearTwoFactorAuthData());
</script>

<template>
    <Head title="Seguridad" />

    <h1 class="sr-only">Seguridad</h1>

    <div
        class="space-y-6 rounded-3xl border border-border/70 bg-card p-5 shadow-sm sm:p-8"
    >
        <Heading
            variant="small"
            title="Cambiar contraseña"
            description="Usa una contraseña larga y difícil de adivinar para mantener tu cuenta segura."
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="current_password">Contraseña actual</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="block h-11 w-full rounded-xl"
                    autocomplete="current-password"
                    placeholder="Tu contraseña actual"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Nueva contraseña</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="block h-11 w-full rounded-xl"
                    autocomplete="new-password"
                    placeholder="Nueva contraseña"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirmar contraseña</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="block h-11 w-full rounded-xl"
                    autocomplete="new-password"
                    placeholder="Repite tu nueva contraseña"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    class="h-11 rounded-full px-8 font-semibold"
                    data-test="update-password-button"
                >
                    Guardar contraseña
                </Button>
            </div>
        </Form>
    </div>

    <div
        v-if="canManageTwoFactor"
        class="space-y-6 rounded-3xl border border-border/70 bg-card p-5 shadow-sm sm:p-8"
    >
        <Heading
            variant="small"
            title="Autenticación en dos pasos"
            description="Agrega una capa extra de seguridad al iniciar sesión."
        />

        <div
            v-if="!twoFactorEnabled"
            class="flex flex-col items-start justify-start space-y-4"
        >
            <p class="text-sm text-muted-foreground">
                Al activar la autenticación en dos pasos, te pediremos un código
                de seguridad al iniciar sesión. Lo obtienes desde una aplicación
                de autenticación (TOTP) en tu teléfono.
            </p>

            <div>
                <Button
                    v-if="hasSetupData"
                    class="rounded-full px-5"
                    @click="showSetupModal = true"
                >
                    <ShieldCheck />Continuar configuración
                </Button>
                <Form
                    v-else
                    v-bind="enable.form()"
                    @success="showSetupModal = true"
                    #default="{ processing }"
                >
                    <Button
                        type="submit"
                        class="rounded-full px-5"
                        :disabled="processing"
                    >
                        Activar autenticación en dos pasos
                    </Button>
                </Form>
            </div>
        </div>

        <div v-else class="flex flex-col items-start justify-start space-y-4">
            <p class="text-sm text-muted-foreground">
                Al iniciar sesión te pediremos un código de seguridad, que
                obtienes desde la aplicación de autenticación (TOTP) de tu
                teléfono.
            </p>

            <div class="relative inline">
                <Form v-bind="disable.form()" #default="{ processing }">
                    <Button
                        variant="destructive"
                        type="submit"
                        class="rounded-full px-5"
                        :disabled="processing"
                    >
                        Desactivar autenticación en dos pasos
                    </Button>
                </Form>
            </div>

            <TwoFactorRecoveryCodes />
        </div>

        <TwoFactorSetupModal
            v-model:isOpen="showSetupModal"
            :requiresConfirmation="requiresConfirmation"
            :twoFactorEnabled="twoFactorEnabled"
        />
    </div>
</template>
