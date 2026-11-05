<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, TicketPercent, Trash2 } from 'lucide-vue-next';
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
import { Progress } from '@/components/ui/progress';
import { Switch } from '@/components/ui/switch';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cupones', href: '/admin/cupones' }],
    },
});

type Vigencia = 'vigente' | 'programado' | 'vencido' | 'agotado' | 'inactivo';

type Cupon = {
    id: number;
    codigo: string;
    nombre: string;
    tipo: 'porcentaje' | 'monto_fijo' | string;
    valor: number;
    compra_minima: number | null;
    usos_maximos: number | null;
    usos_por_usuario: number | null;
    usos_actuales: number;
    inicia_en: string | null;
    termina_en: string | null;
    activo: boolean;
    vigencia: Vigencia;
};

const props = defineProps<{
    cupones: Paginado<Cupon>;
    tipos: Opcion[];
    filtros: { buscar: string; estado: string };
}>();

const { filtros } = useFiltros(
    '/admin/cupones',
    { buscar: props.filtros.buscar ?? '', estado: props.filtros.estado ?? '' },
    { textos: ['buscar'] },
);

const VIGENCIA: Record<Vigencia, { label: string; color: string }> = {
    vigente: { label: 'Vigente', color: 'success' },
    programado: { label: 'Programado', color: 'info' },
    vencido: { label: 'Vencido', color: 'neutral' },
    agotado: { label: 'Agotado', color: 'warning' },
    inactivo: { label: 'Inactivo', color: 'neutral' },
};

const valorTexto = (c: Cupon) =>
    c.tipo === 'porcentaje' ? `${c.valor}%` : dinero(c.valor);

const progresoUsos = (c: Cupon) =>
    c.usos_maximos
        ? Math.min(100, (c.usos_actuales / c.usos_maximos) * 100)
        : 0;

// Fecha ISO local "YYYY-MM-DDTHH:mm" → texto legible.
const fechaLocal = (v: string | null) => (v ? fechaHora(`${v}:00-06:00`) : '');

// ----------------------------------------------------------- formulario
const dialogo = ref(false);
const editando = ref<Cupon | null>(null);

const form = useForm({
    codigo: '',
    nombre: '',
    tipo: 'porcentaje',
    valor: '' as string | number,
    compra_minima: '' as string | number,
    usos_maximos: '' as string | number,
    usos_por_usuario: '' as string | number,
    inicia_en: '',
    termina_en: '',
    activo: true,
});

const esPorcentaje = computed(() => form.tipo === 'porcentaje');

function abrirNuevo() {
    editando.value = null;
    form.reset();
    form.clearErrors();
    dialogo.value = true;
}

function abrirEditar(c: Cupon) {
    editando.value = c;
    form.clearErrors();
    form.codigo = c.codigo;
    form.nombre = c.nombre;
    form.tipo = c.tipo;
    form.valor = c.valor;
    form.compra_minima = c.compra_minima ?? '';
    form.usos_maximos = c.usos_maximos ?? '';
    form.usos_por_usuario = c.usos_por_usuario ?? '';
    form.inicia_en = c.inicia_en ?? '';
    form.termina_en = c.termina_en ?? '';
    form.activo = c.activo;
    dialogo.value = true;
}

function normalizarCodigo() {
    form.codigo = form.codigo.toUpperCase().replace(/[^A-Z0-9_-]/g, '');
}

function guardar() {
    const vacioANulo = (v: string | number) => (v === '' ? null : v);

    form.transform((d) => ({
        ...d,
        compra_minima: vacioANulo(d.compra_minima),
        usos_maximos: vacioANulo(d.usos_maximos),
        usos_por_usuario: vacioANulo(d.usos_por_usuario),
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
        form.put(`/admin/cupones/${editando.value.id}`, opciones);
    } else {
        form.post('/admin/cupones', opciones);
    }
}

function eliminar(c: Cupon) {
    router.delete(`/admin/cupones/${c.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Cupones" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            eyebrow="Promociones"
            title="Cupones"
            description="Códigos de descuento que el cliente aplica en el carrito. Se validan vigencia, límites de uso y compra mínima."
        >
            <Button @click="abrirNuevo">
                <Plus class="size-4" />
                Nuevo cupón
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
                    placeholder="Buscar por código o nombre"
                    class="pl-9"
                    aria-label="Buscar cupones"
                />
            </div>
            <NativeSelect
                v-model="filtros.estado"
                class="sm:w-48"
                aria-label="Filtrar por estado"
            >
                <option value="">Todos</option>
                <option value="activos">Activos</option>
                <option value="inactivos">Inactivos</option>
                <option value="vencidos">Vencidos</option>
            </NativeSelect>
        </div>

        <EmptyState
            v-if="cupones.data.length === 0"
            title="Sin cupones"
            description="Crea un cupón para ofrecer descuentos a tus clientes."
            :icon="TicketPercent"
        >
            <Button variant="outline" @click="abrirNuevo">Crear cupón</Button>
        </EmptyState>

        <template v-else>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="c in cupones.data"
                    :key="c.id"
                    class="flex flex-col gap-3 rounded-2xl border bg-card p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p
                                class="inline-block rounded-lg border border-dashed border-brand-green bg-brand-green-soft px-2 py-0.5 font-mono text-sm font-bold tracking-wider text-brand-green-strong"
                            >
                                {{ c.codigo }}
                            </p>
                            <p
                                class="mt-1 truncate text-sm text-muted-foreground"
                            >
                                {{ c.nombre }}
                            </p>
                        </div>
                        <StatusBadge
                            :label="VIGENCIA[c.vigencia]?.label ?? c.vigencia"
                            :color="VIGENCIA[c.vigencia]?.color ?? 'neutral'"
                            :pulso="c.vigencia === 'vigente'"
                        />
                    </div>

                    <p class="font-display text-3xl leading-none font-bold">
                        {{ valorTexto(c) }}
                        <span class="text-sm font-medium text-muted-foreground"
                            >de descuento</span
                        >
                    </p>

                    <dl class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <dt class="text-muted-foreground">Compra mínima</dt>
                            <dd class="font-medium">
                                {{
                                    c.compra_minima
                                        ? dinero(c.compra_minima)
                                        : 'Sin mínimo'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground">Por cliente</dt>
                            <dd class="font-medium">
                                {{
                                    c.usos_por_usuario
                                        ? `${c.usos_por_usuario} uso(s)`
                                        : 'Sin límite'
                                }}
                            </dd>
                        </div>
                        <div class="col-span-2">
                            <dt class="text-muted-foreground">Vigencia</dt>
                            <dd class="font-medium">
                                <template v-if="c.inicia_en || c.termina_en">
                                    {{
                                        c.inicia_en
                                            ? fechaLocal(c.inicia_en)
                                            : 'Desde ya'
                                    }}
                                    →
                                    {{
                                        c.termina_en
                                            ? fechaLocal(c.termina_en)
                                            : 'sin fin'
                                    }}
                                </template>
                                <template v-else>Sin fecha límite</template>
                            </dd>
                        </div>
                    </dl>

                    <div>
                        <div class="mb-1 flex justify-between text-xs">
                            <span class="text-muted-foreground">Usos</span>
                            <span class="font-medium tabular-nums">
                                {{ c.usos_actuales
                                }}{{
                                    c.usos_maximos
                                        ? ` / ${c.usos_maximos}`
                                        : ' · sin límite'
                                }}
                            </span>
                        </div>
                        <Progress
                            v-if="c.usos_maximos"
                            :model-value="progresoUsos(c)"
                            class="h-1.5 bg-muted"
                            :indicator-class="
                                progresoUsos(c) >= 100
                                    ? 'bg-warning'
                                    : 'bg-brand-green'
                            "
                        />
                    </div>

                    <div class="mt-auto flex gap-2 border-t pt-3">
                        <Button
                            size="sm"
                            variant="outline"
                            class="flex-1"
                            @click="abrirEditar(c)"
                        >
                            <Pencil class="size-4" /> Editar
                        </Button>
                        <ConfirmarAccion
                            title="¿Eliminar cupón?"
                            description="Si el cupón ya se usó en pedidos, se desactivará en lugar de eliminarse para conservar el historial."
                            confirm-label="Eliminar"
                            @confirmar="eliminar(c)"
                        >
                            <template #trigger>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="text-destructive"
                                    :aria-label="`Eliminar cupón ${c.codigo}`"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </template>
                        </ConfirmarAccion>
                    </div>
                </article>
            </div>

            <Paginacion :pagina="cupones" />
        </template>
    </div>

    <Dialog v-model:open="dialogo">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <form class="grid gap-5" @submit.prevent="guardar">
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">
                        {{
                            editando
                                ? `Editar ${editando.codigo}`
                                : 'Nuevo cupón'
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        Las fechas están en hora del centro de México.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 sm:grid-cols-2">
                    <CampoForm
                        label="Código"
                        for="c-codigo"
                        :error="form.errors.codigo"
                        ayuda="Letras, números, guion y guion bajo."
                        requerido
                    >
                        <Input
                            id="c-codigo"
                            v-model="form.codigo"
                            class="font-mono uppercase"
                            maxlength="40"
                            required
                            @input="normalizarCodigo"
                        />
                    </CampoForm>
                    <CampoForm
                        label="Nombre interno"
                        for="c-nombre"
                        :error="form.errors.nombre"
                        requerido
                    >
                        <Input id="c-nombre" v-model="form.nombre" required />
                    </CampoForm>
                    <CampoForm
                        label="Tipo"
                        for="c-tipo"
                        :error="form.errors.tipo"
                        requerido
                    >
                        <NativeSelect
                            id="c-tipo"
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
                        for="c-valor"
                        :error="form.errors.valor"
                        :ayuda="esPorcentaje ? 'De 1 a 100.' : 'Mayor a $0.'"
                        requerido
                    >
                        <Input
                            id="c-valor"
                            v-model="form.valor"
                            type="number"
                            :min="esPorcentaje ? 1 : 0.01"
                            :max="esPorcentaje ? 100 : undefined"
                            step="0.01"
                            required
                        />
                    </CampoForm>
                    <CampoForm
                        label="Compra mínima (MXN)"
                        for="c-minima"
                        :error="form.errors.compra_minima"
                        ayuda="Vacío = sin mínimo."
                    >
                        <Input
                            id="c-minima"
                            v-model="form.compra_minima"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                    </CampoForm>
                    <CampoForm
                        label="Usos máximos"
                        for="c-usos"
                        :error="form.errors.usos_maximos"
                        :ayuda="
                            editando
                                ? `Usado ${editando.usos_actuales} veces.`
                                : 'Vacío = ilimitado.'
                        "
                    >
                        <Input
                            id="c-usos"
                            v-model="form.usos_maximos"
                            type="number"
                            min="1"
                            step="1"
                        />
                    </CampoForm>
                    <CampoForm
                        label="Usos por cliente"
                        for="c-usos-usuario"
                        :error="form.errors.usos_por_usuario"
                        ayuda="Vacío = ilimitado."
                    >
                        <Input
                            id="c-usos-usuario"
                            v-model="form.usos_por_usuario"
                            type="number"
                            min="1"
                            step="1"
                        />
                    </CampoForm>
                    <div
                        class="flex items-center gap-3 self-end rounded-xl border p-3"
                    >
                        <Switch
                            id="c-activo"
                            :model-value="form.activo"
                            @update:model-value="
                                (v: boolean) => (form.activo = v)
                            "
                        />
                        <label for="c-activo" class="text-sm font-medium"
                            >Activo</label
                        >
                    </div>
                    <CampoForm
                        label="Inicia"
                        for="c-inicia"
                        :error="form.errors.inicia_en"
                    >
                        <Input
                            id="c-inicia"
                            v-model="form.inicia_en"
                            type="datetime-local"
                        />
                    </CampoForm>
                    <CampoForm
                        label="Termina"
                        for="c-termina"
                        :error="form.errors.termina_en"
                    >
                        <Input
                            id="c-termina"
                            v-model="form.termina_en"
                            type="datetime-local"
                        />
                    </CampoForm>
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="dialogo = false"
                        >Cancelar</Button
                    >
                    <Button type="submit" :disabled="form.processing">
                        {{ editando ? 'Guardar cambios' : 'Crear cupón' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
