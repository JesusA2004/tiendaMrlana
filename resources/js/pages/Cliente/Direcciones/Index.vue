<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Loader2,
    MapPin,
    Pencil,
    Plus,
    Star,
    Trash2,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, ref } from 'vue';
import CampoTexto from '@/components/compra/CampoTexto.vue';
import CuentaNav from '@/components/compra/CuentaNav.vue';
import { ESTADOS_MX } from '@/components/compra/estados';
import type { Direccion } from '@/components/compra/types';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import PublicLayout from '@/layouts/PublicLayout.vue';
import direccionesRutas from '@/routes/cliente/direcciones';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    direcciones: Direccion[];
}>();

const editandoId = ref<number | null>(null);
const formularioAbierto = ref(props.direcciones.length === 0);
const eliminando = ref<number | null>(null);
const confirmarEliminar = ref<number | null>(null);

const form = useForm({
    alias: '',
    nombre_receptor: '',
    telefono: '',
    calle: '',
    numero_exterior: '',
    numero_interior: '',
    colonia: '',
    municipio: '',
    estado: '',
    pais: 'México',
    codigo_postal: '',
    referencias: '',
    predeterminada: false,
});

const editando = computed(() => editandoId.value !== null);

function abrirNueva() {
    editandoId.value = null;
    form.reset();
    form.clearErrors();
    formularioAbierto.value = true;
    enfocar();
}

function editar(d: Direccion) {
    editandoId.value = d.id;
    form.clearErrors();
    form.alias = d.alias ?? '';
    form.nombre_receptor = d.nombre_receptor;
    form.telefono = d.telefono;
    form.calle = d.calle;
    form.numero_exterior = d.numero_exterior;
    form.numero_interior = d.numero_interior ?? '';
    form.colonia = d.colonia;
    form.municipio = d.municipio;
    form.estado = d.estado;
    form.pais = d.pais || 'México';
    form.codigo_postal = d.codigo_postal;
    form.referencias = d.referencias ?? '';
    form.predeterminada = d.predeterminada;
    formularioAbierto.value = true;
    enfocar();
}

function cerrar() {
    formularioAbierto.value = false;
    editandoId.value = null;
    form.reset();
    form.clearErrors();
}

function enfocar() {
    nextTick(() => {
        document
            .getElementById('formulario-direccion')
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        document.getElementById('alias')?.focus({ preventScroll: true });
    });
}

function guardar() {
    const opciones = { preserveScroll: true, onSuccess: cerrar };

    if (editandoId.value !== null) {
        form.put(direccionesRutas.update.url(editandoId.value), opciones);
    } else {
        form.post(direccionesRutas.store.url(), opciones);
    }
}

function eliminar(id: number) {
    router.delete(direccionesRutas.destroy.url(id), {
        preserveScroll: true,
        onStart: () => (eliminando.value = id),
        onFinish: () => {
            eliminando.value = null;
            confirmarEliminar.value = null;
        },
    });
}

function hacerPrincipal(d: Direccion) {
    router.put(
        direccionesRutas.update.url(d.id),
        { ...d, pais: d.pais || 'México', predeterminada: true },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head title="Mis direcciones" />

    <CuentaNav
        activo="direcciones"
        titulo="Mis direcciones"
        descripcion="Guarda tus domicilios para comprar más rápido."
    />

    <div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="mb-5 flex items-center justify-between gap-3">
            <p
                class="rounded-full bg-card px-3 py-1.5 text-sm font-medium text-brand-deep ring-1 shadow-brand-sm ring-border"
            >
                {{ direcciones.length }} dirección{{
                    direcciones.length === 1 ? '' : 'es'
                }}
                guardada{{ direcciones.length === 1 ? '' : 's' }}
            </p>
            <Button
                v-if="!formularioAbierto || editando"
                class="group rounded-full font-bold"
                @click="abrirNueva"
            >
                <Plus
                    class="transition-transform duration-300 group-hover:rotate-90"
                    aria-hidden="true"
                />
                Agregar dirección
            </Button>
        </div>

        <!-- Formulario -->
        <Transition
            enter-active-class="transition duration-300 ease-out motion-reduce:transition-none"
            enter-from-class="opacity-0 -translate-y-3 scale-[0.98]"
            leave-active-class="transition duration-200 ease-in motion-reduce:transition-none"
            leave-to-class="opacity-0 -translate-y-2 scale-[0.98]"
        >
            <form
                v-if="formularioAbierto"
                id="formulario-direccion"
                class="relative mb-6 scroll-mt-24 overflow-hidden rounded-3xl border-2 border-brand-green/50 bg-card p-5 shadow-brand sm:p-6"
                novalidate
                @submit.prevent="guardar"
            >
                <span
                    class="absolute inset-x-0 top-0 h-1.5 bg-brand-gradient"
                    aria-hidden="true"
                />
                <div class="mb-4 flex items-center justify-between gap-3">
                    <h2 class="font-display text-xl font-bold text-brand-deep">
                        {{ editando ? 'Editar dirección' : 'Nueva dirección' }}
                    </h2>
                    <button
                        v-if="direcciones.length"
                        type="button"
                        class="flex size-9 items-center justify-center rounded-full transition hover:rotate-90 hover:bg-brand-green-soft focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-label="Cerrar formulario"
                        @click="cerrar"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <div class="grid gap-4 sm:grid-cols-6">
                    <div class="sm:col-span-2">
                        <CampoTexto
                            id="alias"
                            v-model="form.alias"
                            etiqueta="Nombre de la dirección"
                            placeholder="Casa, oficina…"
                            requerido
                            :error="form.errors.alias"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <CampoTexto
                            id="nombre_receptor"
                            v-model="form.nombre_receptor"
                            etiqueta="¿Quién recibe?"
                            autocomplete="name"
                            requerido
                            :error="form.errors.nombre_receptor"
                        />
                    </div>
                    <div class="sm:col-span-2">
                        <CampoTexto
                            id="telefono"
                            v-model="form.telefono"
                            etiqueta="Teléfono"
                            tipo="tel"
                            inputmode="tel"
                            autocomplete="tel"
                            requerido
                            :error="form.errors.telefono"
                        />
                    </div>
                    <div class="sm:col-span-6">
                        <CampoTexto
                            id="calle"
                            v-model="form.calle"
                            etiqueta="Calle"
                            autocomplete="address-line1"
                            requerido
                            :error="form.errors.calle"
                        />
                    </div>
                    <div class="sm:col-span-3">
                        <CampoTexto
                            id="numero_exterior"
                            v-model="form.numero_exterior"
                            etiqueta="Núm. ext."
                            requerido
                            :error="form.errors.numero_exterior"
                        />
                    </div>
                    <div class="sm:col-span-3">
                        <CampoTexto
                            id="numero_interior"
                            v-model="form.numero_interior"
                            etiqueta="Núm. int."
                            :error="form.errors.numero_interior"
                        />
                    </div>
                    <div class="sm:col-span-3">
                        <CampoTexto
                            id="colonia"
                            v-model="form.colonia"
                            etiqueta="Colonia"
                            requerido
                            :error="form.errors.colonia"
                        />
                    </div>
                    <div class="sm:col-span-3">
                        <CampoTexto
                            id="municipio"
                            v-model="form.municipio"
                            etiqueta="Municipio o alcaldía"
                            autocomplete="address-level2"
                            requerido
                            :error="form.errors.municipio"
                        />
                    </div>
                    <div class="space-y-1.5 sm:col-span-4">
                        <Label for="estado">Estado</Label>
                        <select
                            id="estado"
                            v-model="form.estado"
                            class="h-11 w-full rounded-md border border-input bg-card px-3 text-sm shadow-xs transition focus-visible:border-brand-green focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            autocomplete="address-level1"
                            :aria-invalid="!!form.errors.estado"
                        >
                            <option value="" disabled>Elige tu estado</option>
                            <option
                                v-if="
                                    form.estado &&
                                    !ESTADOS_MX.includes(form.estado as never)
                                "
                                :value="form.estado"
                            >
                                {{ form.estado }}
                            </option>
                            <option v-for="e in ESTADOS_MX" :key="e" :value="e">
                                {{ e }}
                            </option>
                        </select>
                        <p
                            v-if="form.errors.estado"
                            class="text-xs font-medium text-destructive"
                            role="alert"
                        >
                            {{ form.errors.estado }}
                        </p>
                    </div>
                    <div class="sm:col-span-2">
                        <CampoTexto
                            id="codigo_postal"
                            v-model="form.codigo_postal"
                            etiqueta="Código postal"
                            inputmode="numeric"
                            autocomplete="postal-code"
                            :maxlength="5"
                            requerido
                            :error="form.errors.codigo_postal"
                        />
                    </div>
                    <div class="sm:col-span-6">
                        <CampoTexto
                            id="referencias"
                            v-model="form.referencias"
                            etiqueta="Referencias"
                            placeholder="Entre calles, color de fachada…"
                            :error="form.errors.referencias"
                        />
                    </div>
                    <label
                        class="flex items-center gap-2 text-sm sm:col-span-6"
                    >
                        <input
                            v-model="form.predeterminada"
                            type="checkbox"
                            class="size-4 rounded accent-[var(--brand-green-strong)]"
                        />
                        Usar como dirección principal
                    </label>
                </div>
                <div class="mt-5 flex flex-wrap gap-2">
                    <Button
                        type="submit"
                        class="rounded-full font-bold"
                        :disabled="form.processing"
                    >
                        <Loader2
                            v-if="form.processing"
                            class="animate-spin"
                            aria-hidden="true"
                        />
                        {{ editando ? 'Guardar cambios' : 'Guardar dirección' }}
                    </Button>
                    <Button
                        v-if="direcciones.length"
                        type="button"
                        variant="ghost"
                        class="rounded-full"
                        @click="cerrar"
                    >
                        Cancelar
                    </Button>
                </div>
            </form>
        </Transition>

        <!-- Lista -->
        <TransitionGroup
            v-if="direcciones.length"
            tag="ul"
            class="grid gap-4 sm:grid-cols-2"
            enter-active-class="transition duration-300 motion-reduce:transition-none"
            enter-from-class="opacity-0 scale-95"
            leave-active-class="transition duration-200 motion-reduce:transition-none"
            leave-to-class="opacity-0 scale-90"
            move-class="transition-transform duration-300 motion-reduce:transition-none"
        >
            <li
                v-for="d in direcciones"
                :key="d.id"
                class="group relative flex hover-lift flex-col overflow-hidden rounded-3xl border bg-card p-5 shadow-brand-sm"
                :class="
                    d.predeterminada
                        ? 'border-brand-green ring-2 ring-brand-green/20'
                        : 'border-border'
                "
            >
                <span
                    v-if="d.predeterminada"
                    class="absolute inset-x-0 top-0 h-1 bg-brand-gradient"
                    aria-hidden="true"
                />
                <div class="mb-2 flex items-start gap-3">
                    <span
                        class="flex size-11 shrink-0 items-center justify-center rounded-full transition-transform duration-300 group-hover:scale-110 group-hover:-rotate-6"
                        :class="
                            d.predeterminada
                                ? 'bg-brand-green text-brand-ink'
                                : 'bg-brand-green-soft text-brand-green-strong'
                        "
                    >
                        <MapPin class="size-5" aria-hidden="true" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="font-display text-lg font-bold text-brand-deep"
                        >
                            {{ d.alias || 'Dirección' }}
                        </p>
                        <p
                            v-if="d.predeterminada"
                            class="mt-0.5 inline-flex items-center gap-1 rounded-full bg-brand-green-soft px-2 py-0.5 text-xs font-semibold text-brand-green-strong"
                        >
                            <Star
                                class="size-3 fill-current"
                                aria-hidden="true"
                            />
                            Principal
                        </p>
                    </div>
                </div>
                <div class="flex-1 text-sm">
                    <p class="font-medium">
                        {{ d.nombre_receptor }} · {{ d.telefono }}
                    </p>
                    <p class="text-muted-foreground">
                        {{ d.calle }} {{ d.numero_exterior
                        }}<template v-if="d.numero_interior">
                            int. {{ d.numero_interior }}</template
                        >, {{ d.colonia }}, {{ d.municipio }}, {{ d.estado }},
                        C.P. {{ d.codigo_postal }}
                    </p>
                    <p
                        v-if="d.referencias"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ d.referencias }}
                    </p>
                </div>
                <div
                    class="mt-4 flex flex-wrap items-center gap-2 border-t border-dashed border-border pt-3"
                >
                    <Button
                        size="sm"
                        variant="outline"
                        class="rounded-full hover:border-brand-green hover:bg-brand-green-soft"
                        @click="editar(d)"
                    >
                        <Pencil aria-hidden="true" />
                        Editar
                    </Button>
                    <Button
                        v-if="!d.predeterminada"
                        size="sm"
                        variant="ghost"
                        class="rounded-full text-brand-green-strong hover:bg-brand-green-soft"
                        @click="hacerPrincipal(d)"
                    >
                        <Star aria-hidden="true" />
                        Hacer principal
                    </Button>
                    <template v-if="confirmarEliminar === d.id">
                        <Button
                            size="sm"
                            variant="destructive"
                            class="ml-auto rounded-full"
                            :disabled="eliminando === d.id"
                            @click="eliminar(d.id)"
                        >
                            <Loader2
                                v-if="eliminando === d.id"
                                class="animate-spin"
                                aria-hidden="true"
                            />
                            Sí, eliminar
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            class="rounded-full"
                            @click="confirmarEliminar = null"
                        >
                            No
                        </Button>
                    </template>
                    <Button
                        v-else
                        size="sm"
                        variant="ghost"
                        class="ml-auto rounded-full text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                        :aria-label="`Eliminar ${d.alias || 'dirección'}`"
                        @click="confirmarEliminar = d.id"
                    >
                        <Trash2 aria-hidden="true" />
                        Eliminar
                    </Button>
                </div>
            </li>
        </TransitionGroup>
    </div>
</template>
