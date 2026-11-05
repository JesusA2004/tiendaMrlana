/**
 * Tipos de la tienda pública (props que envía App\Http\Controllers\Publico\TiendaController).
 */

export type Disponibilidad = 'en_stock' | 'bajo_pedido' | 'agotado';

export interface RefNombre {
    nombre: string;
    slug: string;
}

export interface ProductoTarjeta {
    id: number;
    nombre: string;
    slug: string;
    sku: string | null;
    modelo: string | null;
    imagen: string | null;
    precio_original: number | null;
    precio_final: number | null;
    descuento: number;
    tiene_oferta: boolean;
    oferta_nombre: string | null;
    disponibilidad: Disponibilidad;
    stock: number | null;
    tiempo_entrega_dias: number | null;
    destacado: boolean;
    nuevo: boolean;
    categoria: RefNombre | null;
    marca: RefNombre | null;
}

export interface CategoriaPublica {
    id: number;
    nombre: string;
    slug: string;
    imagen: string | null;
    total: number;
}

export interface MarcaPublica {
    nombre: string;
    slug: string;
    total: number;
}

export interface Banner {
    id: number;
    titulo: string;
    descripcion: string | null;
    imagen: string;
    url: string | null;
}

export interface Beneficio {
    titulo: string;
    descripcion: string | null;
    icono: string | null;
}

export interface MetodoPagoPublico {
    clave: string;
    nombre: string;
}

export interface PreguntaFrecuente {
    pregunta: string;
    respuesta: string;
}

export interface EnlaceInformativo {
    titulo: string;
    url: string;
}

/** Prop `seo` (App\Support\Seo::toArray), compartida por defecto y por página. */
export interface SeoProps {
    titulo: string | null;
    titulo_documento: string;
    titulo_social: string;
    descripcion: string;
    canonical: string | null;
    robots: string;
    tipo: 'website' | 'product' | 'article';
    sitio: string;
    locale: string;
    imagen: {
        url: string;
        secure_url: string | null;
        ancho: number | null;
        alto: number | null;
        tipo: string | null;
        alt: string;
    };
    producto: {
        precio: string;
        moneda: string;
        disponibilidad: string;
    } | null;
    json_ld: Record<string, unknown>[];
}

export interface TiendaCompartida {
    nombre?: string | null;
    eslogan?: string | null;
    contacto?: {
        whatsapp?: string | null;
        telefono?: string | null;
        correo?: string | null;
        horario?: string | null;
        direccion?: string | null;
    } | null;
    redes?: {
        facebook?: string | null;
        instagram?: string | null;
        tiktok?: string | null;
    } | null;
    /** Páginas `politicas.*` configuradas + FAQ (pie de página). */
    enlaces?: EnlaceInformativo[];
}

export interface UsuarioCompartido {
    id: number;
    name: string;
    email?: string;
    rol?: string;
}

export interface PropsCompartidasTienda {
    name?: string;
    auth?: { user?: UsuarioCompartido | null };
    carrito?: { cantidad_items?: number };
    tienda?: TiendaCompartida | null;
    flash?: { success?: string | null; error?: string | null };
    /** @deprecated usar `tienda.enlaces` (aún la envían checkout y mis pedidos). */
    enlacesInformativos?: EnlaceInformativo[];
    seo?: SeoProps | null;
    [key: string]: unknown;
}

export const ETIQUETA_DISPONIBILIDAD: Record<Disponibilidad, string> = {
    en_stock: 'En existencia',
    bajo_pedido: 'Bajo pedido',
    agotado: 'Agotado',
};

export interface FiltrosCatalogo {
    q: string | null;
    categoria: string | null;
    marca: string | null;
    disponibilidad: Disponibilidad | null;
    precio_min: number | null;
    precio_max: number | null;
    orden: string;
}
