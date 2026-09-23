<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recategorización visual por edad/género (23/09/2026) — solo visual,
     * nunca reemplaza `categoria` (lo que el participante eligió). Viene
     * calculada por ApiRestEvent (ver RecategorizacionResolver) contra el
     * catálogo de NumeracionRango del evento, cruzando TODAS las
     * categorías (no solo la propia). Null si el evento no tiene ningún
     * rango cargado, o si no matchea ninguno para este participante — en
     * ambos casos el POS sigue mostrando solo `categoria`, sin cambios.
     */
    public function up(): void
    {
        Schema::table('retiros_sitio', function (Blueprint $table) {
            $table->string('categoria_recalculada')->nullable()->after('id_curso');
            $table->string('categoria_recalculada_color', 7)->nullable()->after('categoria_recalculada');
        });
    }

    public function down(): void
    {
        Schema::table('retiros_sitio', function (Blueprint $table) {
            $table->dropColumn(['categoria_recalculada', 'categoria_recalculada_color']);
        });
    }
};
