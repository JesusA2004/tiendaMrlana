<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotización de envío con varias paqueterías (contrato §2):
 * - `paqueterias`: catálogo administrable (sin datos precargados).
 * - `pedido_opciones_envio`: opciones cotizadas por pedido; el cliente elige una.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paqueterias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->unique();
            $table->boolean('activo')->default(true);
            $table->string('url_rastreo', 500)->nullable(); // con marcador {guia}
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->index('activo');
        });

        Schema::create('pedido_opciones_envio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('pedidos')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreignId('paqueteria_id')->nullable()->constrained('paqueterias')->nullOnDelete()->cascadeOnUpdate();
            $table->string('paqueteria_nombre', 80); // copia: el catálogo puede cambiar
            $table->string('servicio', 120);
            $table->decimal('costo', 12, 2);
            $table->unsignedSmallInteger('dias_min')->nullable();
            $table->unsignedSmallInteger('dias_max')->nullable();
            $table->string('tiempo_texto', 120)->nullable();
            $table->string('notas', 500)->nullable(); // visibles al cliente
            $table->boolean('recomendada')->default(false);
            $table->timestamp('seleccionada_en')->nullable();
            $table->foreignId('creado_por')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamps();
            $table->index(['pedido_id', 'seleccionada_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_opciones_envio');
        Schema::dropIfExists('paqueterias');
    }
};
