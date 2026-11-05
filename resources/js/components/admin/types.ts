/** Tipos compartidos del panel administrativo (props que entregan los controladores Admin). */

export type Tono =
    | 'neutral'
    | 'info'
    | 'warning'
    | 'success'
    | 'danger'
    | 'brand';

export type Etiqueta = {
    value: string;
    label: string;
    color: Tono | string;
};

export type Opcion = {
    value: string;
    label: string;
    color?: string;
};

export type EnlacePaginacion = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginado<T> = {
    data: T[];
    links: EnlacePaginacion[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type PedidoFila = {
    id: number;
    folio: string;
    nombre_cliente: string | null;
    correo_cliente: string | null;
    telefono_cliente: string | null;
    total: number;
    envio: number;
    subtotal: number;
    saldo_pendiente: number;
    estatus: Etiqueta | null;
    estado_pago: Etiqueta | null;
    estado_logistico: Etiqueta | null;
    envio_estado: Etiqueta | null;
    metodo_pago: Etiqueta | null;
    modalidad_entrega: Etiqueta | null;
    items_count: number | null;
    paqueteria: string | null;
    numero_guia: string | null;
    fecha_envio: string | null;
    vence_en: string | null;
    enviado_en: string | null;
    created_at: string | null;
    updated_at: string | null;
    destino: string | null;
};

export type Comprobante = {
    id: number;
    nombre_original: string;
    mime: string;
    es_pdf: boolean;
    tamano: number;
    referencia: string | null;
    fecha_transferencia: string | null;
    monto_declarado: number | null;
    estado: 'recibido' | 'aprobado' | 'rechazado' | string;
    created_at: string | null;
    url: string;
    descargar_url: string;
};

export type PagoDatos = {
    id: number;
    pedido_id: number;
    tipo: Etiqueta | null;
    estatus: Etiqueta | null;
    monto: number;
    monto_recibido: number | null;
    referencia_cliente: string | null;
    referencia_externa: string | null;
    motivo_rechazo: string | null;
    notas_internas: string | null;
    pagado_en: string | null;
    revisado_en: string | null;
    revisado_por: string | null;
    created_at: string | null;
    es_historico: boolean;
    comprobantes: Comprobante[];
};

export type AccionesPedido = {
    cotizar_envio: boolean;
    recotizar_envio: boolean;
    aprobar_pago: boolean;
    rechazar_pago: boolean;
    confirmar_efectivo: boolean;
    iniciar_preparacion: boolean;
    marcar_listo_envio: boolean;
    marcar_listo_recoger: boolean;
    registrar_envio: boolean;
    confirmar_entrega: boolean;
    registrar_incidencia: boolean;
    cancelar: boolean;
    reembolsar: boolean;
    agregar_nota: boolean;
};

export type Permisos = {
    gestionar_catalogo: boolean;
    gestionar_configuracion: boolean;
    gestionar_usuarios: boolean;
    revisar_pagos: boolean;
    operar_pedidos: boolean;
    cotizar_envio: boolean;
    recotizar_envio: boolean;
    cancelar_pedidos: boolean;
};

/** Opción de envío cotizada (paquetería + servicio + costo + tiempo). */
export type OpcionEnvio = {
    id: number;
    paqueteria_id: number | null;
    paqueteria: string;
    servicio: string;
    costo: number;
    dias_min: number | null;
    dias_max: number | null;
    tiempo_texto: string | null;
    tiempo: string | null;
    notas: string | null;
    recomendada: boolean;
    seleccionada: boolean;
    seleccionada_en: string | null;
    mas_economica: boolean;
    mas_rapida: boolean;
    creado_por?: string | null;
};

/** Paquetería activa del catálogo (selectores del panel). */
export type PaqueteriaOpcion = {
    id: number;
    nombre: string;
    rastreo: boolean;
};
