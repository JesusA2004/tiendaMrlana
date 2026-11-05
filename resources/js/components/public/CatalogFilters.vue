<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Slider } from '@/components/ui/slider';
import { moneda } from './tienda';
import type {
    CategoriaPublica,
    Disponibilidad,
    FiltrosCatalogo,
    MarcaPublica,
} from './types';

const props = defineProps<{
    filtros: FiltrosCatalogo;
    categorias: CategoriaPublica[];
    marcas: MarcaPublica[];
    rangoPrecio: { min: number; max: number };
}>();

const emit = defineEmits<{ cambiar: [parcial: Partial<FiltrosCatalogo>] }>();

const disponibilidades: { valor: Disponibilidad; etiqueta: string }[] = [
    { valor: 'en_stock', etiqueta: 'En existencia' },
    { valor: 'bajo_pedido', etiqueta: 'Bajo pedido' },
    { valor: 'agotado', etiqueta: 'Agotado' },
];

const verTodasCategorias = ref(false);
const verTodasMarcas = ref(false);
const LIMITE = 8;

const categoriasVisibles = computed(() =>
    verTodasCategorias.value
        ? props.categorias
        : props.categorias.slice(0, LIMITE),
);
const marcasVisibles = computed(() =>
    verTodasMarcas.value ? props.marcas : props.marcas.slice(0, LIMITE),
);

const hayRango = computed(() => props.rangoPrecio.max > props.rangoPrecio.min);
const paso = computed(() => {
    const amplitud = props.rangoPrecio.max - props.rangoPrecio.min;

    if (amplitud <= 500) {
        return 10;
    }

    if (amplitud <= 5000) {
        return 50;
    }

    return 100;
});

const rango = ref<number[]>([0, 0]);

function sincronizarRango() {
    rango.value = [
        props.filtros.precio_min ?? props.rangoPrecio.min,
        props.filtros.precio_max ?? props.rangoPrecio.max,
    ];
}

watch(
    () => [
        props.filtros.precio_min,
        props.filtros.precio_max,
        props.rangoPrecio,
    ],
    sincronizarRango,
    { immediate: true },
);

function confirmarRango(valor: number[] | undefined) {
    if (!valor || valor.length < 2) {
        return;
    }

    const [min, max] = valor;
    emit('cambiar', {
        precio_min: min > props.rangoPrecio.min ? min : null,
        precio_max: max < props.rangoPrecio.max ? max : null,
    });
}

function alternar<K extends 'categoria' | 'marca' | 'disponibilidad'>(
    clave: K,
    valor: FiltrosCatalogo[K],
) {
    emit('cambiar', {
        [clave]: props.filtros[clave] === valor ? null : valor,
    } as Partial<FiltrosCatalogo>);
}
</script>

<template>
    <div class="flex flex-col gap-8">
        <!-- Disponibilidad -->
        <fieldset>
            <legend class="mb-3 text-sm font-bold">Disponibilidad</legend>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="opcion in disponibilidades"
                    :key="opcion.valor"
                    type="button"
                    class="inline-flex h-9 items-center gap-1.5 rounded-full border px-3.5 text-sm font-medium transition-[background-color,border-color,color,transform,box-shadow] duration-200 active:scale-95 motion-reduce:active:scale-100"
                    :class="
                        filtros.disponibilidad === opcion.valor
                            ? 'border-brand-deep bg-brand-deep text-white shadow-brand-sm'
                            : 'border-border bg-card hover:-translate-y-0.5 hover:border-brand-green hover:bg-brand-green-soft motion-reduce:hover:translate-y-0'
                    "
                    :aria-pressed="filtros.disponibilidad === opcion.valor"
                    @click="alternar('disponibilidad', opcion.valor)"
                >
                    <Check
                        v-if="filtros.disponibilidad === opcion.valor"
                        class="size-3.5 animate-in duration-200 zoom-in-50"
                        aria-hidden="true"
                    />
                    {{ opcion.etiqueta }}
                </button>
            </div>
        </fieldset>

        <!-- Precio -->
        <fieldset v-if="hayRango">
            <legend class="mb-4 text-sm font-bold">Precio</legend>
            <Slider
                v-model="rango"
                :min="rangoPrecio.min"
                :max="rangoPrecio.max"
                :step="paso"
                :min-steps-between-thumbs="1"
                class="py-2 [&_[data-slot=slider-range]]:bg-brand-green [&_[data-slot=slider-thumb]]:size-5 [&_[data-slot=slider-thumb]]:border-2 [&_[data-slot=slider-thumb]]:border-brand-green"
                aria-label="Rango de precio"
                @value-commit="confirmarRango"
            />
            <div class="mt-3 flex items-center justify-between text-sm">
                <span
                    class="rounded-full bg-muted px-3 py-1 font-medium tabular-nums"
                    >{{ moneda(rango[0]) }}</span
                >
                <span class="text-muted-foreground" aria-hidden="true">—</span>
                <span
                    class="rounded-full bg-muted px-3 py-1 font-medium tabular-nums"
                    >{{ moneda(rango[1]) }}</span
                >
            </div>
        </fieldset>

        <!-- Categorías -->
        <fieldset v-if="categorias.length">
            <legend class="mb-2 text-sm font-bold">Categoría</legend>
            <ul class="flex flex-col">
                <li
                    v-for="categoria in categoriasVisibles"
                    :key="categoria.slug"
                >
                    <button
                        type="button"
                        class="flex min-h-10 w-full items-center justify-between gap-3 rounded-xl px-3 text-left text-sm transition-[background-color,color,padding] duration-200"
                        :class="
                            filtros.categoria === categoria.slug
                                ? 'bg-brand-green-soft font-semibold text-foreground'
                                : 'text-foreground/80 hover:bg-brand-green-soft/60 hover:pl-4 motion-reduce:hover:pl-3'
                        "
                        :aria-pressed="filtros.categoria === categoria.slug"
                        @click="alternar('categoria', categoria.slug)"
                    >
                        <span class="flex items-center gap-2.5">
                            <span
                                class="flex size-4 items-center justify-center rounded-full border"
                                :class="
                                    filtros.categoria === categoria.slug
                                        ? 'border-brand-green bg-brand-green'
                                        : 'border-input'
                                "
                                aria-hidden="true"
                            >
                                <span
                                    v-if="filtros.categoria === categoria.slug"
                                    class="size-1.5 rounded-full bg-brand-ink"
                                />
                            </span>
                            {{ categoria.nombre }}
                        </span>
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ categoria.total }}</span
                        >
                    </button>
                </li>
            </ul>
            <button
                v-if="categorias.length > LIMITE"
                type="button"
                class="mt-2 px-3 text-sm font-semibold text-brand-green-strong hover:underline"
                @click="verTodasCategorias = !verTodasCategorias"
            >
                {{
                    verTodasCategorias
                        ? 'Ver menos'
                        : `Ver las ${categorias.length}`
                }}
            </button>
        </fieldset>

        <!-- Marcas -->
        <fieldset v-if="marcas.length">
            <legend class="mb-2 text-sm font-bold">Marca</legend>
            <ul class="flex flex-col">
                <li v-for="marca in marcasVisibles" :key="marca.slug">
                    <button
                        type="button"
                        class="flex min-h-10 w-full items-center justify-between gap-3 rounded-xl px-3 text-left text-sm transition-[background-color,color,padding] duration-200"
                        :class="
                            filtros.marca === marca.slug
                                ? 'bg-brand-green-soft font-semibold text-foreground'
                                : 'text-foreground/80 hover:bg-brand-green-soft/60 hover:pl-4 motion-reduce:hover:pl-3'
                        "
                        :aria-pressed="filtros.marca === marca.slug"
                        @click="alternar('marca', marca.slug)"
                    >
                        <span class="flex items-center gap-2.5">
                            <span
                                class="flex size-4 items-center justify-center rounded-[5px] border"
                                :class="
                                    filtros.marca === marca.slug
                                        ? 'border-brand-green bg-brand-green text-brand-ink'
                                        : 'border-input'
                                "
                                aria-hidden="true"
                            >
                                <Check
                                    v-if="filtros.marca === marca.slug"
                                    class="size-3"
                                />
                            </span>
                            {{ marca.nombre }}
                        </span>
                        <span
                            class="text-xs text-muted-foreground tabular-nums"
                            >{{ marca.total }}</span
                        >
                    </button>
                </li>
            </ul>
            <button
                v-if="marcas.length > LIMITE"
                type="button"
                class="mt-2 px-3 text-sm font-semibold text-brand-green-strong hover:underline"
                @click="verTodasMarcas = !verTodasMarcas"
            >
                {{ verTodasMarcas ? 'Ver menos' : `Ver las ${marcas.length}` }}
            </button>
        </fieldset>
    </div>
</template>
