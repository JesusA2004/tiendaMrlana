<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransaccionPasarela extends Model
{
    protected $table = 'transacciones_pasarelas';

    protected $fillable = [
        'pago_id',
        'pasarela',
        'tipo',
        'transaccion_id',
        'payload',
        'respuesta',
    ];

    protected $casts = [
        'payload' => 'array',
        'respuesta' => 'array',
    ];

    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }
}
