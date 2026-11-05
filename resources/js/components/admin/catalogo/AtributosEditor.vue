<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { AtributoProducto } from './types';

defineProps<{
    errores?: Record<string, string | undefined>;
}>();

const atributos = defineModel<AtributoProducto[]>({ required: true });

const sugerencias = [
    'Material',
    'Color',
    'Capacidad de carga',
    'Armado',
    'Garantía',
];

function agregar(nombre = ''): void {
    atributos.value = [...atributos.value, { nombre, valor: '' }];
}

function quitar(indice: number): void {
    atributos.value = atributos.value.filter((_, i) => i !== indice);
}
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="(atributo, i) in atributos"
            :key="i"
            class="grid grid-cols-[1fr_1.4fr_auto] items-start gap-2"
        >
            <div>
                <Input
                    v-model="atributo.nombre"
                    maxlength="60"
                    placeholder="Nombre (ej. Material)"
                    :aria-label="`Nombre del atributo ${i + 1}`"
                />
                <InputError :message="errores?.[`atributos.${i}.nombre`]" />
            </div>
            <div>
                <Input
                    v-model="atributo.valor"
                    maxlength="255"
                    placeholder="Valor (ej. Madera de pino)"
                    :aria-label="`Valor del atributo ${i + 1}`"
                />
                <InputError :message="errores?.[`atributos.${i}.valor`]" />
            </div>
            <Button
                type="button"
                size="icon"
                variant="ghost"
                class="text-muted-foreground hover:text-destructive"
                :aria-label="`Quitar atributo ${i + 1}`"
                @click="quitar(i)"
            >
                <Trash2 />
            </Button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="agregar()"
            >
                <Plus /> Agregar atributo
            </Button>
            <button
                v-for="s in sugerencias.filter(
                    (s) => !atributos.some((a) => a.nombre === s),
                )"
                :key="s"
                type="button"
                class="rounded-full border px-2.5 py-1 text-xs text-muted-foreground transition hover:border-brand-green hover:text-foreground"
                @click="agregar(s)"
            >
                + {{ s }}
            </button>
        </div>
    </div>
</template>
