@php
    $fechaPedido = $pedido['created_at'] ? \Illuminate\Support\Carbon::parse($pedido['created_at'])->timezone('America/Mexico_City')->locale('es')->isoFormat('D [de] MMMM [de] YYYY, h:mm a') : null;
@endphp
<div class="encabezado">
    <div>
        @if (! empty($tienda['logo']))
            <img src="{{ $tienda['logo'] }}" alt="{{ $tienda['nombre'] }}">
        @else
            <div class="marca">{{ $tienda['nombre'] }}</div>
        @endif
        @if (! empty($tienda['eslogan']))
            <div style="margin-top:4px; font-size:11px; color:#14332B;">{{ $tienda['eslogan'] }}</div>
        @endif
    </div>
    <div class="doc">
        <div class="tipo">{{ $tipoDocumento }}</div>
        <div class="folio">{{ $pedido['folio'] }}</div>
        @if ($fechaPedido)
            <div class="fecha">{{ $fechaPedido }}</div>
        @endif
    </div>
</div>
