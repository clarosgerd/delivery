<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cobro en sitio (12/08/2026) — ver
 * ApiRestEvent/brain/api_rest_event/PRD-precios-periodos-fechas.md,
 * sección 0. `monto`/`confirmar_pago_sitio_url` solo vienen poblados por
 * el sync cuando la fila es un form_type sin categoría pendiente de pago
 * (ver RetiroSyncService) — para el resto (ya pagado, o requiere
 * categoría) quedan null, sin efecto visible en el POS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('retiros_sitio', function (Blueprint $table) {
            $table->decimal('monto', 10, 2)->nullable()->after('pago_status');
            $table->string('confirmar_pago_sitio_url', 500)->nullable()->after('monto');
        });
    }

    public function down(): void
    {
        Schema::table('retiros_sitio', function (Blueprint $table) {
            $table->dropColumn(['monto', 'confirmar_pago_sitio_url']);
        });
    }
};
