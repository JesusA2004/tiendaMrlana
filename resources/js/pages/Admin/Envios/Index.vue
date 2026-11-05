<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Hourglass,
    MapPin,
    Package,
    PackageCheck,
    Search,
    Settings2,
    Store,
    Truck,
    Wallet,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import AccionDialog from '@/components/admin/AccionDialog.vue';
import CampoForm from '@/components/admin/CampoForm.vue';
import CotizadorEnvio from '@/components/admin/CotizadorEnvio.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import {
    dinero,
    fecha,
    haceCuanto,
    hoyMexico,
} from '@/components/admin/formato';
import OpcionesEnvioLista from '@/components/admin/OpcionesEnvioLista.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Paginacion from '@/components/admin/Paginacion.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type {
    AccionesPedido,
    OpcionEnvio,
    Paginado,
    PaqueteriaOpcion,
    PedidoFila,
} from '@/components/admin/types';
import { useFiltros } from '@/components/admin/useFiltros';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { index as enviosIndex } from '@/routes/admin/envios';
import { show as pedidoShow } from '@/routes/admin/pedidos';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Envíos', href: enviosIndex().url }],
    },
});

type Fila = PedidoFila & {
    regla_envio: string | null;
    opciones_envio: OpcionEnvio[];
    acciones: AccionesPedido;
};
type Tab =
    | 'por_cotizar'
    | 'esperando_eleccion'
    | 'por_preparar'
    | 'en_preparacion'
    | 'listos'
    | 'enviados';

const props = defineProps<{
    tab: Tab;
    tabs: { value: Tab; label: string; total: number }[];
    pedidos: Paginado<Fila>;
    filtros: { buscar: string };
    puedeGestionarReglas: boolean;
    paqueterias: PaqueteriaOpcion[];
}>();

const ICONOS = {
    por_cotizar: Wallet,
    esperando_eleccion: Hourglass,
    por_preparar: Package,
    en_preparacion: PackageCheck,
    listos: Truck,
    enviados: CheckCircle2,
} as const;

const DESCRIPCION: Record<Tab, string> = {
    por_cotizar:
        'Pedidos a domicilio sin tarifa automática. Envía opciones de paquetería (precio y tiempo) para que el cliente elija.',
    esperando_eleccion:
        'Ya tienen opciones de envío. Cuando el cliente elija, verá el total y cómo pagar. Puedes reemplazarlas.',
    por_preparar:
        'Pago confirmado (o contra entrega autorizado). Inicia la preparación.',
    en_preparacion:
        'Empacando. Márcalos listos o registra la guía directamente.',
    listos: 'Esperan paquetería o al cliente. Captura guías en lote.',
    enviados:
        'En tránsito o con incidencia. Confirma la entrega cuando llegue.',
};

const { filtros } = useFiltros(
    enviosIndex().url,
    { ...props.filtros },
    {
        textos: ['buscar'],
        extra: () => ({ tab: props.tab }),
    },
);

function irTab(tab: Tab) {
    router.get(enviosIndex().url, { tab }, { preserveScroll: true });
}

// ---------------------------------------------------------------- acciones simples
const procesando = ref<number | null>(null);

function postSimple(p: Fila, ruta: string) {
    router.post(
        `/admin/pedidos/${p.id}/${ruta}`,
        {},
        {
            preserveScroll: true,
            onStart: () => (procesando.value = p.id),
            onFinish: () => (procesando.value = null),
        },
    );
}

// ---------------------------------------------------------------- cotizar (opciones de envío)
const cotizando = ref<Fila | null>(null);
const cotizadorAbierto = ref(false);

function abrirCotizador(p: Fila) {
    cotizando.value = p;
    cotizadorAbierto.value = true;
}

// ---------------------------------------------------------------- entrega
const abierto = ref(false);
const objetivo = ref<Fila | null>(null);
const form = useForm({ notas: '' });

function abrirEntrega(p: Fila) {
    form.reset();
    form.clearErrors();
    objetivo.value = p;
    abierto.value = true;
}

function confirmarEntrega() {
    if (!objetivo.value) {
        return;
    }

    form.post(`/admin/pedidos/${objetivo.value.id}/entrega`, {
        preserveScroll: true,
        onSuccess: () => (abierto.value = false),
    });
}

// ---------------------------------------------------------------- guías en lote
const lote = reactive<
    Record<
        number,
        { sel: boolean; paqueteria: string; guia: string; fecha_envio: string }
    >
>({});

function filaLote(p: Fila) {
    if (!lote[p.id]) {
        lote[p.id] = {
            sel: false,
            paqueteria: p.paqueteria ?? '',
            guia: p.numero_guia ?? '',
            fecha_envio: hoyMexico(),
        };
    }

    return lote[p.id];
}

const paqueteriaComun = ref('');
const conGuia = computed(() =>
    props.pedidos.data.filter((p) => p.acciones.registrar_envio),
);
const seleccionados = computed(() =>
    conGuia.value.filter(
        (p) =>
            lote[p.id]?.sel &&
            lote[p.id].guia.trim() &&
            lote[p.id].paqueteria.trim(),
    ),
);
const enviandoLote = ref(false);
const erroresLote = ref<Record<string, string>>({});

function aplicarPaqueteria() {
    for (const p of conGuia.value) {
        const f = filaLote(p);

        if (f.sel || !f.paqueteria) {
            f.paqueteria = paqueteriaComun.value;
        }
    }
}

function registrarLote(soloId?: number) {
    const filas = soloId
        ? conGuia.value.filter((p) => p.id === soloId)
        : seleccionados.value;

    if (filas.length === 0) {
        return;
    }

    router.post(
        '/admin/envios/lote',
        {
            envios: filas.map((p) => ({
                pedido_id: p.id,
                paqueteria: lote[p.id].paqueteria,
                guia: lote[p.id].guia,
                fecha_envio: lote[p.id].fecha_envio || null,
            })),
        },
        {
            preserveScroll: true,
            onStart: () => (enviandoLote.value = true),
            onFinish: () => (enviandoLote.value = false),
            onError: (e) => (erroresLote.value = e),
            onSuccess: () => (erroresLote.value = {}),
        },
    );
}
</script>

<template>
    <Head title="Envíos" />

    <div class="flex flex-col gap-5 p-4 md:p-6">
        <PageHeader
            eyebrow="Logística"
            title="Envíos"
            description="Del pago confirmado a la entrega: cotiza, prepara, registra guías y cierra entregas."
        >
            <Button v-if="puedeGestionarReglas" variant="outline" as-child>
                <Link href="/admin/reglas-envio"
                    ><Settings2 class="size-4" /> Reglas de envío</Link
                >
            </Button>
        </PageHeader>

        <!-- Etapas -->
        <nav
            class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6"
            aria-label="Etapas logísticas"
        >
            <button
                v-for="(t, i) in tabs"
                :key="t.value"
                type="button"
                :aria-current="tab === t.value ? 'page' : undefined"
                :class="[
                    'group relative flex flex-col gap-1 rounded-2xl border p-3 text-left transition',
                    tab === t.value
                        ? 'border-transparent bg-brand-deep-pattern text-white shadow-md'
                        : 'bg-card hover:border-brand-teal/50',
                ]"
                @click="irTab(t.value)"
            >
                <span class="flex items-center justify-between">
                    <component
                        :is="ICONOS[t.value]"
                        :class="[
                            'size-4',
                            tab === t.value
                                ? 'text-brand-green'
                                : 'text-muted-foreground',
                        ]"
                    />
                    <span
                        :class="[
                            'text-[0.68rem]',
                            tab === t.value
                                ? 'text-white/60'
                                : 'text-muted-foreground',
                        ]"
                        >{{ i + 1 }}/{{ tabs.length }}</span
                    >
                </span>
                <span class="font-display text-2xl font-bold tabular-nums">{{
                    t.total
                }}</span>
                <span
                    :class="[
                        'text-xs font-medium',
                        tab === t.value
                            ? 'text-white/85'
                            : 'text-muted-foreground',
                    ]"
                    >{{ t.label }}</span
                >
            </button>
        </nav>

        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <p class="text-sm text-muted-foreground">{{ DESCRIPCION[tab] }}</p>
            <div class="relative sm:w-72">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filtros.buscar"
                    type="search"
                    class="pl-9"
                    placeholder="Folio o cliente…"
                    aria-label="Buscar"
                />
            </div>
        </div>

        <!-- Barra de lote -->
        <div
            v-if="
                (tab === 'listos' || tab === 'en_preparacion') && conGuia.length
            "
            class="flex flex-col gap-2 rounded-2xl border border-brand-teal/40 bg-brand-teal-soft/50 p-3 sm:flex-row sm:items-center"
        >
            <p class="text-sm font-medium sm:flex-1">
                Captura guías en lote: marca los pedidos, escribe la guía y
                registra todos a la vez.
            </p>
            <div class="flex gap-2">
                <Input
                    v-model="paqueteriaComun"
                    list="paqueterias-lote"
                    placeholder="Paquetería para todos"
                    class="bg-background sm:w-48"
                />
                <Button
                    variant="outline"
                    :disabled="!paqueteriaComun"
                    @click="aplicarPaqueteria"
                    >Aplicar</Button
                >
                <Button
                    :disabled="seleccionados.length === 0 || enviandoLote"
                    @click="registrarLote()"
                >
                    <Truck class="size-4" /> Registrar
                    {{ seleccionados.length || '' }}
                </Button>
            </div>
            <datalist id="paqueterias-lote">
                <option
                    v-for="n in paqueterias"
                    :key="n.id"
                    :value="n.nombre"
                />
            </datalist>
        </div>
        <p
            v-if="Object.keys(erroresLote).length"
            class="rounded-xl bg-destructive/10 p-3 text-sm text-destructive"
            role="alert"
        >
            {{ Object.values(erroresLote)[0] }}
        </p>

        <EmptyState
            v-if="pedidos.data.length === 0"
            :icon="ICONOS[tab]"
            title="Nada en esta etapa"
            description="Cuando haya pedidos en esta etapa aparecerán aquí."
        />

        <ul v-else class="grid stagger-children gap-3">
            <li
                v-for="p in pedidos.data"
                :key="p.id"
                class="hover-lift rounded-2xl border bg-card p-4"
            >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <label
                        v-if="
                            p.acciones.registrar_envio &&
                            (tab === 'listos' || tab === 'en_preparacion')
                        "
                        class="flex items-center gap-2 lg:self-start lg:pt-1"
                    >
                        <Checkbox
                            :model-value="filaLote(p).sel"
                            :aria-label="`Seleccionar ${p.folio}`"
                            @update:model-value="
                                (v) => (filaLote(p).sel = v === true)
                            "
                        />
                    </label>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                :href="pedidoShow(p.id).url"
                                class="font-semibold text-brand-green-strong hover:underline"
                                >{{ p.folio }}</Link
                            >
                            <StatusBadge :etiqueta="p.estado_logistico" />
                            <StatusBadge
                                v-if="
                                    p.metodo_pago?.value ===
                                    'efectivo_contra_entrega'
                                "
                                label="Contra entrega"
                                color="warning"
                            />
                        </div>
                        <p class="truncate text-sm">
                            {{ p.nombre_cliente }} · {{ p.items_count }} art. ·
                            {{ dinero(p.total) }}
                        </p>
                        <p
                            class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <template
                                v-if="
                                    p.modalidad_entrega?.value ===
                                    'recoger_en_tienda'
                                "
                                ><Store class="size-3" /> Recoge en
                                tienda</template
                            >
                            <template v-else
                                ><MapPin class="size-3" />
                                {{ p.destino ?? 'Sin dirección' }}</template
                            >
                            <span v-if="p.regla_envio">
                                · {{ p.regla_envio }}</span
                            >
                            <span>
                                · actualizado
                                {{ haceCuanto(p.updated_at) }}</span
                            >
                        </p>
                        <div
                            v-if="
                                tab === 'esperando_eleccion' &&
                                p.opciones_envio.length
                            "
                            class="mt-2"
                        >
                            <OpcionesEnvioLista :opciones="p.opciones_envio" />
                        </div>
                        <p v-if="p.numero_guia" class="mt-0.5 text-xs">
                            <Truck class="inline size-3" /> {{ p.paqueteria }} ·
                            <span class="font-medium">{{ p.numero_guia }}</span>
                            <span
                                v-if="p.fecha_envio"
                                class="text-muted-foreground"
                            >
                                · {{ fecha(p.fecha_envio) }}</span
                            >
                        </p>
                    </div>

                    <!-- Captura de guía en línea -->
                    <div
                        v-if="
                            p.acciones.registrar_envio &&
                            (tab === 'listos' || tab === 'en_preparacion')
                        "
                        class="grid gap-2 sm:grid-cols-[9rem_minmax(0,1fr)_9rem_auto] lg:w-[36rem]"
                    >
                        <Input
                            v-model="filaLote(p).paqueteria"
                            list="paqueterias-lote"
                            placeholder="Paquetería"
                            :aria-label="`Paquetería ${p.folio}`"
                        />
                        <Input
                            v-model="filaLote(p).guia"
                            placeholder="Número de guía"
                            :aria-label="`Guía ${p.folio}`"
                            @input="filaLote(p).sel = Boolean(filaLote(p).guia)"
                        />
                        <Input
                            v-model="filaLote(p).fecha_envio"
                            type="date"
                            :max="hoyMexico()"
                            :aria-label="`Fecha de envío ${p.folio}`"
                        />
                        <Button
                            size="sm"
                            class="h-9"
                            :disabled="
                                !filaLote(p).guia.trim() ||
                                !filaLote(p).paqueteria.trim() ||
                                enviandoLote
                            "
                            @click="registrarLote(p.id)"
                            >Enviar</Button
                        >
                    </div>

                    <div class="flex flex-wrap gap-2 lg:justify-end">
                        <Button
                            v-if="p.acciones.cotizar_envio"
                            size="sm"
                            :variant="
                                p.opciones_envio.length ? 'outline' : 'default'
                            "
                            @click="abrirCotizador(p)"
                            ><Wallet class="size-4" />
                            {{
                                p.opciones_envio.length
                                    ? 'Reemplazar opciones'
                                    : 'Cotizar'
                            }}</Button
                        >
                        <Button
                            v-if="p.acciones.iniciar_preparacion"
                            size="sm"
                            :disabled="procesando === p.id"
                            @click="postSimple(p, 'preparacion')"
                        >
                            <Package class="size-4" /> Preparar
                        </Button>
                        <Button
                            v-if="p.acciones.marcar_listo_envio"
                            size="sm"
                            variant="outline"
                            :disabled="procesando === p.id"
                            @click="postSimple(p, 'listo-envio')"
                            >Listo para envío</Button
                        >
                        <Button
                            v-if="p.acciones.marcar_listo_recoger"
                            size="sm"
                            :disabled="procesando === p.id"
                            @click="postSimple(p, 'listo-recoger')"
                            ><Store class="size-4" /> Listo para recoger</Button
                        >
                        <Button
                            v-if="
                                p.acciones.confirmar_entrega &&
                                !(
                                    p.metodo_pago?.value ===
                                        'efectivo_contra_entrega' &&
                                    p.estado_pago?.value === 'contra_entrega'
                                )
                            "
                            size="sm"
                            @click="abrirEntrega(p)"
                            ><CheckCircle2 class="size-4" /> Entregado</Button
                        >
                        <Button
                            v-else-if="p.acciones.confirmar_entrega"
                            size="sm"
                            variant="outline"
                            as-child
                            ><Link :href="pedidoShow(p.id).url"
                                >Cobrar y entregar</Link
                            ></Button
                        >
                    </div>
                </div>
            </li>
        </ul>

        <Paginacion :pagina="pedidos" />
    </div>

    <!-- Cotizar con opciones de paquetería -->
    <Dialog v-model:open="cotizadorAbierto">
        <DialogContent
            v-if="cotizando"
            class="max-h-[92vh] overflow-y-auto sm:max-w-3xl"
        >
            <DialogHeader>
                <DialogTitle class="font-display text-xl"
                    >Cotizar envío · {{ cotizando.folio }}</DialogTitle
                >
                <DialogDescription>
                    Destino: {{ cotizando.destino ?? 'sin dirección' }} ·
                    {{ cotizando.items_count }} art. · subtotal
                    {{ dinero(cotizando.subtotal) }}. El cliente elegirá una
                    opción y su costo se sumará al total.
                </DialogDescription>
            </DialogHeader>
            <CotizadorEnvio
                :key="cotizando.id"
                :pedido-id="cotizando.id"
                :paqueterias="paqueterias"
                :modo="cotizando.opciones_envio.length ? 'reemplazar' : 'nuevo'"
                :previas="cotizando.opciones_envio"
                :puede-gestionar-paqueterias="puedeGestionarReglas"
                compacto
                @enviado="cotizadorAbierto = false"
                @cancelar="cotizadorAbierto = false"
            />
        </DialogContent>
    </Dialog>

    <AccionDialog
        v-if="objetivo"
        v-model:open="abierto"
        :title="`Confirmar entrega · ${objetivo.folio}`"
        description="El cliente ya recibió su pedido."
        confirm-label="Confirmar entrega"
        :procesando="form.processing"
        @confirmar="confirmarEntrega"
    >
        <CampoForm
            label="Notas (opcional)"
            for="e-notas"
            :error="form.errors.notas"
        >
            <Textarea
                id="e-notas"
                v-model="form.notas"
                rows="2"
                maxlength="500"
            />
        </CampoForm>
    </AccionDialog>
</template>
