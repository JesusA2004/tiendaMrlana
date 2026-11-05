<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Boxes,
    CheckCircle2,
    ExternalLink,
    Lock,
    Pause,
    RotateCcw,
    Save,
    Send,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AjusteInventarioDialog from '@/components/admin/catalogo/AjusteInventarioDialog.vue';
import AtributosEditor from '@/components/admin/catalogo/AtributosEditor.vue';
import CampoTextarea from '@/components/admin/catalogo/CampoTextarea.vue';
import ChecklistPublicacion from '@/components/admin/catalogo/ChecklistPublicacion.vue';
import CompletitudBar from '@/components/admin/catalogo/CompletitudBar.vue';
import ConfirmarAccion from '@/components/admin/catalogo/ConfirmarAccion.vue';
import EstadoBadge from '@/components/admin/catalogo/EstadoBadge.vue';
import {
    formatoFecha,
    formatoMoneda,
} from '@/components/admin/catalogo/formato';
import GaleriaImagenes from '@/components/admin/catalogo/GaleriaImagenes.vue';
import PanelReferencia from '@/components/admin/catalogo/PanelReferencia.vue';
import type {
    AtributoProducto,
    MovimientoInventario,
    OpcionCatalogo,
    OpcionEnum,
    ProductoDetalle,
} from '@/components/admin/catalogo/types';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Switch } from '@/components/ui/switch';
import { index as inventarioIndex } from '@/routes/admin/inventario';
import {
    destroy,
    index as productosIndex,
    store,
    update,
} from '@/routes/admin/productos';
import {
    borrador,
    enviar,
    pausar,
    publicar,
} from '@/routes/admin/productos/publicacion';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Productos', href: productosIndex().url },
            { title: 'Ficha de producto', href: '#' },
        ],
    },
});

const props = defineProps<{
    producto: ProductoDetalle | null;
    movimientos?: MovimientoInventario[];
    categorias: OpcionCatalogo[];
    marcas: OpcionCatalogo[];
    estados: OpcionEnum[];
    modalidades: OpcionEnum[];
    limites: { imagen_max_mb: number; imagenes_por_subida: number };
}>();

const p = props.producto;
const esNuevo = computed(() => props.producto === null);

const form = useForm({
    nombre: p?.nombre ?? '',
    sku: p?.sku ?? '',
    slug: p?.slug ?? '',
    categoria_id: p?.categoria_id ?? (null as number | null),
    marca_id: p?.marca_id ?? (null as number | null),
    descripcion: p?.descripcion ?? '',
    descripcion_larga: p?.descripcion_larga ?? '',
    precio: p?.precio ?? ('' as number | ''),
    precio_comparacion: p?.precio_comparacion ?? ('' as number | ''),
    costo: p?.costo ?? ('' as number | ''),
    modalidad_venta: p?.modalidad_venta ?? 'inventario',
    tiempo_entrega_dias: p?.tiempo_entrega_dias ?? ('' as number | ''),
    stock_minimo: p?.stock_minimo ?? 0,
    stock_inicial: '' as number | '',
    peso: p?.peso ?? ('' as number | ''),
    alto: p?.alto ?? ('' as number | ''),
    ancho: p?.ancho ?? ('' as number | ''),
    largo: p?.largo ?? ('' as number | ''),
    modelo: p?.modelo ?? '',
    acabado: p?.acabado ?? '',
    dimensiones: p?.dimensiones ?? '',
    observaciones: p?.observaciones ?? '',
    atributos: (p?.atributos ?? []).map((a) => ({
        ...a,
    })) as AtributoProducto[],
    meta_titulo: p?.meta_titulo ?? '',
    meta_descripcion: p?.meta_descripcion ?? '',
    destacado: p?.destacado ?? false,
});

const bajoPedido = computed(() => form.modalidad_venta === 'bajo_pedido');

const margen = computed(() => {
    const precio = Number(form.precio);
    const costo = Number(form.costo);

    if (
        !precio ||
        form.costo === null ||
        String(form.costo) === '' ||
        Number.isNaN(costo)
    ) {
        return null;
    }

    return Math.round(((precio - costo) / precio) * 100);
});

const errorComparacion = computed(() => {
    if (
        form.precio_comparacion === null ||
        String(form.precio_comparacion) === ''
    ) {
        return null;
    }

    if (form.precio === null || String(form.precio) === '') {
        return 'Define primero el precio de venta.';
    }

    return Number(form.precio_comparacion) <= Number(form.precio)
        ? 'Debe ser mayor que el precio de venta.'
        : null;
});

function guardar(): void {
    const opciones = {
        preserveScroll: true,
        onSuccess: () => form.defaults(),
    };

    if (props.producto) {
        // eslint-disable-next-line @typescript-eslint/no-unused-vars
        form.transform(({ stock_inicial, ...datos }) => datos).put(
            update(props.producto.id).url,
            opciones,
        );
    } else {
        form.post(store().url, opciones);
    }
}

// ---------------------------------------------------------- Publicación
const procesando = ref(false);
const opcionesVisita = {
    preserveScroll: true,
    onStart: () => (procesando.value = true),
    onFinish: () => (procesando.value = false),
};

function accion(url: string): void {
    router.post(url, {}, opcionesVisita);
}

const ajusteAbierto = ref(false);
const eliminarAbierto = ref(false);

function eliminar(): void {
    if (!props.producto) {
        return;
    }

    router.delete(destroy(props.producto.id).url, {
        ...opcionesVisita,
        onSuccess: () => (eliminarAbierto.value = false),
    });
}

// Dominio real del sitio (APP_URL) para la vista previa en buscadores.
const dominio =
    typeof window !== 'undefined' ? window.location.host : 'tienda.mr-lana.com';

const vistaSeo = computed(() => ({
    titulo: form.meta_titulo || form.nombre || 'Título del producto',
    descripcion:
        form.meta_descripcion ||
        form.descripcion ||
        'La descripción de tu producto aparecerá aquí en los resultados de búsqueda.',
}));
</script>

<template>
    <Head :title="producto ? producto.nombre : 'Nuevo producto'" />

    <form
        class="w-full animate-page-in space-y-6 p-4 pb-0 md:p-6 md:pb-0"
        novalidate
        @submit.prevent="guardar"
    >
        <!-- Encabezado -->
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="min-w-0">
                <Link
                    :href="productosIndex().url"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft class="size-4" /> Productos
                </Link>
                <h1 class="mt-1 font-display text-3xl leading-tight font-bold">
                    {{ producto ? producto.nombre : 'Nuevo producto' }}
                </h1>
                <div
                    v-if="producto"
                    class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                >
                    <EstadoBadge
                        :tono="producto.estado.color"
                        :etiqueta="producto.estado.label"
                        punto
                    />
                    <span>SKU {{ producto.sku }}</span>
                    <span v-if="producto.importado"
                        >· Importado del catálogo 2026</span
                    >
                </div>
                <p v-else class="mt-1 text-sm text-muted-foreground">
                    Se guardará como borrador. Después podrás subir imágenes y
                    enviarlo a aprobación.
                </p>
            </div>
            <div class="hidden gap-2 sm:flex">
                <Button
                    v-if="producto?.url_publica"
                    as="a"
                    :href="producto.url_publica"
                    target="_blank"
                    rel="noopener"
                    variant="outline"
                >
                    <ExternalLink /> Ver en tienda
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <Save />
                    {{ esNuevo ? 'Crear producto' : 'Guardar cambios' }}
                </Button>
            </div>
        </header>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Columna principal -->
            <div class="stagger-children space-y-6 lg:col-span-2">
                <!-- Datos generales -->
                <section
                    class="surface-card rounded-2xl border bg-card p-4 transition-shadow duration-300 hover:shadow-brand md:p-6"
                >
                    <h2 class="font-display text-xl font-semibold">
                        Datos generales
                    </h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="nombre">Nombre *</Label>
                            <Input
                                id="nombre"
                                v-model="form.nombre"
                                maxlength="180"
                                required
                                :aria-invalid="!!form.errors.nombre"
                            />
                            <InputError :message="form.errors.nombre" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="sku">SKU</Label>
                            <Input
                                id="sku"
                                v-model="form.sku"
                                maxlength="100"
                                class="uppercase"
                                placeholder="Se genera automáticamente"
                            />
                            <InputError :message="form.errors.sku" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="slug">URL (slug)</Label>
                            <Input
                                id="slug"
                                v-model="form.slug"
                                maxlength="200"
                                placeholder="Se genera desde el nombre"
                            />
                            <InputError :message="form.errors.slug" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="categoria">Categoría</Label>
                            <NativeSelect
                                id="categoria"
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
                                    >{{ c.nombre
                                    }}{{
                                        c.activa === false ? ' (inactiva)' : ''
                                    }}</NativeSelectOption
                                >
                            </NativeSelect>
                            <InputError :message="form.errors.categoria_id" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="marca">Marca</Label>
                            <NativeSelect
                                id="marca"
                                v-model="form.marca_id"
                                class="w-full"
                            >
                                <NativeSelectOption :value="null"
                                    >Sin marca</NativeSelectOption
                                >
                                <NativeSelectOption
                                    v-for="m in marcas"
                                    :key="m.id"
                                    :value="m.id"
                                    >{{ m.nombre }}</NativeSelectOption
                                >
                            </NativeSelect>
                            <InputError :message="form.errors.marca_id" />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="descripcion">Descripción corta</Label>
                            <CampoTextarea
                                id="descripcion"
                                v-model="form.descripcion"
                                :rows="3"
                                :maxlength="5000"
                            />
                            <p class="text-xs text-muted-foreground">
                                Texto plano: el HTML se elimina al guardar.
                            </p>
                            <InputError :message="form.errors.descripcion" />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="descripcion_larga"
                                >Descripción detallada</Label
                            >
                            <CampoTextarea
                                id="descripcion_larga"
                                v-model="form.descripcion_larga"
                                :rows="6"
                                :maxlength="20000"
                            />
                            <InputError
                                :message="form.errors.descripcion_larga"
                            />
                        </div>
                        <label
                            class="flex items-center justify-between gap-3 rounded-xl border p-3 md:col-span-2"
                        >
                            <span>
                                <span class="block text-sm font-semibold"
                                    >Producto destacado</span
                                >
                                <span
                                    class="block text-xs text-muted-foreground"
                                    >Aparece primero en la portada y el
                                    catálogo.</span
                                >
                            </span>
                            <Switch v-model="form.destacado" />
                        </label>
                    </div>
                </section>

                <!-- Precio -->
                <section
                    class="surface-card rounded-2xl border bg-card p-4 transition-shadow duration-300 hover:shadow-brand md:p-6"
                >
                    <h2 class="font-display text-xl font-semibold">Precio</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="precio">Precio de venta (MXN)</Label>
                            <Input
                                id="precio"
                                v-model.number="form.precio"
                                type="number"
                                min="0.01"
                                step="0.01"
                                inputmode="decimal"
                                placeholder="Sin definir"
                            />
                            <InputError :message="form.errors.precio" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="precio_comparacion"
                                >Precio antes (tachado)</Label
                            >
                            <Input
                                id="precio_comparacion"
                                v-model.number="form.precio_comparacion"
                                type="number"
                                min="0.01"
                                step="0.01"
                                inputmode="decimal"
                                placeholder="Opcional"
                                :aria-invalid="
                                    !!(
                                        errorComparacion ||
                                        form.errors.precio_comparacion
                                    )
                                "
                            />
                            <InputError
                                :message="
                                    form.errors.precio_comparacion ??
                                    errorComparacion ??
                                    undefined
                                "
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="costo" class="flex items-center gap-1"
                                >Costo
                                <Lock class="size-3 text-muted-foreground"
                            /></Label>
                            <Input
                                id="costo"
                                v-model.number="form.costo"
                                type="number"
                                min="0"
                                step="0.01"
                                inputmode="decimal"
                                placeholder="Opcional"
                            />
                            <p class="text-xs text-muted-foreground">
                                Sólo administradores.
                                <span v-if="margen !== null"
                                    >Margen:
                                    <strong>{{ margen }}%</strong></span
                                >
                            </p>
                            <InputError :message="form.errors.costo" />
                        </div>
                    </div>
                    <p
                        v-if="producto?.referencia.precio"
                        class="mt-3 rounded-lg bg-brand-teal-soft/60 px-3 py-2 text-xs text-muted-foreground"
                    >
                        Referencia de mercado (no pública):
                        {{ formatoMoneda(producto.referencia.precio) }}. No es
                        precio de venta; define el tuyo con tu proveedor y
                        margen.
                    </p>
                </section>

                <!-- Venta y disponibilidad -->
                <section
                    class="surface-card rounded-2xl border bg-card p-4 transition-shadow duration-300 hover:shadow-brand md:p-6"
                >
                    <h2 class="font-display text-xl font-semibold">
                        Venta y disponibilidad
                    </h2>
                    <fieldset class="mt-4 grid gap-3 sm:grid-cols-2">
                        <legend class="sr-only">Modalidad de venta</legend>
                        <label
                            v-for="m in modalidades"
                            :key="m.value"
                            class="flex cursor-pointer gap-3 rounded-xl border p-4 transition has-[:checked]:border-brand-green has-[:checked]:bg-brand-green-soft"
                        >
                            <input
                                v-model="form.modalidad_venta"
                                type="radio"
                                name="modalidad_venta"
                                :value="m.value"
                                class="mt-1 accent-[var(--brand-green-strong)]"
                            />
                            <span>
                                <span class="block font-semibold">{{
                                    m.label
                                }}</span>
                                <span
                                    class="block text-xs text-muted-foreground"
                                >
                                    {{
                                        m.value === 'bajo_pedido'
                                            ? 'Se vende sin existencias; se surte en el tiempo de entrega indicado.'
                                            : 'Se vende sólo con existencias; con 0 se muestra “Agotado”.'
                                    }}
                                </span>
                            </span>
                        </label>
                    </fieldset>
                    <InputError :message="form.errors.modalidad_venta" />

                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div v-if="bajoPedido" class="grid gap-2">
                            <Label for="tiempo_entrega_dias"
                                >Tiempo de entrega (días) *</Label
                            >
                            <Input
                                id="tiempo_entrega_dias"
                                v-model.number="form.tiempo_entrega_dias"
                                type="number"
                                min="1"
                                max="365"
                            />
                            <InputError
                                :message="form.errors.tiempo_entrega_dias"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="stock_minimo"
                                >Existencia mínima (alerta)</Label
                            >
                            <Input
                                id="stock_minimo"
                                v-model.number="form.stock_minimo"
                                type="number"
                                min="0"
                            />
                            <InputError :message="form.errors.stock_minimo" />
                        </div>
                        <div v-if="esNuevo" class="grid gap-2">
                            <Label for="stock_inicial"
                                >Existencia inicial</Label
                            >
                            <Input
                                id="stock_inicial"
                                v-model.number="form.stock_inicial"
                                type="number"
                                min="0"
                                placeholder="0"
                            />
                            <p class="text-xs text-muted-foreground">
                                Se registra como entrada de inventario.
                            </p>
                            <InputError :message="form.errors.stock_inicial" />
                        </div>
                        <div
                            v-else-if="producto"
                            class="flex flex-col justify-between rounded-xl border bg-surface/60 p-3"
                        >
                            <span class="text-xs text-muted-foreground"
                                >Existencia actual</span
                            >
                            <span class="font-display text-2xl font-bold">{{
                                producto.stock
                            }}</span>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="mt-2"
                                @click="ajusteAbierto = true"
                            >
                                <Boxes /> Ajustar inventario
                            </Button>
                        </div>
                    </div>
                </section>

                <!-- Ficha técnica -->
                <section
                    class="surface-card rounded-2xl border bg-card p-4 transition-shadow duration-300 hover:shadow-brand md:p-6"
                >
                    <h2 class="font-display text-xl font-semibold">
                        Ficha técnica
                    </h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="modelo">Modelo</Label>
                            <Input
                                id="modelo"
                                v-model="form.modelo"
                                maxlength="120"
                            />
                            <InputError :message="form.errors.modelo" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="acabado">Acabado / color</Label>
                            <Input
                                id="acabado"
                                v-model="form.acabado"
                                maxlength="180"
                            />
                            <InputError :message="form.errors.acabado" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="dimensiones">Dimensiones</Label>
                            <Input
                                id="dimensiones"
                                v-model="form.dimensiones"
                                maxlength="180"
                                placeholder="Ej. 90x26x45 cm"
                            />
                            <InputError :message="form.errors.dimensiones" />
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-2 gap-4 md:grid-cols-4">
                        <div class="grid gap-2">
                            <Label for="peso">Peso (kg)</Label>
                            <Input
                                id="peso"
                                v-model.number="form.peso"
                                type="number"
                                min="0"
                                step="0.01"
                            />
                            <InputError :message="form.errors.peso" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="alto">Alto (cm)</Label>
                            <Input
                                id="alto"
                                v-model.number="form.alto"
                                type="number"
                                min="0"
                                step="0.1"
                            />
                            <InputError :message="form.errors.alto" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ancho">Ancho (cm)</Label>
                            <Input
                                id="ancho"
                                v-model.number="form.ancho"
                                type="number"
                                min="0"
                                step="0.1"
                            />
                            <InputError :message="form.errors.ancho" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="largo">Fondo (cm)</Label>
                            <Input
                                id="largo"
                                v-model.number="form.largo"
                                type="number"
                                min="0"
                                step="0.1"
                            />
                            <InputError :message="form.errors.largo" />
                        </div>
                    </div>
                    <div class="mt-5 grid gap-2">
                        <Label>Atributos</Label>
                        <AtributosEditor
                            v-model="form.atributos"
                            :errores="form.errors as Record<string, string>"
                        />
                    </div>
                    <div class="mt-5 grid gap-2">
                        <Label for="observaciones">Observaciones</Label>
                        <CampoTextarea
                            id="observaciones"
                            v-model="form.observaciones"
                            :rows="2"
                            :maxlength="2000"
                        />
                        <InputError :message="form.errors.observaciones" />
                    </div>
                </section>

                <!-- Imágenes -->
                <section
                    class="surface-card rounded-2xl border bg-card p-4 transition-shadow duration-300 hover:shadow-brand md:p-6"
                >
                    <h2 class="font-display text-xl font-semibold">Imágenes</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        La primera marcada como principal es la portada del
                        producto. Arrastra o usa las flechas para reordenar.
                    </p>
                    <div class="mt-4">
                        <GaleriaImagenes
                            v-if="producto"
                            :producto-id="producto.id"
                            :imagenes="producto.imagenes"
                            :imagen-heredada="producto.imagen_heredada"
                            :max-mb="limites.imagen_max_mb"
                            :max-archivos="limites.imagenes_por_subida"
                        />
                        <p
                            v-else
                            class="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground"
                        >
                            Guarda el producto para poder subir imágenes.
                        </p>
                    </div>
                </section>

                <!-- SEO -->
                <section
                    class="surface-card rounded-2xl border bg-card p-4 transition-shadow duration-300 hover:shadow-brand md:p-6"
                >
                    <h2 class="font-display text-xl font-semibold">SEO</h2>
                    <div class="mt-4 grid gap-4">
                        <div class="grid gap-2">
                            <Label for="meta_titulo"
                                >Título para buscadores</Label
                            >
                            <Input
                                id="meta_titulo"
                                v-model="form.meta_titulo"
                                maxlength="180"
                                :placeholder="form.nombre"
                            />
                            <InputError :message="form.errors.meta_titulo" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="meta_descripcion"
                                >Descripción para buscadores</Label
                            >
                            <CampoTextarea
                                id="meta_descripcion"
                                v-model="form.meta_descripcion"
                                :rows="2"
                                :maxlength="300"
                            />
                            <InputError
                                :message="form.errors.meta_descripcion"
                            />
                        </div>
                        <div
                            class="rounded-xl border bg-surface/60 p-4"
                            aria-label="Vista previa en buscadores"
                        >
                            <p
                                class="line-clamp-1 text-base font-medium text-[#1a0dab]"
                            >
                                {{ vistaSeo.titulo }} - Tienda Mr Lana
                            </p>
                            <p class="text-xs text-brand-green-strong">
                                {{ dominio }}/productos/{{
                                    form.slug || 'tu-producto'
                                }}
                            </p>
                            <p
                                class="mt-1 line-clamp-2 text-sm text-muted-foreground"
                            >
                                {{ vistaSeo.descripcion }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Zona de peligro -->
                <section
                    v-if="producto"
                    class="flex flex-col gap-3 rounded-2xl border border-destructive/30 p-4 transition-colors duration-300 hover:bg-destructive/5 sm:flex-row sm:items-center sm:justify-between md:p-6"
                >
                    <div>
                        <h2 class="font-semibold">
                            {{
                                producto.tiene_pedidos
                                    ? 'Retirar producto'
                                    : 'Eliminar producto'
                            }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{
                                producto.tiene_pedidos
                                    ? 'Tiene pedidos: no se puede eliminar, sólo pausar para conservar el historial.'
                                    : 'Elimina el producto, sus imágenes y su bitácora. No se puede deshacer.'
                            }}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="destructive"
                        @click="eliminarAbierto = true"
                    >
                        <Trash2 />
                        {{ producto.tiene_pedidos ? 'Pausar' : 'Eliminar' }}
                    </Button>
                </section>
            </div>

            <!-- Columna lateral -->
            <aside
                class="animate-fade-up space-y-6 lg:sticky lg:top-4 lg:self-start"
            >
                <section
                    v-if="producto"
                    class="overflow-hidden rounded-2xl border bg-card shadow-brand transition-shadow duration-300"
                    aria-labelledby="titulo-publicacion"
                >
                    <div class="bg-brand-deep-pattern px-5 py-4 text-white">
                        <h2
                            id="titulo-publicacion"
                            class="font-display text-lg font-semibold"
                        >
                            Publicación
                        </h2>
                        <div class="mt-2">
                            <CompletitudBar :valor="producto.completitud" />
                        </div>
                    </div>
                    <div class="space-y-4 p-5">
                        <ChecklistPublicacion :faltantes="producto.faltantes" />

                        <p
                            v-if="form.isDirty"
                            class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800"
                        >
                            Tienes cambios sin guardar. Guárdalos para
                            actualizar la lista de requisitos.
                        </p>

                        <div class="grid gap-2">
                            <Button
                                v-if="producto.estado.value !== 'publicado'"
                                type="button"
                                :disabled="
                                    !producto.puede_publicarse ||
                                    procesando ||
                                    form.isDirty
                                "
                                @click="accion(publicar(producto.id).url)"
                            >
                                <CheckCircle2 />
                                {{
                                    producto.estado.value === 'pausado'
                                        ? 'Reanudar publicación'
                                        : 'Aprobar y publicar'
                                }}
                            </Button>
                            <Button
                                v-if="producto.estado.value === 'borrador'"
                                type="button"
                                variant="outline"
                                :disabled="procesando"
                                @click="accion(enviar(producto.id).url)"
                            >
                                <Send /> Enviar a aprobación
                            </Button>
                            <Button
                                v-if="producto.estado.value === 'publicado'"
                                type="button"
                                variant="outline"
                                :disabled="procesando"
                                @click="accion(pausar(producto.id).url)"
                            >
                                <Pause /> Pausar (ocultar de la tienda)
                            </Button>
                            <Button
                                v-if="
                                    [
                                        'pausado',
                                        'pendiente_aprobacion',
                                    ].includes(producto.estado.value)
                                "
                                type="button"
                                variant="ghost"
                                size="sm"
                                :disabled="procesando"
                                @click="accion(borrador(producto.id).url)"
                            >
                                <RotateCcw /> Regresar a borrador
                            </Button>
                        </div>

                        <dl
                            v-if="producto.aprobado_en"
                            class="border-t pt-3 text-xs text-muted-foreground"
                        >
                            <dt class="inline">Aprobado por</dt>
                            <dd class="inline font-medium text-foreground">
                                {{ producto.aprobado_por ?? '—' }}
                            </dd>
                            <dd>{{ formatoFecha(producto.aprobado_en) }}</dd>
                        </dl>
                    </div>
                </section>

                <section
                    v-if="producto"
                    class="rounded-2xl border bg-card p-5 transition-shadow duration-300 hover:shadow-brand"
                    aria-labelledby="titulo-inventario"
                >
                    <div class="flex items-center justify-between">
                        <h2
                            id="titulo-inventario"
                            class="font-display text-lg font-semibold"
                        >
                            Inventario
                        </h2>
                        <Link
                            :href="
                                inventarioIndex({
                                    query: { producto_id: producto.id },
                                }).url
                            "
                            class="text-xs font-semibold text-brand-green-strong hover:underline"
                            >Ver bitácora</Link
                        >
                    </div>
                    <ul
                        v-if="movimientos?.length"
                        class="mt-3 divide-y text-sm"
                    >
                        <li
                            v-for="m in movimientos"
                            :key="m.id"
                            class="flex items-center justify-between gap-2 py-2"
                        >
                            <span class="min-w-0">
                                <EstadoBadge
                                    :tono="m.tipo.color"
                                    :etiqueta="m.tipo.label"
                                />
                                <span
                                    class="mt-0.5 block truncate text-xs text-muted-foreground"
                                >
                                    {{ m.motivo ?? '—' }} ·
                                    {{ formatoFecha(m.fecha) }}
                                </span>
                            </span>
                            <span
                                class="font-semibold tabular-nums"
                                :class="
                                    m.cantidad < 0
                                        ? 'text-destructive'
                                        : 'text-brand-green-strong'
                                "
                                >{{ m.cantidad > 0 ? '+' : ''
                                }}{{ m.cantidad }}</span
                            >
                        </li>
                    </ul>
                    <p v-else class="mt-3 text-sm text-muted-foreground">
                        Sin movimientos todavía.
                    </p>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="mt-3 w-full"
                        @click="ajusteAbierto = true"
                    >
                        <Boxes /> Registrar movimiento
                    </Button>
                </section>

                <PanelReferencia
                    v-if="
                        producto &&
                        (producto.referencia.precio ||
                            producto.referencia.fuente)
                    "
                    :referencia="producto.referencia"
                    :modelo="producto.modelo"
                />
            </aside>
        </div>

        <!-- Barra fija de guardado -->
        <div
            class="sticky bottom-0 z-30 -mx-4 border-t bg-background/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6"
        >
            <div class="flex items-center justify-between gap-3">
                <p class="hidden text-sm text-muted-foreground sm:block">
                    {{ form.isDirty ? 'Cambios sin guardar' : 'Todo guardado' }}
                </p>
                <div class="flex w-full gap-2 sm:w-auto">
                    <Button
                        as-child
                        variant="outline"
                        class="flex-1 sm:flex-none"
                    >
                        <Link :href="productosIndex().url">Volver</Link>
                    </Button>
                    <Button
                        type="submit"
                        class="flex-1 sm:flex-none"
                        :disabled="form.processing"
                    >
                        <Save />
                        {{ esNuevo ? 'Crear producto' : 'Guardar cambios' }}
                    </Button>
                </div>
            </div>
        </div>
    </form>

    <AjusteInventarioDialog
        v-if="producto"
        v-model:open="ajusteAbierto"
        :producto="{
            id: producto.id,
            nombre: producto.nombre,
            sku: producto.sku,
            stock: producto.stock,
            stock_minimo: producto.stock_minimo,
        }"
    />

    <ConfirmarAccion
        v-if="producto"
        v-model:open="eliminarAbierto"
        :titulo="
            producto.tiene_pedidos
                ? '¿Pausar este producto?'
                : '¿Eliminar este producto?'
        "
        :descripcion="
            producto.tiene_pedidos
                ? 'Tiene pedidos registrados: se pausará y dejará de mostrarse en la tienda.'
                : 'Se eliminarán el producto, sus imágenes y su bitácora de inventario.'
        "
        :confirmar="producto.tiene_pedidos ? 'Pausar' : 'Eliminar'"
        destructiva
        :procesando="procesando"
        @confirmar="eliminar"
    />
</template>
