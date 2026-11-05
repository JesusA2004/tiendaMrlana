<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ImageIcon,
    ImagePlus,
    Link2,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-vue-next';
import { onBeforeUnmount, ref } from 'vue';
import CampoForm from '@/components/admin/CampoForm.vue';
import ConfirmarAccion from '@/components/admin/ConfirmarAccion.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import { fechaHora } from '@/components/admin/formato';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Banners', href: '/admin/banners' }],
    },
});

type Vigencia = 'visible' | 'programado' | 'vencido' | 'inactivo';

type Banner = {
    id: number;
    titulo: string;
    descripcion: string | null;
    imagen: string | null;
    url: string | null;
    activo: boolean;
    orden: number;
    inicia_en: string | null;
    termina_en: string | null;
    vigencia: Vigencia;
};

const props = defineProps<{
    banners: Banner[];
}>();

const VIGENCIA: Record<Vigencia, { label: string; color: string }> = {
    visible: { label: 'Visible', color: 'success' },
    programado: { label: 'Programado', color: 'info' },
    vencido: { label: 'Vencido', color: 'neutral' },
    inactivo: { label: 'Inactivo', color: 'neutral' },
};

const fechaLocal = (v: string | null) => (v ? fechaHora(`${v}:00-06:00`) : '');

// ----------------------------------------------------------- orden
const reordenando = ref(false);

function mover(indice: number, delta: number) {
    const destino = indice + delta;

    if (destino < 0 || destino >= props.banners.length) {
        return;
    }

    const ids = props.banners.map((b) => b.id);
    [ids[indice], ids[destino]] = [ids[destino], ids[indice]];

    reordenando.value = true;
    router.post(
        '/admin/banners/reorder',
        { items: ids.map((id) => ({ id })) },
        { preserveScroll: true, onFinish: () => (reordenando.value = false) },
    );
}

// ----------------------------------------------------------- formulario
const dialogo = ref(false);
const editando = ref<Banner | null>(null);
const vistaPrevia = ref<string | null>(null);
let urlObjeto: string | null = null;

const form = useForm({
    titulo: '',
    descripcion: '',
    url: '',
    activo: true,
    inicia_en: '',
    termina_en: '',
    imagen_file: null as File | null,
});

function liberarVista() {
    if (urlObjeto) {
        URL.revokeObjectURL(urlObjeto);
        urlObjeto = null;
    }
}

onBeforeUnmount(liberarVista);

function abrirNuevo() {
    editando.value = null;
    form.reset();
    form.clearErrors();
    liberarVista();
    vistaPrevia.value = null;
    dialogo.value = true;
}

function abrirEditar(b: Banner) {
    editando.value = b;
    form.clearErrors();
    form.titulo = b.titulo;
    form.descripcion = b.descripcion ?? '';
    form.url = b.url ?? '';
    form.activo = b.activo;
    form.inicia_en = b.inicia_en ?? '';
    form.termina_en = b.termina_en ?? '';
    form.imagen_file = null;
    liberarVista();
    vistaPrevia.value = b.imagen;
    dialogo.value = true;
}

function elegirImagen(e: Event) {
    const archivo = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.imagen_file = archivo;
    liberarVista();

    if (archivo) {
        urlObjeto = URL.createObjectURL(archivo);
        vistaPrevia.value = urlObjeto;
    } else {
        vistaPrevia.value = editando.value?.imagen ?? null;
    }
}

function guardar() {
    const opciones = {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            dialogo.value = false;
            form.reset();
            liberarVista();
        },
    };

    form.transform((d) => ({
        ...d,
        activo: d.activo ? 1 : 0,
        url: d.url || null,
        descripcion: d.descripcion || null,
        inicia_en: d.inicia_en || null,
        termina_en: d.termina_en || null,
        ...(editando.value ? { _method: 'put' } : {}),
    }));

    form.post(
        editando.value
            ? `/admin/banners/${editando.value.id}`
            : '/admin/banners',
        opciones,
    );
}

function eliminar(b: Banner) {
    router.delete(`/admin/banners/${b.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Banners" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            eyebrow="Contenido de la tienda"
            title="Banners del inicio"
            description="Imágenes del carrusel principal. Se muestran en este orden y sólo mientras estén activas y vigentes."
        >
            <Button @click="abrirNuevo">
                <Plus class="size-4" />
                Nuevo banner
            </Button>
        </PageHeader>

        <EmptyState
            v-if="banners.length === 0"
            title="Sin banners"
            description="Agrega una imagen horizontal (mínimo 800 × 300 px) para destacar promociones en el inicio."
            :icon="ImageIcon"
        >
            <Button variant="outline" @click="abrirNuevo"
                >Agregar banner</Button
            >
        </EmptyState>

        <ol
            v-else
            class="grid stagger-children gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
        >
            <li
                v-for="(b, i) in banners"
                :key="b.id"
                class="group flex hover-lift flex-col overflow-hidden rounded-2xl border bg-card"
            >
                <div class="relative aspect-[8/3] bg-muted">
                    <img
                        v-if="b.imagen"
                        :src="b.imagen"
                        :alt="b.titulo"
                        class="size-full object-cover transition-transform duration-500 group-hover:scale-105"
                        loading="lazy"
                    />
                    <div
                        v-else
                        class="grid size-full place-items-center text-muted-foreground"
                    >
                        <ImageIcon class="size-8" />
                    </div>
                    <span
                        class="absolute top-2 left-2 grid size-7 place-items-center rounded-full bg-brand-deep font-display text-sm font-bold text-white shadow"
                        >{{ i + 1 }}</span
                    >
                    <StatusBadge
                        class="absolute top-2 right-2 bg-background/90"
                        :label="VIGENCIA[b.vigencia]?.label ?? b.vigencia"
                        :color="VIGENCIA[b.vigencia]?.color ?? 'neutral'"
                    />
                </div>
                <div class="flex flex-1 flex-col gap-2 p-4">
                    <p class="font-display text-lg leading-tight font-semibold">
                        {{ b.titulo }}
                    </p>
                    <p
                        v-if="b.descripcion"
                        class="line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ b.descripcion }}
                    </p>
                    <p
                        v-if="b.url"
                        class="flex items-center gap-1 truncate text-xs text-brand-teal-strong"
                    >
                        <Link2 class="size-3.5 shrink-0" />
                        <span class="truncate">{{ b.url }}</span>
                    </p>
                    <p
                        v-if="b.inicia_en || b.termina_en"
                        class="text-xs text-muted-foreground"
                    >
                        {{ b.inicia_en ? fechaLocal(b.inicia_en) : 'Desde ya' }}
                        →
                        {{
                            b.termina_en ? fechaLocal(b.termina_en) : 'sin fin'
                        }}
                    </p>
                    <div class="mt-auto flex items-center gap-1 border-t pt-3">
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="i === 0 || reordenando"
                            :aria-label="`Subir ${b.titulo}`"
                            @click="mover(i, -1)"
                        >
                            <ArrowUp class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            :disabled="i === banners.length - 1 || reordenando"
                            :aria-label="`Bajar ${b.titulo}`"
                            @click="mover(i, 1)"
                        >
                            <ArrowDown class="size-4" />
                        </Button>
                        <span class="flex-1" />
                        <Button
                            size="sm"
                            variant="outline"
                            @click="abrirEditar(b)"
                        >
                            <Pencil class="size-4" /> Editar
                        </Button>
                        <ConfirmarAccion
                            title="¿Eliminar banner?"
                            description="La imagen se borrará y dejará de mostrarse en el inicio."
                            confirm-label="Eliminar"
                            @confirmar="eliminar(b)"
                        >
                            <template #trigger>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="text-destructive"
                                    :aria-label="`Eliminar ${b.titulo}`"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </template>
                        </ConfirmarAccion>
                    </div>
                </div>
            </li>
        </ol>
    </div>

    <Dialog v-model:open="dialogo">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <form class="grid gap-5" @submit.prevent="guardar">
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">
                        {{ editando ? 'Editar banner' : 'Nuevo banner' }}
                    </DialogTitle>
                    <DialogDescription>
                        Imagen JPG, PNG o WEBP de hasta 8 MB; recomendado 1600 ×
                        600 px (mínimo 800 × 300).
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <label
                        for="b-imagen"
                        class="group relative grid aspect-[8/3] cursor-pointer place-items-center overflow-hidden rounded-xl border-2 border-dashed bg-muted/40 transition hover:border-brand-green"
                    >
                        <img
                            v-if="vistaPrevia"
                            :src="vistaPrevia"
                            alt="Vista previa del banner"
                            class="absolute inset-0 size-full object-cover"
                        />
                        <span
                            :class="[
                                'relative flex flex-col items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium',
                                vistaPrevia
                                    ? 'bg-background/85 opacity-0 transition group-hover:opacity-100'
                                    : 'text-muted-foreground',
                            ]"
                        >
                            <ImagePlus class="size-6" />
                            {{
                                vistaPrevia
                                    ? 'Cambiar imagen'
                                    : 'Seleccionar imagen'
                            }}
                        </span>
                    </label>
                    <input
                        id="b-imagen"
                        type="file"
                        accept="image/jpeg,image/png,image/webp"
                        class="sr-only"
                        @change="elegirImagen"
                    />
                    <p
                        v-if="form.errors.imagen_file"
                        class="text-xs font-medium text-destructive"
                        role="alert"
                    >
                        {{ form.errors.imagen_file }}
                    </p>
                </div>

                <CampoForm
                    label="Título"
                    for="b-titulo"
                    :error="form.errors.titulo"
                    requerido
                >
                    <Input
                        id="b-titulo"
                        v-model="form.titulo"
                        maxlength="180"
                        required
                    />
                </CampoForm>
                <CampoForm
                    label="Descripción"
                    for="b-desc"
                    :error="form.errors.descripcion"
                >
                    <Textarea
                        id="b-desc"
                        v-model="form.descripcion"
                        rows="2"
                        maxlength="500"
                    />
                </CampoForm>
                <CampoForm
                    label="Enlace (opcional)"
                    for="b-url"
                    :error="form.errors.url"
                    ayuda="Ruta interna como /catalogo?categoria=salas o un enlace https://"
                >
                    <Input
                        id="b-url"
                        v-model="form.url"
                        placeholder="/catalogo"
                    />
                </CampoForm>

                <div class="grid gap-4 sm:grid-cols-2">
                    <CampoForm
                        label="Mostrar desde"
                        for="b-inicia"
                        :error="form.errors.inicia_en"
                    >
                        <Input
                            id="b-inicia"
                            v-model="form.inicia_en"
                            type="datetime-local"
                        />
                    </CampoForm>
                    <CampoForm
                        label="Mostrar hasta"
                        for="b-termina"
                        :error="form.errors.termina_en"
                    >
                        <Input
                            id="b-termina"
                            v-model="form.termina_en"
                            type="datetime-local"
                        />
                    </CampoForm>
                </div>

                <div class="flex items-center gap-3 rounded-xl border p-3">
                    <Switch
                        id="b-activo"
                        :model-value="form.activo"
                        @update:model-value="(v: boolean) => (form.activo = v)"
                    />
                    <label for="b-activo" class="text-sm font-medium"
                        >Activo</label
                    >
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="dialogo = false"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="form.processing">
                        {{ editando ? 'Guardar cambios' : 'Crear banner' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
