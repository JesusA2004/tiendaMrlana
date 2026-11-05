<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copia de la dirección de entrega en el pedido (`pedidos.direccion_envio`), para que editar o
 * borrar una dirección guardada no altere pedidos pasados. Se rellena para pedidos existentes.
 */
return new class extends Migration
{
    private const CAMPOS = [
        'id', 'alias', 'nombre_receptor', 'telefono', 'calle', 'numero_exterior', 'numero_interior',
        'colonia', 'municipio', 'estado', 'pais', 'codigo_postal', 'referencias',
    ];

    public function up(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->json('direccion_envio')->nullable()->after('direccion_id');
        });

        DB::table('pedidos')
            ->whereNotNull('direccion_id')
            ->whereNull('direccion_envio')
            ->orderBy('id')
            ->select(['id', 'direccion_id'])
            ->chunkById(200, function ($pedidos) {
                $direcciones = DB::table('direcciones')
                    ->whereIn('id', $pedidos->pluck('direccion_id')->unique()->all())
                    ->get(self::CAMPOS)
                    ->keyBy('id');

                foreach ($pedidos as $pedido) {
                    $direccion = $direcciones->get($pedido->direccion_id);

                    if (! $direccion) {
                        continue;
                    }

                    DB::table('pedidos')->where('id', $pedido->id)->update([
                        'direccion_envio' => json_encode((array) $direccion, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('pedidos', function (Blueprint $table) {
            $table->dropColumn('direccion_envio');
        });
    }
};
