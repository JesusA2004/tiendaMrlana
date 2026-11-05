<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PedidoEstatusHistorial extends Model
{
    public const TIPO_GENERAL = 'general';

    public const TIPO_PAGO = 'pago';

    public const TIPO_LOGISTICO = 'logistico';

    public const TIPO_ENVIO = 'envio';

    public const TIPO_NOTA = 'nota';

    public const TIPO_SISTEMA = 'sistema';

    protected $table = 'pedido_estatus_historial';

    protected $fillable = [
        'pedido_id',
        'user_id',
        'tipo',
        'estado_anterior',
        'estatus',
        'comentario',
        'datos',
        'visible_cliente',
    ];

    protected $casts = [
        'datos' => 'array',
        'visible_cliente' => 'boolean',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
