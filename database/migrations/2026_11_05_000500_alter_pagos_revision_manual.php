<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->string('tipo', 40)->nullable()->after('metodo_pago_id');
            $table->string('referencia_cliente', 120)->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->string('motivo_rechazo', 500)->nullable();
            $table->text('notas_internas')->nullable();
            $table->decimal('monto_recibido', 12, 2)->nullable();
            $table->foreignId('recibido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recibido_en')->nullable();
            $table->index(['estatus', 'created_at']);
        });

        // Tipo de los pagos históricos según la clave de su método. Los estatus históricos se conservan.
        $metodos = DB::table('metodos_pagos')->pluck('clave', 'id');

        foreach ($metodos as $id => $clave) {
            DB::table('pagos')->where('metodo_pago_id', $id)->whereNull('tipo')->update(['tipo' => $clave]);
        }
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $this->dropForeignSiExiste($table, 'pagos', 'revisado_por');
            $this->dropForeignSiExiste($table, 'pagos', 'recibido_por');
            $table->dropIndex(['estatus', 'created_at']);
        });

        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn([
                'tipo', 'referencia_cliente', 'revisado_por', 'revisado_en', 'motivo_rechazo',
                'notas_internas', 'monto_recibido', 'recibido_por', 'recibido_en',
            ]);
        });
    }

    /**
     * Borra la llave foránea sólo si existe (en motores MyISAM no se crean FKs).
     */
    private function dropForeignSiExiste(Blueprint $table, string $tabla, string $columna): void
    {
        foreach (Schema::getForeignKeys($tabla) as $fk) {
            if ($fk['columns'] === [$columna]) {
                $table->dropForeign([$columna]);

                return;
            }
        }
    }
};
