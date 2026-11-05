<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_padre_id')->nullable()
                ->constrained('categorias')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            // Si el campo es null entonces la categoría es de primer nivel, si tiene un valor entonces es una subcategoría de la categoría con el id igual al valor del campo
            $table->string('nombre', 150);
            $table->string('slug', 170)->unique();
            $table->text('descripcion')->nullable();
            $table->string('imagen')->nullable();
            $table->boolean('activa')->default(true);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias');
    }
};
