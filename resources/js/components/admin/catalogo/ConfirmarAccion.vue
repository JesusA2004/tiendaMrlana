<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

defineProps<{
    titulo: string;
    descripcion?: string;
    confirmar?: string;
    destructiva?: boolean;
    procesando?: boolean;
}>();

const abierto = defineModel<boolean>('open', { default: false });

const emit = defineEmits<{ confirmar: [] }>();
</script>

<template>
    <Dialog v-model:open="abierto">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="font-display text-xl">{{
                    titulo
                }}</DialogTitle>
                <DialogDescription v-if="descripcion">{{
                    descripcion
                }}</DialogDescription>
            </DialogHeader>
            <slot />
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="abierto = false"
                    >Cancelar</Button
                >
                <Button
                    :variant="destructiva ? 'destructive' : 'default'"
                    :disabled="procesando"
                    @click="emit('confirmar')"
                >
                    {{ confirmar ?? 'Confirmar' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
