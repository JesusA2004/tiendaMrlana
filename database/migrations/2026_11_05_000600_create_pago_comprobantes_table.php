<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pago_comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pago_id')->constrained('pagos')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('ruta', 255);
            $table->string('nombre_original', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('tamano');
            $table->char('sha256', 64);
            $table->string('referencia', 120)->nullable();
            $table->date('fecha_transferencia')->nullable();
            $table->decimal('monto_declarado', 12, 2)->nullable();
            $table->string('estado', 20)->default('recibido'); // recibido|aprobado|rechazado
            $table->timestamps();
            $table->index(['pago_id', 'estado']);
            $table->index('sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pago_comprobantes');
    }
};
