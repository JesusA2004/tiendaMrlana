<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transacciones_pasarelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')
                ->constrained('pagos')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('pasarela', 50); // openpay
            $table->string('tipo', 50); // cargo, webhook, devolucion
            $table->string('transaccion_id', 150)->nullable();
            $table->json('payload')->nullable();
            $table->json('respuesta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transacciones_pasarelas');
    }
};
