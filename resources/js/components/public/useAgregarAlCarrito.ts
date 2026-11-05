import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { urls } from './tienda';

/**
 * Agrega un producto al carrito (POST carrito.agregar con {producto_id, cantidad}).
 * El backend recalcula precios/existencias y responde con flash success|error.
 */
export function useAgregarAlCarrito() {
    const enviando = ref<number | null>(null);

    function agregar(
        productoId: number,
        cantidad = 1,
        alTerminar?: () => void,
    ) {
        if (enviando.value !== null) {
            return;
        }

        router.post(
            urls.carritoAgregar,
            { producto_id: productoId, cantidad },
            {
                preserveScroll: true,
                preserveState: true,
                onStart: () => {
                    enviando.value = productoId;
                },
                onFinish: () => {
                    enviando.value = null;
                    alTerminar?.();
                },
            },
        );
    }

    return { enviando, agregar };
}
