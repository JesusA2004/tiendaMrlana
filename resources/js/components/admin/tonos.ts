import type { Tono } from './types';

/** Clases por tono semántico (enums `color()` del backend). Contraste AA en claro y oscuro. */
export const TONO_BADGE: Record<Tono, string> = {
    neutral: 'border-border bg-muted text-muted-foreground',
    info: 'border-info/25 bg-info/10 text-info',
    warning: 'border-warning/30 bg-warning/10 text-warning',
    success: 'border-success/25 bg-success/10 text-success',
    danger: 'border-destructive/30 bg-destructive/10 text-destructive',
    brand: 'border-brand-teal/40 bg-brand-teal-soft text-brand-teal-strong',
};

export const TONO_PUNTO: Record<Tono, string> = {
    neutral: 'bg-muted-foreground/60',
    info: 'bg-info',
    warning: 'bg-warning',
    success: 'bg-success',
    danger: 'bg-destructive',
    brand: 'bg-brand-teal',
};

export const TONO_BARRA: Record<Tono, string> = {
    neutral: 'bg-muted-foreground/40',
    info: 'bg-info',
    warning: 'bg-warning',
    success: 'bg-brand-green',
    danger: 'bg-destructive',
    brand: 'bg-brand-teal',
};

export const TONO_ICONO: Record<Tono, string> = {
    neutral: 'bg-muted text-muted-foreground',
    info: 'bg-info/12 text-info',
    warning: 'bg-warning/12 text-warning',
    success: 'bg-brand-green-soft text-brand-green-strong',
    danger: 'bg-destructive/10 text-destructive',
    brand: 'bg-brand-teal-soft text-brand-teal-strong',
};

export function tono(color: string | null | undefined): Tono {
    return (color && color in TONO_BADGE ? color : 'neutral') as Tono;
}
