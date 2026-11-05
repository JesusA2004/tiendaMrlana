<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { BadgePercent, Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CampoForm from '@/components/admin/CampoForm.vue';
import ConfirmarAccion from '@/components/admin/ConfirmarAccion.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import { dinero, fechaHora } from '@/components/admin/formato';
import PageHeader from '@/components/admin/PageHeader.vue';
import Paginacion from '@/components/admin/Paginacion.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Opcion, Paginado } from '@/components/admin/types';
import { useFiltros } from '@/components/admin/useFiltros';
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
import { NativeSelect } from '@/components/ui/native-select';
import { Switch } from '@/components/ui/switch';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ofertas', href: '/admin/ofertas' }],
    },
});

type Vigencia = 'vigente' | 'programada' | 'vencida' | 'inactiva';
type Aplica = 'productos' | 'categoria' | 'marca';

type Oferta = {
    id: number;
    nombre: string;
    tipo: string;
    valor: number;
    aplica_a: Aplica;
    categoria_id: number | null;
    categoria_nombre: string | null;
    marca_id: number | null;
    marca_nombre: string | null;
    productos_ids: number[];
    productos_nombres: string[];
    inicia_en: string | null;
    termina_en: string | null;
    activa: boolean;
    vigencia: Vigencia;
};

type Item = { id: number; nombre: string };
type ProductoItem = Item & { estado_publicacion: string | null };

const props = defineProps<{
    ofertas: Paginado<Oferta>;
    tipos: Opcion[];
    aplicaOptions: Opcion[];
    categorias: Item[];
    marcas: Item[];
    productos: ProductoItem[];
    filtros: { buscar: string; estado: string };
}>();

const { filtros } = useFiltros(
    '/admin/ofertas',
    { buscar: props.filtros.buscar ?? '', estado: props.filtros.estado ?? '' },
    { textos: ['buscar'] },
);

const VIGENCIA: Record<Vigencia, { label: string; color: string }> = {
    vigente: { label: 'Vigente', color: 'success' },
    programada: { label: 'Programada', color: 'info' },
    vencida: { label: 'Vencida', color: 'neutral' },
    inactiva: { label: 'Inactiva', color: 'neutral' },
};

const ESTADO_PRODUCTO: Record<string, { label: string; color: string }> = {
    publicado: { label: 'Publicado', color: 'success' },
    borrador: { label: 'Borrador', color: 'neutral' },
    pendiente_aprobacion: { label: 'Por aprobar', color: 'warning' },
    pausado: { label: 'Pausado', color: 'neutral' },
};

const valorTexto = (o: Oferta) =>
    o.tipo === 'porcentaje' ? `${o.valor}%` : dinero(o.valor);

const destino = (o: Oferta) => {
    if (o.aplica_a === 'categoria') {
        return `Categoría: ${o.categoria_nombre ?? '—'}`;
    }

    if (o.aplica_a === 'marca') {
        return `Marca: ${o.marca_nombre ?? '—'}`;
    }

    const n = o.productos_nombres.length;

    return n === 1 ? o.productos_nombres[0] : `${n} productos`;
};

const fechaLocal = (v: string | null) => (v ? fechaHora(`${v}:00-06:00`) : '');

// ----------------------------------------------------------- formulario
const dialogo = ref(false);
const editando = ref<Oferta | null>(null);
const buscarProducto = ref('');

const form = useForm({
    nombre: '',
    tipo: 'porcentaje',
    valor: '' as string | number,
    aplica_a: 'productos' as Aplica,
    categoria_id: '' as string | number,
    marca_id: '' as string | number,
    productos_ids: [] as number[],
    inicia_en: '',
    termina_en: '',
    activa: true,
});

const esPorcentaje = computed(() => form.tipo === 'porcentaje');

const productosFiltrados = computed(() => {
    const q = buscarProducto.value.trim().toLowerCase();
    const lista = q
        ? props.productos.filter((p) => p.nombre.toLowerCase().includes(q))
        : props.productos;

    return lista.slice(0, 200);
});

function alternarProducto(id: number) {
    const i = form.productos_ids.indexOf(id);

    if (i >= 0) {
        form.productos_ids.splice(i, 1);
    } else {
        form.productos_ids.push(id);
    }
}

function abrirNuevo() {
    editando.value = null;
    form.reset();
    form.clearErrors();
    buscarProducto.value = '';
    dialogo.value = true;
}

function abrirEditar(o: Oferta) {
    editando.value = o;
    form.clearErrors();
    buscarProducto.value = '';
    form.nombre = o.nombre;
    form.tipo = o.tipo;
    form.valor = o.valor;
    form.aplica_a = o.aplica_a;
    form.categoria_id = o.categoria_id ?? '';
    form.marca_id = o.marca_id ?? '';
    form.productos_ids = [...o.productos_ids];
    form.inicia_en = o.inicia_en ?? '';
    form.termina_en = o.termina_en ?? '';
    form.activa = o.activa;
    dialogo.value = true;
}

function guardar() {
    form.transform((d) => ({
        ...d,
        categoria_id:
            d.aplica_a === 'categoria' && d.categoria_id !== ''
                ? d.categoria_id
                : null,
        marca_id:
            d.aplica_a === 'marca' && d.marca_id !== '' ? d.marca_id : null,
        productos_ids: d.aplica_a === 'productos' ? d.productos_ids : [],
        inicia_en: d.inicia_en || null,
        termina_en: d.termina_en || null,
    }));

    const opciones = {
        preserveScroll: true,
        onSuccess: () => {
            dialogo.value = false;
            form.reset();
        },
    };

    if (editando.value) {
        form.put(`/admin/ofertas/${editando.value.id}`, opciones);
    } else {
        form.post('/admin/ofertas', opciones);
    }
}

function eliminar(o: Oferta) {
    router.delete(`/admin/ofertas/${o.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Ofertas" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            eyebrow="Promociones"
            title="Ofertas"
            description="Descuentos automáticos por producto, categoría o marca. Si un producto tiene varias, se aplica la de mayor descuento."
        >
            <Button @click="abrirNuevo">
                <Plus class="size-4" />
                Nueva oferta
            </Button>
        </PageHeader>

        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filtros.buscar"
                    type="search"
                    placeholder="Buscar por nombre"
                    class="pl-9"
                    aria-label="Buscar ofertas"
                />
            </div>
            <NativeSelect
                v-model="filtros.estado"
                class="sm:w-48"
                aria-label="Filtrar por estado"
            >
                <option value="">Todas</option>
                <option value="vigentes">Vigentes</option>
                <option value="inactivas">Inactivas</option>
                <option value="vencidas">Vencidas</option>
            </NativeSelect>
        </div>

        <EmptyState
            v-if="ofertas.data.length === 0"
            title="Sin ofertas"
            description="Crea una oferta para mostrar precios rebajados en la tienda."
            :icon="BadgePercent"
        >
            <Button variant="outline" @click="abrirNuevo">Crear oferta</Button>
        </EmptyState>

        <template v-else>
            <div
                class="grid stagger-children gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
            >
                <article
                    v-for="o in ofertas.data"
                    :key="o.id"
                    class="flex hover-lift flex-col gap-3 rounded-2xl border bg-card p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <p class="min-w-0 font-medium">{{ o.nombre }}</p>
                        <StatusBadge
                            :label="VIGENCIA[o.vigencia]?.label ?? o.vigencia"
                            :color="VIGENCIA[o.vigencia]?.color ?? 'neutral'"
                            :pulso="o.vigencia === 'vigente'"
                        />
                    </div>
                    <div class="flex items-end gap-3">
                        <span
                            class="rounded-xl bg-brand-gradient px-3 py-1.5 font-display text-2xl leading-none font-bold text-brand-ink"
                        >
                            -{{ valorTexto(o) }}
                        </span>
                        <span class="pb-1 text-sm text-muted-foreground">{{
                            destino(o)
                        }}</span>
                    </div>
                    <p
                        v-if="
                            o.aplica_a === 'productos' &&
                            o.productos_nombres.length > 1
                        "
                        class="line-clamp-2 text-xs text-muted-foreground"
                    >
                        {{ o.productos_nombres.join(', ') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        <template v-if="o.inicia_en || o.termina_en">
                            {{
                                o.inicia_en
                                    ? fechaLocal(o.inicia_en)
                                    : 'Desde ya'
                            }}
                            →
                            {{
                                o.termina_en
                                    ? fechaLocal(o.termina_en)
                                    : 'sin fin'
                            }}
                        </template>
                        <template v-else>Sin fecha límite</template>
                    </p>
                    <div class="mt-auto flex gap-2 border-t pt-3">
                        <Button
                            size="sm"
                            variant="outline"
                            class="flex-1"
                            @click="abrirEditar(o)"
                        >
                            <Pencil class="size-4" /> Editar
                        </Button>
                        <ConfirmarAccion
                            title="¿Eliminar oferta?"
                            description="Los precios volverán a su valor normal en la tienda."
                            confirm-label="Eliminar"
                            @confirmar="eliminar(o)"
                        >
                            <template #trigger>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="text-destructive"
                                    :aria-label="`Eliminar oferta ${o.nombre}`"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </template>
                        </ConfirmarAccion>
                    </div>
                </article>
            </div>

            <Paginacion :pagina="ofertas" />
        </template>
    </div>

    <Dialog v-model:open="dialogo">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
            <form class="grid gap-5" @submit.prevent="guardar">
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">
                        {{ editando ? 'Editar oferta' : 'Nueva oferta' }}
                    </DialogTitle>
                    <DialogDescription>
                        Las fechas están en hora del centro de México.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <CampoForm
                        class="sm:col-span-2"
                        label="Nombre"
                        for="o-nombre"
                        :error="form.errors.nombre"
                        requerido
                    >
                        <Input id="o-nombre" v-model="form.nombre" required />
                    </CampoForm>
                    <CampoForm
                        label="Tipo"
                        for="o-tipo"
                        :error="form.errors.tipo"
                        requerido
                    >
                        <NativeSelect
                            id="o-tipo"
                            v-model="form.tipo"
                            class="w-full"
                        >
                            <option
                                v-for="t in tipos"
                                :key="t.value"
                                :value="t.value"
                            >
                                {{ t.label }}
                            </option>
                        </NativeSelect>
                    </CampoForm>
                    <CampoForm
                        :label="
                            esPorcentaje ? 'Descuento (%)' : 'Descuento (MXN)'
                        "
                        for="o-valor"
                        :error="form.errors.valor"
                        :ayuda="
                            esPorcentaje
                                ? 'De 1 a 100.'
                                : 'Mayor a $0; nunca deja el precio en negativo.'
                        "
                        requerido
                    >
                        <Input
                            id="o-valor"
                            v-model="form.valor"
                            type="number"
                            :min="esPorcentaje ? 1 : 0.01"
                            :max="esPorcentaje ? 100 : undefined"
                            step="0.01"
                            required
                        />
                    </CampoForm>
                </div>

                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">Aplica a</legend>
                    <div class="grid grid-cols-3 gap-2">
                        <label
                            v-for="a in aplicaOptions"
                            :key="a.value"
                            :class="[
                                'cursor-pointer rounded-xl border p-2.5 text-center text-sm transition',
                                form.aplica_a === a.value
                                    ? 'border-brand-green bg-brand-green-soft font-semibold text-brand-green-strong'
                                    : 'hover:bg-muted/50',
                            ]"
                        >
                            <input
                                v-model="form.aplica_a"
                                type="radio"
                                :value="a.value"
                                class="sr-only"
                            />
                            {{ a.label }}
                        </label>
                    </div>
                </fieldset>

                <CampoForm
                    v-if="form.aplica_a === 'categoria'"
                    label="Categoría"
                    for="o-categoria"
                    :error="form.errors.categoria_id"
                    requerido
                >
                    <NativeSelect
                        id="o-categoria"
                        v-model="form.categoria_id"
                        class="w-full"
                    >
                        <option value="">Selecciona…</option>
                        <option
                            v-for="c in categorias"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.nombre }}
                        </option>
                    </NativeSelect>
                </CampoForm>

                <CampoForm
                    v-else-if="form.aplica_a === 'marca'"
                    label="Marca"
                    for="o-marca"
                    :error="form.errors.marca_id"
                    requerido
                >
                    <NativeSelect
                        id="o-marca"
                        v-model="form.marca_id"
                        class="w-full"
                    >
                        <option value="">Selecciona…</option>
                        <option v-for="m in marcas" :key="m.id" :value="m.id">
                            {{ m.nombre }}
                        </option>
                    </NativeSelect>
                </CampoForm>

                <div v-else class="grid gap-2">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm font-medium">Productos</span>
                        <span class="text-xs text-muted-foreground">
                            {{ form.productos_ids.length }} seleccionado(s)
                        </span>
                    </div>
                    <Input
                        v-model="buscarProducto"
                        type="search"
                        placeholder="Filtrar productos…"
                        aria-label="Filtrar productos"
                    />
                    <div
                        class="max-h-60 divide-y overflow-y-auto rounded-xl border"
                    >
                        <label
                            v-for="p in productosFiltrados"
                            :key="p.id"
                            class="flex cursor-pointer items-center gap-3 px-3 py-2 text-sm hover:bg-muted/40"
                        >
                            <input
                                type="checkbox"
                                class="size-4 accent-[var(--brand-green-strong)]"
                                :checked="form.productos_ids.includes(p.id)"
                                @change="alternarProducto(p.id)"
                            />
                            <span class="min-w-0 flex-1 truncate">{{
                                p.nombre
                            }}</span>
                            <StatusBadge
                                v-if="p.estado_publicacion"
                                :label="
                                    ESTADO_PRODUCTO[p.estado_publicacion]
                                        ?.label ?? p.estado_publicacion
                                "
                                :color="
                                    ESTADO_PRODUCTO[p.estado_publicacion]
                                        ?.color ?? 'neutral'
                                "
                            />
                        </label>
                        <p
                            v-if="productosFiltrados.length === 0"
                            class="p-3 text-center text-sm text-muted-foreground"
                        >
                            Sin coincidencias.
                        </p>
                    </div>
                    <p
                        v-if="form.errors.productos_ids"
                        class="text-xs font-medium text-destructive"
                        role="alert"
                    >
                        {{ form.errors.productos_ids }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <CampoForm
                        label="Inicia"
                        for="o-inicia"
                        :error="form.errors.inicia_en"
                    >
                        <Input
                            id="o-inicia"
                            v-model="form.inicia_en"
                            type="datetime-local"
                        />
                    </CampoForm>
                    <CampoForm
                        label="Termina"
                        for="o-termina"
                        :error="form.errors.termina_en"
                    >
                        <Input
                            id="o-termina"
                            v-model="form.termina_en"
                            type="datetime-local"
                        />
                    </CampoForm>
                    <div
                        class="flex items-center gap-3 self-end rounded-xl border p-3"
                    >
                        <Switch
                            id="o-activa"
                            :model-value="form.activa"
                            @update:model-value="
                                (v: boolean) => (form.activa = v)
                            "
                        />
                        <label for="o-activa" class="text-sm font-medium"
                            >Activa</label
                        >
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="dialogo = false"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="form.processing">
                        {{ editando ? 'Guardar cambios' : 'Crear oferta' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
