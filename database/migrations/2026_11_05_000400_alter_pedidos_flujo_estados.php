<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** estatus viejo => [estatus nuevo, estado_pago, estado_logistico] */
    private const MAPEO = [
        'pendiente' => ['pendiente_pago', 'pendiente', 'pendiente'],
        'pagado' => ['confirmado', 'aprobado', 'pendiente'],
        'preparando' => ['en_preparacion', 'aprobado', 'en_preparacion'],
        'enviado' => ['enviado', 'aprobado', 'enviado'],
        'entregado' => ['entregado', 'aprobado', 'entregado'],
        'cancelado' => ['cancelado', 'pendiente', 'pendiente'],
        'reembolsado' => ['reembolsado', 'reembolsado', 'pendiente'],
    ];

    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->string('estado_pago', 40)->default('pendiente')->after('estatus');
            $table->string('estado_logistico', 40)->default('pendiente')->after('estado_pago');
            $table->string('metodo_pago', 40)->nullable()->after('estado_logistico');
            $table->string('modalidad_entrega', 30)->default('envio_domicilio')->after('metodo_pago');
            $table->foreignId('regla_envio_id')->nullable()->constrained('reglas_envio')->nullOnDelete();
            $table->string('envio_estado', 20)->default('calculado');
            $table->foreignId('envio_cotizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('envio_cotizado_en')->nullable();
            $table->decimal('saldo_pendiente', 12, 2)->default(0);
            $table->timestamp('vence_en')->nullable();
            $table->timestamp('listo_envio_en')->nullable();
            $table->timestamp('vencido_en')->nullable();
            $table->date('fecha_envio')->nullable();
            $table->foreignId('cancelado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_cancelacion', 500)->nullable();
            $table->foreignId('cupon_id')->nullable()->constrained('cupones')->nullOnDelete();
            $table->string('cupon_codigo', 60)->nullable();
            $table->boolean('stock_reservado')->default(false);
            $table->index(['estado_pago', 'created_at']);
            $table->index(['estado_logistico', 'created_at']);
            $table->index(['estatus', 'vence_en']);
        });

        foreach (self::MAPEO as $viejo => [$nuevo, $pago, $logistico]) {
            DB::table('pedidos')->where('estatus', $viejo)->update([
                'estatus' => $nuevo,
                'estado_pago' => $pago,
                'estado_logistico' => $logistico,
            ]);
        }

        // Saldo pendiente de los pedidos que aún no se pagan.
        DB::table('pedidos')->where('estatus', 'pendiente_pago')->update(['saldo_pendiente' => DB::raw('total')]);

        // Los pedidos históricos se crearon con Openpay como único método.
        DB::table('pedidos')->whereNull('metodo_pago')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('pagos')
                    ->join('metodos_pagos', 'metodos_pagos.id', '=', 'pagos.metodo_pago_id')
                    ->whereColumn('pagos.pedido_id', 'pedidos.id')
                    ->where('metodos_pagos.clave', 'openpay');
            })
            ->update(['metodo_pago' => 'openpay']);
    }

    public function down(): void
    {
        $inverso = [
            'pendiente_cotizacion' => 'pendiente',
            'pendiente_pago' => 'pendiente',
            'pago_en_revision' => 'pendiente',
            'vencido' => 'cancelado',
            'confirmado' => 'pagado',
            'en_preparacion' => 'preparando',
            'listo_envio' => 'preparando',
        ];

        foreach ($inverso as $nuevo => $viejo) {
            DB::table('pedidos')->where('estatus', $nuevo)->update(['estatus' => $viejo]);
        }

        Schema::table('pedidos', function (Blueprint $table) {
            $this->dropForeignSiExiste($table, 'pedidos', 'regla_envio_id');
            $this->dropForeignSiExiste($table, 'pedidos', 'envio_cotizado_por');
            $this->dropForeignSiExiste($table, 'pedidos', 'cancelado_por');
            $this->dropForeignSiExiste($table, 'pedidos', 'cupon_id');
            $table->dropIndex(['estado_pago', 'created_at']);
            $table->dropIndex(['estado_logistico', 'created_at']);
            $table->dropIndex(['estatus', 'vence_en']);
        });

        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn([
                'estado_pago', 'estado_logistico', 'metodo_pago', 'modalidad_entrega',
                'regla_envio_id', 'envio_estado', 'envio_cotizado_por', 'envio_cotizado_en',
                'saldo_pendiente', 'vence_en', 'listo_envio_en', 'vencido_en', 'fecha_envio',
                'cancelado_por', 'motivo_cancelacion', 'cupon_id', 'cupon_codigo', 'stock_reservado',
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
