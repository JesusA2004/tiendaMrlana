/** Formatos del panel: moneda MXN y fechas en hora de México (es-MX). */

const ZONA = 'America/Mexico_City';

const moneda = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
});

const numero = new Intl.NumberFormat('es-MX');

export function dinero(valor: number | string | null | undefined): string {
    return moneda.format(Number(valor ?? 0));
}

export function entero(valor: number | string | null | undefined): string {
    return numero.format(Number(valor ?? 0));
}

export function fechaHora(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: ZONA,
    }).format(new Date(iso));
}

/** Fecha sin hora. Acepta 'YYYY-MM-DD' (se interpreta como fecha local, sin corrimiento). */
export function fecha(valor: string | null | undefined): string {
    if (!valor) {
        return '—';
    }

    const soloFecha = /^\d{4}-\d{2}-\d{2}$/.test(valor);
    const d = soloFecha ? new Date(`${valor}T12:00:00`) : new Date(valor);

    return new Intl.DateTimeFormat('es-MX', {
        dateStyle: 'medium',
        timeZone: soloFecha ? undefined : ZONA,
    }).format(d);
}

const relativo = new Intl.RelativeTimeFormat('es-MX', { numeric: 'auto' });

export function haceCuanto(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    const segundos = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const abs = Math.abs(segundos);

    if (abs < 60) {
        return relativo.format(segundos, 'second');
    }

    if (abs < 3600) {
        return relativo.format(Math.round(segundos / 60), 'minute');
    }

    if (abs < 86400) {
        return relativo.format(Math.round(segundos / 3600), 'hour');
    }

    return relativo.format(Math.round(segundos / 86400), 'day');
}

/** Fecha de hoy en México como 'YYYY-MM-DD' (para <input type="date">). */
export function hoyMexico(): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone: ZONA }).format(
        new Date(),
    );
}

export function tamanoArchivo(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(0)} KB`;
    }

    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}
