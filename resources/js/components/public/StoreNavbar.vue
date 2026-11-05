<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { useWindowScroll } from '@vueuse/core';
import {
    ChevronDown,
    LayoutDashboard,
    LogIn,
    LogOut,
    MapPin,
    Menu,
    Package,
    Search,
    ShoppingBag,
    Store,
    UserRound,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, ref, watch } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import logo from '@/img/logo.png';
import SearchBox from './SearchBox.vue';
import { urls } from './tienda';
import type { PropsCompartidasTienda } from './types';
import { useMenuCategorias } from './useMenuCategorias';

const page = usePage<PropsCompartidasTienda>();
const { y } = useWindowScroll();
const { categorias, cargado, cargar } = useMenuCategorias();

const menuMovil = ref(false);
const buscadorMovil = ref(false);
const menuCategorias = ref(false);

const usuario = computed(() => page.props.auth?.user ?? null);
const esPersonal = computed(
    () => usuario.value?.rol === 'admin' || usuario.value?.rol === 'vendedor',
);
const cantidad = computed(() =>
    Math.max(0, Number(page.props.carrito?.cantidad_items ?? 0)),
);
const conScroll = computed(() => y.value > 8);
const busquedaActual = computed(() => {
    const url = page.url ?? '';
    const consulta = url.includes('?') ? url.split('?')[1] : '';

    return new URLSearchParams(consulta).get('q') ?? '';
});
const rutaActual = computed(() => (page.url ?? '/').split('?')[0]);
const categoriaActual = computed(() => {
    const url = page.url ?? '';
    const consulta = url.includes('?') ? url.split('?')[1] : '';

    return new URLSearchParams(consulta).get('categoria');
});
// Franja de categorías siempre visible (las 10 con más productos, en el orden del menú).
const categoriasFranja = computed(() => categorias.value.slice(0, 10));

// Pequeño rebote del contador del carrito cuando cambia.
const rebote = ref(false);
watch(cantidad, (nuevo, anterior) => {
    if (nuevo > anterior) {
        rebote.value = false;
        requestAnimationFrame(() => {
            rebote.value = true;
        });
    }
});

watch(menuCategorias, (abierto) => {
    if (abierto) {
        cargar();
    }
});

watch(menuMovil, (abierto) => {
    if (abierto) {
        cargar();
    }
});

onMounted(() => {
    // Precarga perezosa del menú cuando el navegador esté libre.
    const ric = (
        window as Window & { requestIdleCallback?: (cb: () => void) => number }
    ).requestIdleCallback;

    // La franja de categorías las necesita pronto: en reposo o, si no hay soporte, al instante.
    if (ric) {
        ric(() => cargar());
    } else {
        cargar();
    }
});

function iniciales(nombre: string): string {
    return nombre
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((p) => p[0]?.toUpperCase())
        .join('');
}
</script>

<template>
    <header
        class="sticky top-0 z-40 w-full border-b transition-[background-color,border-color,box-shadow] duration-300"
        :class="
            conScroll
                ? 'border-border/70 bg-background/85 shadow-[0_8px_24px_-18px_rgba(0,0,0,0.35)] backdrop-blur-xl supports-[backdrop-filter]:bg-background/75'
                : 'border-transparent bg-background'
        "
    >
        <a
            href="#contenido"
            class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-full focus:bg-primary focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:text-primary-foreground"
        >
            Saltar al contenido
        </a>

        <div
            class="mx-auto flex h-16 w-full max-w-7xl items-center gap-2 px-4 sm:gap-3 sm:px-6 lg:h-[72px] lg:gap-6 lg:px-8"
        >
            <!-- Menú móvil -->
            <button
                type="button"
                class="-ml-2 inline-flex size-11 items-center justify-center rounded-full text-foreground hover:bg-muted lg:hidden"
                aria-label="Abrir menú"
                @click="menuMovil = true"
            >
                <Menu class="size-5" />
            </button>

            <Link
                :href="urls.home"
                class="flex shrink-0 items-center rounded-lg focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                aria-label="Mr Lana, ir al inicio"
            >
                <img
                    :src="logo"
                    alt="Mr Lana"
                    width="508"
                    height="192"
                    class="h-8 w-auto transition-transform duration-300 hover:-rotate-2 sm:h-9 lg:h-10"
                />
            </Link>

            <!-- Navegación de escritorio -->
            <nav
                class="hidden items-center gap-1 lg:flex"
                aria-label="Principal"
            >
                <Popover v-model:open="menuCategorias">
                    <PopoverTrigger as-child>
                        <button
                            type="button"
                            class="inline-flex h-10 items-center gap-1.5 rounded-full px-4 text-sm font-semibold text-foreground transition-colors hover:bg-muted data-[state=open]:bg-muted"
                        >
                            Categorías
                            <ChevronDown
                                class="size-4 transition-transform duration-200"
                                :class="{ 'rotate-180': menuCategorias }"
                            />
                        </button>
                    </PopoverTrigger>
                    <PopoverContent
                        align="start"
                        :side-offset="12"
                        class="w-[min(92vw,640px)] rounded-2xl p-3"
                    >
                        <div v-if="!cargado" class="grid grid-cols-2 gap-2 p-1">
                            <div
                                v-for="n in 6"
                                :key="n"
                                class="h-12 animate-pulse rounded-xl bg-muted"
                            />
                        </div>
                        <div
                            v-else-if="categorias.length"
                            class="grid grid-cols-2 gap-1"
                        >
                            <Link
                                v-for="categoria in categorias"
                                :key="categoria.slug"
                                :href="urls.categoria(categoria.slug)"
                                class="group flex items-center gap-3 rounded-xl p-2 transition-colors hover:bg-brand-green-soft"
                                @click="menuCategorias = false"
                            >
                                <span
                                    class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-surface"
                                >
                                    <img
                                        v-if="categoria.imagen"
                                        :src="categoria.imagen"
                                        alt=""
                                        class="size-full object-cover"
                                        loading="lazy"
                                        decoding="async"
                                    />
                                    <Store
                                        v-else
                                        class="size-4 text-brand-green-strong"
                                    />
                                </span>
                                <span class="min-w-0">
                                    <span
                                        class="block truncate text-sm font-semibold"
                                        >{{ categoria.nombre }}</span
                                    >
                                    <span class="text-xs text-muted-foreground"
                                        >{{ categoria.total }}
                                        {{
                                            categoria.total === 1
                                                ? 'producto'
                                                : 'productos'
                                        }}</span
                                    >
                                </span>
                            </Link>
                        </div>
                        <p v-else class="p-3 text-sm text-muted-foreground">
                            Aún no hay categorías con productos publicados.
                        </p>
                        <div class="mt-2 border-t pt-2">
                            <Link
                                :href="urls.catalogo"
                                class="flex items-center justify-between rounded-xl px-3 py-2.5 text-sm font-semibold text-brand-green-strong hover:bg-muted"
                                @click="menuCategorias = false"
                            >
                                Ver todo el catálogo
                                <span aria-hidden="true">→</span>
                            </Link>
                        </div>
                    </PopoverContent>
                </Popover>

                <Link
                    :href="urls.catalogo"
                    class="inline-flex h-10 items-center rounded-full px-4 text-sm font-semibold transition-colors hover:bg-muted"
                    :class="
                        rutaActual === urls.catalogo
                            ? 'text-brand-green-strong'
                            : 'text-foreground'
                    "
                >
                    Catálogo
                </Link>
            </nav>

            <!-- Buscador de escritorio -->
            <div class="hidden flex-1 justify-center md:flex">
                <div class="w-full max-w-xl">
                    <SearchBox :valor-inicial="busquedaActual" />
                </div>
            </div>

            <div class="ml-auto flex items-center gap-1 sm:gap-2 md:ml-0">
                <button
                    type="button"
                    class="inline-flex size-11 items-center justify-center rounded-full text-foreground hover:bg-muted md:hidden"
                    :aria-label="buscadorMovil ? 'Cerrar buscador' : 'Buscar'"
                    :aria-expanded="buscadorMovil"
                    @click="buscadorMovil = !buscadorMovil"
                >
                    <X v-if="buscadorMovil" class="size-5" />
                    <Search v-else class="size-5" />
                </button>

                <!-- Cuenta (escritorio) -->
                <DropdownMenu v-if="usuario">
                    <DropdownMenuTrigger as-child>
                        <button
                            type="button"
                            class="hidden h-11 items-center gap-2 rounded-full pr-3 pl-1.5 text-sm font-semibold hover:bg-muted lg:inline-flex"
                        >
                            <span
                                class="flex size-8 items-center justify-center rounded-full bg-brand-gradient text-xs font-bold text-brand-ink"
                                >{{ iniciales(usuario.name) }}</span
                            >
                            <span class="max-w-28 truncate">{{
                                usuario.name.split(' ')[0]
                            }}</span>
                            <ChevronDown class="size-4 text-muted-foreground" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-60 rounded-xl">
                        <DropdownMenuLabel class="font-normal">
                            <p class="truncate text-sm font-semibold">
                                {{ usuario.name }}
                            </p>
                            <p
                                v-if="usuario.email"
                                class="truncate text-xs text-muted-foreground"
                            >
                                {{ usuario.email }}
                            </p>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem as-child>
                            <Link
                                :href="urls.misPedidos"
                                class="cursor-pointer"
                            >
                                <Package class="size-4" /> Mis pedidos
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link
                                :href="urls.direcciones"
                                class="cursor-pointer"
                            >
                                <MapPin class="size-4" /> Mis direcciones
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem v-if="esPersonal" as-child>
                            <Link :href="urls.admin" class="cursor-pointer">
                                <LayoutDashboard class="size-4" /> Panel
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem as-child>
                            <Link
                                :href="urls.logout"
                                method="post"
                                as="button"
                                class="w-full cursor-pointer"
                            >
                                <LogOut class="size-4" /> Cerrar sesión
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <Link
                    v-else
                    :href="urls.login"
                    class="hidden h-11 items-center gap-2 rounded-full px-4 text-sm font-semibold hover:bg-muted lg:inline-flex"
                >
                    <UserRound class="size-[18px]" />
                    Entrar
                </Link>

                <!-- Carrito -->
                <Link
                    :href="urls.carrito"
                    class="relative inline-flex h-11 items-center gap-2 rounded-full bg-brand-ink pr-4 pl-3.5 text-sm font-semibold text-white transition-[background-color,transform] hover:bg-brand-deep active:scale-95"
                    :aria-label="`Carrito, ${cantidad} ${cantidad === 1 ? 'artículo' : 'artículos'}`"
                >
                    <ShoppingBag class="size-[18px]" aria-hidden="true" />
                    <span class="hidden sm:inline">Carrito</span>
                    <span
                        class="inline-flex min-w-6 items-center justify-center rounded-full px-1.5 py-0.5 text-xs leading-none font-bold"
                        :class="[
                            cantidad > 0
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-white/15 text-white',
                            rebote ? 'animate-in duration-300 zoom-in-50' : '',
                        ]"
                        aria-hidden="true"
                        @animationend="rebote = false"
                        >{{ cantidad > 99 ? '99+' : cantidad }}</span
                    >
                </Link>
            </div>
        </div>

        <!-- Buscador móvil desplegable -->
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0 -translate-y-2"
        >
            <div v-if="buscadorMovil" class="border-t px-4 py-3 md:hidden">
                <SearchBox
                    :valor-inicial="busquedaActual"
                    autofocus
                    variante="panel"
                    @navegar="buscadorMovil = false"
                />
            </div>
        </Transition>

        <!-- Menú lateral móvil -->
        <Sheet v-model:open="menuMovil">
            <SheetContent
                side="left"
                class="w-[88vw] max-w-sm gap-0 overflow-y-auto p-0 [&>button:last-child]:text-white [&>button:last-child]:opacity-90"
            >
                <SheetHeader
                    class="relative overflow-hidden bg-brand-deep-pattern px-5 pt-6 pb-5 text-left"
                >
                    <img
                        :src="logo"
                        alt="Mr Lana"
                        width="508"
                        height="192"
                        class="h-9 w-auto self-start"
                        loading="lazy"
                        decoding="async"
                    />
                    <SheetTitle class="sr-only">Menú principal</SheetTitle>
                    <SheetDescription class="mt-3 text-sm text-white/80">
                        <template v-if="usuario"
                            >Hola, {{ usuario.name.split(' ')[0] }}</template
                        >
                        <template v-else>Bienvenido a la tienda</template>
                    </SheetDescription>
                </SheetHeader>

                <nav class="flex flex-col gap-1 p-3" aria-label="Menú móvil">
                    <Link
                        :href="urls.catalogo"
                        class="flex h-12 items-center gap-3 rounded-xl px-3 text-[15px] font-semibold hover:bg-muted"
                        @click="menuMovil = false"
                    >
                        <Store class="size-5 text-brand-green-strong" />
                        Todo el catálogo
                    </Link>
                    <Link
                        :href="urls.carrito"
                        class="flex h-12 items-center gap-3 rounded-xl px-3 text-[15px] font-semibold hover:bg-muted"
                        @click="menuMovil = false"
                    >
                        <ShoppingBag class="size-5 text-brand-green-strong" />
                        Mi carrito
                        <span
                            v-if="cantidad > 0"
                            class="ml-auto rounded-full bg-primary px-2 py-0.5 text-xs font-bold text-primary-foreground"
                            >{{ cantidad }}</span
                        >
                    </Link>
                </nav>

                <div class="px-3 pb-2">
                    <p
                        class="px-3 pt-2 pb-1 text-[11px] font-bold tracking-[0.12em] text-muted-foreground uppercase"
                    >
                        Categorías
                    </p>
                    <div v-if="!cargado" class="space-y-2 p-2">
                        <div
                            v-for="n in 5"
                            :key="n"
                            class="h-10 animate-pulse rounded-lg bg-muted"
                        />
                    </div>
                    <ul v-else class="flex flex-col">
                        <li
                            v-for="categoria in categorias"
                            :key="categoria.slug"
                        >
                            <Link
                                :href="urls.categoria(categoria.slug)"
                                class="flex h-11 items-center justify-between rounded-xl px-3 text-sm font-medium hover:bg-muted"
                                @click="menuMovil = false"
                            >
                                {{ categoria.nombre }}
                                <span class="text-xs text-muted-foreground">{{
                                    categoria.total
                                }}</span>
                            </Link>
                        </li>
                        <li
                            v-if="!categorias.length"
                            class="px-3 py-2 text-sm text-muted-foreground"
                        >
                            Pronto verás aquí nuestras categorías.
                        </li>
                    </ul>
                </div>

                <div class="mt-auto border-t p-3">
                    <template v-if="usuario">
                        <Link
                            :href="urls.misPedidos"
                            class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium hover:bg-muted"
                            @click="menuMovil = false"
                        >
                            <Package class="size-4" /> Mis pedidos
                        </Link>
                        <Link
                            :href="urls.direcciones"
                            class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium hover:bg-muted"
                            @click="menuMovil = false"
                        >
                            <MapPin class="size-4" /> Mis direcciones
                        </Link>
                        <Link
                            v-if="esPersonal"
                            :href="urls.admin"
                            class="flex h-11 items-center gap-3 rounded-xl px-3 text-sm font-medium hover:bg-muted"
                            @click="menuMovil = false"
                        >
                            <LayoutDashboard class="size-4" /> Panel
                        </Link>
                        <Link
                            :href="urls.logout"
                            method="post"
                            as="button"
                            class="flex h-11 w-full items-center gap-3 rounded-xl px-3 text-sm font-medium hover:bg-muted"
                            @click="menuMovil = false"
                        >
                            <LogOut class="size-4" /> Cerrar sesión
                        </Link>
                    </template>
                    <div v-else class="grid grid-cols-2 gap-2">
                        <Link
                            :href="urls.login"
                            class="inline-flex h-11 items-center justify-center gap-2 rounded-full border text-sm font-semibold hover:bg-muted"
                            @click="menuMovil = false"
                        >
                            <LogIn class="size-4" /> Entrar
                        </Link>
                        <Link
                            :href="urls.registro"
                            class="inline-flex h-11 items-center justify-center rounded-full bg-primary text-sm font-semibold text-primary-foreground hover:bg-primary/90"
                            @click="menuMovil = false"
                        >
                            Crear cuenta
                        </Link>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    </header>
    <!-- Franja de categorías: fuera del header fijo (no cambia su altura; se va con el scroll) -->
    <nav
        v-if="categoriasFranja.length"
        class="border-b border-border/60 bg-card/50"
        aria-label="Categorías"
    >
        <ul
            class="franja mx-auto flex w-full max-w-7xl items-center gap-1.5 overflow-x-auto px-4 py-2 sm:px-6 lg:px-8"
        >
            <li class="shrink-0">
                <Link
                    :href="urls.catalogo"
                    class="chip"
                    :class="{
                        'chip-activo':
                            rutaActual === urls.catalogo && !categoriaActual,
                    }"
                >
                    Todo
                </Link>
            </li>
            <li
                v-for="(categoria, indice) in categoriasFranja"
                :key="categoria.slug"
                class="shrink-0 animate-in [animation-fill-mode:both] fade-in slide-in-from-left-2 motion-reduce:animate-none"
                :style="{ animationDelay: `${indice * 35}ms` }"
            >
                <Link
                    :href="urls.categoria(categoria.slug)"
                    class="chip"
                    :class="{
                        'chip-activo': categoriaActual === categoria.slug,
                    }"
                >
                    {{ categoria.nombre }}
                </Link>
            </li>
        </ul>
    </nav>
</template>

<style scoped>
.franja {
    scrollbar-width: none;
    mask-image: linear-gradient(
        90deg,
        transparent 0,
        #000 12px,
        #000 calc(100% - 24px),
        transparent 100%
    );
}

.franja::-webkit-scrollbar {
    display: none;
}

.chip {
    display: inline-flex;
    height: 2rem;
    align-items: center;
    border-radius: 9999px;
    padding-inline: 0.875rem;
    font-size: 0.8125rem;
    font-weight: 600;
    white-space: nowrap;
    color: var(--secondary-foreground);
    background: color-mix(in srgb, var(--card) 70%, transparent);
    box-shadow: inset 0 0 0 1px var(--border);
    transition:
        background-color 0.2s var(--ease-brand),
        color 0.2s var(--ease-brand),
        box-shadow 0.2s var(--ease-brand),
        transform 0.2s var(--ease-brand);
}

.chip:hover {
    background: var(--brand-green-soft);
    box-shadow: inset 0 0 0 1px var(--brand-green);
    transform: translateY(-1px);
}

.chip:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ring) 45%, transparent);
}

.chip-activo,
.chip-activo:hover {
    background: var(--brand-deep);
    color: #fff;
    box-shadow: none;
}

@media (prefers-reduced-motion: reduce) {
    .chip {
        transition: none;
    }

    .chip:hover {
        transform: none;
    }
}
</style>
