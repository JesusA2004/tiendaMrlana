<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 18px; background:#F3F2EF; border-radius:14px;">
    <tr>
        <td style="padding:14px 16px;">
            <p style="margin:0; font-size:12px; letter-spacing:1px; text-transform:uppercase; color:#6B7280;">Folio del pedido</p>
            <p style="margin:2px 0 0; font-size:22px; font-weight:800; color:#0A0A0A; letter-spacing:0.5px;">{{ $pedido['folio'] }}</p>
        </td>
        <td align="right" style="padding:14px 16px;">
            <p style="margin:0; font-size:12px; letter-spacing:1px; text-transform:uppercase; color:#6B7280;">Total</p>
            <p style="margin:2px 0 0; font-size:20px; font-weight:800; color:#0A0A0A;">${{ number_format($pedido['total'], 2) }} <span style="font-size:12px; font-weight:600; color:#6B7280;">MXN</span></p>
        </td>
    </tr>
</table>
