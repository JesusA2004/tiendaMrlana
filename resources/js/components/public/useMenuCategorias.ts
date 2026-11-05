import { ref } from 'vue';
import { urls } from './tienda';

export interface CategoriaMenu {
    nombre: string;
    slug: string;
    imagen: string | null;
    total: number;
}

// Caché compartida entre navegaciones (se pide una sola vez por carga de la app).
const categorias = ref<CategoriaMenu[]>([]);
const cargado = ref(false);
let peticion: Promise<void> | null = null;

export function useMenuCategorias() {
    function cargar(): Promise<void> {
        if (cargado.value) {
            return Promise.resolve();
        }

        if (!peticion) {
            peticion = fetch(urls.menuCategorias, {
                headers: { Accept: 'application/json' },
            })
                .then((r) => (r.ok ? r.json() : { categorias: [] }))
                .then((datos: { categorias?: CategoriaMenu[] }) => {
                    categorias.value = datos.categorias ?? [];
                    cargado.value = true;
                })
                .catch(() => {
                    peticion = null;
                });
        }

        return peticion;
    }

    return { categorias, cargado, cargar };
}
