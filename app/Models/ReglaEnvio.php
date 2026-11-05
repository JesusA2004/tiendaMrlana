<?php

namespace App\Models;

use App\Enums\TipoReglaEnvio;
use Database\Factories\ReglaEnvioFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ReglaEnvio extends Model
{
    /** @use HasFactory<ReglaEnvioFactory> */
    use HasFactory;

    protected $table = 'reglas_envio';

    protected $fillable = [
        'nombre',
        'tipo',
        'estados',
        'cp_prefijos',
        'monto',
        'gratis_desde',
        'dias_estimados',
        'permite_contra_entrega',
        'activo',
        'prioridad',
        'notas',
    ];

    protected $casts = [
        'estados' => 'array',
        'cp_prefijos' => 'array',
        'monto' => 'decimal:2',
        'gratis_desde' => 'decimal:2',
        'permite_contra_entrega' => 'boolean',
        'activo' => 'boolean',
        'prioridad' => 'integer',
    ];

    public function pedidos(): HasMany
    {
        return $this->hasMany(Pedido::class, 'regla_envio_id');
    }

    /** Activas ordenadas por prioridad (mayor primero). */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activo', true)->orderByDesc('prioridad')->orderBy('id');
    }

    public function esRecoger(): bool
    {
        return $this->tipo === TipoReglaEnvio::RecogerEnTienda->value;
    }

    /**
     * ¿Aplica a la dirección (estado + CP)? null/vacío en estados o cp_prefijos = todos.
     */
    public function aplicaA(?string $estado, ?string $codigoPostal): bool
    {
        $estados = array_filter((array) ($this->estados ?? []));

        if ($estados !== []) {
            $normal = fn (?string $s) => mb_strtolower(trim(Str::ascii((string) $s)));

            if (! in_array($normal($estado), array_map($normal, $estados), true)) {
                return false;
            }
        }

        $prefijos = array_filter((array) ($this->cp_prefijos ?? []));

        if ($prefijos === []) {
            return true;
        }

        $cp = preg_replace('/\D/', '', (string) $codigoPostal);

        foreach ($prefijos as $prefijo) {
            $prefijo = preg_replace('/\D/', '', (string) $prefijo);

            if ($cp !== '' && $prefijo !== '' && str_starts_with($cp, $prefijo)) {
                return true;
            }
        }

        return false;
    }
}
