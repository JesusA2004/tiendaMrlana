<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglas_envio', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('tipo', 30); // tarifa_fija|cotizacion_manual|recoger_en_tienda
            $table->json('estados')->nullable();
            $table->json('cp_prefijos')->nullable();
            $table->decimal('monto', 12, 2)->nullable();
            $table->decimal('gratis_desde', 12, 2)->nullable();
            $table->string('dias_estimados', 60)->nullable();
            $table->boolean('permite_contra_entrega')->default(false);
            $table->boolean('activo')->default(true);
            $table->integer('prioridad')->default(0);
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->index(['activo', 'prioridad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglas_envio');
    }
};
