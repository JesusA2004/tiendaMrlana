<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { rapido } from '@/routes/admin/productos';
import { formatoMoneda } from './formato';
import type { OpcionCatalogo, OpcionEnum, ProductoFila } from './types';

const props = defineProps<{
    producto: ProductoFila | null;
    categorias: OpcionCatalogo[];
    modalidades: OpcionEnum[];
}>();

const abierto = defineModel<boolean>('open', { default: false });

const form = useForm({
    precio: '' as number | '',
    categoria_id: null as number | null,
    modalidad_venta: 'inventario',
    tiempo_entrega_dias: '' as number | '',
});

watch(abierto, (valor) => {
    if (valor && props.producto) {
        form.clearErrors();
        form.precio = props.producto.precio ?? '';
        form.categoria_id = props.producto.categoria_id;
        form.modalidad_venta = props.producto.modalidad_venta;
        form.tiempo_entrega_dias = props.producto.tiempo_entrega_dias ?? '';
    }
});

const bajoPedido = computed(() => form.modalidad_venta === 'bajo_pedido');

function guardar(): void {
    if (!props.producto) {
        return;
    }

    // Los campos vacíos ('') llegan como null al backend (ConvertEmptyStringsToNull).
    form.patch(rapido(props.producto.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="abierto">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="font-display text-xl"
                    >Edición rápida</DialogTitle
                >
                <DialogDescription v-if="producto">
                    {{ producto.nombre }} · {{ producto.sku }}
                </DialogDescription>
            </DialogHeader>

            <form v-if="producto" class="space-y-4" @submit.prevent="guardar">
                <div class="grid gap-2">
                    <Label for="rapido-precio">Precio de venta (MXN)</Label>
                    <Input
                        id="rapido-precio"
                        v-model.number="form.precio"
                        type="number"
                        min="0.01"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="Sin definir"
                    />
                    <p
                        v-if="producto.precio_referencia"
                        class="text-xs text-muted-foreground"
                    >
                        Referencia de mercado (no pública):
                        {{ formatoMoneda(producto.precio_referencia) }}
                    </p>
                    <InputError :message="form.errors.precio" />
                </div>

                <div class="grid gap-2">
                    <Label for="rapido-categoria">Categoría</Label>
                    <NativeSelect
                        id="rapido-categoria"
                        v-model="form.categoria_id"
                        class="w-full"
                    >
                        <NativeSelectOption :value="null"
                            >Sin categoría</NativeSelectOption
                        >
                        <NativeSelectOption
                            v-for="c in categorias"
                            :key="c.id"
                            :value="c.id"
                            >{{ c.nombre }}</NativeSelectOption
                        >
                    </NativeSelect>
                    <InputError :message="form.errors.categoria_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="rapido-modalidad">Modalidad de venta</Label>
                    <NativeSelect
                        id="rapido-modalidad"
                        v-model="form.modalidad_venta"
                        class="w-full"
                    >
                        <NativeSelectOption
                            v-for="m in modalidades"
                            :key="m.value"
                            :value="m.value"
                            >{{ m.label }}</NativeSelectOption
                        >
                    </NativeSelect>
                    <InputError :message="form.errors.modalidad_venta" />
                </div>

                <div v-if="bajoPedido" class="grid gap-2">
                    <Label for="rapido-dias">Tiempo de entrega (días)</Label>
                    <Input
                        id="rapido-dias"
                        v-model.number="form.tiempo_entrega_dias"
                        type="number"
                        min="1"
                        max="365"
                        required
                    />
                    <InputError :message="form.errors.tiempo_entrega_dias" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="abierto = false"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Guardar</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
