<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    Scale,
    SlidersHorizontal,
} from 'lucide-vue-next';
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
import { ajustar } from '@/routes/admin/inventario';
import type { ProductoInventario } from './types';

const props = defineProps<{
    producto?: ProductoInventario | null;
    productos?: ProductoInventario[];
}>();

const abierto = defineModel<boolean>('open', { default: false });

const operaciones = [
    {
        value: 'entrada',
        label: 'Entrada',
        ayuda: 'Llegó mercancía',
        icono: ArrowDownToLine,
    },
    {
        value: 'salida',
        label: 'Salida',
        ayuda: 'Merma, muestra, daño',
        icono: ArrowUpFromLine,
    },
    {
        value: 'ajuste',
        label: 'Ajuste ±',
        ayuda: 'Corrección con signo',
        icono: SlidersHorizontal,
    },
    {
        value: 'establecer',
        label: 'Conteo físico',
        ayuda: 'Fijar existencia real',
        icono: Scale,
    },
] as const;

const form = useForm({
    producto_id: null as number | null,
    operacion: 'entrada' as (typeof operaciones)[number]['value'],
    cantidad: '' as number | '',
    motivo: '',
});

watch(abierto, (valor) => {
    if (valor) {
        form.reset();
        form.clearErrors();
        form.producto_id = props.producto?.id ?? null;
    }
});

const seleccionado = computed<ProductoInventario | null>(() => {
    if (props.producto) {
        return props.producto;
    }

    return props.productos?.find((p) => p.id === form.producto_id) ?? null;
});

const resultado = computed<number | null>(() => {
    const actual = seleccionado.value?.stock;
    const cantidad = Number(form.cantidad);

    if (
        actual === undefined ||
        form.cantidad === '' ||
        Number.isNaN(cantidad)
    ) {
        return null;
    }

    switch (form.operacion) {
        case 'entrada':
            return actual + Math.abs(cantidad);
        case 'salida':
            return actual - Math.abs(cantidad);
        case 'ajuste':
            return actual + cantidad;
        default:
            return cantidad;
    }
});

function guardar(): void {
    form.post(ajustar().url, {
        preserveScroll: true,
        onSuccess: () => {
            abierto.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="abierto">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="font-display text-xl"
                    >Movimiento de inventario</DialogTitle
                >
                <DialogDescription>
                    Cada cambio queda registrado en la bitácora con tu usuario y
                    el motivo.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="guardar">
                <div v-if="producto" class="rounded-lg border bg-surface p-3">
                    <p class="font-semibold">{{ producto.nombre }}</p>
                    <p class="text-xs text-muted-foreground">
                        SKU {{ producto.sku }} · Existencia actual:
                        <span class="font-semibold text-foreground">{{
                            producto.stock
                        }}</span>
                    </p>
                </div>
                <div v-else class="grid gap-2">
                    <Label for="ajuste-producto">Producto</Label>
                    <NativeSelect
                        id="ajuste-producto"
                        v-model="form.producto_id"
                        class="w-full"
                    >
                        <NativeSelectOption :value="null"
                            >Selecciona un producto…</NativeSelectOption
                        >
                        <NativeSelectOption
                            v-for="p in productos ?? []"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.nombre }} · {{ p.sku }} ({{ p.stock }})
                        </NativeSelectOption>
                    </NativeSelect>
                    <InputError :message="form.errors.producto_id" />
                </div>

                <fieldset class="grid grid-cols-2 gap-2">
                    <legend class="sr-only">Tipo de movimiento</legend>
                    <label
                        v-for="op in operaciones"
                        :key="op.value"
                        class="flex cursor-pointer items-start gap-2 rounded-lg border p-3 transition has-[:checked]:border-brand-green has-[:checked]:bg-brand-green-soft"
                    >
                        <input
                            v-model="form.operacion"
                            type="radio"
                            name="operacion"
                            :value="op.value"
                            class="sr-only"
                        />
                        <component
                            :is="op.icono"
                            class="mt-0.5 size-4 shrink-0 text-brand-deep"
                        />
                        <span>
                            <span class="block text-sm font-semibold">{{
                                op.label
                            }}</span>
                            <span class="block text-xs text-muted-foreground">{{
                                op.ayuda
                            }}</span>
                        </span>
                    </label>
                </fieldset>
                <InputError :message="form.errors.operacion" />

                <div class="grid gap-2">
                    <Label for="ajuste-cantidad">
                        {{
                            form.operacion === 'establecer'
                                ? 'Existencia contada'
                                : form.operacion === 'ajuste'
                                  ? 'Cantidad (usa negativo para restar)'
                                  : 'Cantidad'
                        }}
                    </Label>
                    <Input
                        id="ajuste-cantidad"
                        v-model.number="form.cantidad"
                        type="number"
                        inputmode="numeric"
                        :min="
                            form.operacion === 'ajuste'
                                ? undefined
                                : form.operacion === 'establecer'
                                  ? 0
                                  : 1
                        "
                        step="1"
                        required
                    />
                    <InputError :message="form.errors.cantidad" />
                    <p
                        v-if="resultado !== null && seleccionado"
                        class="text-xs"
                        :class="
                            resultado < 0
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                        "
                    >
                        Quedará en
                        <span class="font-semibold">{{ resultado }}</span>
                        unidades{{
                            resultado < 0 ? ' (no se permite negativo)' : ''
                        }}.
                    </p>
                </div>

                <div class="grid gap-2">
                    <Label for="ajuste-motivo">Motivo</Label>
                    <Input
                        id="ajuste-motivo"
                        v-model="form.motivo"
                        maxlength="255"
                        placeholder="Ej. Compra a proveedor, conteo mensual, pieza dañada…"
                        required
                    />
                    <InputError :message="form.errors.motivo" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="abierto = false"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="form.processing"
                        >Registrar movimiento</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
