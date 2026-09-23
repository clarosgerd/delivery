<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Numeración/chip solo aplica a carreras, no a congresos (16/09/2026)
     * — el POS mostraba igual la sección de N° corredor/chip (y su aviso)
     * para cualquier evento, incluidos congresos (ej. COLABIOCLI) donde no
     * reparten numeración ni chip de cronometraje. Default `true`: un
     * evento que todavía no resincronizó tras este cambio sigue mostrando
     * numeración exactamente como hasta ahora, sin regresión.
     */
    public function up(): void
    {
        Schema::table('eventos_retiro_config', function (Blueprint $table) {
            $table->boolean('usa_numeracion')->default(true)->after('csv_url');
        });
    }

    public function down(): void
    {
        Schema::table('eventos_retiro_config', function (Blueprint $table) {
            $table->dropColumn('usa_numeracion');
        });
    }
};
