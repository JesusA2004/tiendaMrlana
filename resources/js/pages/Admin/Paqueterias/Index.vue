<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ExternalLink,
    Link2,
    Pencil,
    Plus,
    Trash2,
    Truck,
} from 'lucide-vue-next';
import { ref } from 'vue';
import CampoForm from '@/components/admin/CampoForm.vue';
import ConfirmarAccion from '@/components/admin/ConfirmarAccion.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    destroy as paqueteriaDestroy,
    index as paqueteriasIndex,
    store as paqueteriaStore,
    update as paqueteriaUpdate,
} from '@/routes/admin/paqueterias';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Envíos', href: '/admin/envios' },
            { title: 'Paqueterías', href: paqueteriasIndex().url },
        ],
    },
});

type Paqueteria = {
    id: number;
    nombre: string;
    activo: boolean;
    url_rastreo: string | null;
    notas: string | null;
    opciones_count: number;
    ejemplo_rastreo: string | null;
};

defineProps<{
    paqueterias: Paqueteria[];
    marcador: string;
}>();

const abierto = ref(false);
const editando = ref<Paqueteria | null>(null);
const form = useForm({
    nombre: '',
    activo: true,
    url_rastreo: '',
    notas: '',
});

function nueva() {
    editando.value = null;
    form.reset();
    form.clearErrors();
    abierto.value = true;
}

function editar(p: Paqueteria) {
    editando.value = p;
    form.clearErrors();
    form.nombre = p.nombre;
    form.activo = p.activo;
    form.url_rastreo = p.url_rastreo ?? '';
    form.notas = p.notas ?? '';
    abierto.value = true;
}

function enviar() {
    const datos = form.transform((d) => ({
        ...d,
        url_rastreo: d.url_rastreo.trim() || null,
        notas: d.notas || null,
    }));
    const opciones = {
        preserveScroll: true,
        onSuccess: () => (abierto.value = false),
    };

    if (editando.value) {
        datos.put(paqueteriaUpdate(editando.value.id).url, opciones);
    } else {
        datos.post(paqueteriaStore().url, opciones);
    }
}

function alternar(p: Paqueteria) {
    router.put(
        paqueteriaUpdate(p.id).url,
        {
            nombre: p.nombre,
            activo: !p.activo,
            url_rastreo: p.url_rastreo,
            notas: p.notas,
        },
        { preserveScroll: true },
    );
}

function eliminar(p: Paqueteria) {
    router.delete(paqueteriaDestroy(p.id).url, { preserveScroll: true });
}
</script>

<template>
    <Head title="Paqueterías" />

    <div class="flex w-full animate-page-in flex-col gap-6 p-4 md:p-6">
        <PageHeader
            eyebrow="Envíos"
            title="Paqueterías"
            description="Las paqueterías con las que cotizas envíos. Al cotizar un pedido eliges una o varias y el cliente decide según precio y urgencia."
        >
            <Button @click="nueva">
                <Plus class="size-4" /> Nueva paquetería
            </Button>
        </PageHeader>

        <Alert class="animate-fade-up border-info/30 bg-info/5">
            <Link2 class="size-4 text-info" />
            <AlertDescription>
                Si agregas la URL de rastreo con el marcador
                <code class="rounded bg-muted px-1 font-mono text-xs">{{
                    marcador
                }}</code
                >, el cliente verá un botón «Rastrear» con su número de guía.
                Ejemplo:
                <span class="font-mono text-xs break-all"
                    >https://paqueteria.example/rastreo?guia={{
                        marcador
                    }}</span
                >
            </AlertDescription>
        </Alert>

        <EmptyState
            v-if="paqueterias.length === 0"
            title="Aún no hay paqueterías"
            description="Da de alta las paqueterías con las que trabajas para poder enviar opciones de envío a tus clientes."
            :icon="Truck"
        >
            <Button @click="nueva"
                ><Plus class="size-4" /> Agregar la primera</Button
            >
        </EmptyState>

        <div
            v-else
            class="grid stagger-children gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
        >
            <article
                v-for="p in paqueterias"
                :key="p.id"
                :class="[
                    'group flex hover-lift flex-col gap-3 surface-card rounded-2xl border bg-card p-4',
                    !p.activo && 'opacity-70',
                ]"
            >
                <header class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="grid size-10 shrink-0 place-items-center rounded-xl bg-brand-teal-soft text-brand-teal-strong transition-all duration-300 group-hover:scale-110 group-hover:bg-brand-green-soft group-hover:text-brand-green-strong"
                        >
                            <Truck class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <h2
                                class="truncate font-display text-lg font-semibold transition-colors group-hover:text-brand-green-strong"
                            >
                                {{ p.nombre }}
                            </h2>
                            <StatusBadge
                                :label="p.activo ? 'Activa' : 'Inactiva'"
                                :color="p.activo ? 'success' : 'neutral'"
                            />
                        </div>
                    </div>
                    <Switch
                        :model-value="p.activo"
                        :aria-label="`${p.activo ? 'Desactivar' : 'Activar'} ${p.nombre}`"
                        @update:model-value="alternar(p)"
                    />
                </header>

                <div class="grid gap-1 text-sm">
                    <p
                        v-if="p.url_rastreo"
                        class="flex items-start gap-1.5 text-xs text-muted-foreground"
                    >
                        <Link2 class="mt-0.5 size-3.5 shrink-0" />
                        <span class="font-mono break-all">{{
                            p.url_rastreo
                        }}</span>
                    </p>
                    <p v-else class="text-xs text-muted-foreground">
                        Sin enlace de rastreo.
                    </p>
                    <a
                        v-if="p.ejemplo_rastreo"
                        :href="p.ejemplo_rastreo"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="group/link inline-flex items-center gap-1 text-xs font-semibold text-brand-green-strong hover:underline"
                    >
                        Probar enlace
                        <ExternalLink
                            class="size-3 transition-transform group-hover/link:translate-x-0.5 group-hover/link:-translate-y-0.5"
                        />
                    </a>
                    <p
                        v-if="p.notas"
                        class="mt-1 text-xs whitespace-pre-line text-muted-foreground"
                    >
                        {{ p.notas }}
                    </p>
                </div>

                <footer
                    class="mt-auto flex items-center justify-between gap-2 border-t pt-3"
                >
                    <span class="text-xs text-muted-foreground">
                        {{ p.opciones_count }}
                        {{
                            p.opciones_count === 1
                                ? 'cotización'
                                : 'cotizaciones'
                        }}
                    </span>
                    <div class="flex gap-1">
                        <Button variant="ghost" size="sm" @click="editar(p)">
                            <Pencil class="size-4" /> Editar
                        </Button>
                        <ConfirmarAccion
                            :title="
                                p.opciones_count > 0
                                    ? 'Desactivar paquetería'
                                    : 'Eliminar paquetería'
                            "
                            :description="
                                p.opciones_count > 0
                                    ? 'Ya se usó en cotizaciones; se desactivará para conservar el historial y el rastreo.'
                                    : `Se eliminará «${p.nombre}». Esta acción no se puede deshacer.`
                            "
                            :confirm-label="
                                p.opciones_count > 0 ? 'Desactivar' : 'Eliminar'
                            "
                            @confirmar="eliminar(p)"
                        >
                            <template #trigger>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-8 text-destructive"
                                    :aria-label="`Eliminar ${p.nombre}`"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </template>
                        </ConfirmarAccion>
                    </div>
                </footer>
            </article>
        </div>

        <p class="text-xs text-muted-foreground">
            ¿Tarifas automáticas por zona?
            <Link
                href="/admin/reglas-envio"
                class="link-underline font-semibold text-brand-green-strong"
                >Reglas de envío</Link
            >. Sin reglas, todo envío a domicilio se cotiza con estas
            paqueterías.
        </p>
    </div>

    <Dialog v-model:open="abierto">
        <DialogContent class="max-h-[92vh] overflow-y-auto sm:max-w-lg">
            <form class="grid gap-5" @submit.prevent="enviar">
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">
                        {{
                            editando ? 'Editar paquetería' : 'Nueva paquetería'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Sólo el nombre es obligatorio.
                    </DialogDescription>
                </DialogHeader>

                <CampoForm
                    label="Nombre"
                    for="pq-nombre"
                    :error="form.errors.nombre"
                    requerido
                >
                    <Input
                        id="pq-nombre"
                        v-model="form.nombre"
                        maxlength="80"
                        required
                        autocomplete="off"
                    />
                </CampoForm>
                <CampoForm
                    label="URL de rastreo (opcional)"
                    for="pq-url"
                    :error="form.errors.url_rastreo"
                    :ayuda="`Debe incluir ${marcador} donde va el número de guía.`"
                >
                    <Input
                        id="pq-url"
                        v-model="form.url_rastreo"
                        type="url"
                        inputmode="url"
                        maxlength="500"
                        :placeholder="`https://…/rastreo?guia=${marcador}`"
                    />
                </CampoForm>
                <CampoForm
                    label="Notas internas (opcional)"
                    for="pq-notas"
                    :error="form.errors.notas"
                >
                    <Textarea
                        id="pq-notas"
                        v-model="form.notas"
                        rows="2"
                        maxlength="1000"
                    />
                </CampoForm>
                <div
                    class="flex items-center justify-between gap-3 rounded-xl bg-surface p-3"
                >
                    <Label for="pq-activo" class="text-sm"
                        >Disponible para cotizar</Label
                    >
                    <Switch
                        id="pq-activo"
                        :model-value="form.activo"
                        @update:model-value="(v: boolean) => (form.activo = v)"
                    />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="abierto = false"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="form.processing">
                        {{ editando ? 'Guardar cambios' : 'Agregar' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
