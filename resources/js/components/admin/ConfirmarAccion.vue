<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { buttonVariants } from '@/components/ui/button';

/** Confirmación simple (eliminar, desactivar...). El disparador va en el slot `trigger`. */
withDefaults(
    defineProps<{
        title: string;
        description?: string;
        confirmLabel?: string;
        destructivo?: boolean;
    }>(),
    { description: '', confirmLabel: 'Confirmar', destructivo: true },
);

const emit = defineEmits<{ confirmar: [] }>();
</script>

<template>
    <AlertDialog>
        <AlertDialogTrigger as-child>
            <slot name="trigger" />
        </AlertDialogTrigger>
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle class="font-display text-xl">{{
                    title
                }}</AlertDialogTitle>
                <AlertDialogDescription v-if="description">{{
                    description
                }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancelar</AlertDialogCancel>
                <AlertDialogAction
                    :class="
                        destructivo
                            ? buttonVariants({ variant: 'destructive' })
                            : undefined
                    "
                    @click="emit('confirmar')"
                    >{{ confirmLabel }}</AlertDialogAction
                >
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
