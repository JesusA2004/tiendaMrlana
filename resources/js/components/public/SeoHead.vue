<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SeoProps } from './types';

/**
 * Metaetiquetas SEO / Open Graph en el cliente.
 *
 * El HTML inicial ya las trae renderizadas por el servidor (resources/views/app.blade.php),
 * que es lo que leen WhatsApp, Facebook, X, etc. (no ejecutan JavaScript). Este componente
 * repite exactamente las mismas etiquetas con el mismo `head-key` (= `data-inertia`) para que
 * Inertia las reemplace —sin duplicarlas— al navegar entre páginas. La fuente es siempre la
 * prop `seo` (App\Support\Seo); el JSON-LD sólo se emite en el servidor.
 */
const page = usePage<{ seo?: SeoProps | null }>();
const seo = computed(() => page.props.seo ?? null);

// Inertia inserta el <title> como HTML sin escapar: se escapa aquí (el navegador lo decodifica).
const titulo = computed(() =>
    (seo.value?.titulo ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;'),
);
</script>

<template>
    <Head v-if="seo" :title="titulo">
        <meta
            head-key="description"
            name="description"
            :content="seo.descripcion"
        />
        <meta head-key="robots" name="robots" :content="seo.robots" />
        <link
            v-if="seo.canonical"
            head-key="canonical"
            rel="canonical"
            :href="seo.canonical"
        />
        <meta
            v-if="seo.canonical"
            head-key="og:url"
            property="og:url"
            :content="seo.canonical"
        />
        <meta
            head-key="og:site_name"
            property="og:site_name"
            :content="seo.sitio"
        />
        <meta head-key="og:locale" property="og:locale" :content="seo.locale" />
        <meta head-key="og:type" property="og:type" :content="seo.tipo" />
        <meta
            head-key="og:title"
            property="og:title"
            :content="seo.titulo_social"
        />
        <meta
            head-key="og:description"
            property="og:description"
            :content="seo.descripcion"
        />
        <meta
            head-key="og:image"
            property="og:image"
            :content="seo.imagen.url"
        />
        <meta
            v-if="seo.imagen.secure_url"
            head-key="og:image:secure_url"
            property="og:image:secure_url"
            :content="seo.imagen.secure_url"
        />
        <meta
            v-if="seo.imagen.tipo"
            head-key="og:image:type"
            property="og:image:type"
            :content="seo.imagen.tipo"
        />
        <meta
            v-if="seo.imagen.ancho && seo.imagen.alto"
            head-key="og:image:width"
            property="og:image:width"
            :content="String(seo.imagen.ancho)"
        />
        <meta
            v-if="seo.imagen.ancho && seo.imagen.alto"
            head-key="og:image:height"
            property="og:image:height"
            :content="String(seo.imagen.alto)"
        />
        <meta
            head-key="og:image:alt"
            property="og:image:alt"
            :content="seo.imagen.alt"
        />
        <meta
            v-if="seo.producto"
            head-key="product:price:amount"
            property="product:price:amount"
            :content="seo.producto.precio"
        />
        <meta
            v-if="seo.producto"
            head-key="product:price:currency"
            property="product:price:currency"
            :content="seo.producto.moneda"
        />
        <meta
            v-if="seo.producto"
            head-key="product:availability"
            property="product:availability"
            :content="seo.producto.disponibilidad"
        />
        <meta
            head-key="twitter:card"
            name="twitter:card"
            content="summary_large_image"
        />
        <meta
            head-key="twitter:title"
            name="twitter:title"
            :content="seo.titulo_social"
        />
        <meta
            head-key="twitter:description"
            name="twitter:description"
            :content="seo.descripcion"
        />
        <meta
            head-key="twitter:image"
            name="twitter:image"
            :content="seo.imagen.url"
        />
        <meta
            head-key="twitter:image:alt"
            name="twitter:image:alt"
            :content="seo.imagen.alt"
        />
    </Head>
</template>
