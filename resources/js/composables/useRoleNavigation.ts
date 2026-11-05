import type { LucideIcon } from 'lucide-vue-next';
import {
    BadgePercent,
    Boxes,
    ClipboardCheck,
    ClipboardList,
    CreditCard,
    House,
    Image,
    Layers3,
    MapPin,
    Package,
    Receipt,
    Settings,
    ShoppingCart,
    Store,
    Tags,
    TicketPercent,
    Truck,
    Users,
    Wallet,
    Waypoints,
} from 'lucide-vue-next';

export type NavLink = {
    title: string;
    href: string;
    icon: LucideIcon;
    /** Prefijo de ruta que también marca el enlace como activo (p. ej. /admin/pedidos/15). */
    activoEn?: string;
    /** El enlace sólo está activo si la URL contiene exactamente esta query. */
    query?: string;
    externo?: boolean;
};

export type NavGroup = {
    label: string;
    items: NavLink[];
};

export type PermisosNav = Partial<
    Record<
        | 'gestionar_catalogo'
        | 'gestionar_configuracion'
        | 'gestionar_usuarios'
        | 'revisar_pagos'
        | 'operar_pedidos'
        | 'cotizar_envio'
        | 'cancelar_pedidos',
        boolean
    >
>;

/**
 * Menú del panel según permisos (`auth.can`). El vendedor sólo ve operación; el cliente su cuenta.
 * Las páginas de catálogo (productos, inventario, categorías, marcas) las mantiene el equipo Catálogo.
 */
export const useRoleNavigation = (
    role?: string | null,
    can: PermisosNav = {},
): NavGroup[] => {
    if (!can.operar_pedidos) {
        return [
            {
                label: 'Mi cuenta',
                items: [
                    {
                        title: 'Mis pedidos',
                        href: '/mi-cuenta/pedidos',
                        icon: ClipboardList,
                        activoEn: '/mi-cuenta/pedidos',
                    },
                    {
                        title: 'Mis direcciones',
                        href: '/mi-cuenta/direcciones',
                        icon: MapPin,
                    },
                    {
                        title: 'Mi carrito',
                        href: '/carrito',
                        icon: ShoppingCart,
                    },
                    {
                        title: 'Ir a la tienda',
                        href: '/',
                        icon: Store,
                        externo: true,
                    },
                ],
            },
        ];
    }

    const grupos: NavGroup[] = [
        {
            label: 'Operación',
            items: [
                { title: 'Inicio', href: '/dashboard', icon: House },
                {
                    title: 'Pedidos',
                    href: '/admin/pedidos',
                    icon: ClipboardList,
                    activoEn: '/admin/pedidos',
                },
                ...(can.revisar_pagos
                    ? [
                          {
                              title: 'Pagos',
                              href: '/admin/pagos',
                              icon: Wallet,
                              activoEn: '/admin/pagos',
                          },
                      ]
                    : []),
                {
                    title: 'Envíos',
                    href: '/admin/envios',
                    icon: Truck,
                    activoEn: '/admin/envios',
                },
            ],
        },
    ];

    if (can.gestionar_catalogo) {
        grupos.push(
            {
                label: 'Catálogo',
                items: [
                    {
                        title: 'Productos',
                        href: '/admin/productos',
                        icon: Package,
                    },
                    {
                        title: 'Aprobación de catálogo',
                        href: '/admin/productos?estado=pendiente_aprobacion',
                        icon: ClipboardCheck,
                        query: 'estado=pendiente_aprobacion',
                    },
                    {
                        title: 'Inventario',
                        href: '/admin/inventario',
                        icon: Boxes,
                        activoEn: '/admin/inventario',
                    },
                    {
                        title: 'Categorías',
                        href: '/admin/categorias',
                        icon: Layers3,
                    },
                    { title: 'Marcas', href: '/admin/marcas', icon: Tags },
                ],
            },
            {
                label: 'Promociones',
                items: [
                    {
                        title: 'Ofertas',
                        href: '/admin/ofertas',
                        icon: BadgePercent,
                    },
                    {
                        title: 'Cupones',
                        href: '/admin/cupones',
                        icon: TicketPercent,
                    },
                    { title: 'Banners', href: '/admin/banners', icon: Image },
                ],
            },
        );
    }

    const ajustes: NavLink[] = [];

    if (can.gestionar_configuracion) {
        ajustes.push(
            {
                title: 'Configuración',
                href: '/admin/configuraciones',
                icon: Settings,
            },
            {
                title: 'Métodos de pago',
                href: '/admin/configuraciones?tab=metodos',
                icon: CreditCard,
                query: 'tab=metodos',
            },
            {
                title: 'Reglas de envío',
                href: '/admin/reglas-envio',
                icon: Waypoints,
            },
            {
                title: 'Paqueterías',
                href: '/admin/paqueterias',
                icon: Truck,
            },
        );
    }

    if (can.gestionar_usuarios) {
        ajustes.push({
            title: 'Usuarios',
            href: '/admin/usuarios',
            icon: Users,
        });
    }

    if (ajustes.length > 0) {
        grupos.push({ label: 'Administración', items: ajustes });
    }

    grupos.push({
        label: 'Tienda',
        items: [
            { title: 'Ver tienda', href: '/', icon: Store, externo: true },
            ...(role === 'vendedor'
                ? [
                      {
                          title: 'Mis compras',
                          href: '/mi-cuenta/pedidos',
                          icon: Receipt,
                      },
                  ]
                : []),
        ],
    });

    return grupos;
};
