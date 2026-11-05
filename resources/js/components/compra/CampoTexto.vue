<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Campo de formulario con etiqueta, ayuda y error accesibles.
 */
const props = withDefaults(
    defineProps<{
        id: string;
        etiqueta: string;
        error?: string;
        ayuda?: string;
        tipo?: string;
        autocomplete?: string;
        inputmode?: 'text' | 'numeric' | 'tel' | 'email' | 'decimal';
        maxlength?: number;
        requerido?: boolean;
        placeholder?: string;
    }>(),
    {
        error: undefined,
        ayuda: undefined,
        tipo: 'text',
        autocomplete: undefined,
        inputmode: undefined,
        maxlength: undefined,
        requerido: false,
        placeholder: undefined,
    },
);

const modelo = defineModel<string>({ default: '' });

const describedBy = computed(() =>
    [
        props.error ? `${props.id}-error` : null,
        props.ayuda ? `${props.id}-ayuda` : null,
    ]
        .filter(Boolean)
        .join(' '),
);
</script>

<template>
    <div class="space-y-1.5">
        <Label
            :for="id"
            class="flex-wrap gap-x-1.5 gap-y-0 text-sm font-medium"
        >
            {{ etiqueta }}
            <span v-if="!requerido" class="font-normal text-muted-foreground"
                >(opcional)</span
            >
        </Label>
        <Input
            :id="id"
            v-model="modelo"
            :type="tipo"
            :autocomplete="autocomplete"
            :inputmode="inputmode"
            :maxlength="maxlength"
            :placeholder="placeholder"
            :required="requerido"
            :aria-invalid="!!error"
            :aria-describedby="describedBy || undefined"
            class="h-11"
        />
        <p
            v-if="ayuda && !error"
            :id="`${id}-ayuda`"
            class="text-xs text-muted-foreground"
        >
            {{ ayuda }}
        </p>
        <p
            v-if="error"
            :id="`${id}-error`"
            class="text-xs font-medium text-destructive"
            role="alert"
        >
            {{ error }}
        </p>
    </div>
</template>
