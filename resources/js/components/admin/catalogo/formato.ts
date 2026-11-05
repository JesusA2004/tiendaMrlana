import { router } from '@inertiajs/vue3';
import type { Tono } from './types';

const moneda = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

export function formatoMoneda(valor: number | null | undefined): string {
    if (valor === null || valor === undefined) {
        return '—';
    }

    return moneda.format(valor);
}

export function formatoFecha(
    iso: string | null | undefined,
    conHora = true,
): string {
    if (!iso) {
        return '—';
    }

    // Fechas "YYYY-MM-DD" (sin hora) se muestran tal cual, sin desfase de zona.
    const soloFecha = /^\d{4}-\d{2}-\d{2}$/.test(iso);
    const fecha = soloFecha ? new Date(`${iso}T12:00:00Z`) : new Date(iso);

    return new Intl.DateTimeFormat('es-MX', {
        timeZone: soloFecha ? 'UTC' : 'America/Mexico_City',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        ...(conHora && !soloFecha
            ? { hour: '2-digit', minute: '2-digit' }
            : {}),
    }).format(fecha);
}

export const clasesTono: Record<Tono, string> = {
    neutral: 'bg-muted text-muted-foreground border-border',
    info: 'bg-brand-teal-soft text-brand-teal-strong border-brand-teal/30',
    warning: 'bg-amber-50 text-amber-800 border-amber-200 ',
    success:
        'bg-brand-green-soft text-brand-green-strong border-brand-green/30',
    danger: 'bg-destructive/10 text-destructive border-destructive/30',
    brand: 'bg-brand-deep text-white border-transparent',
};

export function claseTono(tono: string | null | undefined): string {
    return clasesTono[(tono as Tono) ?? 'neutral'] ?? clasesTono.neutral;
}

/**
 * Navega conservando el estado de scroll y los filtros (búsqueda de servidor).
 */
export function visitarConFiltros(
    url: string,
    filtros: Record<string, string | number | null | undefined>,
): void {
    const query: Record<string, string | number> = {};

    for (const [clave, valor] of Object.entries(filtros)) {
        if (valor !== null && valor !== undefined && valor !== '') {
            query[clave] = valor;
        }
    }

    router.get(url, query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
