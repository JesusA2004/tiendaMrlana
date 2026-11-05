import { router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

/**
 * Filtros sincronizados con la URL (paginación de servidor). Los cambios de texto se
 * aplican con retardo; los selects al instante. Valores vacíos no se envían.
 */
export function useFiltros<T extends Record<string, string>>(
    url: string,
    iniciales: T,
    opciones: {
        textos?: (keyof T)[];
        extra?: () => Record<string, string>;
    } = {},
) {
    const filtros = reactive({ ...iniciales }) as T;
    let temporizador: ReturnType<typeof setTimeout> | undefined;

    function aplicar() {
        const query: Record<string, string> = { ...(opciones.extra?.() ?? {}) };

        for (const [clave, valor] of Object.entries(filtros)) {
            if (valor !== '' && valor !== null && valor !== undefined) {
                query[clave] = String(valor);
            }
        }

        router.get(url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    for (const clave of Object.keys(iniciales) as (keyof T)[]) {
        watch(
            () => filtros[clave],
            () => {
                clearTimeout(temporizador);

                if (opciones.textos?.includes(clave)) {
                    temporizador = setTimeout(aplicar, 350);
                } else {
                    aplicar();
                }
            },
        );
    }

    function limpiar() {
        for (const clave of Object.keys(filtros) as (keyof T)[]) {
            (filtros as Record<keyof T, string>)[clave] = '';
        }
    }

    return { filtros, aplicar, limpiar };
}
