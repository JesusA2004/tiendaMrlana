{{-- Productos y totales del pedido --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0; border-collapse:collapse;">
    <tr>
        <td colspan="2" style="padding:0 0 8px; font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#3F8A1C;">Tu pedido</td>
    </tr>
    @foreach ($pedido['items'] as $item)
        <tr>
            <td style="padding:10px 0; border-top:1px solid #EEECE9; font-size:14px; color:#1F2937;">
                {{ $item['nombre'] }}
                <span style="color:#6B7280;">× {{ $item['cantidad'] }}</span>
            </td>
            <td align="right" style="padding:10px 0; border-top:1px solid #EEECE9; font-size:14px; color:#1F2937; white-space:nowrap;">${{ number_format($item['subtotal'], 2) }}</td>
        </tr>
    @endforeach
    <tr>
        <td style="padding:10px 0 2px; border-top:1px solid #E3E1DE; font-size:14px; color:#6B7280;">Subtotal</td>
        <td align="right" style="padding:10px 0 2px; border-top:1px solid #E3E1DE; font-size:14px; color:#1F2937;">${{ number_format($pedido['subtotal'], 2) }}</td>
    </tr>
    @if ($pedido['descuento_cupon'] > 0)
        <tr>
            <td style="padding:2px 0; font-size:14px; color:#6B7280;">Descuento @if ($pedido['cupon_codigo'])({{ $pedido['cupon_codigo'] }})@endif</td>
            <td align="right" style="padding:2px 0; font-size:14px; color:#3F8A1C;">−${{ number_format($pedido['descuento_cupon'], 2) }}</td>
        </tr>
    @endif
    <tr>
        <td style="padding:2px 0; font-size:14px; color:#6B7280;">Envío</td>
        <td align="right" style="padding:2px 0; font-size:14px; color:#1F2937;">
            @if (! empty($pedido['envio_esperando_eleccion']))
                Por elegir
            @elseif ($pedido['envio_por_cotizar'])
                Por cotizar
            @elseif ($pedido['modalidad_entrega'] === 'recoger_en_tienda')
                Recoges en tienda
            @else
                ${{ number_format($pedido['envio'], 2) }}@if (! empty($pedido['paqueteria'])) <span style="color:#6B7280;">({{ $pedido['paqueteria'] }})</span>@endif
            @endif
        </td>
    </tr>
    <tr>
        <td style="padding:8px 0 0; font-size:16px; font-weight:800; color:#0A0A0A;">Total</td>
        <td align="right" style="padding:8px 0 0; font-size:16px; font-weight:800; color:#0A0A0A;">${{ number_format($pedido['total'], 2) }} MXN</td>
    </tr>
</table>
