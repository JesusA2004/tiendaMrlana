<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nombre', 150);
            $table->string('tipo', 30); // porcentaje, monto_fijo
            $table->decimal('valor', 12, 2);
            $table->decimal('compra_minima', 12, 2)->nullable();
            $table->integer('usos_maximos')->nullable();
            $table->integer('usos_por_usuario')->nullable();
            $table->integer('usos_actuales')->default(0);
            $table->timestamp('inicia_en')->nullable();
            $table->timestamp('termina_en')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupones');
    }
};
