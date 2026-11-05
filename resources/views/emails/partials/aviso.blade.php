{{-- Bloque destacado. $tono: info|warning|danger|success --}}
@php
    $colores = [
        'info' => ['#E6F7F9', '#64CBD4', '#0E5960'],
        'warning' => ['#FFF7E6', '#F2B544', '#7A4A00'],
        'danger' => ['#FDECEC', '#E35D5D', '#7F1D1D'],
        'success' => ['#EEF8E7', '#73C83A', '#2F6B12'],
    ][$tono ?? 'info'];
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0;">
    <tr>
        <td style="background:{{ $colores[0] }}; border-left:4px solid {{ $colores[1] }}; border-radius:12px; padding:14px 16px; font-size:14px; line-height:1.55; color:{{ $colores[2] }};">
            @if (! empty($titulo))
                <strong style="display:block; margin-bottom:4px;">{{ $titulo }}</strong>
            @endif
            {{ $mensaje ?? '' }}
        </td>
    </tr>
</table>
