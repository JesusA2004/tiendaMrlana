<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Direccion extends Model
{
    use HasFactory;

    protected $table = 'direcciones';

    protected $fillable = [
        'user_id',
        'alias',
        'nombre_receptor',
        'telefono',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'municipio',
        'estado',
        'pais',
        'codigo_postal',
        'referencias',
        'predeterminada',
    ];

    protected $casts = [
        'predeterminada' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'direccion_id');
    }

    /** Campos que se copian al pedido (`pedidos.direccion_envio`). */
    public const CAMPOS_COPIA = [
        'alias', 'nombre_receptor', 'telefono', 'calle', 'numero_exterior', 'numero_interior',
        'colonia', 'municipio', 'estado', 'pais', 'codigo_postal', 'referencias',
    ];

    /**
     * Copia inmutable de la dirección para guardarla en el pedido.
     *
     * @return array<string, mixed>
     */
    public function copiaParaPedido(): array
    {
        $copia = ['id' => $this->id];

        foreach (self::CAMPOS_COPIA as $campo) {
            $copia[$campo] = $this->getAttribute($campo);
        }

        return $copia;
    }
}
