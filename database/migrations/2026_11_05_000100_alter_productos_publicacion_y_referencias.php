<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->string('estado_publicacion', 30)->default('borrador')->after('activo');
            $table->string('modalidad_venta', 20)->default('inventario')->after('estado_publicacion');
            $table->unsignedSmallInteger('tiempo_entrega_dias')->nullable()->after('modalidad_venta');
            $table->decimal('precio_referencia', 12, 2)->nullable();
            $table->string('fuente_referencia', 255)->nullable();
            $table->date('referencia_consultada_en')->nullable();
            $table->string('codigo_referencia', 60)->nullable()->index();
            $table->string('modelo', 120)->nullable();
            $table->string('acabado', 180)->nullable();
            $table->string('dimensiones', 180)->nullable();
            $table->text('observaciones')->nullable();
            $table->json('atributos')->nullable();
            $table->string('meta_titulo', 180)->nullable();
            $table->string('meta_descripcion', 300)->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('aprobado_en')->nullable();
            $table->string('importacion_origen', 80)->nullable();
            $table->string('importacion_clave', 120)->nullable()->unique();
            $table->index('estado_publicacion');
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio', 12, 2)->nullable()->change();
        });

        // Mapeo de datos: activos y visibles se publican; el resto queda pausado.
        DB::table('productos')->where('activo', true)->where('visible', true)
            ->update(['estado_publicacion' => 'publicado']);
        DB::table('productos')->where(function ($q) {
            $q->where('activo', false)->orWhere('visible', false);
        })->update(['estado_publicacion' => 'pausado']);
    }

    public function down(): void
    {
        // Los borradores sin precio no pueden quedar con precio NULL al revertir.
        DB::table('productos')->whereNull('precio')->update(['precio' => 0]);

        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio', 12, 2)->nullable(false)->change();
        });

        Schema::table('productos', function (Blueprint $table) {
            $this->dropForeignSiExiste($table, 'productos', 'aprobado_por');
            $table->dropIndex(['estado_publicacion']);
            $table->dropIndex(['codigo_referencia']);
            $table->dropUnique(['importacion_clave']);
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn([
                'estado_publicacion', 'modalidad_venta', 'tiempo_entrega_dias',
                'precio_referencia', 'fuente_referencia', 'referencia_consultada_en',
                'codigo_referencia', 'modelo', 'acabado', 'dimensiones', 'observaciones',
                'atributos', 'meta_titulo', 'meta_descripcion', 'aprobado_por', 'aprobado_en',
                'importacion_origen', 'importacion_clave',
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
