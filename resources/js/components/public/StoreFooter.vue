<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Clock, Mail, MapPin, MessageCircle, Phone } from 'lucide-vue-next';
import { computed } from 'vue';
import logo from '@/img/logo.png';
import { urls } from './tienda';
import type { PropsCompartidasTienda } from './types';

const page = usePage<PropsCompartidasTienda>();

const tienda = computed(() => page.props.tienda ?? null);
const nombre = computed(() => tienda.value?.nombre || 'Mr Lana');
const contacto = computed(() => tienda.value?.contacto ?? {});
const enlaces = computed(
    () => tienda.value?.enlaces ?? page.props.enlacesInformativos ?? [],
);
const anio = new Date().getFullYear();

function soloDigitos(valor: string): string {
    return valor.replace(/\D+/g, '');
}

const whatsapp = computed(() => {
    const numero = contacto.value?.whatsapp
        ? soloDigitos(contacto.value.whatsapp)
        : '';

    if (numero.length < 10) {
        return null;
    }

    return {
        etiqueta: contacto.value?.whatsapp as string,
        url: `https://wa.me/${numero.length === 10 ? `52${numero}` : numero}`,
    };
});

const redes = computed(() => {
    const r = tienda.value?.redes ?? {};

    return [
        { nombre: 'Facebook', url: r?.facebook },
        { nombre: 'Instagram', url: r?.instagram },
        { nombre: 'TikTok', url: r?.tiktok },
    ].filter(
        (red): red is { nombre: string; url: string } =>
            typeof red.url === 'string' && /^https?:\/\//i.test(red.url),
    );
});

const hayContacto = computed(
    () =>
        !!(
            whatsapp.value ||
            contacto.value?.telefono ||
            contacto.value?.correo ||
            contacto.value?.horario ||
            contacto.value?.direccion
        ),
);
</script>

<template>
    <footer class="relative mt-20 bg-brand-deep-pattern text-white sm:mt-28">
        <div class="h-1.5 w-full bg-brand-gradient" aria-hidden="true" />

        <div
            class="mx-auto grid w-full max-w-7xl gap-10 px-4 pt-14 pb-10 sm:px-6 md:grid-cols-2 lg:grid-cols-12 lg:gap-8 lg:px-8"
        >
            <div class="lg:col-span-4">
                <Link
                    :href="urls.home"
                    class="inline-block"
                    aria-label="Mr Lana, inicio"
                >
                    <img
                        :src="logo"
                        alt="Mr Lana"
                        width="508"
                        height="192"
                        class="h-11 w-auto"
                        loading="lazy"
                        decoding="async"
                    />
                </Link>
                <p
                    v-if="tienda?.eslogan"
                    class="mt-5 max-w-sm font-display text-xl leading-snug text-white/90"
                >
                    {{ tienda.eslogan }}
                </p>
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-white/70">
                    Compra en línea y da seguimiento a tu pedido desde tu
                    cuenta.
                </p>

                <ul v-if="redes.length" class="mt-6 flex flex-wrap gap-2">
                    <li v-for="red in redes" :key="red.nombre">
                        <a
                            :href="red.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex h-10 items-center rounded-full border border-white/20 px-4 text-sm font-medium text-white/90 transition-colors hover:border-brand-green hover:bg-white/5 hover:text-white"
                        >
                            {{ red.nombre }}
                        </a>
                    </li>
                </ul>
            </div>

            <nav class="lg:col-span-2" aria-label="Tienda">
                <h2
                    class="font-display text-base font-semibold text-brand-green"
                >
                    Tienda
                </h2>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li>
                        <Link
                            :href="urls.catalogo"
                            class="text-white/75 transition-colors hover:text-white"
                            >Catálogo</Link
                        >
                    </li>
                    <li>
                        <Link
                            :href="urls.carrito"
                            class="text-white/75 transition-colors hover:text-white"
                            >Mi carrito</Link
                        >
                    </li>
                    <li>
                        <Link
                            :href="urls.misPedidos"
                            class="text-white/75 transition-colors hover:text-white"
                            >Mis pedidos</Link
                        >
                    </li>
                </ul>
            </nav>

            <nav v-if="enlaces.length" class="lg:col-span-3" aria-label="Ayuda">
                <h2
                    class="font-display text-base font-semibold text-brand-green"
                >
                    Ayuda
                </h2>
                <ul class="mt-4 space-y-2.5 text-sm">
                    <li v-for="enlace in enlaces" :key="enlace.url">
                        <Link
                            :href="enlace.url"
                            class="text-white/75 transition-colors hover:text-white"
                            >{{ enlace.titulo }}</Link
                        >
                    </li>
                </ul>
            </nav>

            <div
                v-if="hayContacto"
                class="lg:col-span-3"
                :class="{ 'lg:col-start-10': !enlaces.length }"
            >
                <h2
                    class="font-display text-base font-semibold text-brand-green"
                >
                    Contacto
                </h2>
                <ul class="mt-4 space-y-3 text-sm">
                    <li v-if="whatsapp">
                        <a
                            :href="whatsapp.url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="group inline-flex items-start gap-3 text-white/80 hover:text-white"
                        >
                            <MessageCircle
                                class="mt-0.5 size-4 shrink-0 text-brand-green"
                                aria-hidden="true"
                            />
                            <span>WhatsApp {{ whatsapp.etiqueta }}</span>
                        </a>
                    </li>
                    <li v-if="contacto?.telefono">
                        <a
                            :href="`tel:${soloDigitos(contacto.telefono)}`"
                            class="inline-flex items-start gap-3 text-white/80 hover:text-white"
                        >
                            <Phone
                                class="mt-0.5 size-4 shrink-0 text-brand-green"
                                aria-hidden="true"
                            />
                            <span>{{ contacto.telefono }}</span>
                        </a>
                    </li>
                    <li v-if="contacto?.correo">
                        <a
                            :href="`mailto:${contacto.correo}`"
                            class="inline-flex items-start gap-3 break-all text-white/80 hover:text-white"
                        >
                            <Mail
                                class="mt-0.5 size-4 shrink-0 text-brand-green"
                                aria-hidden="true"
                            />
                            <span>{{ contacto.correo }}</span>
                        </a>
                    </li>
                    <li
                        v-if="contacto?.horario"
                        class="flex items-start gap-3 text-white/80"
                    >
                        <Clock
                            class="mt-0.5 size-4 shrink-0 text-brand-green"
                            aria-hidden="true"
                        />
                        <span class="whitespace-pre-line">{{
                            contacto.horario
                        }}</span>
                    </li>
                    <li
                        v-if="contacto?.direccion"
                        class="flex items-start gap-3 text-white/80"
                    >
                        <MapPin
                            class="mt-0.5 size-4 shrink-0 text-brand-green"
                            aria-hidden="true"
                        />
                        <span class="whitespace-pre-line">{{
                            contacto.direccion
                        }}</span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="border-t border-white/10">
            <div
                class="mx-auto flex w-full max-w-7xl flex-col gap-2 px-4 py-6 text-xs text-white/60 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8"
            >
                <p>© {{ anio }} {{ nombre }}. Todos los derechos reservados.</p>
                <p>Precios en pesos mexicanos (MXN).</p>
            </div>
        </div>
    </footer>
</template>
