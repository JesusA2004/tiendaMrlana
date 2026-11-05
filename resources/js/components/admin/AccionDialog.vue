<script setup lang="ts">
import { LoaderCircle } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

/**
 * Diálogo de acción del workflow con campos en el slot. Emite `confirmar` (submit del form);
 * el padre decide cuándo cerrarlo (onSuccess).
 */
withDefaults(
    defineProps<{
        title: string;
        description?: string;
        confirmLabel?: string;
        destructivo?: boolean;
        procesando?: boolean;
        deshabilitado?: boolean;
    }>(),
    {
        description: '',
        confirmLabel: 'Confirmar',
        destructivo: false,
        procesando: false,
        deshabilitado: false,
    },
);

const abierto = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ confirmar: [] }>();
</script>

<template>
    <Dialog v-model:open="abierto">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <form class="grid gap-5" @submit.prevent="emit('confirmar')">
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">{{
                        title
                    }}</DialogTitle>
                    <DialogDescription v-if="description">{{
                        description
                    }}</DialogDescription>
                </DialogHeader>
                <div class="grid gap-4">
                    <slot />
                </div>
                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="procesando"
                        @click="abierto = false"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        :variant="destructivo ? 'destructive' : 'default'"
                        :disabled="procesando || deshabilitado"
                    >
                        <LoaderCircle
                            v-if="procesando"
                            class="size-4 animate-spin"
                        />
                        {{ confirmLabel }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
