<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Editar datos del participante desde el POS de retiro en sitio
     * (28/09/2026) — `categoria_id` (id real, `categoria` sigue siendo el
     * nombre ya resuelto que trae el CSV) y `editar_datos_url` (link firmado
     * por fila, mismo patrón que `actualizar_numeracion_url`) para poder
     * empujar cambios a ApiRestEvent al momento de la entrega.
     */
    public function up(): void
    {
        Schema::table('retiros_sitio', function (Blueprint $table) {
            $table->unsignedBigInteger('categoria_id')->nullable()->after('categoria');
            $table->text('editar_datos_url')->nullable()->after('actualizar_numeracion_url');
        });
    }

    public function down(): void
    {
        Schema::table('retiros_sitio', function (Blueprint $table) {
            $table->dropColumn(['categoria_id', 'editar_datos_url']);
        });
    }
};
