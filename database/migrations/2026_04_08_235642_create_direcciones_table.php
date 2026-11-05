<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direcciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('alias', 100)->nullable();
            $table->string('nombre_receptor', 180);
            $table->string('telefono', 30)->nullable();
            $table->string('calle', 180);
            $table->string('numero_exterior', 50)->nullable();
            $table->string('numero_interior', 50)->nullable();
            $table->string('colonia', 150);
            $table->string('municipio', 150);
            $table->string('estado', 150);
            $table->string('pais', 100)->default('México');
            $table->string('codigo_postal', 20);
            $table->text('referencias')->nullable();
            $table->boolean('predeterminada')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('direcciones');
    }
};
