<?php

namespace App\Listeners;

use App\Enums\MetodoPagoClave;
use App\Events\Pedidos\ComprobanteRecibido;
use App\Events\Pedidos\EnvioCotizado;
use App\Events\Pedidos\EnvioElegido;
use App\Events\Pedidos\EventoPedido;
use App\Events\Pedidos\OpcionesEnvioEnviadas;
use App\Events\Pedidos\PagoAprobado;
use App\Events\Pedidos\PagoRechazado;
use App\Events\Pedidos\PedidoCancelado;
use App\Events\Pedidos\PedidoEnPreparacion;
use App\Events\Pedidos\PedidoEntregado;
use App\Events\Pedidos\PedidoEnviado;
use App\Events\Pedidos\PedidoListoParaRecoger;
use App\Events\Pedidos\PedidoRegistrado;
use App\Events\Pedidos\PedidoVencido;
use App\Mail\Pedidos\AvisoStaffComprobanteMail;
use App\Mail\Pedidos\AvisoStaffPedidoNuevoMail;
use App\Mail\Pedidos\ComprobanteRecibidoMail;
use App\Mail\Pedidos\CotizacionEnvioListaMail;
use App\Mail\Pedidos\EnvioCotizadoMail;
use App\Mail\Pedidos\InstruccionesTransferenciaMail;
use App\Mail\Pedidos\PagoAprobadoMail;
use App\Mail\Pedidos\PagoRechazadoMail;
use App\Mail\Pedidos\PedidoCanceladoMail;
use App\Mail\Pedidos\PedidoEnPreparacionMail;
use App\Mail\Pedidos\PedidoEntregadoMail;
use App\Mail\Pedidos\PedidoEnviadoMail;
use App\Mail\Pedidos\PedidoListoParaRecogerMail;
use App\Mail\Pedidos\PedidoRegistradoMail;
use App\Mail\Pedidos\PedidoVencidoMail;
use App\Models\NotificacionPedido;
use App\Models\Pedido;
use App\Models\User;
use App\Services\Configuracion\ConfiguracionTienda;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Correos de los eventos de dominio de pedidos (auto-descubierto por los métodos handle*).
 * Cada correo se registra una sola vez con NotificacionPedido::registrarUnaVez (clave del
 * evento + tipo de correo), así un evento repetido o reintentado no duplica correos.
 * Los Mailables van en cola y después del commit.
 */
class NotificarEventosPedido
{
    public function __construct(private readonly ConfiguracionTienda $config) {}

    public function handlePedidoRegistrado(PedidoRegistrado $e): void
    {
        $this->alCliente($e, 'pedido_registrado', new PedidoRegistradoMail($e->pedido));

        if ($this->debeEnviarInstrucciones($e->pedido)) {
            $this->alCliente($e, 'instrucciones_transferencia', new InstruccionesTransferenciaMail($e->pedido));
        }

        $this->alStaff($e, 'staff_pedido_nuevo', new AvisoStaffPedidoNuevoMail($e->pedido));
    }

    public function handleEnvioCotizado(EnvioCotizado $e): void
    {
        $this->alCliente($e, 'envio_cotizado', new EnvioCotizadoMail($e->pedido));
    }

    /** Una vez por juego de opciones (re-cotizar sí vuelve a avisar). */
    public function handleOpcionesEnvioEnviadas(OpcionesEnvioEnviadas $e): void
    {
        $this->alCliente($e, 'cotizacion_envio', new CotizacionEnvioListaMail($e->pedido));
    }

    /**
     * El cliente eligió su envío: ahora sí van las instrucciones de pago. Transferencia usa el
     * mismo correo de instrucciones que al registrar el pedido (que no se envió porque el envío
     * estaba pendiente); otros métodos reciben el correo de "envío cotizado" con sus instrucciones.
     */
    public function handleEnvioElegido(EnvioElegido $e): void
    {
        if ($this->debeEnviarInstrucciones($e->pedido)) {
            $this->alCliente($e, 'instrucciones_transferencia', new InstruccionesTransferenciaMail($e->pedido));

            return;
        }

        $this->alCliente($e, 'envio_cotizado', new EnvioCotizadoMail($e->pedido));
    }

    public function handleComprobanteRecibido(ComprobanteRecibido $e): void
    {
        $this->alCliente($e, 'comprobante_recibido', new ComprobanteRecibidoMail($e->pedido));
        $this->alStaff($e, 'staff_comprobante', new AvisoStaffComprobanteMail($e->pedido));
    }

    public function handlePagoAprobado(PagoAprobado $e): void
    {
        $this->alCliente($e, 'pago_aprobado', new PagoAprobadoMail($e->pedido));
    }

    public function handlePagoRechazado(PagoRechazado $e): void
    {
        $this->alCliente($e, 'pago_rechazado', new PagoRechazadoMail($e->pedido, $e->motivo));
    }

    public function handlePedidoEnPreparacion(PedidoEnPreparacion $e): void
    {
        $this->alCliente($e, 'en_preparacion', new PedidoEnPreparacionMail($e->pedido));
    }

    public function handlePedidoEnviado(PedidoEnviado $e): void
    {
        $this->alCliente($e, 'enviado', new PedidoEnviadoMail($e->pedido));
    }

    public function handlePedidoEntregado(PedidoEntregado $e): void
    {
        $this->alCliente($e, 'entregado', new PedidoEntregadoMail($e->pedido));
    }

    public function handlePedidoCancelado(PedidoCancelado $e): void
    {
        $this->alCliente($e, 'cancelado', new PedidoCanceladoMail($e->pedido, $e->motivo, $e->requiereReembolso));
    }

    public function handlePedidoVencido(PedidoVencido $e): void
    {
        $this->alCliente($e, 'vencido', new PedidoVencidoMail($e->pedido));
    }

    public function handlePedidoListoParaRecoger(PedidoListoParaRecoger $e): void
    {
        $this->alCliente($e, 'listo_recoger', new PedidoListoParaRecogerMail($e->pedido));
    }

    // ------------------------------------------------------------------ internos

    private function debeEnviarInstrucciones(Pedido $pedido): bool
    {
        return $pedido->metodo_pago === MetodoPagoClave::Transferencia->value
            && ! $pedido->envioPendiente();
    }

    private function alCliente(EventoPedido $e, string $tipo, Mailable $correo): void
    {
        $destino = $e->pedido->correo_cliente ?: $e->pedido->user?->email;

        if (blank($destino)) {
            return;
        }

        $this->enviarUnaVez($e, $tipo, $correo, [$destino]);
    }

    private function alStaff(EventoPedido $e, string $tipo, Mailable $correo): void
    {
        $destinos = $this->correosStaff();

        if ($destinos === []) {
            return;
        }

        $this->enviarUnaVez($e, $tipo, $correo, $destinos);
    }

    /** @param  list<string>  $destinos */
    private function enviarUnaVez(EventoPedido $e, string $tipo, Mailable $correo, array $destinos): void
    {
        $clave = mb_substr($e->claveNotificacion().':'.$tipo, 0, 120);

        if (! NotificacionPedido::registrarUnaVez($e->pedido, $tipo, $clave)) {
            return;
        }

        try {
            Mail::to($destinos)->queue($correo);
        } catch (Throwable $ex) {
            // Si no se pudo encolar, se libera la clave para permitir un reintento.
            NotificacionPedido::query()->where('clave', $clave)->delete();
            report($ex);
        }
    }

    /**
     * Aviso interno: correo de contacto configurado; si no hay, administradores.
     *
     * @return list<string>
     */
    private function correosStaff(): array
    {
        $contacto = $this->config->contacto()['correo'] ?? null;

        if (filled($contacto) && filter_var($contacto, FILTER_VALIDATE_EMAIL)) {
            return [$contacto];
        }

        return User::query()
            ->where('rol', User::ROL_ADMIN)
            ->whereNotNull('email')
            ->orderBy('id')
            ->limit(10)
            ->pluck('email')
            ->filter()
            ->values()
            ->all();
    }
}
