/**
 * Tipos del flujo de compra (props de CarritoController, CheckoutController,
 * MisPedidosController). Importes siempre calculados por el servidor.
 */

export type Tono =
    | 'neutral'
    | 'info'
    | 'warning'
    | 'success'
    | 'danger'
    | 'brand';

export interface EstadoInfo {
    value: string | null;
    label: string;
    color: Tono | string;
}

export interface LineaCarrito {
    producto_id: number;
    nombre: string;
    slug: string;
    sku: string | null;
    imagen: string | null;
    precio: number;
    precio_original: number;
    cantidad: number;
    stock: number;
    cantidad_maxima: number;
    modalidad_venta: 'inventario' | 'bajo_pedido' | string;
    tiempo_entrega_dias: number | null;
    subtotal: number;
    subtotal_original: number;
    descuento_oferta: number;
    tiene_oferta: boolean;
    oferta: { nombre?: string | null } | null;
    disponible: boolean;
    error: string | null;
}

export interface CuponAplicado {
    id: number;
    codigo: string;
    nombre: string | null;
    tipo: string;
    valor: number;
    descuento_aplicado: number;
}

export interface ResumenCompra {
    subtotal: number;
    subtotal_original: number;
    envio: number;
    envio_estado: 'calculado' | 'por_cotizar' | string;
    envio_por_cotizar: boolean;
    descuento: number;
    descuento_ofertas: number;
    descuento_cupon: number;
    total: number;
    total_productos: number;
}

export interface CotizacionEnvio {
    modalidad: string;
    regla_id: number | null;
    estado: string;
    monto: number | null;
    permite_contra_entrega: boolean;
    dias_estimados: string | null;
    nombre: string | null;
    mensaje: string | null;
}

export interface Direccion {
    id: number;
    alias: string | null;
    nombre_receptor: string;
    telefono: string;
    calle: string;
    numero_exterior: string;
    numero_interior: string | null;
    colonia: string;
    municipio: string;
    estado: string;
    pais: string | null;
    codigo_postal: string;
    referencias: string | null;
    predeterminada: boolean;
}

export interface PasoLinea {
    clave: string;
    titulo: string;
    estado: 'hecho' | 'actual' | 'pendiente' | 'error';
}

export interface ComprobanteCliente {
    id: number;
    nombre: string;
    mime: string;
    tamano: number;
    referencia: string | null;
    fecha_transferencia: string | null;
    monto_declarado: number | null;
    estado: 'recibido' | 'aprobado' | 'rechazado' | string;
    motivo_rechazo: string | null;
    created_at: string | null;
    url: string;
}

export interface ItemPedido {
    id: number;
    nombre: string;
    sku: string | null;
    slug: string | null;
    imagen: string | null;
    cantidad: number;
    precio_unitario: number;
    subtotal: number;
}

export interface HistorialCliente {
    id: number;
    tipo: string;
    estatus: string | null;
    comentario: string | null;
    created_at: string | null;
}

export interface PedidoCliente {
    id: number;
    folio: string;
    estatus: EstadoInfo;
    estado_pago: EstadoInfo;
    estado_logistico: EstadoInfo;
    metodo_pago: string | null;
    metodo_pago_label: string;
    modalidad_entrega: string;
    modalidad_entrega_label: string | null;
    envio_estado: string;
    envio_por_cotizar: boolean;
    envio_esperando_eleccion: boolean;
    es_terminal: boolean;
    moneda: string;
    subtotal: number;
    descuento: number;
    descuento_cupon: number;
    ahorro_ofertas: number;
    envio: number;
    total: number;
    saldo_pendiente: number;
    cupon_codigo: string | null;
    nombre_cliente: string;
    correo_cliente: string;
    telefono_cliente: string | null;
    notas_cliente: string | null;
    paqueteria: string | null;
    numero_guia: string | null;
    fecha_envio: string | null;
    motivo_cancelacion: string | null;
    vence_en: string | null;
    pagado_en: string | null;
    enviado_en: string | null;
    entregado_en: string | null;
    cancelado_en: string | null;
    created_at: string | null;
    items: ItemPedido[];
    direccion: Direccion | null;
    pago: {
        id: number;
        estatus: string;
        monto: number;
        motivo_rechazo: string | null;
        referencia_cliente: string | null;
    } | null;
    comprobantes: ComprobanteCliente[];
    opciones_envio: OpcionEnvioCliente[];
    url_rastreo: string | null;
    historial: HistorialCliente[];
    linea_pago: PasoLinea[];
    linea_logistica: PasoLinea[];
}

export interface DatosBanco {
    configurado: boolean;
    nombre?: string | null;
    beneficiario?: string | null;
    clabe?: string | null;
    cuenta?: string | null;
    referencia?: string | null;
    indicaciones?: string | null;
}

export interface InstruccionesPago {
    texto: string | null;
    banco: DatosBanco | null;
    puede_subir_comprobante: boolean;
    recoger: { direccion: string | null; horario: string | null } | null;
    efectivo_presencial: boolean;
    contra_entrega: boolean;
}

export interface ReglasComprobante {
    extensiones: string[];
    max_mb: number;
    accept: string;
}

/** Opción de envío cotizada que el cliente puede elegir. */
export interface OpcionEnvioCliente {
    id: number;
    paqueteria: string;
    servicio: string;
    costo: number;
    tiempo: string | null;
    notas: string | null;
    recomendada: boolean;
    seleccionada: boolean;
    seleccionada_en: string | null;
    mas_economica: boolean;
    mas_rapida: boolean;
}
