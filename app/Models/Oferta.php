<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Oferta extends Model
{
    use HasFactory;

    protected $table = 'ofertas';

    protected $fillable = [
        'nombre',
        'tipo',
        'valor',
        'aplica_a',
        'categoria_id',
        'marca_id',
        'inicia_en',
        'termina_en',
        'activa',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'inicia_en' => 'datetime',
        'termina_en' => 'datetime',
        'activa' => 'boolean',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(
            Producto::class,
            'oferta_producto',
            'oferta_id',
            'producto_id'
        )->withTimestamps();
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query
            ->where('activa', true)
            ->where(function ($subQuery) {
                $subQuery
                    ->whereNull('inicia_en')
                    ->orWhere('inicia_en', '<=', now());
            })
            ->where(function ($subQuery) {
                $subQuery
                    ->whereNull('termina_en')
                    ->orWhere('termina_en', '>=', now());
            });
    }
}
