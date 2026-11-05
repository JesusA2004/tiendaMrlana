<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('direccion_id')->nullable()
                ->constrained('direcciones')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('folio', 50)->unique();
            $table->string('estatus', 40)->default('pendiente');
            // pendiente, pagado, preparando, enviado, entregado, cancelado, reembolsado
            $table->string('moneda', 10)->default('MXN');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('envio', 12, 2)->default(0);
            $table->decimal('impuesto', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('nombre_cliente', 180);
            $table->string('correo_cliente', 180);
            $table->string('telefono_cliente', 30)->nullable();
            $table->text('notas_cliente')->nullable();
            $table->timestamp('pagado_en')->nullable();
            $table->timestamp('cancelado_en')->nullable();
            $table->string('paqueteria', 120)->nullable();
            $table->string('numero_guia', 180)->nullable();
            $table->timestamp('preparando_en')->nullable();
            $table->timestamp('enviado_en')->nullable();
            $table->timestamp('entregado_en')->nullable();
            $table->text('comentario_interno')->nullable();
            $table->timestamps();
            $table->index(['estatus', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos');
    }
};
