import type { Tono } from './types';

export { moneda } from '@/components/public/tienda';

const ZONA = 'America/Mexico_City';

function fecha(iso: string | null | undefined): Date | null {
    if (!iso) {
        return null;
    }

    const valor = new Date(iso);

    return Number.isNaN(valor.getTime()) ? null : valor;
}

/** 5 de noviembre de 2026 */
export function fechaLarga(iso: string | null | undefined): string | null {
    const f = fecha(iso);

    return f
        ? new Intl.DateTimeFormat('es-MX', {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
              timeZone: ZONA,
          }).format(f)
        : null;
}

/** 5 nov 2026, 10:44 a. m. */
export function fechaHora(iso: string | null | undefined): string | null {
    const f = fecha(iso);

    return f
        ? new Intl.DateTimeFormat('es-MX', {
              day: 'numeric',
              month: 'short',
              year: 'numeric',
              hour: 'numeric',
              minute: '2-digit',
              timeZone: ZONA,
          }).format(f)
        : null;
}

/** Fecha "YYYY-MM-DD" (sin hora) sin desfase de zona. */
export function fechaSimple(ymd: string | null | undefined): string | null {
    if (!ymd) {
        return null;
    }

    const [y, m, d] = ymd.split('-').map(Number);

    if (!y || !m || !d) {
        return ymd;
    }

    return new Intl.DateTimeFormat('es-MX', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(Date.UTC(y, m - 1, d)));
}

export function tamanoArchivo(bytes: number): string {
    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

/** CLABE en grupos de 4 para leerla fácil. */
export function agruparClabe(clabe: string | null | undefined): string {
    return (clabe ?? '')
        .replace(/\s+/g, '')
        .replace(/(.{4})/g, '$1 ')
        .trim();
}

/** Clases de chip por tono semántico de los enums. */
export function clasesTono(tono: Tono | string | null | undefined): string {
    switch (tono) {
        case 'success':
            return 'bg-success/12 text-success ring-success/30';
        case 'warning':
            return 'bg-warning/12 text-warning ring-warning/35';
        case 'info':
            return 'bg-info/12 text-info ring-info/30';
        case 'danger':
            return 'bg-destructive/10 text-destructive ring-destructive/30';
        case 'brand':
            return 'bg-brand-teal-soft text-brand-teal-strong ring-brand-teal/40';
        default:
            return 'bg-muted text-muted-foreground ring-border';
    }
}
