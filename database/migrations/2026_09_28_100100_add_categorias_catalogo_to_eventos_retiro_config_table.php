<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Editar datos del participante desde el POS (28/09/2026) — catálogo de
     * categorías del evento (agrupado por nombre de tipo de formulario),
     * leído solo de la primera fila del CSV en cada sync (mismo criterio que
     * `usa_numeracion`). Alimenta el <select> de cambio de categoría en el
     * popup del POS.
     */
    public function up(): void
    {
        Schema::table('eventos_retiro_config', function (Blueprint $table) {
            $table->json('categorias_catalogo')->nullable()->after('csv_url');
        });
    }

    public function down(): void
    {
        Schema::table('eventos_retiro_config', function (Blueprint $table) {
            $table->dropColumn('categorias_catalogo');
        });
    }
};
