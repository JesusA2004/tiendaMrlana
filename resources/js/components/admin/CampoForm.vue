<script setup lang="ts">
import { Label } from '@/components/ui/label';

/** Etiqueta + control (slot) + ayuda + error de validación. */
defineProps<{
    label: string;
    for?: string;
    error?: string;
    ayuda?: string;
    requerido?: boolean;
}>();
</script>

<template>
    <div class="grid gap-1.5">
        <Label :for="$props.for" class="text-sm font-medium">
            {{ label }}
            <span v-if="requerido" class="text-destructive" aria-hidden="true"
                >*</span
            >
        </Label>
        <slot />
        <p
            v-if="error"
            class="text-xs font-medium text-destructive"
            role="alert"
        >
            {{ error }}
        </p>
        <p v-else-if="ayuda" class="text-xs text-muted-foreground">
            {{ ayuda }}
        </p>
    </div>
</template>
