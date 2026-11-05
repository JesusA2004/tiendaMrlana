<script setup lang="ts">
import { CircleAlert, CircleCheck } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    faltantes: string[];
}>();

/** Requisitos de Producto::faltantesParaPublicar() en lenguaje del panel. */
const requisitos = [
    { clave: 'precio', etiqueta: 'Precio de venta mayor a cero' },
    { clave: 'categoría', etiqueta: 'Categoría asignada' },
    { clave: 'imagen', etiqueta: 'Al menos una imagen autorizada' },
    { clave: 'existencias', etiqueta: 'Existencias o modalidad definida' },
];

const items = computed(() =>
    requisitos.map((r) => ({
        ...r,
        ok: !props.faltantes.some((f) => f.toLowerCase().includes(r.clave)),
    })),
);

const extras = computed(() =>
    props.faltantes.filter(
        (f) => !requisitos.some((r) => f.toLowerCase().includes(r.clave)),
    ),
);
</script>

<template>
    <ul class="space-y-2">
        <li
            v-for="item in items"
            :key="item.clave"
            class="flex items-start gap-2 text-sm"
        >
            <CircleCheck
                v-if="item.ok"
                class="mt-0.5 size-4 shrink-0 text-brand-green-strong"
                aria-hidden="true"
            />
            <CircleAlert
                v-else
                class="mt-0.5 size-4 shrink-0 text-amber-600"
                aria-hidden="true"
            />
            <span
                :class="
                    item.ok
                        ? 'text-muted-foreground line-through decoration-brand-green/50'
                        : 'font-medium'
                "
            >
                {{ item.etiqueta }}
                <span class="sr-only">{{
                    item.ok ? '(completo)' : '(pendiente)'
                }}</span>
            </span>
        </li>
        <li
            v-for="extra in extras"
            :key="extra"
            class="flex items-start gap-2 text-sm font-medium"
        >
            <CircleAlert
                class="mt-0.5 size-4 shrink-0 text-amber-600"
                aria-hidden="true"
            />
            {{ extra }}
        </li>
    </ul>
</template>
