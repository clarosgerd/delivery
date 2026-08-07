<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Numeración de corredor/chip cargada al momento de la entrega en el POS
     * (retiro en sitio), para cuando el proveedor externo de numeración no
     * llegó a tiempo. `actualizar_numeracion_url` es el link firmado que
     * ApiRestEvent entrega en cada fila del CSV de sync (ver
     * OrganizadorDashboardController::exportCsv en ApiRestEvent) para poder
     * empujar el valor de vuelta — mismo patrón que
     * envios_delivery.actualizar_estado_url.
     */
    public function up(): void
    {
        Schema::table('retiros_sitio', function (Blueprint $table) {
            $table->string('numero_corredor')->nullable()->after('estado');
            $table->string('chip')->nullable()->after('numero_corredor');
            $table->string('actualizar_numeracion_url')->nullable()->after('chip');
        });
    }

    public function down(): void
    {
        Schema::table('retiros_sitio', function (Blueprint $table) {
            $table->dropColumn(['numero_corredor', 'chip', 'actualizar_numeracion_url']);
        });
    }
};
