<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ArrowUpRight, Loader2, Search, X } from 'lucide-vue-next';
import { computed, onMounted, ref, useId, watch } from 'vue';
import { moneda, urls } from './tienda';

interface SugerenciaProducto {
    id: number;
    nombre: string;
    slug: string;
    imagen: string | null;
    precio_final: number | null;
    categoria: string | null;
}

interface SugerenciaCategoria {
    nombre: string;
    slug: string;
}

const props = withDefaults(
    defineProps<{
        valorInicial?: string | null;
        autofocus?: boolean;
        variante?: 'barra' | 'panel';
    }>(),
    { valorInicial: '', autofocus: false, variante: 'barra' },
);

const emit = defineEmits<{ navegar: [] }>();

const id = useId();
const listaId = `sugerencias-${id}`;
const texto = ref(props.valorInicial ?? '');
const campo = ref<HTMLInputElement | null>(null);

// `autofocus` sólo aplica al cargar la página; el panel móvil se monta después.
onMounted(() => {
    if (props.autofocus) {
        campo.value?.focus();
    }
});
const abierto = ref(false);
const cargando = ref(false);
const productos = ref<SugerenciaProducto[]>([]);
const categorias = ref<SugerenciaCategoria[]>([]);
const activo = ref(-1);
let controlador: AbortController | null = null;

watch(
    () => props.valorInicial,
    (v) => {
        texto.value = v ?? '';
    },
);

type Opcion =
    | { tipo: 'categoria'; url: string; etiqueta: string }
    | {
          tipo: 'producto';
          url: string;
          etiqueta: string;
          item: SugerenciaProducto;
      }
    | { tipo: 'todo'; url: string; etiqueta: string };

const opciones = computed<Opcion[]>(() => {
    const q = texto.value.trim();

    if (q.length < 2) {
        return [];
    }

    return [
        ...categorias.value.map((c) => ({
            tipo: 'categoria' as const,
            url: urls.categoria(c.slug),
            etiqueta: c.nombre,
        })),
        ...productos.value.map((p) => ({
            tipo: 'producto' as const,
            url: urls.producto(p.slug),
            etiqueta: p.nombre,
            item: p,
        })),
        {
            tipo: 'todo' as const,
            url: urls.buscar(q),
            etiqueta: `Ver todos los resultados para “${q}”`,
        },
    ];
});

const buscarSugerencias = useDebounceFn(async (q: string) => {
    controlador?.abort();

    if (q.length < 2) {
        productos.value = [];
        categorias.value = [];
        cargando.value = false;

        return;
    }

    controlador = new AbortController();
    cargando.value = true;

    try {
        const respuesta = await fetch(
            `${urls.sugerencias}?q=${encodeURIComponent(q)}`,
            {
                headers: { Accept: 'application/json' },
                signal: controlador.signal,
            },
        );

        if (respuesta.ok) {
            const datos = (await respuesta.json()) as {
                productos: SugerenciaProducto[];
                categorias: SugerenciaCategoria[];
            };
            productos.value = datos.productos ?? [];
            categorias.value = datos.categorias ?? [];
            activo.value = -1;
        }
    } catch {
        // Cancelada o sin red: se conservan las sugerencias anteriores.
    } finally {
        cargando.value = false;
    }
}, 220);

watch(texto, (v) => {
    abierto.value = true;
    buscarSugerencias(v.trim());
});

function ir(url: string) {
    abierto.value = false;
    emit('navegar');
    router.visit(url);
}

function enviar() {
    const opcion = activo.value >= 0 ? opciones.value[activo.value] : null;

    if (opcion) {
        ir(opcion.url);

        return;
    }

    const q = texto.value.trim();
    ir(q ? urls.buscar(q) : urls.catalogo);
}

function mover(delta: number) {
    if (!opciones.value.length) {
        return;
    }

    abierto.value = true;
    const total = opciones.value.length;
    activo.value = (activo.value + delta + total) % total;
}

function limpiar() {
    texto.value = '';
    productos.value = [];
    categorias.value = [];
}

function cerrarConRetraso() {
    window.setTimeout(() => {
        abierto.value = false;
    }, 150);
}
</script>

<template>
    <form role="search" class="relative w-full" @submit.prevent="enviar">
        <label :for="`buscar-${id}`" class="sr-only">Buscar productos</label>
        <div
            class="group/search flex items-center gap-2 rounded-full border bg-background pr-1.5 pl-4 transition-[border-color,box-shadow] focus-within:border-brand-green focus-within:shadow-[0_0_0_4px_rgba(115,200,58,0.18)]"
            :class="
                variante === 'panel'
                    ? 'h-12 border-border'
                    : 'h-11 border-border/80'
            "
        >
            <Search
                class="size-[18px] shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <input
                ref="campo"
                :id="`buscar-${id}`"
                v-model="texto"
                type="search"
                inputmode="search"
                autocomplete="off"
                enterkeyhint="search"
                :autofocus="autofocus"
                placeholder="Busca por nombre, modelo o SKU"
                class="h-full min-w-0 flex-1 bg-transparent text-sm text-foreground outline-none placeholder:text-muted-foreground [&::-webkit-search-cancel-button]:hidden"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="abierto && opciones.length > 0"
                :aria-controls="listaId"
                :aria-activedescendant="
                    activo >= 0 ? `${listaId}-${activo}` : undefined
                "
                @focus="abierto = true"
                @blur="cerrarConRetraso"
                @keydown.down.prevent="mover(1)"
                @keydown.up.prevent="mover(-1)"
                @keydown.esc="abierto = false"
            />
            <Loader2
                v-if="cargando"
                class="size-4 shrink-0 animate-spin text-muted-foreground"
                aria-hidden="true"
            />
            <button
                v-else-if="texto"
                type="button"
                class="inline-flex size-8 shrink-0 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                aria-label="Borrar búsqueda"
                @click="limpiar"
            >
                <X class="size-4" />
            </button>
            <button
                type="submit"
                class="inline-flex h-8 shrink-0 items-center rounded-full bg-brand-ink px-4 text-xs font-semibold text-white transition-colors hover:bg-brand-deep"
            >
                Buscar
            </button>
        </div>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0 -translate-y-1"
        >
            <ul
                v-if="abierto && opciones.length > 0"
                :id="listaId"
                role="listbox"
                class="absolute inset-x-0 top-[calc(100%+8px)] z-50 max-h-[70vh] overflow-auto rounded-2xl border border-border bg-popover p-2 text-popover-foreground shadow-xl"
            >
                <li
                    v-for="(opcion, indice) in opciones"
                    :id="`${listaId}-${indice}`"
                    :key="`${opcion.tipo}-${opcion.url}`"
                    role="option"
                    :aria-selected="activo === indice"
                    class="cursor-pointer rounded-xl"
                    :class="
                        activo === indice ? 'bg-muted' : 'hover:bg-muted/70'
                    "
                    @mousedown.prevent="ir(opcion.url)"
                    @mouseenter="activo = indice"
                >
                    <div
                        v-if="opcion.tipo === 'producto'"
                        class="flex items-center gap-3 px-2 py-2"
                    >
                        <div
                            class="size-11 shrink-0 overflow-hidden rounded-lg bg-surface"
                        >
                            <img
                                v-if="opcion.item.imagen"
                                :src="opcion.item.imagen"
                                alt=""
                                class="size-full object-cover"
                                loading="lazy"
                                decoding="async"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ opcion.etiqueta }}
                            </p>
                            <p
                                v-if="opcion.item.categoria"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ opcion.item.categoria }}
                            </p>
                        </div>
                        <span
                            v-if="opcion.item.precio_final !== null"
                            class="shrink-0 text-sm font-semibold"
                        >
                            {{ moneda(opcion.item.precio_final) }}
                        </span>
                    </div>
                    <div
                        v-else-if="opcion.tipo === 'categoria'"
                        class="flex items-center justify-between gap-2 px-3 py-2.5 text-sm"
                    >
                        <span>
                            <span class="text-muted-foreground"
                                >Categoría ·
                            </span>
                            <span class="font-medium">{{
                                opcion.etiqueta
                            }}</span>
                        </span>
                        <ArrowUpRight
                            class="size-4 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </div>
                    <div
                        v-else
                        class="flex items-center gap-2 px-3 py-2.5 text-sm font-semibold text-brand-green-strong"
                    >
                        <Search class="size-4" aria-hidden="true" />
                        {{ opcion.etiqueta }}
                    </div>
                </li>
            </ul>
        </Transition>
    </form>
</template>
