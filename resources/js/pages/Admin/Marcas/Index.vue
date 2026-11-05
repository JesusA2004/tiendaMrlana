<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import {
    BadgeCheck,
    Pencil,
    Plus,
    Power,
    Search,
    Trash2,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import ConfirmarAccion from '@/components/admin/catalogo/ConfirmarAccion.vue';
import EstadoBadge from '@/components/admin/catalogo/EstadoBadge.vue';
import { visitarConFiltros } from '@/components/admin/catalogo/formato';
import Paginacion from '@/components/admin/catalogo/Paginacion.vue';
import type { Paginado } from '@/components/admin/catalogo/types';
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
    index as marcasIndex,
    store,
    update,
} from '@/routes/admin/marcas';
import { index as productosIndex } from '@/routes/admin/productos';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Marcas', href: marcasIndex().url }],
    },
});

interface MarcaFila {
    id: number;
    nombre: string;
    slug: string;
    logo: string | null;
    activa: boolean;
    productos_count: number;
}

const props = defineProps<{
    marcas: Paginado<MarcaFila>;
    filtros: { q: string; estado: string };
}>();

const filtros = ref({ ...props.filtros });

watch(
    () => props.filtros,
    (nuevos) => (filtros.value = { ...nuevos }),
);

function aplicar(): void {
    visitarConFiltros(marcasIndex().url, filtros.value);
}

const buscar = useDebounceFn(aplicar, 350);

// ------------------------------------------------------------ Formulario
const panelAbierto = ref(false);
const editando = ref<MarcaFila | null>(null);
const vistaPrevia = ref<string | null>(null);

const form = useForm({
    nombre: '',
    slug: '',
    activa: true,
    logo: null as File | null,
    quitar_logo: false,
});

function limpiarVista(): void {
    if (vistaPrevia.value) {
        URL.revokeObjectURL(vistaPrevia.value);
    }

    vistaPrevia.value = null;
}

onBeforeUnmount(limpiarVista);

function abrir(marca: MarcaFila | null = null): void {
    editando.value = marca;
    form.clearErrors();
    form.nombre = marca?.nombre ?? '';
    form.slug = marca?.slug ?? '';
    form.activa = marca?.activa ?? true;
    form.logo = null;
    form.quitar_logo = false;
    limpiarVista();
    panelAbierto.value = true;
}

function elegirLogo(evento: Event): void {
    const archivo = (evento.target as HTMLInputElement).files?.[0] ?? null;
    limpiarVista();
    form.logo = archivo;

    if (archivo) {
        vistaPrevia.value = URL.createObjectURL(archivo);
        form.quitar_logo = false;
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
const porEliminar = ref<MarcaFila | null>(null);
const confirmarAbierto = ref(false);
const bloqueada = computed(() => (porEliminar.value?.productos_count ?? 0) > 0);

const visita = {
    preserveScroll: true,
    onStart: () => (procesando.value = true),
    onFinish: () => (procesando.value = false),
    onSuccess: () => (confirmarAbierto.value = false),
};

function alternar(marca: MarcaFila): void {
    router.patch(cambiarEstado(marca.id).url, {}, visita);
}

function pedirEliminar(marca: MarcaFila): void {
    porEliminar.value = marca;
    confirmarAbierto.value = true;
}

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

    router.delete(destroy(porEliminar.value.id).url, visita);
}
</script>

<template>
    <Head title="Marcas" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-4 md:p-6">
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
                    Marcas
                </h1>
                <p class="mt-1 max-w-xl text-sm text-white/75">
                    Registra sólo marcas que Mr Lana comercializa con
                    autorización. Los modelos de referencia del catálogo no
                    llevan marca asignada.
                </p>
            </div>
            <Button @click="abrir()"><Plus /> Nueva marca</Button>
        </header>

        <section
            class="flex flex-col gap-3 rounded-xl border bg-card p-4 sm:flex-row"
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
                    placeholder="Buscar marca…"
                    aria-label="Buscar marca"
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
            v-if="!marcas.data.length"
            class="rounded-2xl border border-dashed bg-surface/60 px-6 py-14 text-center"
        >
            <BadgeCheck class="mx-auto size-10 text-brand-green" />
            <p class="mt-3 font-display text-xl font-semibold">
                No hay marcas registradas
            </p>
        </div>

        <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <li
                v-for="m in marcas.data"
                :key="m.id"
                class="flex flex-col gap-3 rounded-xl border bg-card p-4"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="grid size-14 shrink-0 place-items-center overflow-hidden rounded-xl border bg-white"
                    >
                        <img
                            v-if="m.logo"
                            :src="m.logo"
                            :alt="`Logo de ${m.nombre}`"
                            class="size-full object-contain p-1"
                            loading="lazy"
                        />
                        <span
                            v-else
                            class="font-display text-xl font-bold text-brand-deep"
                            >{{ m.nombre.charAt(0) }}</span
                        >
                    </div>
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ m.nombre }}</p>
                        <p class="text-xs text-muted-foreground">
                            /{{ m.slug }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <EstadoBadge
                        :tono="m.activa ? 'success' : 'neutral'"
                        :etiqueta="m.activa ? 'Activa' : 'Inactiva'"
                    />
                    <a
                        :href="
                            productosIndex({ query: { marca_id: m.id } }).url
                        "
                        class="text-xs font-semibold text-brand-green-strong hover:underline"
                        >{{ m.productos_count }} productos</a
                    >
                </div>
                <div class="flex gap-1 border-t pt-3">
                    <Button size="sm" variant="ghost" @click="abrir(m)"
                        ><Pencil /> Editar</Button
                    >
                    <Button
                        size="sm"
                        variant="ghost"
                        :disabled="procesando"
                        @click="alternar(m)"
                        ><Power />
                        {{ m.activa ? 'Desactivar' : 'Activar' }}</Button
                    >
                    <Button
                        size="icon-sm"
                        variant="ghost"
                        class="ml-auto text-destructive hover:text-destructive"
                        aria-label="Eliminar"
                        @click="pedirEliminar(m)"
                        ><Trash2
                    /></Button>
                </div>
            </li>
        </ul>

        <Paginacion
            :links="marcas.links"
            :from="marcas.from"
            :to="marcas.to"
            :total="marcas.total"
            etiqueta="marcas"
        />
    </div>

    <Sheet v-model:open="panelAbierto">
        <SheetContent class="w-full overflow-y-auto sm:max-w-md">
            <SheetHeader>
                <SheetTitle class="font-display text-xl">
                    {{ editando ? 'Editar marca' : 'Nueva marca' }}
                </SheetTitle>
                <SheetDescription>
                    El identificador web se genera desde el nombre si lo dejas
                    vacío.
                </SheetDescription>
            </SheetHeader>

            <form class="grid gap-4 px-4 pb-6" @submit.prevent="guardar">
                <div class="grid gap-2">
                    <Label for="marca-nombre">Nombre *</Label>
                    <Input
                        id="marca-nombre"
                        v-model="form.nombre"
                        maxlength="150"
                        required
                    />
                    <InputError :message="form.errors.nombre" />
                </div>
                <div class="grid gap-2">
                    <Label for="marca-slug">Identificador web</Label>
                    <Input
                        id="marca-slug"
                        v-model="form.slug"
                        maxlength="170"
                    />
                    <InputError :message="form.errors.slug" />
                </div>
                <label class="flex items-center justify-between gap-2">
                    <span class="text-sm font-medium">Activa</span>
                    <Switch v-model="form.activa" />
                </label>
                <div class="grid gap-2">
                    <Label for="marca-logo">Logo (opcional)</Label>
                    <div class="flex items-center gap-3">
                        <img
                            v-if="
                                vistaPrevia ||
                                (editando?.logo && !form.quitar_logo)
                            "
                            :src="vistaPrevia ?? editando?.logo ?? ''"
                            alt="Vista previa del logo"
                            class="size-16 rounded-lg border bg-white object-contain p-1"
                        />
                        <Input
                            id="marca-logo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            @change="elegirLogo"
                        />
                    </div>
                    <label
                        v-if="editando?.logo && !vistaPrevia"
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <input v-model="form.quitar_logo" type="checkbox" />
                        Quitar logo actual
                    </label>
                    <InputError :message="form.errors.logo" />
                </div>
                <Button type="submit" :disabled="form.processing" class="mt-2">
                    {{ editando ? 'Guardar cambios' : 'Crear marca' }}
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
                ? `Tiene ${porEliminar?.productos_count ?? 0} producto(s) asociados. ${porEliminar?.activa ? 'Puedes desactivarla en su lugar.' : 'Ya está desactivada.'}`
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
