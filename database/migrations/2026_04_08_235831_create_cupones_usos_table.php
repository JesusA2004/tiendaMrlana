<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cupones_usos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cupon_id')
                ->constrained('cupones')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('pedido_id')
                ->constrained('pedidos')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cupones_usos');
    }
};
