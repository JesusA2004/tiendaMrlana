<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MAPEO = [
        'pendiente' => 'pendiente_pago',
        'pagado' => 'confirmado',
        'preparando' => 'en_preparacion',
    ];

    public function up(): void
    {
        Schema::table('pedido_estatus_historial', function (Blueprint $table) {
            $table->string('tipo', 20)->default('general')->after('user_id');
            $table->string('estado_anterior', 40)->nullable()->after('tipo');
            $table->json('datos')->nullable();
            $table->boolean('visible_cliente')->default(true);
        });

        foreach (self::MAPEO as $viejo => $nuevo) {
            DB::table('pedido_estatus_historial')->where('estatus', $viejo)->update(['estatus' => $nuevo]);
        }
    }

    public function down(): void
    {
        foreach (self::MAPEO as $viejo => $nuevo) {
            DB::table('pedido_estatus_historial')->where('estatus', $nuevo)->update(['estatus' => $viejo]);
        }

        Schema::table('pedido_estatus_historial', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'estado_anterior', 'datos', 'visible_cliente']);
        });
    }
};
