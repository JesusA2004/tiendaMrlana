<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->nullable()
                ->constrained('categorias')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('marca_id')->nullable()
                ->constrained('marcas')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('sku', 100)->unique();
            $table->string('nombre', 180);
            $table->string('slug', 200)->unique();
            $table->text('descripcion')->nullable();
            $table->longText('descripcion_larga')->nullable();
            $table->decimal('precio', 12, 2);
            $table->decimal('precio_comparacion', 12, 2)->nullable();
            $table->decimal('costo', 12, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->integer('stock_minimo')->default(0);
            $table->decimal('peso', 10, 2)->nullable();
            $table->decimal('alto', 10, 2)->nullable();
            $table->decimal('ancho', 10, 2)->nullable();
            $table->decimal('largo', 10, 2)->nullable();
            $table->string('imagen_principal')->nullable();
            $table->boolean('destacado')->default(false);
            $table->boolean('visible')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamp('publicado_en')->nullable();
            $table->timestamps();
            $table->index(['activo', 'visible']);
            $table->index(['categoria_id', 'activo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
