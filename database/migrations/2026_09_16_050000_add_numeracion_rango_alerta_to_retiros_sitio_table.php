<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aviso de numeración vs. género/edad real en entrega de kit
     * (16/09/2026) — Género/FechaNacimiento no viajaban antes del CSV de
     * ApiRestEvent (ver OrganizadorDashboardController::exportCsv);
     * `alerta_numeracion` llega vacía cuando no hay nada que avisar (sin
     * numeracion_rangos configurados, participante bien numerado, o falta
     * algún dato) — ver NumeracionRangoChecker en ApiRestEvent. Puramente
     * informativo, no bloquea "Confirmar entrega".
     */
    public function up(): void
    {
        Schema::table('retiros_sitio', function (Blueprint $table) {
            $table->string('genero')->nullable()->after('actualizar_numeracion_url');
            $table->date('fecha_nacimiento')->nullable()->after('genero');
            $table->string('alerta_numeracion')->nullable()->after('fecha_nacimiento');
        });
    }

    public function down(): void
    {
        Schema::table('retiros_sitio', function (Blueprint $table) {
            $table->dropColumn(['genero', 'fecha_nacimiento', 'alerta_numeracion']);
        });
    }
};
