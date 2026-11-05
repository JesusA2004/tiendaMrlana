<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Opción de envío cotizada para un pedido (paquetería + servicio + costo + tiempo).
 * El cliente elige una (`seleccionada_en`); al re-cotizar se reemplazan.
 */
class PedidoOpcionEnvio extends Model
{
    protected $table = 'pedido_opciones_envio';

    protected $fillable = [
        'pedido_id',
        'paqueteria_id',
        'paqueteria_nombre',
        'servicio',
        'costo',
        'dias_min',
        'dias_max',
        'tiempo_texto',
        'notas',
        'recomendada',
        'seleccionada_en',
        'creado_por',
    ];

    protected $casts = [
        'costo' => 'decimal:2',
        'dias_min' => 'integer',
        'dias_max' => 'integer',
        'recomendada' => 'boolean',
        'seleccionada_en' => 'datetime',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    public function paqueteria(): BelongsTo
    {
        return $this->belongsTo(Paqueteria::class, 'paqueteria_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** Texto del tiempo estimado: texto libre o "3 a 5 días hábiles". */
    public function tiempoEstimado(): ?string
    {
        if (filled($this->tiempo_texto)) {
            return $this->tiempo_texto;
        }

        $min = $this->dias_min;
        $max = $this->dias_max;

        return match (true) {
            $min !== null && $max !== null && $min !== $max => "{$min} a {$max} días hábiles",
            $min !== null || $max !== null => ($n = $max ?? $min).' '.($n === 1 ? 'día hábil' : 'días hábiles'),
            default => null,
        };
    }

    /** Días para ordenar por rapidez (máximo, si no el mínimo). */
    public function diasOrden(): ?int
    {
        return $this->dias_max ?? $this->dias_min;
    }

    /**
     * Serialización compartida (cliente, panel y correos).
     *
     * @return array<string, mixed>
     */
    public function presentar(): array
    {
        return [
            'id' => $this->id,
            'paqueteria_id' => $this->paqueteria_id,
            'paqueteria' => $this->paqueteria_nombre,
            'servicio' => $this->servicio,
            'costo' => (float) $this->costo,
            'dias_min' => $this->dias_min,
            'dias_max' => $this->dias_max,
            'tiempo_texto' => $this->tiempo_texto,
            'tiempo' => $this->tiempoEstimado(),
            'notas' => $this->notas,
            'recomendada' => (bool) $this->recomendada,
            'seleccionada' => $this->seleccionada_en !== null,
            'seleccionada_en' => $this->seleccionada_en?->toIso8601String(),
        ];
    }

    /**
     * Presenta un conjunto de opciones marcando la más económica y la más rápida.
     *
     * @param  iterable<PedidoOpcionEnvio>  $opciones
     * @return list<array<string, mixed>>
     */
    public static function presentarLista(iterable $opciones): array
    {
        $lista = collect($opciones)->sortBy([['costo', 'asc'], ['id', 'asc']])->values();

        if ($lista->isEmpty()) {
            return [];
        }

        // Las insignias sólo tienen sentido si hay diferencia entre opciones.
        $costos = $lista->map(fn (self $o) => (float) $o->costo);
        $dias = $lista->map(fn (self $o) => $o->diasOrden())->filter(fn ($d) => $d !== null);
        $minCosto = $costos->min() < $costos->max() ? $costos->min() : null;
        $minDias = $dias->count() > 1 && $dias->min() < $dias->max() ? $dias->min() : null;

        return $lista->map(fn (self $o) => [
            ...$o->presentar(),
            'mas_economica' => $minCosto !== null && (float) $o->costo === (float) $minCosto,
            'mas_rapida' => $minDias !== null && $o->diasOrden() === $minDias,
        ])->all();
    }
}
