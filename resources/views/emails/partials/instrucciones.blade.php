{{-- Instrucciones de pago según método (sólo correos al dueño). --}}
@if (! empty($pedido['envio_esperando_eleccion']))
    @include('emails.partials.aviso', ['tono' => 'warning', 'titulo' => 'Elige tu envío', 'mensaje' => 'Ya cotizamos tu envío con varias paqueterías. Entra a tu pedido y elige una opción; después te mostraremos el total final y cómo pagar.'])
@elseif ($pedido['envio_por_cotizar'])
    @include('emails.partials.aviso', ['tono' => 'warning', 'titulo' => 'Estamos cotizando tu envío', 'mensaje' => 'Te enviaremos el costo de envío antes de que pagues. No realices ningún pago todavía.'])
@elseif ($pedido['metodo_pago'] === 'transferencia' && ! empty($instrucciones['banco']))
    @include('emails.partials.banco', ['banco' => $instrucciones['banco'], 'venceEn' => $venceEn ?? null])
    <p style="margin:0 0 6px;">Cuando hayas pagado, sube tu comprobante desde tu pedido para que podamos confirmarlo.</p>
@elseif (! empty($instrucciones['efectivo_presencial']))
    @php
        $recoger = $instrucciones['recoger'] ?? [];
        $detalle = 'Menciona tu folio '.$pedido['folio'].' al pagar.';
        if (! empty($recoger['direccion'])) {
            $detalle .= ' Dirección: '.$recoger['direccion'].'.';
        }
        if (! empty($recoger['horario'])) {
            $detalle .= ' Horario: '.$recoger['horario'].'.';
        }
    @endphp
    @include('emails.partials.aviso', ['tono' => 'info', 'titulo' => 'Paga en efectivo en nuestra tienda', 'mensaje' => $detalle])
@elseif (! empty($instrucciones['contra_entrega']))
    @include('emails.partials.aviso', ['tono' => 'info', 'titulo' => 'Pago contra entrega', 'mensaje' => 'Pagarás $'.number_format($pedido['saldo_pendiente'], 2).' MXN en efectivo al recibir tu pedido.'])
@endif
@if (! empty($instrucciones['texto']) && ! $pedido['envio_por_cotizar'])
    <p style="margin:8px 0 0; font-size:14px; color:#374151;">{{ $instrucciones['texto'] }}</p>
@endif
