<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fusión de inscripciones duplicadas por persona — curso pre-congreso
     * (16/09/2026) — caso COLABIOCLI: una persona puede estar inscrita como
     * Congresista Y a un Curso Pre-Congreso (mismo correo, 2 registrations
     * en ApiRestEvent). El CSV ahora fusiona ambas en una sola fila (ver
     * OrganizadorDashboardController::exportCsv) para no perder la
     * categoría de Congresista — estas 2 columnas nuevas traen el nombre y
     * el id del curso cuando corresponda, vacías para cualquier otro caso.
     */
    public function up(): void
    {
        Schema::table('retiros_sitio', function (Blueprint $table) {
            $table->string('nombre_curso')->nullable()->after('alerta_numeracion');
            $table->string('id_curso')->nullable()->after('nombre_curso');
        });
    }

    public function down(): void
    {
        Schema::table('retiros_sitio', function (Blueprint $table) {
            $table->dropColumn(['nombre_curso', 'id_curso']);
        });
    }
};
