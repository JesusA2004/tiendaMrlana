<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    CornerDownRight,
    FolderTree,
    ImageOff,
    Pencil,
    Plus,
    Power,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CampoTextarea from '@/components/admin/catalogo/CampoTextarea.vue';
import ConfirmarAccion from '@/components/admin/catalogo/ConfirmarAccion.vue';
import EstadoBadge from '@/components/admin/catalogo/EstadoBadge.vue';
import { visitarConFiltros } from '@/components/admin/catalogo/formato';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import {
    destroy,
    estado as cambiarEstado,
    index as categoriasIndex,
    store,
    update,
} from '@/routes/admin/categorias';
import { index as productosIndex } from '@/routes/admin/productos';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Categorías', href: categoriasIndex().url }],
    },
});

interface CategoriaFila {
    id: number;
    nombre: string;
    slug: string;
    descripcion: string | null;
    categoria_padre_id: number | null;
    padre: string | null;
    nivel: number;
    imagen: string | null;
    activa: boolean;
    orden: number;
    productos_count: number;
    hijas_count: number;
}

const props = defineProps<{
    categorias: CategoriaFila[];
    padres: { id: number; nombre: string; categoria_padre_id: number | null }[];
    filtros: { q: string; estado: string };
}>();

const filtros = ref({ ...props.filtros });

watch(
    () => props.filtros,
    (nuevos) => (filtros.value = { ...nuevos }),
);

function aplicar(): void {
    visitarConFiltros(categoriasIndex().url, filtros.value);
}

const buscar = useDebounceFn(aplicar, 350);

// ------------------------------------------------------------ Formulario
const panelAbierto = ref(false);
const editando = ref<CategoriaFila | null>(null);
const imagenActual = ref<string | null>(null);
const vistaPrevia = ref<string | null>(null);

const form = useForm({
    nombre: '',
    slug: '',
    categoria_padre_id: null as number | null,
    descripcion: '',
    orden: 0,
    activa: true,
    imagen: null as File | null,
    quitar_imagen: false,
});

function limpiarVista(): void {
    if (vistaPrevia.value) {
        URL.revokeObjectURL(vistaPrevia.value);
    }

    vistaPrevia.value = null;
}

onBeforeUnmount(limpiarVista);

function abrir(categoria: CategoriaFila | null = null): void {
    editando.value = categoria;
    form.clearErrors();
    form.nombre = categoria?.nombre ?? '';
    form.slug = categoria?.slug ?? '';
    form.categoria_padre_id = categoria?.categoria_padre_id ?? null;
    form.descripcion = categoria?.descripcion ?? '';
    form.orden = categoria?.orden ?? 0;
    form.activa = categoria?.activa ?? true;
    form.imagen = null;
    form.quitar_imagen = false;
    imagenActual.value = categoria?.imagen ?? null;
    limpiarVista();
    panelAbierto.value = true;
}

/** Excluye la propia categoría y sus descendientes como posibles padres. */
const padresDisponibles = computed(() => {
    if (!editando.value) {
        return props.padres;
    }

    const excluidos = new Set<number>([editando.value.id]);
    let cambio = true;

    while (cambio) {
        cambio = false;

        for (const c of props.padres) {
            if (
                c.categoria_padre_id !== null &&
                excluidos.has(c.categoria_padre_id) &&
                !excluidos.has(c.id)
            ) {
                excluidos.add(c.id);
                cambio = true;
            }
        }
    }

    return props.padres.filter((c) => !excluidos.has(c.id));
});

function elegirImagen(evento: Event): void {
    const archivo = (evento.target as HTMLInputElement).files?.[0] ?? null;
    limpiarVista();
    form.imagen = archivo;

    if (archivo) {
        vistaPrevia.value = URL.createObjectURL(archivo);
        form.quitar_imagen = false;
    }
}

function guardar(): void {
    const opciones = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            panelAbierto.value = false;
            limpiarVista();
        },
    };

    if (editando.value) {
        form.transform((datos) => ({ ...datos, _method: 'put' })).post(
            update(editando.value.id).url,
            opciones,
        );
    } else {
        form.transform((datos) => datos).post(store().url, opciones);
    }
}

// ------------------------------------------------------------ Acciones
const procesando = ref(false);
const porEliminar = ref<CategoriaFila | null>(null);
const confirmarAbierto = ref(false);

function alternar(categoria: CategoriaFila): void {
    router.patch(
        cambiarEstado(categoria.id).url,
        {},
        {
            preserveScroll: true,
            onStart: () => (procesando.value = true),
            onFinish: () => (procesando.value = false),
            onSuccess: () => (confirmarAbierto.value = false),
        },
    );
}

function pedirEliminar(categoria: CategoriaFila): void {
    porEliminar.value = categoria;
    confirmarAbierto.value = true;
}

const bloqueada = computed(
    () =>
        !!porEliminar.value &&
        (porEliminar.value.productos_count > 0 ||
            porEliminar.value.hijas_count > 0),
);

function confirmar(): void {
    if (!porEliminar.value) {
        return;
    }

    if (bloqueada.value) {
        if (porEliminar.value.activa) {
            alternar(porEliminar.value);
        } else {
            confirmarAbierto.value = false;
        }

        return;
    }

    router.delete(destroy(porEliminar.value.id).url, {
        preserveScroll: true,
        onStart: () => (procesando.value = true),
        onFinish: () => (procesando.value = false),
        onSuccess: () => (confirmarAbierto.value = false),
    });
}
</script>

<template>
    <Head title="Categorías" />

    <div class="w-full animate-page-in space-y-6 p-4 md:p-6">
        <header
            class="flex flex-col gap-4 rounded-2xl bg-brand-deep-pattern px-5 py-6 text-white md:flex-row md:items-end md:justify-between md:px-8"
        >
            <div>
                <p
                    class="text-xs font-semibold tracking-[0.2em] text-brand-green uppercase"
                >
                    Catálogo
                </p>
                <h1 class="font-display text-3xl font-bold md:text-4xl">
                    Categorías
                </h1>
                <p class="mt-1 max-w-xl text-sm text-white/75">
                    Organiza el catálogo en categorías y subcategorías. Las
                    categorías sin productos publicados no se muestran en la
                    tienda.
                </p>
            </div>
            <Button @click="abrir()"><Plus /> Nueva categoría</Button>
        </header>

        <section
            class="flex animate-fade-up flex-col gap-3 surface-card rounded-xl border bg-card p-4 sm:flex-row"
            aria-label="Filtros"
        >
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filtros.q"
                    type="search"
                    class="pl-9"
                    placeholder="Buscar categoría…"
                    aria-label="Buscar categoría"
                    @input="buscar"
                />
            </div>
            <NativeSelect
                v-model="filtros.estado"
                aria-label="Estado"
                class="sm:w-48"
                @change="aplicar"
            >
                <NativeSelectOption value="">Todas</NativeSelectOption>
                <NativeSelectOption value="activas">Activas</NativeSelectOption>
                <NativeSelectOption value="inactivas"
                    >Inactivas</NativeSelectOption
                >
            </NativeSelect>
        </section>

        <div
            v-if="!categorias.length"
            class="rounded-2xl border border-dashed bg-surface/60 px-6 py-14 text-center"
        >
            <FolderTree
                class="mx-auto size-10 animate-scale-in text-brand-green-strong"
            />
            <p class="mt-3 font-display text-xl font-semibold">
                No hay categorías
            </p>
        </div>

        <ul
            v-else
            class="stagger-children divide-y overflow-hidden surface-card rounded-xl border bg-card"
        >
            <li
                v-for="c in categorias"
                :key="c.id"
                class="group flex flex-col gap-3 p-4 transition-colors duration-200 hover:bg-brand-green-soft/60 sm:flex-row sm:items-center"
                :style="{ paddingLeft: `${1 + c.nivel * 1.5}rem` }"
            >
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <CornerDownRight
                        v-if="c.nivel > 0"
                        class="size-4 shrink-0 text-muted-foreground transition-colors group-hover:text-brand-green-strong"
                    />
                    <div
                        class="grid size-12 shrink-0 place-items-center overflow-hidden rounded-lg border bg-muted transition-shadow duration-300 group-hover:shadow-brand"
                    >
                        <img
                            v-if="c.imagen"
                            :src="c.imagen"
                            :alt="c.nombre"
                            class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                            loading="lazy"
                        />
                        <ImageOff v-else class="size-4 text-muted-foreground" />
                    </div>
                    <div class="min-w-0">
                        <p
                            class="flex flex-wrap items-center gap-2 font-semibold transition-colors group-hover:text-brand-green-strong"
                        >
                            {{ c.nombre }}
                            <EstadoBadge
                                :tono="c.activa ? 'success' : 'neutral'"
                                :etiqueta="c.activa ? 'Activa' : 'Inactiva'"
                            />
                        </p>
                        <p class="text-xs text-muted-foreground">
                            /{{ c.slug }} · orden {{ c.orden }}
                            <span v-if="c.hijas_count">
                                · {{ c.hijas_count }} subcategorías</span
                            >
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:justify-end">
                    <a
                        :href="
                            productosIndex({ query: { categoria_id: c.id } })
                                .url
                        "
                        class="rounded-full bg-muted px-2.5 py-1 text-xs font-semibold tabular-nums transition-colors hover:bg-brand-green-soft hover:text-brand-green-strong"
                        >{{ c.productos_count }} productos</a
                    >
                    <Button
                        size="icon-sm"
                        variant="ghost"
                        aria-label="Editar"
                        @click="abrir(c)"
                    >
                        <Pencil />
                    </Button>
                    <Button
                        size="icon-sm"
                        variant="ghost"
                        :aria-label="c.activa ? 'Desactivar' : 'Activar'"
                        :title="c.activa ? 'Desactivar' : 'Activar'"
                        :disabled="procesando"
                        @click="alternar(c)"
                    >
                        <Power
                            :class="c.activa ? 'text-brand-green-strong' : ''"
                        />
                    </Button>
                    <Button
                        size="icon-sm"
                        variant="ghost"
                        class="text-destructive hover:text-destructive"
                        aria-label="Eliminar"
                        @click="pedirEliminar(c)"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </li>
        </ul>
    </div>

    <Sheet v-model:open="panelAbierto">
        <SheetContent class="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
                <SheetTitle class="font-display text-xl">
                    {{ editando ? 'Editar categoría' : 'Nueva categoría' }}
                </SheetTitle>
                <SheetDescription>
                    El identificador web se genera desde el nombre si lo dejas
                    vacío.
                </SheetDescription>
            </SheetHeader>

            <form class="grid gap-4 px-4 pb-6" @submit.prevent="guardar">
                <div class="grid gap-2">
                    <Label for="cat-nombre">Nombre *</Label>
                    <Input
                        id="cat-nombre"
                        v-model="form.nombre"
                        maxlength="150"
                        required
                    />
                    <InputError :message="form.errors.nombre" />
                </div>
                <div class="grid gap-2">
                    <Label for="cat-slug">Identificador web</Label>
                    <Input
                        id="cat-slug"
                        v-model="form.slug"
                        maxlength="170"
                        placeholder="sala"
                    />
                    <InputError :message="form.errors.slug" />
                </div>
                <div class="grid gap-2">
                    <Label for="cat-padre">Categoría padre</Label>
                    <NativeSelect
                        id="cat-padre"
                        v-model="form.categoria_padre_id"
                        class="w-full"
                    >
                        <NativeSelectOption :value="null"
                            >Ninguna (principal)</NativeSelectOption
                        >
                        <NativeSelectOption
                            v-for="p in padresDisponibles"
                            :key="p.id"
                            :value="p.id"
                            >{{ p.nombre }}</NativeSelectOption
                        >
                    </NativeSelect>
                    <InputError :message="form.errors.categoria_padre_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="cat-descripcion">Descripción</Label>
                    <CampoTextarea
                        id="cat-descripcion"
                        v-model="form.descripcion"
                        :rows="3"
                        :maxlength="2000"
                    />
                    <InputError :message="form.errors.descripcion" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="cat-orden">Orden</Label>
                        <Input
                            id="cat-orden"
                            v-model.number="form.orden"
                            type="number"
                            min="0"
                            max="9999"
                        />
                        <InputError :message="form.errors.orden" />
                    </div>
                    <label class="flex items-end justify-between gap-2 pb-2">
                        <span class="text-sm font-medium">Activa</span>
                        <Switch v-model="form.activa" />
                    </label>
                </div>
                <div class="grid gap-2">
                    <Label for="cat-imagen">Imagen (opcional)</Label>
                    <div class="flex items-center gap-3">
                        <img
                            v-if="
                                vistaPrevia ||
                                (imagenActual && !form.quitar_imagen)
                            "
                            :src="vistaPrevia ?? imagenActual ?? ''"
                            alt="Vista previa"
                            class="size-16 rounded-lg object-cover"
                        />
                        <Input
                            id="cat-imagen"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            @change="elegirImagen"
                        />
                    </div>
                    <label
                        v-if="imagenActual && !vistaPrevia"
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <input v-model="form.quitar_imagen" type="checkbox" />
                        Quitar imagen actual
                    </label>
                    <p class="text-xs text-muted-foreground">
                        JPG, PNG o WebP, máx. 5 MB.
                    </p>
                    <InputError :message="form.errors.imagen" />
                </div>
                <Button type="submit" :disabled="form.processing" class="mt-2">
                    {{ editando ? 'Guardar cambios' : 'Crear categoría' }}
                </Button>
            </form>
        </SheetContent>
    </Sheet>

    <ConfirmarAccion
        v-model:open="confirmarAbierto"
        :titulo="
            bloqueada
                ? 'No se puede eliminar'
                : `¿Eliminar «${porEliminar?.nombre ?? ''}»?`
        "
        :descripcion="
            bloqueada
                ? `Tiene ${porEliminar?.productos_count ?? 0} producto(s) y ${porEliminar?.hijas_count ?? 0} subcategoría(s). ${porEliminar?.activa ? 'Puedes desactivarla para ocultarla sin perder la información.' : 'Ya está desactivada.'}`
                : 'Esta acción no se puede deshacer.'
        "
        :confirmar="
            bloqueada
                ? porEliminar?.activa
                    ? 'Desactivar'
                    : 'Entendido'
                : 'Eliminar'
        "
        :destructiva="!bloqueada"
        :procesando="procesando"
        @confirmar="confirmar"
    />
</template>
