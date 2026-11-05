<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Mail,
    Pencil,
    Search,
    ShieldCheck,
    Trash2,
    UserCog,
    UserPlus,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CampoForm from '@/components/admin/CampoForm.vue';
import ConfirmarAccion from '@/components/admin/ConfirmarAccion.vue';
import EmptyState from '@/components/admin/EmptyState.vue';
import { fecha } from '@/components/admin/formato';
import KpiCard from '@/components/admin/KpiCard.vue';
import PageHeader from '@/components/admin/PageHeader.vue';
import Paginacion from '@/components/admin/Paginacion.vue';
import StatusBadge from '@/components/admin/StatusBadge.vue';
import type { Paginado } from '@/components/admin/types';
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

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuarios', href: '/admin/usuarios' }],
    },
});

type Rol = 'admin' | 'vendedor' | 'cliente';

type UsuarioFila = {
    id: number;
    name: string;
    email: string;
    rol: Rol;
    verificado: boolean;
    pedidos_count: number;
    created_at: string | null;
    es_yo: boolean;
};

type RolOpcion = { value: Rol; label: string; descripcion: string };

const props = defineProps<{
    usuarios: Paginado<UsuarioFila>;
    filtros: { buscar: string; rol: string };
    conteos: Record<Rol, number>;
    roles: RolOpcion[];
}>();

const { filtros } = useFiltros(
    '/admin/usuarios',
    { buscar: props.filtros.buscar ?? '', rol: props.filtros.rol ?? '' },
    { textos: ['buscar'] },
);

const etiquetaRol = (rol: string) =>
    props.roles.find((r) => r.value === rol)?.label ?? rol;

const colorRol = (rol: string) =>
    rol === 'admin' ? 'brand' : rol === 'vendedor' ? 'info' : 'neutral';

// ----------------------------------------------------------- formulario
const dialogo = ref(false);
const editando = ref<UsuarioFila | null>(null);

const form = useForm({
    name: '',
    email: '',
    rol: 'cliente' as Rol,
});

const bloqueoRol = computed(
    () => editando.value?.es_yo === true && editando.value.rol === 'admin',
);

function abrirNuevo() {
    editando.value = null;
    form.reset();
    form.clearErrors();
    dialogo.value = true;
}

function abrirEditar(u: UsuarioFila) {
    editando.value = u;
    form.clearErrors();
    form.name = u.name;
    form.email = u.email;
    form.rol = u.rol;
    dialogo.value = true;
}

function guardar() {
    const opciones = {
        preserveScroll: true,
        onSuccess: () => {
            dialogo.value = false;
            form.reset();
        },
    };

    if (editando.value) {
        form.put(`/admin/usuarios/${editando.value.id}`, opciones);
    } else {
        form.post('/admin/usuarios', opciones);
    }
}

const enviando = ref<number | null>(null);

function enviarEnlace(u: UsuarioFila) {
    enviando.value = u.id;
    router.post(
        `/admin/usuarios/${u.id}/enlace-acceso`,
        {},
        { preserveScroll: true, onFinish: () => (enviando.value = null) },
    );
}

function eliminar(u: UsuarioFila) {
    router.delete(`/admin/usuarios/${u.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Usuarios" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <PageHeader
            eyebrow="Administración"
            title="Usuarios y roles"
            description="Da acceso a tu equipo. Nunca enviamos contraseñas: cada persona define la suya con un enlace seguro."
        >
            <Button @click="abrirNuevo">
                <UserPlus class="size-4" />
                Nuevo usuario
            </Button>
        </PageHeader>

        <div class="grid stagger-children grid-cols-1 gap-3 sm:grid-cols-3">
            <KpiCard
                label="Administradores"
                :value="conteos.admin"
                :icon="ShieldCheck"
                color="brand"
                hint="Acceso total"
            />
            <KpiCard
                label="Vendedores"
                :value="conteos.vendedor"
                :icon="UserCog"
                color="info"
                hint="Operan pedidos y envíos"
            />
            <KpiCard
                label="Clientes"
                :value="conteos.cliente"
                :icon="Users"
                color="success"
                hint="Cuentas de la tienda"
            />
        </div>

        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="relative flex-1">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filtros.buscar"
                    type="search"
                    placeholder="Buscar por nombre o correo"
                    class="pl-9"
                    aria-label="Buscar usuarios"
                />
            </div>
            <NativeSelect
                v-model="filtros.rol"
                class="sm:w-52"
                aria-label="Filtrar por rol"
            >
                <option value="">Todos los roles</option>
                <option v-for="r in roles" :key="r.value" :value="r.value">
                    {{ r.label }}
                </option>
            </NativeSelect>
        </div>

        <EmptyState
            v-if="usuarios.data.length === 0"
            title="Sin usuarios"
            description="No hay usuarios que coincidan con los filtros."
            :icon="Users"
        />

        <template v-else>
            <!-- Escritorio -->
            <div class="hidden overflow-hidden rounded-2xl border md:block">
                <table class="w-full table-hover text-sm">
                    <thead
                        class="bg-surface/70 text-left text-xs text-muted-foreground uppercase"
                    >
                        <tr>
                            <th class="px-4 py-3 font-semibold">Usuario</th>
                            <th class="px-4 py-3 font-semibold">Rol</th>
                            <th class="px-4 py-3 font-semibold">Correo</th>
                            <th class="px-4 py-3 font-semibold">Pedidos</th>
                            <th class="px-4 py-3 font-semibold">Alta</th>
                            <th class="px-4 py-3 text-right font-semibold">
                                Acciones
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr
                            v-for="u in usuarios.data"
                            :key="u.id"
                            class="transition hover:bg-muted/40"
                        >
                            <td class="px-4 py-3">
                                <p class="font-medium">
                                    {{ u.name }}
                                    <span
                                        v-if="u.es_yo"
                                        class="ml-1 rounded-full bg-brand-green-soft px-2 py-0.5 text-[0.7rem] font-semibold text-brand-green-strong"
                                        >Tú</span
                                    >
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ u.email }}
                                </p>
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :label="etiquetaRol(u.rol)"
                                    :color="colorRol(u.rol)"
                                />
                            </td>
                            <td class="px-4 py-3">
                                <StatusBadge
                                    :label="
                                        u.verificado
                                            ? 'Verificado'
                                            : 'Sin verificar'
                                    "
                                    :color="
                                        u.verificado ? 'success' : 'warning'
                                    "
                                />
                            </td>
                            <td class="px-4 py-3 tabular-nums">
                                {{ u.pedidos_count }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ fecha(u.created_at) }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="`Enviar enlace de acceso a ${u.name}`"
                                        title="Enviar enlace de acceso"
                                        :disabled="enviando === u.id"
                                        @click="enviarEnlace(u)"
                                    >
                                        <Mail class="size-4" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="`Editar ${u.name}`"
                                        title="Editar"
                                        @click="abrirEditar(u)"
                                    >
                                        <Pencil class="size-4" />
                                    </Button>
                                    <ConfirmarAccion
                                        v-if="!u.es_yo"
                                        title="¿Eliminar usuario?"
                                        :description="`Se eliminará la cuenta de ${u.name}. Si tiene pedidos, cámbiale el rol en lugar de eliminarla.`"
                                        confirm-label="Eliminar"
                                        @confirmar="eliminar(u)"
                                    >
                                        <template #trigger>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                class="text-destructive"
                                                :aria-label="`Eliminar ${u.name}`"
                                                title="Eliminar"
                                            >
                                                <Trash2 class="size-4" />
                                            </Button>
                                        </template>
                                    </ConfirmarAccion>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Móvil -->
            <div class="grid gap-3 md:hidden">
                <article
                    v-for="u in usuarios.data"
                    :key="u.id"
                    class="rounded-2xl border bg-card p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate font-medium">
                                {{ u.name }}
                                <span
                                    v-if="u.es_yo"
                                    class="text-xs text-brand-green-strong"
                                    >(tú)</span
                                >
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ u.email }}
                            </p>
                        </div>
                        <StatusBadge
                            :label="etiquetaRol(u.rol)"
                            :color="colorRol(u.rol)"
                        />
                    </div>
                    <div
                        class="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1">
                            <BadgeCheck
                                :class="[
                                    'size-3.5',
                                    u.verificado
                                        ? 'text-success'
                                        : 'text-warning',
                                ]"
                            />
                            {{ u.verificado ? 'Verificado' : 'Sin verificar' }}
                        </span>
                        <span>· {{ u.pedidos_count }} pedidos</span>
                        <span>· alta {{ fecha(u.created_at) }}</span>
                    </div>
                    <div class="mt-3 flex gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            :disabled="enviando === u.id"
                            @click="enviarEnlace(u)"
                        >
                            <Mail class="size-4" /> Enlace
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="abrirEditar(u)"
                        >
                            <Pencil class="size-4" /> Editar
                        </Button>
                        <ConfirmarAccion
                            v-if="!u.es_yo"
                            title="¿Eliminar usuario?"
                            :description="`Se eliminará la cuenta de ${u.name}.`"
                            confirm-label="Eliminar"
                            @confirmar="eliminar(u)"
                        >
                            <template #trigger>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="text-destructive"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </template>
                        </ConfirmarAccion>
                    </div>
                </article>
            </div>

            <Paginacion :pagina="usuarios" />
        </template>
    </div>

    <Dialog v-model:open="dialogo">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <form class="grid gap-5" @submit.prevent="guardar">
                <DialogHeader>
                    <DialogTitle class="font-display text-xl">
                        {{ editando ? 'Editar usuario' : 'Nuevo usuario' }}
                    </DialogTitle>
                    <DialogDescription>
                        <template v-if="editando">
                            Si cambias el correo, la persona deberá verificarlo
                            de nuevo.
                        </template>
                        <template v-else>
                            Enviaremos un correo con un enlace para que la
                            persona defina su propia contraseña. No se envían
                            contraseñas por correo.
                        </template>
                    </DialogDescription>
                </DialogHeader>

                <CampoForm
                    label="Nombre"
                    for="u-name"
                    :error="form.errors.name"
                    requerido
                >
                    <Input
                        id="u-name"
                        v-model="form.name"
                        autocomplete="off"
                        required
                    />
                </CampoForm>
                <CampoForm
                    label="Correo"
                    for="u-email"
                    :error="form.errors.email"
                    requerido
                >
                    <Input
                        id="u-email"
                        v-model="form.email"
                        type="email"
                        autocomplete="off"
                        required
                    />
                </CampoForm>

                <fieldset class="grid gap-2">
                    <legend class="mb-1 text-sm font-medium">Rol</legend>
                    <label
                        v-for="r in roles"
                        :key="r.value"
                        :class="[
                            'flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition',
                            form.rol === r.value
                                ? 'border-brand-green bg-brand-green-soft'
                                : 'hover:bg-muted/50',
                            bloqueoRol &&
                                r.value !== 'admin' &&
                                'cursor-not-allowed opacity-50',
                        ]"
                    >
                        <input
                            v-model="form.rol"
                            type="radio"
                            name="rol"
                            :value="r.value"
                            class="mt-1 accent-[var(--brand-green-strong)]"
                            :disabled="bloqueoRol && r.value !== 'admin'"
                        />
                        <span>
                            <span class="block text-sm font-semibold">{{
                                r.label
                            }}</span>
                            <span class="block text-xs text-muted-foreground">{{
                                r.descripcion
                            }}</span>
                        </span>
                    </label>
                    <p v-if="bloqueoRol" class="text-xs text-muted-foreground">
                        No puedes quitarte tu propio rol de administrador.
                    </p>
                    <p
                        v-if="form.errors.rol"
                        class="text-xs font-medium text-destructive"
                        role="alert"
                    >
                        {{ form.errors.rol }}
                    </p>
                </fieldset>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="dialogo = false"
                    >
                        Cancelar
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            editando
                                ? 'Guardar cambios'
                                : 'Crear y enviar enlace'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
