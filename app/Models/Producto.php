<?php

namespace App\Models;

use App\Enums\EstadoPublicacion;
use App\Enums\ModalidadVenta;
use App\Support\ImagenUrl;
use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory;

    protected $table = 'productos';

    protected $fillable = [
        'categoria_id',
        'marca_id',
        'sku',
        'nombre',
        'slug',
        'descripcion',
        'descripcion_larga',
        'precio',
        'precio_comparacion',
        'costo',
        'stock',
        'stock_minimo',
        'peso',
        'alto',
        'ancho',
        'largo',
        'imagen_principal',
        'destacado',
        'visible',
        'activo',
        'publicado_en',
        'estado_publicacion',
        'modalidad_venta',
        'tiempo_entrega_dias',
        'precio_referencia',
        'fuente_referencia',
        'referencia_consultada_en',
        'codigo_referencia',
        'modelo',
        'acabado',
        'dimensiones',
        'observaciones',
        'atributos',
        'meta_titulo',
        'meta_descripcion',
        'aprobado_por',
        'aprobado_en',
        'importacion_origen',
        'importacion_clave',
    ];

    /**
     * Datos de referencia NO comerciales: nunca se envían al público.
     */
    protected $hidden = [
        'costo',
        'precio_referencia',
        'fuente_referencia',
        'referencia_consultada_en',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'precio_comparacion' => 'decimal:2',
        'costo' => 'decimal:2',
        'precio_referencia' => 'decimal:2',
        'stock' => 'integer',
        'stock_minimo' => 'integer',
        'tiempo_entrega_dias' => 'integer',
        'destacado' => 'boolean',
        'visible' => 'boolean',
        'activo' => 'boolean',
        'publicado_en' => 'datetime',
        'aprobado_en' => 'datetime',
        'referencia_consultada_en' => 'date',
        'atributos' => 'array',
    ];

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    public function imagenes(): HasMany
    {
        return $this->hasMany(ProductoImagen::class, 'producto_id');
    }

    public function ofertas(): BelongsToMany
    {
        return $this->belongsToMany(
            Oferta::class,
            'oferta_producto',
            'producto_id',
            'oferta_id'
        )->withTimestamps();
    }

    public function movimientosInventario(): HasMany
    {
        return $this->hasMany(InventarioMovimiento::class, 'producto_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /**
     * Visibles al público: publicado + activo + visible + con precio.
     */
    public function scopePublicados(Builder $query): Builder
    {
        return $query
            ->where($query->qualifyColumn('estado_publicacion'), EstadoPublicacion::Publicado->value)
            ->where($query->qualifyColumn('activo'), true)
            ->where($query->qualifyColumn('visible'), true)
            ->whereNotNull($query->qualifyColumn('precio'))
            ->where($query->qualifyColumn('precio'), '>', 0);
    }

    /**
     * Publicados que pueden comprarse ahora (bajo pedido o con existencias).
     */
    public function scopeVendibles(Builder $query): Builder
    {
        return $query->publicados()->where(function (Builder $q) use ($query) {
            $q->where($query->qualifyColumn('modalidad_venta'), ModalidadVenta::BajoPedido->value)
                ->orWhere($query->qualifyColumn('stock'), '>', 0);
        });
    }

    public function esBajoPedido(): bool
    {
        return $this->modalidad_venta === ModalidadVenta::BajoPedido->value;
    }

    public function estaPublicado(): bool
    {
        return $this->estado_publicacion === EstadoPublicacion::Publicado->value
            && $this->activo
            && (bool) $this->getAttribute('visible') // $this->visible es la propiedad de serialización de Eloquent
            && $this->precio !== null
            && (float) $this->precio > 0;
    }

    /**
     * ¿Se puede vender la cantidad indicada ahora mismo?
     */
    public function estaDisponible(int $cantidad = 1): bool
    {
        if (! $this->estaPublicado() || $cantidad < 1) {
            return false;
        }

        if ($this->esBajoPedido()) {
            return true;
        }

        return (int) $this->stock >= $cantidad;
    }

    /**
     * Requisitos pendientes para poder publicar el producto (mensajes legibles).
     *
     * @return list<string>
     */
    public function faltantesParaPublicar(): array
    {
        $faltantes = [];

        if ($this->precio === null || (float) $this->precio <= 0) {
            $faltantes[] = 'Definir un precio mayor a cero.';
        }

        if (! $this->categoria_id) {
            $faltantes[] = 'Asignar una categoría.';
        }

        $tieneImagen = filled($this->imagen_principal)
            || ($this->relationLoaded('imagenes') ? $this->imagenes->isNotEmpty() : $this->imagenes()->exists());

        if (! $tieneImagen) {
            $faltantes[] = 'Agregar al menos una imagen autorizada.';
        }

        if (($this->modalidad_venta ?? ModalidadVenta::Inventario->value) === ModalidadVenta::Inventario->value
            && $this->stock === null) {
            $faltantes[] = 'Definir las existencias (puede ser 0).';
        }

        if (! $this->modalidad_venta) {
            $faltantes[] = 'Elegir la modalidad de venta.';
        }

        return $faltantes;
    }

    public function puedePublicarse(): bool
    {
        return $this->faltantesParaPublicar() === [];
    }

    /**
     * URL pública de la imagen principal (primera imagen por orden o imagen_principal).
     */
    public function imagenPrincipalUrl(): ?string
    {
        $ruta = null;

        if ($this->relationLoaded('imagenes')) {
            $ruta = $this->imagenes
                ->sortBy([['principal', 'desc'], ['orden', 'asc'], ['id', 'asc']])
                ->pluck('ruta')
                ->filter()
                ->first();
        }

        return ImagenUrl::resolver($ruta ?: $this->imagen_principal);
    }
}
