export type Tono =
    | 'neutral'
    | 'info'
    | 'warning'
    | 'success'
    | 'danger'
    | 'brand';

export interface OpcionEnum {
    value: string;
    label: string;
    color: Tono | string;
}

export interface OpcionCatalogo {
    id: number;
    nombre: string;
    activa?: boolean;
}

export interface EnlacePaginacion {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginado<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: EnlacePaginacion[];
}

export interface ProductoFila {
    id: number;
    nombre: string;
    sku: string;
    slug: string;
    modelo: string | null;
    codigo_referencia: string | null;
    acabado: string | null;
    precio: number | null;
    precio_referencia: number | null;
    stock: number;
    stock_minimo: number;
    categoria_id: number | null;
    categoria: string | null;
    marca: string | null;
    modalidad_venta: string;
    tiempo_entrega_dias: number | null;
    estado: OpcionEnum;
    destacado: boolean;
    imagen: string | null;
    imagenes_count: number;
    faltantes: string[];
    completitud: number;
    importado: boolean;
    tiene_pedidos: boolean;
    url_publica: string | null;
}

export interface ImagenProducto {
    id: number;
    url: string | null;
    principal: boolean;
    orden: number;
}

export interface AtributoProducto {
    nombre: string;
    valor: string;
}

export interface ReferenciaProducto {
    precio: number | null;
    fuente: string | null;
    url: string | null;
    consultada_en: string | null;
    codigo: string | null;
    origen: string | null;
}

export interface ProductoDetalle extends ProductoFila {
    marca_id: number | null;
    descripcion: string | null;
    descripcion_larga: string | null;
    precio_comparacion: number | null;
    costo: number | null;
    peso: number | null;
    alto: number | null;
    ancho: number | null;
    largo: number | null;
    dimensiones: string | null;
    observaciones: string | null;
    atributos: AtributoProducto[];
    meta_titulo: string | null;
    meta_descripcion: string | null;
    puede_publicarse: boolean;
    aprobado_por: string | null;
    aprobado_en: string | null;
    publicado_en: string | null;
    imagen_heredada: string | null;
    imagenes: ImagenProducto[];
    referencia: ReferenciaProducto;
}

export interface MovimientoInventario {
    id: number;
    producto?: { id: number; nombre: string; sku: string } | null;
    tipo: OpcionEnum;
    cantidad: number;
    stock_anterior: number;
    stock_nuevo: number;
    motivo: string | null;
    usuario: string | null;
    pedido?: { id: number; folio: string } | null;
    fecha: string | null;
}

export interface ProductoInventario {
    id: number;
    nombre: string;
    sku: string;
    stock: number;
    stock_minimo: number;
    modalidad_venta?: string;
    imagen?: string | null;
}
