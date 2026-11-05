<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    Building2,
    CircleAlert,
    CircleCheck,
    CreditCard,
    FileText,
    Home,
    Landmark,
    Lock,
    MapPin,
    Plus,
    Save,
    Sparkles,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CampoForm from '@/components/admin/CampoForm.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import BeneficioIcono from '@/components/public/BeneficioIcono.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { update as configUpdate } from '@/routes/admin/configuraciones';
import { update as metodoUpdate } from '@/routes/admin/metodos-pago';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Configuración', href: '/admin/configuraciones' },
        ],
    },
});

type Faq = { pregunta: string; respuesta: string };
type Beneficio = { titulo: string; descripcion: string; icono: string | null };

type Metodo = {
    id: number;
    clave: string;
    nombre: string;
    activo: boolean;
    bloqueado: boolean;
    pagos_count: number;
    configuracion: {
        instrucciones: string | null;
        horas_vencimiento: number | null;
        estados: string[];
        monto_maximo: number | null;
    };
};

const props = defineProps<{
    tab: string;
    valores: {
        negocio: {
            nombre: string | null;
            telefono: string | null;
            whatsapp: string | null;
            correo: string | null;
            direccion: string | null;
            horario: string | null;
        };
        identidad: {
            eslogan: string | null;
            facebook: string | null;
            instagram: string | null;
            tiktok: string | null;
            seo_descripcion: string | null;
        };
        banco: {
            nombre: string | null;
            beneficiario: string | null;
            clabe: string | null;
            cuenta: string | null;
            referencia_instrucciones: string | null;
        };
        recoger: { direccion: string | null; horario: string | null };
        politicas: {
            envios: string | null;
            devoluciones: string | null;
            privacidad: string | null;
            terminos: string | null;
        };
        home: { faq: Faq[]; beneficios: Beneficio[] };
    };
    estado: {
        banco_configurado: boolean;
        recoger_disponible: boolean;
        pagos_en_linea: boolean;
    };
    metodos: Metodo[];
    estadosMexico: string[];
    iconosBeneficio: string[];
}>();

const TABS = [
    { value: 'negocio', label: 'Negocio', icon: Building2 },
    { value: 'identidad', label: 'Identidad', icon: Sparkles },
    { value: 'banco', label: 'Datos bancarios', icon: Landmark },
    { value: 'metodos', label: 'Métodos de pago', icon: CreditCard },
    { value: 'recoger', label: 'Recoger en tienda', icon: MapPin },
    { value: 'politicas', label: 'Políticas', icon: FileText },
    { value: 'home', label: 'Inicio', icon: Home },
] as const;

const tabActiva = ref(
    TABS.some((t) => t.value === props.tab) ? props.tab : 'negocio',
);

function cambiarTab(valor: string | number) {
    tabActiva.value = String(valor);
    const url = new URL(window.location.href);
    url.searchParams.set('tab', String(valor));
    window.history.replaceState(window.history.state, '', url.toString());
}

const indiceActivo = computed(() =>
    Math.max(
        0,
        TABS.findIndex((t) => t.value === tabActiva.value),
    ),
);

const botonesTab = ref<HTMLButtonElement[]>([]);

// Navegación con flechas / Inicio / Fin entre pestañas (patrón WAI-ARIA tabs).
function moverConTeclado(e: KeyboardEvent) {
    const total = TABS.length;
    let destino: number | null = null;

    if (['ArrowDown', 'ArrowRight'].includes(e.key)) {
        destino = (indiceActivo.value + 1) % total;
    } else if (['ArrowUp', 'ArrowLeft'].includes(e.key)) {
        destino = (indiceActivo.value - 1 + total) % total;
    } else if (e.key === 'Home') {
        destino = 0;
    } else if (e.key === 'End') {
        destino = total - 1;
    }

    if (destino === null) {
        return;
    }

    e.preventDefault();
    cambiarTab(TABS[destino].value);
    botonesTab.value[destino]?.focus();
}

const opciones = { preserveScroll: true };

// ---------------------------------------------------------------- formularios por sección
const negocio = useForm({
    nombre: props.valores.negocio.nombre ?? '',
    telefono: props.valores.negocio.telefono ?? '',
    whatsapp: props.valores.negocio.whatsapp ?? '',
    correo: props.valores.negocio.correo ?? '',
    direccion: props.valores.negocio.direccion ?? '',
    horario: props.valores.negocio.horario ?? '',
});

const identidad = useForm({
    eslogan: props.valores.identidad.eslogan ?? '',
    facebook: props.valores.identidad.facebook ?? '',
    instagram: props.valores.identidad.instagram ?? '',
    tiktok: props.valores.identidad.tiktok ?? '',
    seo_descripcion: props.valores.identidad.seo_descripcion ?? '',
});

const banco = useForm({
    nombre: props.valores.banco.nombre ?? '',
    beneficiario: props.valores.banco.beneficiario ?? '',
    clabe: props.valores.banco.clabe ?? '',
    cuenta: props.valores.banco.cuenta ?? '',
    referencia_instrucciones:
        props.valores.banco.referencia_instrucciones ?? '',
});

const recoger = useForm({
    direccion: props.valores.recoger.direccion ?? '',
    horario: props.valores.recoger.horario ?? '',
});

const politicas = useForm({
    envios: props.valores.politicas.envios ?? '',
    devoluciones: props.valores.politicas.devoluciones ?? '',
    privacidad: props.valores.politicas.privacidad ?? '',
    terminos: props.valores.politicas.terminos ?? '',
});

const home = useForm({
    faq: props.valores.home.faq.map((f) => ({
        pregunta: f.pregunta ?? '',
        respuesta: f.respuesta ?? '',
    })) as Faq[],
    beneficios: props.valores.home.beneficios.map((b) => ({
        titulo: b.titulo ?? '',
        descripcion: b.descripcion ?? '',
        icono: b.icono ?? null,
    })) as Beneficio[],
});

function guardar(seccion: string, form: ReturnType<typeof useForm<any>>) {
    form.put(configUpdate(seccion).url, opciones);
}

// ---------------------------------------------------------------- CLABE
const clabeLimpia = computed(() => banco.clabe.replace(/\s+/g, ''));

const clabeEstado = computed<'vacia' | 'incompleta' | 'invalida' | 'valida'>(
    () => {
        const c = clabeLimpia.value;

        if (c === '') {
            return 'vacia';
        }

        if (!/^\d{18}$/.test(c)) {
            return /^\d{0,17}$/.test(c) ? 'incompleta' : 'invalida';
        }

        const pesos = [3, 7, 1];
        let suma = 0;

        for (let i = 0; i < 17; i++) {
            suma += (Number(c[i]) * pesos[i % 3]) % 10;
        }

        return (10 - (suma % 10)) % 10 === Number(c[17])
            ? 'valida'
            : 'invalida';
    },
);

// ---------------------------------------------------------------- listas del inicio
function mover<T>(lista: T[], i: number, delta: number) {
    const j = i + delta;

    if (j < 0 || j >= lista.length) {
        return;
    }

    const [item] = lista.splice(i, 1);
    lista.splice(j, 0, item);
}

function errorDe(form: { errors: Record<string, string> }, clave: string) {
    return form.errors[clave];
}

// ---------------------------------------------------------------- métodos de pago
const formsMetodos = Object.fromEntries(
    props.metodos.map((m) => [
        m.id,
        useForm({
            nombre: m.nombre,
            clave: m.clave,
            activo: m.activo,
            configuracion: {
                instrucciones: m.configuracion.instrucciones ?? '',
                horas_vencimiento: m.configuracion.horas_vencimiento ?? 48,
                estados: [...(m.configuracion.estados ?? [])],
                monto_maximo: (m.configuracion.monto_maximo ?? '') as
                    | number
                    | '',
            },
        }),
    ]),
);

function guardarMetodo(m: Metodo) {
    const form = formsMetodos[m.id];
    form.transform((d) => {
        const conf: Record<string, unknown> = {
            instrucciones: d.configuracion.instrucciones || null,
        };

        if (['transferencia', 'efectivo_presencial'].includes(m.clave)) {
            conf.horas_vencimiento = d.configuracion.horas_vencimiento || null;
        }

        if (m.clave === 'efectivo_contra_entrega') {
            conf.estados = d.configuracion.estados;
            conf.monto_maximo =
                d.configuracion.monto_maximo === '' ||
                d.configuracion.monto_maximo === null
                    ? null
                    : Number(d.configuracion.monto_maximo);
        }

        return { ...d, configuracion: conf };
    }).put(metodoUpdate(m.id).url, opciones);
}

function alternarEstado(lista: string[], estado: string) {
    const i = lista.indexOf(estado);

    if (i >= 0) {
        lista.splice(i, 1);
    } else {
        lista.push(estado);
    }
}

const DESCRIPCION_METODO: Record<string, string> = {
    transferencia:
        'El cliente transfiere y sube su comprobante; el equipo lo revisa y aprueba.',
    efectivo_presencial:
        'El cliente paga en tienda; el equipo confirma el monto recibido.',
    efectivo_contra_entrega:
        'Se cobra al entregar. Requiere una regla de envío que permita contra entrega.',
    openpay: 'Pagos con tarjeta en línea.',
};
</script>

<template>
    <Head title="Configuración" />

    <div class="flex w-full flex-col gap-6 p-4 md:p-6">
        <PageHeader
            eyebrow="Administración"
            title="Configuración de la tienda"
            description="Datos del negocio, pagos, entregas y contenido de la tienda. Los cambios se reflejan de inmediato."
        >
            <StatusBadge
                :label="
                    estado.banco_configurado
                        ? 'Transferencia lista'
                        : 'Banco pendiente de configurar'
                "
                :color="estado.banco_configurado ? 'success' : 'warning'"
                :pulso="!estado.banco_configurado"
                size="md"
            />
        </PageHeader>

        <div
            class="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)] lg:items-start"
        >
            <!-- Navegación de secciones: horizontal con scroll en móvil, vertical sticky en lg+ -->
            <div
                class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0 lg:sticky lg:top-20 lg:overflow-visible"
            >
                <nav
                    role="tablist"
                    aria-label="Secciones de configuración"
                    aria-orientation="vertical"
                    class="relative flex w-max gap-1 surface-card p-1.5 lg:w-full lg:flex-col"
                    @keydown="moverConTeclado"
                >
                    <span
                        aria-hidden="true"
                        class="pointer-events-none absolute inset-x-1.5 top-1.5 hidden h-10 rounded-lg bg-brand-gradient shadow-brand-sm transition-all duration-300 ease-out lg:block"
                        :style="{
                            transform: `translateY(calc(${indiceActivo} * 2.75rem))`,
                        }"
                    />
                    <button
                        v-for="t in TABS"
                        :id="`tab-${t.value}`"
                        :key="t.value"
                        ref="botonesTab"
                        type="button"
                        role="tab"
                        :aria-selected="tabActiva === t.value"
                        :aria-controls="`panel-${t.value}`"
                        :tabindex="tabActiva === t.value ? 0 : -1"
                        :class="[
                            'relative z-10 flex h-10 shrink-0 items-center gap-2 rounded-lg px-3 text-sm font-medium whitespace-nowrap focus-brand transition-colors duration-300',
                            tabActiva === t.value
                                ? 'text-brand-ink max-lg:bg-brand-gradient max-lg:shadow-brand-sm'
                                : 'text-muted-foreground hover:bg-brand-green-soft hover:text-brand-deep',
                        ]"
                        @click="cambiarTab(t.value)"
                    >
                        <component :is="t.icon" class="size-4 shrink-0" />
                        <span class="lg:flex-1 lg:text-left">{{
                            t.label
                        }}</span>
                        <span
                            v-if="
                                t.value === 'banco' && !estado.banco_configurado
                            "
                            class="size-1.5 rounded-full bg-warning"
                        />
                    </button>
                </nav>
            </div>

            <!-- Contenido de la sección activa -->
            <div :key="tabActiva" class="min-w-0 animate-fade-up">
                <!-- Negocio -->
                <section
                    v-if="tabActiva === 'negocio'"
                    id="panel-negocio"
                    role="tabpanel"
                    aria-labelledby="tab-negocio"
                >
                    <Card
                        class="transition-colors duration-300 hover:border-brand-green/40"
                    >
                        <CardHeader>
                            <CardTitle class="font-display text-xl"
                                >Datos del negocio y contacto</CardTitle
                            >
                            <CardDescription
                                >Se muestran en el pie de la tienda, correos y
                                botón de WhatsApp.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <form
                                class="grid stagger-children gap-4 lg:grid-cols-2"
                                @submit.prevent="guardar('negocio', negocio)"
                            >
                                <CampoForm
                                    label="Nombre de la tienda"
                                    for="neg-nombre"
                                    requerido
                                    :error="negocio.errors.nombre"
                                >
                                    <Input
                                        id="neg-nombre"
                                        v-model="negocio.nombre"
                                        maxlength="120"
                                    />
                                </CampoForm>
                                <CampoForm
                                    label="Correo de contacto"
                                    for="neg-correo"
                                    :error="negocio.errors.correo"
                                >
                                    <Input
                                        id="neg-correo"
                                        v-model="negocio.correo"
                                        type="email"
                                        maxlength="160"
                                    />
                                </CampoForm>
                                <CampoForm
                                    label="Teléfono"
                                    for="neg-tel"
                                    :error="negocio.errors.telefono"
                                >
                                    <Input
                                        id="neg-tel"
                                        v-model="negocio.telefono"
                                        inputmode="tel"
                                        maxlength="30"
                                    />
                                </CampoForm>
                                <CampoForm
                                    label="WhatsApp"
                                    for="neg-wa"
                                    :error="negocio.errors.whatsapp"
                                    ayuda="Sólo dígitos con lada, p. ej. 5217771234567."
                                >
                                    <Input
                                        id="neg-wa"
                                        v-model="negocio.whatsapp"
                                        inputmode="numeric"
                                        maxlength="20"
                                    />
                                </CampoForm>
                                <CampoForm
                                    label="Dirección"
                                    for="neg-dir"
                                    :error="negocio.errors.direccion"
                                >
                                    <Textarea
                                        id="neg-dir"
                                        v-model="negocio.direccion"
                                        rows="3"
                                        maxlength="300"
                                    />
                                </CampoForm>
                                <CampoForm
                                    label="Horario de atención"
                                    for="neg-hor"
                                    :error="negocio.errors.horario"
                                >
                                    <Textarea
                                        id="neg-hor"
                                        v-model="negocio.horario"
                                        rows="3"
                                        maxlength="300"
                                    />
                                </CampoForm>
                                <div class="flex justify-end lg:col-span-2">
                                    <Button
                                        type="submit"
                                        :disabled="negocio.processing"
                                    >
                                        <Save class="size-4" /> Guardar
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </section>

                <!-- Identidad -->
                <section
                    v-if="tabActiva === 'identidad'"
                    id="panel-identidad"
                    role="tabpanel"
                    aria-labelledby="tab-identidad"
                >
                    <Card
                        class="transition-colors duration-300 hover:border-brand-green/40"
                    >
                        <CardHeader>
                            <CardTitle class="font-display text-xl"
                                >Identidad y redes</CardTitle
                            >
                            <CardDescription
                                >Eslogan, redes sociales y descripción para
                                buscadores.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <form
                                class="grid stagger-children gap-4 lg:grid-cols-2"
                                @submit.prevent="
                                    guardar('identidad', identidad)
                                "
                            >
                                <CampoForm
                                    class="lg:col-span-2"
                                    label="Eslogan"
                                    for="id-eslogan"
                                    :error="identidad.errors.eslogan"
                                >
                                    <Input
                                        id="id-eslogan"
                                        v-model="identidad.eslogan"
                                        maxlength="160"
                                    />
                                </CampoForm>
                                <CampoForm
                                    label="Facebook"
                                    for="id-fb"
                                    :error="identidad.errors.facebook"
                                >
                                    <Input
                                        id="id-fb"
                                        v-model="identidad.facebook"
                                        type="url"
                                        placeholder="https://facebook.com/..."
                                    />
                                </CampoForm>
                                <CampoForm
                                    label="Instagram"
                                    for="id-ig"
                                    :error="identidad.errors.instagram"
                                >
                                    <Input
                                        id="id-ig"
                                        v-model="identidad.instagram"
                                        type="url"
                                        placeholder="https://instagram.com/..."
                                    />
                                </CampoForm>
                                <CampoForm
                                    label="TikTok"
                                    for="id-tt"
                                    :error="identidad.errors.tiktok"
                                >
                                    <Input
                                        id="id-tt"
                                        v-model="identidad.tiktok"
                                        type="url"
                                        placeholder="https://tiktok.com/@..."
                                    />
                                </CampoForm>
                                <CampoForm
                                    class="lg:col-span-2"
                                    label="Descripción para buscadores (SEO)"
                                    for="id-seo"
                                    :error="identidad.errors.seo_descripcion"
                                    :ayuda="`${identidad.seo_descripcion.length}/300 caracteres`"
                                >
                                    <Textarea
                                        id="id-seo"
                                        v-model="identidad.seo_descripcion"
                                        rows="3"
                                        maxlength="300"
                                    />
                                </CampoForm>
                                <div class="flex justify-end lg:col-span-2">
                                    <Button
                                        type="submit"
                                        :disabled="identidad.processing"
                                    >
                                        <Save class="size-4" /> Guardar
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </section>

                <!-- Banco -->
                <section
                    v-if="tabActiva === 'banco'"
                    id="panel-banco"
                    role="tabpanel"
                    aria-labelledby="tab-banco"
                    class="grid stagger-children gap-4"
                >
                    <Alert
                        v-if="!estado.banco_configurado"
                        class="border-warning/40 bg-warning/5"
                    >
                        <CircleAlert class="size-4 text-warning" />
                        <AlertTitle>Pendiente de configurar</AlertTitle>
                        <AlertDescription>
                            Sin banco, beneficiario y una CLABE válida, los
                            clientes no reciben instrucciones de transferencia.
                        </AlertDescription>
                    </Alert>
                    <Alert v-else class="border-success/30 bg-success/5">
                        <CircleCheck class="size-4 text-success" />
                        <AlertTitle>Datos bancarios completos</AlertTitle>
                        <AlertDescription
                            >Se muestran sólo al dueño del pedido, en su correo
                            de instrucciones y en este panel.</AlertDescription
                        >
                    </Alert>
                    <Card
                        class="transition-colors duration-300 hover:border-brand-green/40"
                    >
                        <CardHeader>
                            <CardTitle class="font-display text-xl"
                                >Cuenta para transferencias</CardTitle
                            >
                            <CardDescription
                                >Revisa cada dato: es lo que verá el cliente
                                para pagarte.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <form
                                class="grid stagger-children gap-4 lg:grid-cols-2"
                                @submit.prevent="guardar('banco', banco)"
                            >
                                <CampoForm
                                    label="Banco"
                                    for="b-nombre"
                                    :error="banco.errors.nombre"
                                >
                                    <Input
                                        id="b-nombre"
                                        v-model="banco.nombre"
                                        maxlength="80"
                                    />
                                </CampoForm>
                                <CampoForm
                                    label="Beneficiario"
                                    for="b-benef"
                                    :error="banco.errors.beneficiario"
                                >
                                    <Input
                                        id="b-benef"
                                        v-model="banco.beneficiario"
                                        maxlength="160"
                                    />
                                </CampoForm>
                                <CampoForm
                                    label="CLABE interbancaria"
                                    for="b-clabe"
                                    :error="banco.errors.clabe"
                                >
                                    <Input
                                        id="b-clabe"
                                        v-model="banco.clabe"
                                        inputmode="numeric"
                                        maxlength="22"
                                        class="font-mono tracking-wider"
                                        :aria-invalid="
                                            clabeEstado === 'invalida' ||
                                            !!banco.errors.clabe
                                        "
                                    />
                                    <div
                                        class="flex items-center justify-between text-xs"
                                    >
                                        <span
                                            :class="{
                                                'text-muted-foreground':
                                                    clabeEstado === 'vacia' ||
                                                    clabeEstado ===
                                                        'incompleta',
                                                'text-destructive':
                                                    clabeEstado === 'invalida',
                                                'text-success':
                                                    clabeEstado === 'valida',
                                            }"
                                        >
                                            <template
                                                v-if="clabeEstado === 'valida'"
                                                >Dígito verificador
                                                correcto</template
                                            >
                                            <template
                                                v-else-if="
                                                    clabeEstado === 'invalida'
                                                "
                                                >La CLABE no parece
                                                válida</template
                                            >
                                            <template
                                                v-else-if="
                                                    clabeEstado === 'incompleta'
                                                "
                                                >Faltan dígitos</template
                                            >
                                            <template v-else
                                                >18 dígitos</template
                                            >
                                        </span>
                                        <span
                                            class="text-muted-foreground tabular-nums"
                                            >{{ clabeLimpia.length }}/18</span
                                        >
                                    </div>
                                </CampoForm>
                                <CampoForm
                                    label="Número de cuenta (opcional)"
                                    for="b-cuenta"
                                    :error="banco.errors.cuenta"
                                >
                                    <Input
                                        id="b-cuenta"
                                        v-model="banco.cuenta"
                                        inputmode="numeric"
                                        maxlength="24"
                                        class="font-mono"
                                    />
                                </CampoForm>
                                <CampoForm
                                    class="lg:col-span-2"
                                    label="Instrucciones de referencia"
                                    for="b-ref"
                                    :error="
                                        banco.errors.referencia_instrucciones
                                    "
                                    ayuda="P. ej. «Escribe tu número de pedido en el concepto». Texto plano."
                                >
                                    <Textarea
                                        id="b-ref"
                                        v-model="banco.referencia_instrucciones"
                                        rows="3"
                                        maxlength="600"
                                    />
                                </CampoForm>
                                <div class="flex justify-end lg:col-span-2">
                                    <Button
                                        type="submit"
                                        :disabled="banco.processing"
                                    >
                                        <Save class="size-4" /> Guardar datos
                                        bancarios
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </section>

                <!-- Métodos de pago -->
                <section
                    v-if="tabActiva === 'metodos'"
                    id="panel-metodos"
                    role="tabpanel"
                    aria-labelledby="tab-metodos"
                    class="grid stagger-children gap-4 xl:grid-cols-2"
                >
                    <Card
                        v-for="m in metodos"
                        class="transition-colors duration-300 hover:border-brand-green/40"
                        :key="m.id"
                        :class="m.bloqueado ? 'border-dashed bg-muted/30' : ''"
                    >
                        <CardHeader
                            class="flex flex-row items-start justify-between gap-3 space-y-0"
                        >
                            <div class="min-w-0">
                                <CardTitle
                                    class="flex items-center gap-2 font-display text-lg"
                                >
                                    <Lock
                                        v-if="m.bloqueado"
                                        class="size-4 text-muted-foreground"
                                    />
                                    {{
                                        m.bloqueado
                                            ? 'Tarjetas — próximamente'
                                            : m.nombre
                                    }}
                                </CardTitle>
                                <CardDescription class="mt-1">
                                    {{
                                        m.bloqueado
                                            ? 'Los pagos con tarjeta están deshabilitados en esta tienda y no pueden activarse desde el panel.'
                                            : DESCRIPCION_METODO[m.clave]
                                    }}
                                </CardDescription>
                            </div>
                            <StatusBadge
                                v-if="m.bloqueado"
                                label="Bloqueado"
                                color="neutral"
                            />
                            <Switch
                                v-else
                                :model-value="formsMetodos[m.id].activo"
                                :aria-label="`Activar ${m.nombre}`"
                                @update:model-value="
                                    (v: boolean) =>
                                        (formsMetodos[m.id].activo = v)
                                "
                            />
                        </CardHeader>
                        <CardContent v-if="!m.bloqueado">
                            <form
                                class="grid gap-4"
                                @submit.prevent="guardarMetodo(m)"
                            >
                                <p
                                    v-if="formsMetodos[m.id].errors.activo"
                                    class="text-xs font-medium text-destructive"
                                    role="alert"
                                >
                                    {{ formsMetodos[m.id].errors.activo }}
                                </p>
                                <Alert
                                    v-if="
                                        m.clave === 'transferencia' &&
                                        !estado.banco_configurado
                                    "
                                    class="border-warning/40 bg-warning/5"
                                >
                                    <CircleAlert class="size-4 text-warning" />
                                    <AlertDescription
                                        >Configura los datos bancarios para
                                        cobrar por
                                        transferencia.</AlertDescription
                                    >
                                </Alert>
                                <CampoForm
                                    label="Instrucciones para el cliente"
                                    :for="`m-ins-${m.id}`"
                                    :error="
                                        errorDe(
                                            formsMetodos[m.id],
                                            'configuracion.instrucciones',
                                        )
                                    "
                                >
                                    <Textarea
                                        :id="`m-ins-${m.id}`"
                                        v-model="
                                            formsMetodos[m.id].configuracion
                                                .instrucciones
                                        "
                                        rows="3"
                                        maxlength="1000"
                                    />
                                </CampoForm>
                                <CampoForm
                                    v-if="
                                        [
                                            'transferencia',
                                            'efectivo_presencial',
                                        ].includes(m.clave)
                                    "
                                    label="Horas para pagar antes de que venza el pedido"
                                    :for="`m-hrs-${m.id}`"
                                    :error="
                                        errorDe(
                                            formsMetodos[m.id],
                                            'configuracion.horas_vencimiento',
                                        )
                                    "
                                    ayuda="Al vencer, el pedido se cancela y se liberan las existencias."
                                >
                                    <Input
                                        :id="`m-hrs-${m.id}`"
                                        v-model.number="
                                            formsMetodos[m.id].configuracion
                                                .horas_vencimiento
                                        "
                                        type="number"
                                        min="1"
                                        max="720"
                                        class="w-32"
                                    />
                                </CampoForm>
                                <template
                                    v-if="m.clave === 'efectivo_contra_entrega'"
                                >
                                    <CampoForm
                                        label="Monto máximo por pedido (opcional)"
                                        :for="`m-max-${m.id}`"
                                        :error="
                                            errorDe(
                                                formsMetodos[m.id],
                                                'configuracion.monto_maximo',
                                            )
                                        "
                                    >
                                        <Input
                                            :id="`m-max-${m.id}`"
                                            v-model="
                                                formsMetodos[m.id].configuracion
                                                    .monto_maximo
                                            "
                                            type="number"
                                            min="1"
                                            step="0.01"
                                            class="w-40"
                                        />
                                    </CampoForm>
                                    <div class="grid gap-2">
                                        <p class="text-sm font-medium">
                                            Estados permitidos
                                        </p>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                formsMetodos[m.id].configuracion
                                                    .estados.length === 0
                                                    ? 'Sin selección: se permite en todos los estados que cubran tus reglas de envío.'
                                                    : `${formsMetodos[m.id].configuracion.estados.length} seleccionados`
                                            }}
                                        </p>
                                        <div
                                            class="flex max-h-48 flex-wrap gap-1.5 overflow-y-auto"
                                        >
                                            <button
                                                v-for="e in estadosMexico"
                                                :key="e"
                                                type="button"
                                                :aria-pressed="
                                                    formsMetodos[
                                                        m.id
                                                    ].configuracion.estados.includes(
                                                        e,
                                                    )
                                                "
                                                :class="[
                                                    'rounded-full border px-2.5 py-1 text-xs transition',
                                                    formsMetodos[
                                                        m.id
                                                    ].configuracion.estados.includes(
                                                        e,
                                                    )
                                                        ? 'border-brand-green bg-brand-green-soft font-semibold text-brand-green-strong'
                                                        : 'hover:bg-accent',
                                                ]"
                                                @click="
                                                    alternarEstado(
                                                        formsMetodos[m.id]
                                                            .configuracion
                                                            .estados,
                                                        e,
                                                    )
                                                "
                                            >
                                                {{ e }}
                                            </button>
                                        </div>
                                        <p
                                            v-if="
                                                errorDe(
                                                    formsMetodos[m.id],
                                                    'configuracion.estados',
                                                )
                                            "
                                            class="text-xs text-destructive"
                                        >
                                            {{
                                                errorDe(
                                                    formsMetodos[m.id],
                                                    'configuracion.estados',
                                                )
                                            }}
                                        </p>
                                    </div>
                                </template>
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <span class="text-xs text-muted-foreground"
                                        >{{ m.pagos_count }} pagos
                                        registrados</span
                                    >
                                    <Button
                                        type="submit"
                                        size="sm"
                                        :disabled="
                                            formsMetodos[m.id].processing
                                        "
                                    >
                                        <Save class="size-4" /> Guardar
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </section>

                <!-- Recoger -->
                <section
                    v-if="tabActiva === 'recoger'"
                    id="panel-recoger"
                    role="tabpanel"
                    aria-labelledby="tab-recoger"
                >
                    <Card
                        class="transition-colors duration-300 hover:border-brand-green/40"
                    >
                        <CardHeader>
                            <CardTitle
                                class="flex items-center gap-2 font-display text-xl"
                            >
                                Recoger en tienda
                                <StatusBadge
                                    :label="
                                        estado.recoger_disponible
                                            ? 'Disponible en checkout'
                                            : 'No disponible'
                                    "
                                    :color="
                                        estado.recoger_disponible
                                            ? 'success'
                                            : 'neutral'
                                    "
                                />
                            </CardTitle>
                            <CardDescription>
                                Con una dirección configurada (o una regla
                                «Recoger en tienda» activa) el cliente puede
                                elegir recoger su pedido.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                class="grid stagger-children gap-4 lg:grid-cols-2"
                                @submit.prevent="guardar('recoger', recoger)"
                            >
                                <CampoForm
                                    label="Dirección de entrega"
                                    for="r-dir"
                                    :error="recoger.errors.direccion"
                                >
                                    <Textarea
                                        id="r-dir"
                                        v-model="recoger.direccion"
                                        rows="4"
                                        maxlength="300"
                                    />
                                </CampoForm>
                                <CampoForm
                                    label="Horario para recoger"
                                    for="r-hor"
                                    :error="recoger.errors.horario"
                                >
                                    <Textarea
                                        id="r-hor"
                                        v-model="recoger.horario"
                                        rows="4"
                                        maxlength="300"
                                    />
                                </CampoForm>
                                <div class="flex justify-end lg:col-span-2">
                                    <Button
                                        type="submit"
                                        :disabled="recoger.processing"
                                    >
                                        <Save class="size-4" /> Guardar
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </section>

                <!-- Políticas -->
                <section
                    v-if="tabActiva === 'politicas'"
                    id="panel-politicas"
                    role="tabpanel"
                    aria-labelledby="tab-politicas"
                >
                    <Card
                        class="transition-colors duration-300 hover:border-brand-green/40"
                    >
                        <CardHeader>
                            <CardTitle class="font-display text-xl"
                                >Políticas de la tienda</CardTitle
                            >
                            <CardDescription>
                                Texto plano: se conservan los saltos de línea y
                                se elimina cualquier HTML.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form
                                class="grid stagger-children gap-5 lg:grid-cols-2"
                                @submit.prevent="
                                    guardar('politicas', politicas)
                                "
                            >
                                <CampoForm
                                    label="Envíos"
                                    for="p-env"
                                    :error="politicas.errors.envios"
                                >
                                    <Textarea
                                        id="p-env"
                                        v-model="politicas.envios"
                                        rows="6"
                                    />
                                </CampoForm>
                                <CampoForm
                                    label="Cambios y devoluciones"
                                    for="p-dev"
                                    :error="politicas.errors.devoluciones"
                                >
                                    <Textarea
                                        id="p-dev"
                                        v-model="politicas.devoluciones"
                                        rows="6"
                                    />
                                </CampoForm>
                                <CampoForm
                                    label="Aviso de privacidad"
                                    for="p-priv"
                                    :error="politicas.errors.privacidad"
                                >
                                    <Textarea
                                        id="p-priv"
                                        v-model="politicas.privacidad"
                                        rows="6"
                                    />
                                </CampoForm>
                                <CampoForm
                                    label="Términos y condiciones"
                                    for="p-ter"
                                    :error="politicas.errors.terminos"
                                >
                                    <Textarea
                                        id="p-ter"
                                        v-model="politicas.terminos"
                                        rows="6"
                                    />
                                </CampoForm>
                                <div class="flex justify-end lg:col-span-2">
                                    <Button
                                        type="submit"
                                        :disabled="politicas.processing"
                                    >
                                        <Save class="size-4" /> Guardar
                                        políticas
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </section>

                <!-- Inicio -->
                <section
                    v-if="tabActiva === 'home'"
                    id="panel-home"
                    role="tabpanel"
                    aria-labelledby="tab-home"
                >
                    <form
                        class="grid stagger-children gap-4 xl:grid-cols-2"
                        @submit.prevent="guardar('home', home)"
                    >
                        <Card
                            class="transition-colors duration-300 hover:border-brand-green/40"
                        >
                            <CardHeader
                                class="flex flex-row items-center justify-between gap-2 space-y-0"
                            >
                                <div>
                                    <CardTitle class="font-display text-xl"
                                        >Beneficios</CardTitle
                                    >
                                    <CardDescription
                                        >Franja de beneficios de la página de
                                        inicio.</CardDescription
                                    >
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="home.beneficios.length >= 12"
                                    @click="
                                        home.beneficios.push({
                                            titulo: '',
                                            descripcion: '',
                                            icono: 'sparkles',
                                        })
                                    "
                                >
                                    <Plus class="size-4" /> Agregar
                                </Button>
                            </CardHeader>
                            <CardContent class="grid gap-3">
                                <p
                                    v-if="home.beneficios.length === 0"
                                    class="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground"
                                >
                                    Sin beneficios: la franja no se muestra en
                                    la tienda.
                                </p>
                                <div
                                    v-for="(b, i) in home.beneficios"
                                    :key="i"
                                    class="grid gap-2 rounded-xl border bg-surface/50 p-3"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="grid size-9 shrink-0 place-items-center rounded-lg bg-brand-green-soft text-brand-green-strong"
                                        >
                                            <BeneficioIcono
                                                :nombre="b.icono"
                                                class="size-4.5"
                                            />
                                        </span>
                                        <NativeSelect
                                            v-model="b.icono"
                                            class="w-40"
                                            :aria-label="`Ícono del beneficio ${i + 1}`"
                                        >
                                            <option
                                                v-for="ic in iconosBeneficio"
                                                :key="ic"
                                                :value="ic"
                                            >
                                                {{ ic }}
                                            </option>
                                        </NativeSelect>
                                        <div class="ml-auto flex gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                class="size-8"
                                                aria-label="Subir"
                                                :disabled="i === 0"
                                                @click="
                                                    mover(
                                                        home.beneficios,
                                                        i,
                                                        -1,
                                                    )
                                                "
                                                ><ArrowUp class="size-4"
                                            /></Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                class="size-8"
                                                aria-label="Bajar"
                                                :disabled="
                                                    i ===
                                                    home.beneficios.length - 1
                                                "
                                                @click="
                                                    mover(home.beneficios, i, 1)
                                                "
                                                ><ArrowDown class="size-4"
                                            /></Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                class="size-8 text-destructive"
                                                aria-label="Quitar"
                                                @click="
                                                    home.beneficios.splice(i, 1)
                                                "
                                                ><Trash2 class="size-4"
                                            /></Button>
                                        </div>
                                    </div>
                                    <Input
                                        v-model="b.titulo"
                                        placeholder="Título"
                                        maxlength="80"
                                        :aria-invalid="
                                            !!errorDe(
                                                home,
                                                `beneficios.${i}.titulo`,
                                            )
                                        "
                                    />
                                    <p
                                        v-if="
                                            errorDe(
                                                home,
                                                `beneficios.${i}.titulo`,
                                            )
                                        "
                                        class="text-xs text-destructive"
                                    >
                                        {{
                                            errorDe(
                                                home,
                                                `beneficios.${i}.titulo`,
                                            )
                                        }}
                                    </p>
                                    <Input
                                        v-model="b.descripcion"
                                        placeholder="Descripción breve"
                                        maxlength="240"
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card
                            class="transition-colors duration-300 hover:border-brand-green/40"
                        >
                            <CardHeader
                                class="flex flex-row items-center justify-between gap-2 space-y-0"
                            >
                                <div>
                                    <CardTitle class="font-display text-xl"
                                        >Preguntas frecuentes</CardTitle
                                    >
                                    <CardDescription
                                        >Se muestran en el inicio y en la página
                                        de preguntas.</CardDescription
                                    >
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="home.faq.length >= 30"
                                    @click="
                                        home.faq.push({
                                            pregunta: '',
                                            respuesta: '',
                                        })
                                    "
                                >
                                    <Plus class="size-4" /> Agregar
                                </Button>
                            </CardHeader>
                            <CardContent class="grid gap-3">
                                <p
                                    v-if="home.faq.length === 0"
                                    class="rounded-xl border border-dashed p-4 text-center text-sm text-muted-foreground"
                                >
                                    Aún no hay preguntas frecuentes.
                                </p>
                                <div
                                    v-for="(f, i) in home.faq"
                                    :key="i"
                                    class="grid gap-2 rounded-xl border bg-surface/50 p-3"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-xs font-semibold text-muted-foreground"
                                            >#{{ i + 1 }}</span
                                        >
                                        <div class="ml-auto flex gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                class="size-8"
                                                aria-label="Subir"
                                                :disabled="i === 0"
                                                @click="mover(home.faq, i, -1)"
                                                ><ArrowUp class="size-4"
                                            /></Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                class="size-8"
                                                aria-label="Bajar"
                                                :disabled="
                                                    i === home.faq.length - 1
                                                "
                                                @click="mover(home.faq, i, 1)"
                                                ><ArrowDown class="size-4"
                                            /></Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                class="size-8 text-destructive"
                                                aria-label="Quitar"
                                                @click="home.faq.splice(i, 1)"
                                                ><Trash2 class="size-4"
                                            /></Button>
                                        </div>
                                    </div>
                                    <Input
                                        v-model="f.pregunta"
                                        placeholder="Pregunta"
                                        maxlength="200"
                                        :aria-invalid="
                                            !!errorDe(home, `faq.${i}.pregunta`)
                                        "
                                    />
                                    <p
                                        v-if="
                                            errorDe(home, `faq.${i}.pregunta`)
                                        "
                                        class="text-xs text-destructive"
                                    >
                                        {{ errorDe(home, `faq.${i}.pregunta`) }}
                                    </p>
                                    <Textarea
                                        v-model="f.respuesta"
                                        placeholder="Respuesta"
                                        rows="3"
                                        maxlength="1500"
                                        :aria-invalid="
                                            !!errorDe(
                                                home,
                                                `faq.${i}.respuesta`,
                                            )
                                        "
                                    />
                                    <p
                                        v-if="
                                            errorDe(home, `faq.${i}.respuesta`)
                                        "
                                        class="text-xs text-destructive"
                                    >
                                        {{
                                            errorDe(home, `faq.${i}.respuesta`)
                                        }}
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <div class="flex justify-end xl:col-span-2">
                            <Button type="submit" :disabled="home.processing">
                                <Save class="size-4" /> Guardar contenido de
                                inicio
                            </Button>
                        </div>
                    </form>
                </section>
            </div>
        </div>

        <p class="text-center text-xs text-muted-foreground">
            ¿Buscas los costos de envío? Se configuran en
            <a
                href="/admin/reglas-envio"
                class="font-medium text-brand-green-strong underline-offset-2 hover:underline"
                >Reglas de envío</a
            >.
        </p>
    </div>
</template>
