<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { TriangleAlert } from 'lucide-vue-next';
import { useTemplateRef } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const passwordInput = useTemplateRef('passwordInput');
</script>

<template>
    <div
        class="space-y-6 rounded-3xl border border-border/70 bg-card p-5 shadow-sm sm:p-8"
    >
        <Heading
            variant="small"
            title="Eliminar cuenta"
            description="Elimina tu cuenta y todos sus datos."
        />
        <div
            class="space-y-4 rounded-2xl border border-red-200 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
        >
            <div class="flex items-start gap-3 text-red-700 dark:text-red-100">
                <TriangleAlert
                    class="mt-0.5 size-5 shrink-0"
                    aria-hidden="true"
                />
                <div class="space-y-0.5">
                    <p class="font-semibold">Atención</p>
                    <p class="text-sm">
                        Procede con cuidado: esta acción no se puede deshacer.
                    </p>
                </div>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button
                        variant="destructive"
                        class="rounded-full px-5"
                        data-test="delete-user-button"
                        >Eliminar cuenta</Button
                    >
                </DialogTrigger>
                <DialogContent class="rounded-3xl">
                    <Form
                        v-bind="ProfileController.destroy.form()"
                        reset-on-success
                        @error="() => passwordInput?.focus()"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="space-y-6"
                        v-slot="{ errors, processing, reset, clearErrors }"
                    >
                        <DialogHeader class="space-y-3">
                            <DialogTitle class="font-display text-xl"
                                >¿Seguro que quieres eliminar tu
                                cuenta?</DialogTitle
                            >
                            <DialogDescription>
                                Al eliminar tu cuenta, todos sus datos se
                                borrarán de forma permanente. Escribe tu
                                contraseña para confirmar que quieres
                                eliminarla.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only"
                                >Contraseña</Label
                            >
                            <PasswordInput
                                id="password"
                                name="password"
                                ref="passwordInput"
                                class="h-11 rounded-xl"
                                placeholder="Tu contraseña"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button
                                    variant="secondary"
                                    class="rounded-full"
                                    @click="
                                        () => {
                                            clearErrors();
                                            reset();
                                        }
                                    "
                                >
                                    Cancelar
                                </Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                class="rounded-full"
                                :disabled="processing"
                                data-test="confirm-delete-user-button"
                            >
                                Eliminar cuenta
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
