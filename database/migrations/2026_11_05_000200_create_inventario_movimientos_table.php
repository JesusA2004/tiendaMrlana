<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 30); // entrada|salida|ajuste|venta|liberacion|devolucion
            $table->integer('cantidad'); // con signo
            $table->integer('stock_anterior');
            $table->integer('stock_nuevo');
            $table->foreignId('pedido_id')->nullable()->constrained('pedidos')->nullOnDelete();
            $table->string('motivo', 255)->nullable();
            $table->timestamps();
            $table->index(['producto_id', 'created_at']);
            $table->index(['pedido_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_movimientos');
    }
};
