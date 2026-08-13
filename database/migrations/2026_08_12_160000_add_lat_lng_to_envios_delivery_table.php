<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mapa de ubicación de delivery (12/08/2026) — el pin que el participante
 * marca en elascenso/event ahora llega acá vía DeliverySyncService (ver
 * ApiRestEvent::DeliveryController::json(), campos `lat`/`lng`). Opcional,
 * complementa `direccion` (texto libre), no la reemplaza.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('envios_delivery', function (Blueprint $table) {
            $table->float('lat', 10, 6)->nullable()->after('direccion');
            $table->float('lng', 10, 6)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('envios_delivery', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng']);
        });
    }
};
