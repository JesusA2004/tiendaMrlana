<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')
                ->constrained('pedidos')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('metodo_pago_id')
                ->constrained('metodos_pagos')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('estatus', 40)->default('pendiente');
            // pendiente, procesando, completado, fallido, cancelado, reembolsado
            $table->decimal('monto', 12, 2);
            $table->string('moneda', 10)->default('MXN');
            $table->string('referencia_externa', 150)->nullable();
            $table->string('autorizacion', 150)->nullable();
            $table->text('respuesta_pasarela')->nullable();
            $table->timestamp('pagado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
