<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { CircleCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Mi perfil',
                href: edit(),
            },
        ],
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <Head title="Mi perfil" />

    <h1 class="sr-only">Mi perfil</h1>

    <div
        class="flex flex-col space-y-6 rounded-3xl border border-border/70 bg-card p-5 shadow-sm sm:p-8"
    >
        <Heading
            variant="small"
            title="Información del perfil"
            description="Actualiza tu nombre y tu correo electrónico."
        />

        <Form
            v-bind="ProfileController.update.form()"
            class="space-y-6"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="name">Nombre</Label>
                <Input
                    id="name"
                    class="block h-11 w-full rounded-xl"
                    name="name"
                    :default-value="user.name"
                    required
                    autocomplete="name"
                    placeholder="Nombre completo"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">Correo electrónico</Label>
                <Input
                    id="email"
                    type="email"
                    class="block h-11 w-full rounded-xl"
                    name="email"
                    :default-value="user.email"
                    required
                    autocomplete="username"
                    placeholder="tucorreo@ejemplo.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div
                v-if="mustVerifyEmail && !user.email_verified_at"
                class="rounded-2xl bg-muted/60 px-4 py-3"
            >
                <p class="text-sm text-muted-foreground">
                    Tu correo aún no está verificado.
                    <Link
                        :href="send()"
                        as="button"
                        class="rounded-sm font-medium text-brand-green-strong underline decoration-brand-green/40 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    >
                        Reenviar correo de verificación.
                    </Link>
                </p>

                <div
                    v-if="status === 'verification-link-sent'"
                    role="status"
                    class="mt-3 flex items-start gap-2 rounded-xl border border-brand-green/40 bg-brand-green-soft px-3 py-2 text-sm font-medium text-brand-green-strong"
                >
                    <CircleCheck
                        class="mt-0.5 size-4 shrink-0"
                        aria-hidden="true"
                    />
                    <span>
                        Te enviamos un nuevo enlace de verificación a tu correo.
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    class="h-11 rounded-full px-8 font-semibold"
                    data-test="update-profile-button"
                    >Guardar</Button
                >
            </div>
        </Form>
    </div>

    <DeleteUser />
</template>
