<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PagoComprobante extends Model
{
    public const ESTADO_RECIBIDO = 'recibido';

    public const ESTADO_APROBADO = 'aprobado';

    public const ESTADO_RECHAZADO = 'rechazado';

    /** Extensiones/mimes permitidos y tamaño máximo (KB) para validar la subida. */
    public const EXTENSIONES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public const MAX_KB = 5120;

    protected $table = 'pago_comprobantes';

    protected $fillable = [
        'pago_id',
        'user_id',
        'disk',
        'ruta',
        'nombre_original',
        'mime',
        'tamano',
        'sha256',
        'referencia',
        'fecha_transferencia',
        'monto_declarado',
        'estado',
    ];

    protected $hidden = ['ruta', 'disk'];

    protected $casts = [
        'tamano' => 'integer',
        'fecha_transferencia' => 'date',
        'monto_declarado' => 'decimal:2',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
