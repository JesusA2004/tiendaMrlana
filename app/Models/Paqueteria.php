<?php

namespace App\Models;

use Database\Factories\PaqueteriaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de paqueterías (administrable). `url_rastreo` lleva el marcador {guia}.
 */
class Paqueteria extends Model
{
    /** @use HasFactory<PaqueteriaFactory> */
    use HasFactory;

    public const MARCADOR_GUIA = '{guia}';

    protected $table = 'paqueterias';

    protected $fillable = [
        'nombre',
        'activo',
        'url_rastreo',
        'notas',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function opciones(): HasMany
    {
        return $this->hasMany(PedidoOpcionEnvio::class, 'paqueteria_id');
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true)->orderBy('nombre');
    }

    /** Enlace de rastreo para una guía, sólo si la URL tiene el marcador {guia}. */
    public function urlRastreo(?string $guia): ?string
    {
        return self::construirUrlRastreo($this->url_rastreo, $guia);
    }

    public static function construirUrlRastreo(?string $plantilla, ?string $guia): ?string
    {
        $guia = trim((string) $guia);

        if ($guia === '' || blank($plantilla) || ! str_contains((string) $plantilla, self::MARCADOR_GUIA)) {
            return null;
        }

        $url = str_replace(self::MARCADOR_GUIA, rawurlencode($guia), (string) $plantilla);

        return preg_match('#^https?://#i', $url) ? $url : null;
    }
}
