<?php

namespace App\Models;

use App\Enums\MetodoPagoClave;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MetodoPago extends Model
{
    protected $table = 'metodos_pagos';

    protected $fillable = [
        'nombre',
        'clave',
        'activo',
        'configuracion',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'configuracion' => 'array',
    ];

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'metodo_pago_id');
    }

    /**
     * Métodos que el cliente puede elegir en el checkout (nunca Openpay).
     */
    public function scopeParaCheckout(Builder $query): Builder
    {
        return $query->where('activo', true)->whereIn('clave', [
            MetodoPagoClave::Transferencia->value,
            MetodoPagoClave::EfectivoPresencial->value,
            MetodoPagoClave::EfectivoContraEntrega->value,
        ]);
    }

    /** Clave usada por el sistema (no se renombra ni se elimina desde el panel). */
    public function esClaveDeSistema(): bool
    {
        return MetodoPagoClave::tryFrom((string) $this->clave) !== null;
    }

    /** Openpay queda bloqueado mientras los pagos en línea estén deshabilitados. */
    public function estaBloqueado(): bool
    {
        return $this->clave === MetodoPagoClave::Openpay->value && ! config('payments.enabled', false);
    }

    public function config(string $clave, mixed $default = null): mixed
    {
        return ($this->configuracion ?? [])[$clave] ?? $default;
    }
}
