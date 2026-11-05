import type { Directive } from 'vue';

/**
 * Utilidades de la tienda pública: URLs, formato de moneda y animaciones.
 * Las URLs se escriben aquí (en lugar de Wayfinder) para no depender del
 * momento en que se regeneran las rutas de otros equipos.
 */
export const urls = {
    home: '/',
    catalogo: '/productos',
    producto: (slug: string) => `/productos/${encodeURIComponent(slug)}`,
    categoria: (slug: string) =>
        `/productos?categoria=${encodeURIComponent(slug)}`,
    marca: (slug: string) => `/productos?marca=${encodeURIComponent(slug)}`,
    buscar: (q: string) => `/productos?q=${encodeURIComponent(q)}`,
    sugerencias: '/buscar/sugerencias',
    menuCategorias: '/buscar/categorias',
    carrito: '/carrito',
    carritoAgregar: '/carrito/agregar',
    misPedidos: '/mi-cuenta/pedidos',
    direcciones: '/mi-cuenta/direcciones',
    login: '/login',
    registro: '/register',
    logout: '/logout',
    admin: '/dashboard',
    preguntas: '/preguntas-frecuentes',
};

const formatoMoneda = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

const formatoEntero = new Intl.NumberFormat('es-MX');

export function moneda(valor: number | string | null | undefined): string {
    return formatoMoneda.format(Number(valor ?? 0));
}

export function entero(valor: number): string {
    return formatoEntero.format(Math.round(valor));
}

export function fechaCorta(iso: string | null | undefined): string | null {
    if (!iso) {
        return null;
    }

    const fecha = new Date(iso);

    if (Number.isNaN(fecha.getTime())) {
        return null;
    }

    return new Intl.DateTimeFormat('es-MX', {
        day: 'numeric',
        month: 'long',
        timeZone: 'America/Mexico_City',
    }).format(fecha);
}

export function porcentajeDescuento(
    original: number | null,
    final: number | null,
): number {
    if (!original || final === null || original <= 0) {
        return 0;
    }

    return Math.round(((original - final) / original) * 100);
}

export function prefiereMenosMovimiento(): boolean {
    return (
        typeof window !== 'undefined' &&
        window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    );
}

/**
 * v-reveal: agrega la clase `.reveal` y la hace visible al entrar al viewport.
 * Acepta un retraso en ms: v-reveal="120".
 */
let observador: IntersectionObserver | null = null;

function obtenerObservador(): IntersectionObserver | null {
    if (typeof window === 'undefined' || !('IntersectionObserver' in window)) {
        return null;
    }

    if (!observador) {
        observador = new IntersectionObserver(
            (entradas) => {
                for (const entrada of entradas) {
                    if (entrada.isIntersecting) {
                        entrada.target.classList.add('is-visible');
                        observador?.unobserve(entrada.target);
                    }
                }
            },
            { rootMargin: '0px 0px -8% 0px', threshold: 0.08 },
        );
    }

    return observador;
}

export const vReveal: Directive<HTMLElement, number | undefined> = {
    mounted(el, binding) {
        const io = obtenerObservador();

        if (!io || prefiereMenosMovimiento()) {
            return;
        }

        // Lo que ya está en pantalla al montar no se anima (evita parpadeo).
        if (el.getBoundingClientRect().top < window.innerHeight * 0.92) {
            return;
        }

        el.classList.add('reveal');

        if (binding.value) {
            el.style.setProperty('--reveal-delay', `${binding.value}ms`);
        }

        io.observe(el);
    },
    unmounted(el) {
        observador?.unobserve(el);
    },
};
