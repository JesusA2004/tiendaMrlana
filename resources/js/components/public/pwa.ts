/**
 * Estado de instalación de la PWA a nivel de módulo: el layout público se
 * vuelve a montar en cada página, pero `beforeinstallprompt` sólo se dispara
 * una vez por carga completa del documento.
 */
export interface BeforeInstallPromptEvent extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

let evento: BeforeInstallPromptEvent | null = null;
const suscriptores = new Set<() => void>();

export const inicioSesionNavegacion =
    typeof window !== 'undefined' ? Date.now() : 0;

if (typeof window !== 'undefined') {
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        evento = e as BeforeInstallPromptEvent;
        suscriptores.forEach((fn) => fn());
    });
    window.addEventListener('appinstalled', () => {
        evento = null;
        suscriptores.forEach((fn) => fn());
    });
}

export function eventoInstalacion(): BeforeInstallPromptEvent | null {
    return evento;
}

export function consumirEventoInstalacion(): BeforeInstallPromptEvent | null {
    const actual = evento;
    evento = null;

    return actual;
}

export function suscribirInstalacion(fn: () => void): () => void {
    suscriptores.add(fn);

    return () => suscriptores.delete(fn);
}
